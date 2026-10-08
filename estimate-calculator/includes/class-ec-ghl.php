<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * GoHighLevel Private Integration API V2 integration.
 *
 * Compatible with GHL Private Integrations (API V2.0).
 * Uses sub-account Private Integration access tokens — NOT agency keys.
 *
 * Auth: Bearer token from Private Integration → Sub-Account scope
 * Base: https://services.leadconnectorhq.com
 * Docs: https://highlevel.stoplight.io/docs/integrations
 *
 * Required Private Integration scopes:
 *   - contacts.write     (create/update contacts)
 *   - contacts.readonly  (lookup existing)
 *   - opportunities.write (create opportunities)
 *   - locations.readonly  (validate location)
 *
 * Full tracking loop:
 *   1. Calculator submit  → contact upserted in GHL
 *   2. Pixel fires        → handled client-side
 *   3. Estimate booked    → GHL calendar handles via embed
 *   4. Opportunity created → placed into configured pipeline/stage
 */
class EC_GHL {

    private $api_key;
    private $location_id;
    private $pipeline_id;
    private $stage_id;
    private $base_url = 'https://services.leadconnectorhq.com';

    /**
     * API Version header.
     * GHL V2 requires a Version header on every request.
     */
    private $api_version = '2021-07-28';

    public function __construct( $settings ) {
        $this->api_key     = $settings['ghl_api_key'] ?? '';
        $this->location_id = $settings['ghl_location_id'] ?? '';
        $this->pipeline_id = $settings['ghl_pipeline_id'] ?? '';
        $this->stage_id    = $settings['ghl_stage_id'] ?? '';
    }

    /* -------------------------------------------------------------- */
    /*  Create or Update Contact + Opportunity                         */
    /* -------------------------------------------------------------- */
    public function create_or_update_contact( $data, $service, $estimate ) {
        if ( empty( $this->api_key ) || empty( $this->location_id ) ) {
            $this->log( 'SKIPPED: GHL API key or Location ID not configured.' );
            return [
                'status'     => 'skipped',
                'reason'     => 'GHL not configured',
                'opp_status' => 'skipped',
            ];
        }

        // Split full name
        $name_parts = explode( ' ', trim( $data['full_name'] ?? '' ), 2 );
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        // ----- 1. Upsert Contact -----
        $contact_body = [
            'firstName'    => $first_name,
            'lastName'     => $last_name,
            'email'        => $data['email'] ?? '',
            'phone'        => $data['phone'] ?? '',
            'locationId'   => $this->location_id,
            'source'       => 'Estimate Calculator',
            'tags'         => [
                'estimate-calculator',
                'service-' . $service,
            ],
            'customFields' => $this->build_custom_fields( $service, $data, $estimate ),
        ];

        // ZIP → GHL native postalCode field (only sent when populated so we
        // don't wipe an existing value with an empty string).
        if ( ! empty( $data['zip_code'] ) ) {
            $contact_body['postalCode'] = sanitize_text_field( (string) $data['zip_code'] );
        }

        // Lead source detection + tagging
        if ( class_exists( 'EC_Lead_Source' ) ) {
            $detected_source = EC_Lead_Source::detect( $data );
            $contact_body['tags'] = array_values( array_unique( array_merge(
                $contact_body['tags'],
                EC_Lead_Source::build_tags( $detected_source, $data )
            ) ) );
            // Append tracking custom fields (UTM + click IDs + landing/referrer)
            $contact_body['customFields'] = array_merge(
                $contact_body['customFields'],
                EC_Lead_Source::build_custom_fields( $detected_source, $data )
            );
        } elseif ( ! empty( $data['utm_source'] ) ) {
            // Fallback when the lead-source class isn't loaded
            $contact_body['tags'][] = 'utm-' . sanitize_title( $data['utm_source'] );
        }

        $this->log( '--- CONTACT UPSERT START ---' );
        $this->log( 'Email: ' . ( $data['email'] ?? 'none' ) );
        $this->log( 'Request body: ' . wp_json_encode( $contact_body ) );

        $contact_response = $this->request( 'POST', '/contacts/upsert', $contact_body );

        if ( is_wp_error( $contact_response ) ) {
            $this->log( 'CONTACT UPSERT FAILED: ' . $contact_response->get_error_message() );
            return [
                'status'     => 'error',
                'reason'     => $contact_response->get_error_message(),
                'opp_status' => 'skipped',
            ];
        }

        $contact_id = $contact_response['contact']['id'] ?? '';
        $this->log( 'Contact upserted. ID: ' . $contact_id );

        // ----- 2. Create Opportunity -----
        $opp_result = $this->create_opportunity( $contact_id, $service, $estimate, $data );

        return [
            'status'     => 'success',
            'contact_id' => $contact_id,
            'opp_status' => $opp_result['status'] ?? 'unknown',
            'opp_reason' => $opp_result['reason'] ?? '',
            'opp_id'     => $opp_result['opp_id'] ?? '',
        ];
    }

