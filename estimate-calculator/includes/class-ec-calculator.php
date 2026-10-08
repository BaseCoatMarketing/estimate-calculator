<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EC_Calculator {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'estimate_calculator', [ $this, 'render' ] );
        add_action( 'wp_ajax_ec_submit',        [ $this, 'handle_submit' ] );
        add_action( 'wp_ajax_nopriv_ec_submit',  [ $this, 'handle_submit' ] );
    }

    /* -------------------------------------------------------------- */
    /*  Shortcode output                                               */
    /* -------------------------------------------------------------- */
    public function render( $atts ) {
        $settings = wp_parse_args(
            get_option( 'ec_settings', [] ),
            EC_Settings::defaults()
        );

        ob_start();
        include EC_PLUGIN_DIR . 'templates/calculator.php';
        return ob_get_clean();
    }

    /* -------------------------------------------------------------- */
    /*  AJAX submission handler                                        */
    /* -------------------------------------------------------------- */
    public function handle_submit() {
        check_ajax_referer( 'ec_submit', 'nonce' );

        $result = $this->process_submission( $_POST );

        if ( empty( $result['success'] ) ) {
            wp_send_json_error( [ 'message' => $result['message'] ?? 'Submission failed.' ] );
        }

        wp_send_json_success( [
            'redirect' => $result['redirect'] ?? '',
            'estimate' => $result['estimate'] ?? null,
            'contact'  => $result['contact']  ?? null,
            'schema'   => $result['schema']   ?? null,
        ] );
    }

    /**
     * Shared submission pipeline used by the WP AJAX handler AND the REST API.
     * Takes a raw input array (e.g. $_POST or REST params) and returns the
     * result of running calc + GHL + schema. Pure data — no HTTP side effects.
     *
     * @param array $input Submitted form fields.
     * @return array { success: bool, message?: string, estimate, contact, schema, redirect }
     */
    public function process_submission( $input ) {
        $service = sanitize_text_field( $input['service'] ?? '' );

        $is_builtin = in_array( $service, [ 'interior', 'exterior', 'cabinet' ], true );
        $is_custom  = strpos( $service, 'custom_' ) === 0;
        if ( ! $is_builtin && ! $is_custom ) {
            return [ 'success' => false, 'message' => 'Invalid service type.' ];
        }

        $data = $this->sanitize_form_data( $input );

        $settings = wp_parse_args( get_option( 'ec_settings', [] ), EC_Settings::defaults() );
        $pricing  = EC_Settings::get_pricing( $settings );
        $estimate = $this->calculate( $service, $data, $pricing );

        $ghl     = new EC_GHL( $settings );
        $contact = $ghl->create_or_update_contact( $data, $service, $estimate );

        // Server-side Meta Conversions API event (best-effort, non-blocking)
        if ( ! empty( $settings['meta_capi_enabled'] ) && class_exists( 'EC_Meta_CAPI' ) ) {
            $capi = new EC_Meta_CAPI( $settings );
            $capi->fire_lead( $data, $estimate, $service );
        }

        // Redirect URL (used by the WP-shortcode flow; REST clients can ignore it)
        $thankyou_url = EC_Settings::get_thankyou_url( $settings );
        if ( ! $thankyou_url ) $thankyou_url = home_url( '/' );

        $redirect = add_query_arg( [
            'low'     => $estimate['low'],
            'high'    => $estimate['high'],
            'service' => $service,
        ], $thankyou_url );

        // UTM passthrough
        $utm_keys = [ 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content' ];
        foreach ( $utm_keys as $key ) {
            if ( ! empty( $input[ $key ] ) ) {
                $redirect = add_query_arg( $key, sanitize_text_field( $input[ $key ] ), $redirect );
            }
        }

        // Offer schema for Google Online Estimate
        $labels       = EC_Settings::get_labels( $settings );
        foreach ( EC_Settings::get_custom_services( $settings ) as $cs ) {
            $labels[ 'custom_' . $cs['slug'] ] = $cs['label'];
        }
        $business     = EC_Settings::get_business( $settings );
        $offer_schema = null;
        if ( $business['schema_on'] ) {
            $offer_schema = EC_Schema::build_offer_schema(
                $settings,
                $service,
                $labels[ $service ] ?? ucfirst( $service ),
                $estimate['low'],
                $estimate['high']
            );
        }

        return [
            'success'  => true,
            'redirect' => $redirect,
            'estimate' => $estimate,
            'contact'  => $contact,
            'schema'   => $offer_schema,
        ];
    }

    /* -------------------------------------------------------------- */
    /*  Calculation engine                                             */
    /* -------------------------------------------------------------- */
    private function calculate( $service, $data, $pricing ) {
        switch ( $service ) {
            case 'interior':
                return $this->calc_interior( $data, $pricing['interior'] );
            case 'exterior':
                return $this->calc_exterior( $data, $pricing['exterior'] );
            case 'cabinet':
                return $this->calc_cabinet( $data, $pricing['cabinet'] );
        }
        // Custom service — slug follows "custom_" prefix
        if ( strpos( $service, 'custom_' ) === 0 ) {
            $slug = substr( $service, 7 );
            $svc  = EC_Settings::get_custom_service( $slug );

            // Fallback: also look in the raw stored list in case the service
            // is defined but temporarily marked disabled (protects against a
            // race where the user submits right after an admin toggle change).
            if ( ! $svc ) {
                $settings = wp_parse_args( get_option( 'ec_settings', [] ), EC_Settings::defaults() );
                $raw = is_array( $settings['custom_services'] ?? null ) ? $settings['custom_services'] : [];
                foreach ( $raw as $entry ) {
                    if ( ( $entry['slug'] ?? '' ) === $slug ) { $svc = $entry; break; }
                }
            }

            if ( $svc ) {
                return $this->calc_custom_service( $data, $svc );
            }

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[EC-Calc] No custom service found for slug "' . $slug . '". Returning 0.' );
            }
        }
        return [ 'total' => 0, 'low' => 0, 'high' => 0 ];
    }

    /**
     * Custom service calculation:
     *   subtotal  = sqft × price/sqft
     *           + custom-question adjustments
     *   total     = subtotal × condition multiplier
     *   low/high  = total ±range_pct
     */
    private function calc_custom_service( $d, $svc ) {
        // Quantity field is always named "sqft" (label changes based on mode)
        $qty = (int) ( $d['sqft'] ?? 0 );

        $use_sqft     = ! isset( $svc['price_per_sqft_enabled'] ) ? true : ! empty( $svc['price_per_sqft_enabled'] );
        $cond_enabled = ! isset( $svc['condition_enabled'] )      ? true : ! empty( $svc['condition_enabled'] );

        // Resolve condition multiplier (if enabled). Looks up the user's
        // selection inside the repeater list `conditions`. Legacy fallback
        // keeps support for the old fixed object `condition`.
        $cond_mult = 1.0;
        if ( $cond_enabled ) {
            $condition = sanitize_text_field( $d['condition'] ?? '' );

            if ( ! empty( $svc['conditions'] ) && is_array( $svc['conditions'] ) ) {
                foreach ( $svc['conditions'] as $c ) {
                    if ( isset( $c['slug'] ) && $c['slug'] === $condition ) {
                        $cond_mult = (float) ( $c['multiplier'] ?? 1.0 );
                        break;
                    }
                }
            } elseif ( ! empty( $svc['condition'] ) && is_array( $svc['condition'] ) && isset( $svc['condition'][ $condition ] ) ) {
                $cond_mult = (float) $svc['condition'][ $condition ];
            }
        }

        /*
         * Pricing rules:
         *   1) sqft + condition  → subtotal = qty × ppsf;   total = subtotal × cond_mult
         *   2) sqft only         → subtotal = qty × ppsf;   total = subtotal
         *   3) multiplier only   → subtotal = qty × price_multiplier;  total = subtotal
         *   4) multiplier + cond → subtotal = qty × cond_mult (multiplier is bypassed);
         *                          condition multiplier IS the rate
         */
        $apply_cond_after = $cond_enabled; // when to apply cond_mult on top
        if ( $use_sqft ) {
            $rate     = (float) ( $svc['price_per_sqft'] ?? 0 );
            $subtotal = $qty * $rate;
        } else {
            if ( $cond_enabled ) {
                // Multiplier + condition: cond_mult acts as the per-unit rate.
                $subtotal         = $qty * $cond_mult;
                $apply_cond_after = false; // already applied via the rate
            } else {
                $rate     = (float) ( $svc['price_multiplier'] ?? 0 );
                $subtotal = $qty * $rate;
            }
        }

        // Apply this service's custom questions (if any)
        $questions = isset( $svc['custom_questions'] ) && is_array( $svc['custom_questions'] )
            ? $svc['custom_questions'] : [];
        $subtotal = $this->apply_custom_questions( $subtotal, $d, $questions );

        // Apply condition multiplier on top of subtotal (skipped when it was already used as the rate)
        $total = $apply_cond_after ? ( $subtotal * $cond_mult ) : $subtotal;

        $pct      = isset( $svc['range_pct'] ) ? (float) $svc['range_pct'] : 25;
        $variance = $total * ( $pct / 100 );
        $low  = round( $total - $variance, 0 );
        $high = round( $total + $variance, 0 );

        return [
            'total' => round( $total, 2 ),
            'low'   => max( 0, $low ),
            'high'  => max( 0, $high ),
        ];
    }

    /**
     * Apply custom-question values to a running subtotal.
     * Returns the adjusted subtotal.
     */
    private function apply_custom_questions( $subtotal, $data, $questions ) {
        if ( empty( $questions ) || ! is_array( $questions ) ) return $subtotal;

        foreach ( $questions as $q ) {
            $field = 'custom_' . ( $q['slug'] ?? '' );
            $value = isset( $data[ $field ] ) ? $data[ $field ] : '';

            if ( ( $q['type'] ?? '' ) === 'yesno' ) {
                $apply = ( $value === 'yes' )
                    ? (float) ( $q['yes_value'] ?? 0 )
                    : (float) ( $q['no_value']  ?? 0 );
                if ( ( $q['op'] ?? 'add' ) === 'multiply' ) {
                    // Skip multiplication by 0 (would zero-out the subtotal)
                    if ( $apply > 0 ) $subtotal *= $apply;
                } else {
                    $subtotal += $apply;
                }
            } elseif ( ( $q['type'] ?? '' ) === 'number' ) {
                $count = (int) $value;
                $per   = (float) ( $q['unit_value'] ?? 0 );
                $subtotal += $count * $per;
            } elseif ( ( $q['type'] ?? '' ) === 'percent_select' ) {
                // Look up the picked option's percent and apply it as
                // subtotal *= (1 + percent/100). A 0% option leaves the
                // subtotal unchanged; -10 = 10% discount; 15 = +15%.
                $opts = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : [];
                foreach ( $opts as $opt ) {
                    if ( ( $opt['value'] ?? '' ) === (string) $value ) {
                        $pct = (float) ( $opt['percent'] ?? 0 );
                        $subtotal += $subtotal * ( $pct / 100 );
                        break;
                    }
                }
            }
            // 'select' type is intentionally ignored — it only syncs to GHL,
            // doesn't affect the calculation.
        }
        return $subtotal;
    }

    private function calc_interior( $d, $p ) {
        $small  = (int) ( $d['small_rooms']  ?? 0 );
        $medium = (int) ( $d['medium_rooms'] ?? 0 );
        $large  = (int) ( $d['large_rooms']  ?? 0 );
        $xlarge = (int) ( $d['xlarge_rooms'] ?? 0 );
        $entry_doors  = (int) ( $d['entry_doors']  ?? 0 );
        $closet_doors = (int) ( $d['closet_doors'] ?? 0 );
        $ceilings  = ! empty( $d['ceilings'] );
        $trim      = ! empty( $d['trim'] );
        $condition = sanitize_text_field( $d['condition'] ?? 'like_new' );

        // Base room total
        $room_total = ( $small * $p['small_room'] )
                    + ( $medium * $p['medium_room'] )
                    + ( $large * $p['large_room'] )
                    + ( $xlarge * $p['xlarge_room'] );

        // Add-ons (multiplied against room total)
        $addon_total = 0;
        if ( $ceilings ) $addon_total += $room_total * $p['ceiling_mult'];
        if ( $trim )     $addon_total += $room_total * $p['trim_mult'];

        // Doors (entry doors are typically more involved than closet doors)
        $door_total = ( $entry_doors * $p['entry_door'] ) + ( $closet_doors * $p['closet_door'] );

        // Subtotal before condition
        $subtotal = $room_total + $addon_total + $door_total;

        // Apply custom questions to subtotal
        $subtotal = $this->apply_custom_questions( $subtotal, $d, $p['custom_questions'] ?? [] );

        // Condition multiplier
        $cond_mult = $p['condition'][ $condition ] ?? 1.0;
        $total = $subtotal * $cond_mult;

        // Range — ±% variance from total
        $variance = $total * ( $p['range_pct'] / 100 );
        $low  = round( $total - $variance, 0 );
        $high = round( $total + $variance, 0 );

        return [
            'total' => round( $total, 2 ),
            'low'   => max( 0, $low ),
            'high'  => max( 0, $high ),
        ];
    }

    private function calc_exterior( $d, $p ) {
        $home_size    = sanitize_text_field( $d['home_size'] ?? 'small' );
        $material     = sanitize_text_field( $d['material']  ?? 'wood' );
        $single_g     = (int) ( $d['single_garage'] ?? 0 );
        $double_g     = (int) ( $d['double_garage'] ?? 0 );
        $shutters     = (int) ( $d['shutters'] ?? 0 );
        $has_trim     = ! empty( $d['ext_trim'] );
        $has_gutters  = ! empty( $d['ext_gutters'] );
        $condition    = sanitize_text_field( $d['condition'] ?? 'like_new' );

        // Base price
        $base = $p['base'][ $home_size ] ?? $p['base']['small'];

        // Material multiplier
        $mat_mult = $p['material'][ $material ] ?? 1.0;
        $subtotal = $base * $mat_mult;

        // Add-on multipliers (based on base)
        if ( $has_trim )    $subtotal += $base * $p['trim_mult'];
        if ( $has_gutters ) $subtotal += $base * $p['gutter_mult'];

        // Per-unit add-ons
        $subtotal += $single_g * $p['single_garage'];
        $subtotal += $double_g * $p['double_garage'];
        $subtotal += $shutters * $p['shutter'];

        // Apply custom questions to subtotal
        $subtotal = $this->apply_custom_questions( $subtotal, $d, $p['custom_questions'] ?? [] );

        // Condition
        $cond_mult = $p['condition'][ $condition ] ?? 1.0;
        $total = $subtotal * $cond_mult;

        // Range — ±% variance from total
        $variance = $total * ( $p['range_pct'] / 100 );
        $low  = round( $total - $variance, 0 );
        $high = round( $total + $variance, 0 );

        return [
            'total' => round( $total, 2 ),
            'low'   => max( 0, $low ),
            'high'  => max( 0, $high ),
        ];
    }

    private function calc_cabinet( $d, $p ) {
        $doors     = (int) ( $d['cab_doors'] ?? 0 );
        $drawers   = (int) ( $d['cab_drawers'] ?? 0 );
        $island    = ! empty( $d['has_island'] ) && $d['has_island'] === 'yes';
        $condition = sanitize_text_field( $d['condition'] ?? 'like_new' );

        $total = $p['base']
               + ( $doors * $p['door'] )
               + ( $drawers * $p['drawer'] );

        if ( $island ) {
            $total += $p['island'];
        }

        // Apply custom questions (before condition multiplier)
        $total = $this->apply_custom_questions( $total, $d, $p['custom_questions'] ?? [] );

        // Condition
        $cond_mult = $p['condition'][ $condition ] ?? 1.0;
        $total *= $cond_mult;

        // Range — ±% variance from total
        $variance = $total * ( $p['range_pct'] / 100 );
        $low  = round( $total - $variance, 0 );
        $high = round( $total + $variance, 0 );

        return [
            'total' => round( $total, 2 ),
            'low'   => max( 0, $low ),
            'high'  => max( 0, $high ),
        ];
    }

    /* -------------------------------------------------------------- */
    /*  Sanitization                                                   */
    /* -------------------------------------------------------------- */
    private function sanitize_form_data( $post ) {
        $fields = [
            'service', 'full_name', 'email', 'phone', 'zip_code',
            // Interior
            'small_rooms', 'medium_rooms', 'large_rooms', 'xlarge_rooms',
            'entry_doors', 'closet_doors', 'ceilings', 'trim',
            // Exterior
            'home_size', 'material', 'single_garage', 'double_garage',
            'shutters', 'ext_trim', 'ext_gutters',
            // Cabinet
            'cab_doors', 'cab_drawers', 'has_island',
            // Custom services
            'sqft',
            // Shared
            'condition',
            // Tracking
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
            'fbclid', 'fbp', 'fbc',
            'gclid', 'gbraid', 'wbraid',
            'msclkid', 'ttclid', 'li_fat_id',
            'referrer', 'landing_page_url',
            'lp_variant',
        ];

        $data = [];
        foreach ( $fields as $f ) {
            if ( isset( $post[ $f ] ) ) {
                $data[ $f ] = sanitize_text_field( $post[ $f ] );
            }
        }
        if ( isset( $post['email'] ) ) {
            $data['email'] = sanitize_email( $post['email'] );
        }
        // URL fields should use esc_url_raw to preserve query strings
        if ( isset( $post['referrer'] ) ) {
            $data['referrer'] = esc_url_raw( $post['referrer'] );
        }
        if ( isset( $post['landing_page_url'] ) ) {
            $data['landing_page_url'] = esc_url_raw( $post['landing_page_url'] );
        }

        // Pass through any custom_* fields (from admin-defined custom questions)
        foreach ( $post as $key => $value ) {
            if ( ! is_string( $key ) ) continue;
            if ( strpos( $key, 'custom_' ) !== 0 ) continue;
            // Allow only sensible scalar values
            if ( ! is_scalar( $value ) ) continue;
            $data[ $key ] = sanitize_text_field( (string) $value );
        }
        return $data;
    }
}