    /* -------------------------------------------------------------- */
    /*  Create Opportunity                                             */
    /* -------------------------------------------------------------- */
    /**
     * Creates an opportunity via POST /opportunities/
     *
     * This always attempts to create an opportunity.
     * - If pipeline + stage are configured: uses those
     * - If not configured: logs a warning and skips
     *
     * GHL V2 endpoint: POST /opportunities/
     * NOT /opportunities/upsert (that's for updating by external ID)
     */
    private function create_opportunity( $contact_id, $service, $estimate, $data ) {
        // Check required config
        if ( empty( $contact_id ) ) {
            $this->log( 'OPPORTUNITY SKIPPED: No contact ID available.' );
            return [ 'status' => 'skipped', 'reason' => 'No contact ID' ];
        }

        if ( empty( $this->pipeline_id ) ) {
            $this->log( 'OPPORTUNITY SKIPPED: Pipeline ID not configured in settings.' );
            return [ 'status' => 'skipped', 'reason' => 'Pipeline ID not configured' ];
        }

        if ( empty( $this->stage_id ) ) {
            $this->log( 'OPPORTUNITY SKIPPED: Stage ID not configured in settings.' );
            return [ 'status' => 'skipped', 'reason' => 'Stage ID not configured' ];
        }

        $name_parts = explode( ' ', trim( $data['full_name'] ?? '' ), 2 );
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        $opp_name = trim( $first_name . ' ' . $last_name ) . ' - ' . ucfirst( $service ) . ' Estimate';

        // Form-label the opportunity came in on — written to the opportunity's
        // native `source` field so it shows up in GHL opportunity views and can
        // drive automations on the opportunity (not the contact).
        $form_labels = [
            'interior' => 'Estimate Form- INT',
            'exterior' => 'Estimate Form- EXT',
            'cabinet'  => 'Estimate Form - CAB',
        ];
        $opp_source_label = $form_labels[ $service ] ?? 'Estimate Form - OTH';

        $opp_body = [
            'pipelineId'      => $this->pipeline_id,
            'locationId'      => $this->location_id,
            'name'            => $opp_name,
            'pipelineStageId' => $this->stage_id,
            'contactId'       => $contact_id,
            'status'          => 'open',
            'monetaryValue'   => round( (float) $estimate['total'], 2 ),
            'source'          => $opp_source_label,
        ];

        $this->log( '--- OPPORTUNITY CREATE START ---' );
        $this->log( 'Opportunity name: ' . $opp_name );
        $this->log( 'Pipeline ID: ' . $this->pipeline_id );
        $this->log( 'Stage ID: ' . $this->stage_id );
        $this->log( 'Contact ID: ' . $contact_id );
        $this->log( 'Request body: ' . wp_json_encode( $opp_body ) );

        $response = $this->request( 'POST', '/opportunities/', $opp_body );

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            $this->log( 'OPPORTUNITY CREATE FAILED: ' . $error_msg );

            // If it failed, also try the upsert endpoint as fallback
            $this->log( 'Attempting fallback: /opportunities/upsert' );
            $response = $this->request( 'POST', '/opportunities/upsert', $opp_body );

            if ( is_wp_error( $response ) ) {
                $this->log( 'OPPORTUNITY UPSERT FALLBACK ALSO FAILED: ' . $response->get_error_message() );
                return [
                    'status' => 'error',
                    'reason' => $error_msg . ' | Fallback: ' . $response->get_error_message(),
                ];
            }
        }

        $opp_id = $response['opportunity']['id']
               ?? $response['id']
               ?? 'unknown';

        $this->log( 'Opportunity created successfully. ID: ' . $opp_id );

        return [
            'status' => 'success',
            'opp_id' => $opp_id,
        ];
    }

    /* -------------------------------------------------------------- */
    /*  Update contact after booking                                   */
    /* -------------------------------------------------------------- */
    public function update_contact_after_booking( $contact_id, $booking_data ) {
        if ( empty( $this->api_key ) || empty( $contact_id ) ) return;

        $body = [
            'tags'         => [ 'estimate-booked' ],
            'customFields' => [
                [
                    'id'          => 'estimate_booking_date',
                    'field_value' => $booking_data['date'] ?? '',
                ],
            ],
        ];

        return $this->request( 'PUT', '/contacts/' . $contact_id, $body );
    }

    /* -------------------------------------------------------------- */
    /*  Build custom fields payload (V2 format)                        */
    /* -------------------------------------------------------------- */
    private function build_custom_fields( $service, $data, $estimate ) {
        // Pull the current pricing snapshot so we can echo the prices used for
        // the calc back to GHL alongside the user's inputs.
        $settings = wp_parse_args( get_option( 'ec_settings', [] ), EC_Settings::defaults() );
        $pricing  = EC_Settings::get_pricing( $settings );

        // Human-readable formatted range for GHL display: "$4,455 - $7,425"
        $range = '$' . number_format( (float) $estimate['low'] )
               . ' - $' . number_format( (float) $estimate['high'] );

        // NOTE: `lead_source` is now applied to the OPPORTUNITY's native
        // source field on creation (see create_opportunity()), not to a
        // contact custom field.
        $fields = [
            [ 'id' => 'estimate_service',    'field_value' => $service ],
            [ 'id' => 'estimate_total',      'field_value' => (string) $estimate['total'] ],
            [ 'id' => 'estimate_low_range',  'field_value' => (string) $estimate['low'] ],
            [ 'id' => 'estimate_high_range', 'field_value' => (string) $estimate['high'] ],
            [ 'id' => 'estimate_date',       'field_value' => current_time( 'Y-m-d H:i:s' ) ],
            [ 'id' => 'last_general_source', 'field_value' => $data['utm_source'] ?? 'direct' ],
        ];

        switch ( $service ) {
            case 'interior':
                $p = $pricing['interior'];
                $fields[] = [ 'id' => 'interior_small_rooms',  'field_value' => $data['small_rooms']  ?? '0' ];
                $fields[] = [ 'id' => 'interior_medium_rooms', 'field_value' => $data['medium_rooms'] ?? '0' ];
                $fields[] = [ 'id' => 'interior_large_rooms',  'field_value' => $data['large_rooms']  ?? '0' ];
                $fields[] = [ 'id' => 'interior_xlarge_rooms', 'field_value' => $data['xlarge_rooms'] ?? '0' ];
                $fields[] = [ 'id' => 'interior_entry_doors',  'field_value' => $data['entry_doors']  ?? '0' ];
                $fields[] = [ 'id' => 'interior_closet_doors', 'field_value' => $data['closet_doors'] ?? '0' ];
                $fields[] = [ 'id' => 'interior_ceilings',     'field_value' => ! empty( $data['ceilings'] ) ? 'yes' : 'no' ];
                $fields[] = [ 'id' => 'interior_trim',         'field_value' => ! empty( $data['trim'] )     ? 'yes' : 'no' ];
                $fields[] = [ 'id' => 'interior_condition',    'field_value' => $data['condition']    ?? '' ];

                // ---- Legacy-format fields (for existing GHL workflows) ----
                $fields[] = [ 'id' => 'interior_price_range',                'field_value' => $range ];
                $fields[] = [ 'id' => 'score_high_range_interior_painting',  'field_value' => (string) $estimate['high'] ];
                $fields[] = [ 'id' => 'score_low_range_interior_painting',   'field_value' => (string) $estimate['low'] ];
                $fields[] = [ 'id' => 'score_total_estimated_cost_interior_painting', 'field_value' => (string) $estimate['total'] ];
                $fields[] = [ 'id' => 'interior_calc_small_room_count',      'field_value' => $data['small_rooms']  ?? '0' ];
                $fields[] = [ 'id' => 'interior_calc_medium_room_count',     'field_value' => $data['medium_rooms'] ?? '0' ];
                $fields[] = [ 'id' => 'interior_calc_large_room_count',      'field_value' => $data['large_rooms']  ?? '0' ];
                $fields[] = [ 'id' => 'interior_calc_xlarge_room_count',     'field_value' => $data['xlarge_rooms'] ?? '0' ];
                $fields[] = [ 'id' => 'interior_calc_door_count',            'field_value' => (string) ( (int) ( $data['entry_doors'] ?? 0 ) + (int) ( $data['closet_doors'] ?? 0 ) ) ];
                $fields[] = [ 'id' => 'interior_small_room_price',           'field_value' => (string) $p['small_room'] ];
                $fields[] = [ 'id' => 'interior_medium_room_price',          'field_value' => (string) $p['medium_room'] ];
                $fields[] = [ 'id' => 'interior_large_room_price',           'field_value' => (string) $p['large_room'] ];
                $fields[] = [ 'id' => 'interior_xlarge_room_price',          'field_value' => (string) $p['xlarge_room'] ];
                break;

            case 'exterior':
                $p = $pricing['exterior'];
                $fields[] = [ 'id' => 'exterior_home_size',     'field_value' => $data['home_size']     ?? '' ];
                $fields[] = [ 'id' => 'exterior_material',      'field_value' => $data['material']      ?? '' ];
                $fields[] = [ 'id' => 'exterior_single_garage', 'field_value' => $data['single_garage'] ?? '0' ];
                $fields[] = [ 'id' => 'exterior_double_garage', 'field_value' => $data['double_garage'] ?? '0' ];
                $fields[] = [ 'id' => 'exterior_shutters',      'field_value' => $data['shutters'] ?? '0' ];
                $fields[] = [ 'id' => 'exterior_trim',          'field_value' => ! empty( $data['ext_trim'] )     ? 'yes' : 'no' ];
                $fields[] = [ 'id' => 'exterior_gutters',       'field_value' => ! empty( $data['ext_gutters'] )  ? 'yes' : 'no' ];
                $fields[] = [ 'id' => 'exterior_condition',     'field_value' => $data['condition']     ?? '' ];

                // ---- Legacy-format fields (for existing GHL workflows) ----
                $fields[] = [ 'id' => 'exterior_price_range',                          'field_value' => $range ];
                $fields[] = [ 'id' => 'score_high_range_exterior_painting',            'field_value' => (string) $estimate['high'] ];
                $fields[] = [ 'id' => 'score_low_range_exterior_painting',             'field_value' => (string) $estimate['low'] ];
                $fields[] = [ 'id' => 'score_total_estimated_cost_exterior_painting',  'field_value' => (string) $estimate['total'] ];
                $fields[] = [ 'id' => 'exterior_calc_single_garage_door_count',        'field_value' => $data['single_garage'] ?? '0' ];
                $fields[] = [ 'id' => 'exterior_calc_double_garage_door_count',        'field_value' => $data['double_garage'] ?? '0' ];
                $fields[] = [ 'id' => 'exterior_calc_shutter_count',                   'field_value' => $data['shutters']      ?? '0' ];
                $fields[] = [ 'id' => 'exterior_shutter_price',                        'field_value' => (string) $p['shutter'] ];
                $fields[] = [ 'id' => 'single_garage_door_price',                      'field_value' => (string) $p['single_garage'] ];
                $fields[] = [ 'id' => 'double_garage_door_price',                      'field_value' => (string) $p['double_garage'] ];
                break;

            case 'cabinet':
                $p = $pricing['cabinet'];
                $fields[] = [ 'id' => 'cabinet_doors',     'field_value' => $data['cab_doors']   ?? '0' ];
                $fields[] = [ 'id' => 'cabinet_drawers',   'field_value' => $data['cab_drawers'] ?? '0' ];
                $fields[] = [ 'id' => 'cabinet_island',    'field_value' => $data['has_island']  ?? 'no' ];
                $fields[] = [ 'id' => 'cabinet_condition', 'field_value' => $data['condition']   ?? '' ];

                // ---- Legacy-format fields (for existing GHL workflows) ----
                $fields[] = [ 'id' => 'cabinet_price_range',           'field_value' => $range ];
                $fields[] = [ 'id' => 'score_total_estimated_cost',    'field_value' => (string) $estimate['total'] ];
                $fields[] = [ 'id' => 'score_high_range',              'field_value' => (string) $estimate['high'] ];
                $fields[] = [ 'id' => 'score_low_range',               'field_value' => (string) $estimate['low'] ];
                $fields[] = [ 'id' => 'cabinet_calc_door_count',       'field_value' => $data['cab_doors']   ?? '0' ];
                $fields[] = [ 'id' => 'cabinet_calc_drawer_count',     'field_value' => $data['cab_drawers'] ?? '0' ];
                $fields[] = [ 'id' => 'cabinet_doors_price',           'field_value' => (string) $p['door'] ];
                $fields[] = [ 'id' => 'cabinet_drawer_price',          'field_value' => (string) $p['drawer'] ];
                $fields[] = [ 'id' => 'cabinet_base_price_2',          'field_value' => (string) $p['base'] ];
                break;

            default:
                // Custom service — slug after "custom_" prefix
                if ( strpos( $service, 'custom_' ) === 0 ) {
                    $slug = substr( $service, 7 );
                    $fields[] = [ 'id' => 'custom_service_slug', 'field_value' => $slug ];
                    $fields[] = [ 'id' => $slug . '_sqft',       'field_value' => $data['sqft']      ?? '0' ];
                    $fields[] = [ 'id' => $slug . '_condition',  'field_value' => $data['condition'] ?? '' ];
                }
                break;
        }

        // Append custom-question answers as custom fields
        if ( class_exists( 'EC_Settings' ) ) {
            $questions = [];
            if ( in_array( $service, [ 'interior', 'exterior', 'cabinet' ], true ) ) {
                $questions = EC_Settings::get_custom_questions( $service );
            } elseif ( strpos( $service, 'custom_' ) === 0 ) {
                $slug = substr( $service, 7 );
                $svc  = EC_Settings::get_custom_service( $slug );
                if ( $svc && ! empty( $svc['custom_questions'] ) ) {
                    foreach ( $svc['custom_questions'] as $q ) {
                        if ( ! empty( $q['enabled'] ) ) $questions[] = $q;
                    }
                }
            }
            foreach ( $questions as $q ) {
                // Select-style questions can override the destination GHL field key.
                $default_key = $service . '_q_' . $q['slug'];
                $qtype       = $q['type'] ?? '';
                $field_key   = ( in_array( $qtype, [ 'select', 'percent_select' ], true ) && ! empty( $q['ghl_field_key'] ) )
                    ? $q['ghl_field_key']
                    : $default_key;

                $form_field = 'custom_' . $q['slug'];
                $value      = isset( $data[ $form_field ] ) ? (string) $data[ $form_field ] : '';

                // For percent_select, sync the human-readable label (not the internal value)
                // so GHL shows something meaningful like "Complex" instead of "very_complex".
                if ( $qtype === 'percent_select' && $value !== '' && ! empty( $q['options'] ) && is_array( $q['options'] ) ) {
                    foreach ( $q['options'] as $opt ) {
                        if ( ( $opt['value'] ?? '' ) === $value ) {
                            $value = (string) ( $opt['label'] ?? $value );
                            break;
                        }
                    }
                }
                $fields[] = [ 'id' => $field_key, 'field_value' => $value ];
            }
        }

        return $fields;
    }

    /* -------------------------------------------------------------- */
    /*  HTTP helper (GHL V2 Private Integration)                       */
    /* -------------------------------------------------------------- */
    private function request( $method, $endpoint, $body = [] ) {
        $url = $this->base_url . $endpoint;

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Version'       => $this->api_version,
                'Accept'        => 'application/json',
            ],
            'body'    => wp_json_encode( $body ),
            'timeout' => 30,
        ];

        $this->log( sprintf( '%s %s', $method, $url ) );

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->log( 'HTTP Error: ' . $response->get_error_message() );
            return $response;
        }

        $code     = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        $decoded  = json_decode( $raw_body, true );

        $this->log( sprintf( 'Response %d: %s', $code, substr( $raw_body, 0, 800 ) ) );

        if ( $code >= 400 ) {
            $error_msg = $decoded['message']
                      ?? $decoded['error']
                      ?? $decoded['msg']
                      ?? $raw_body;

            return new WP_Error(
                'ghl_api_error',
                sprintf( 'GHL API %d: %s', $code, $error_msg )
            );
        }

        return $decoded;
    }

    /* -------------------------------------------------------------- */
    /*  Debug logger (always logs — not just WP_DEBUG)                 */
    /* -------------------------------------------------------------- */
    private function log( $message ) {
        // Always log GHL calls for debugging during setup.
        // Uses WordPress error_log which writes to debug.log or PHP error log.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[EC-GHL] ' . $message );
        }
    }
}
