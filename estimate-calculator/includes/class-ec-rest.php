<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * REST API for external (non-PHP) sites embedding the widget.
 *
 * Routes:
 *   GET  /wp-json/ec/v1/config  → returns calc config (pricing, services, labels…)
 *   POST /wp-json/ec/v1/submit  → processes a calculator submission + GHL push
 *
 * Auth: per-token (X-EC-Site-Token header or `site_token` query/body param).
 * CORS: validated against the token's origins allow-list.
 */
class EC_REST {

    private static $instance = null;
    private $namespace = 'ec/v1';

    public static function instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        // Send CORS headers on responses
        add_filter( 'rest_pre_serve_request', [ $this, 'send_cors_on_response' ], 10, 2 );
        // Handle preflight OPTIONS requests
        add_action( 'init', [ $this, 'handle_preflight' ], 1 );
    }

    /* -------------------------------------------------------------- */
    /*  Route registration                                             */
    /* -------------------------------------------------------------- */
    public function register_routes() {
        register_rest_route( $this->namespace, '/config', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_get_config' ],
            'permission_callback' => [ $this, 'check_token' ],
        ] );
        register_rest_route( $this->namespace, '/submit', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'rest_submit' ],
            'permission_callback' => [ $this, 'check_token' ],
        ] );
    }

    /* -------------------------------------------------------------- */
    /*  CORS                                                           */
    /* -------------------------------------------------------------- */
    public function handle_preflight() {
        if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'OPTIONS' ) return;

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( strpos( $uri, '/wp-json/' . $this->namespace . '/' ) === false &&
             strpos( $_GET['rest_route'] ?? '', '/' . $this->namespace . '/' ) === false ) {
            return;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ( $origin && $this->origin_matches_any_token( $origin ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Vary: Origin' );
        }
        header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
        header( 'Access-Control-Allow-Headers: Content-Type, X-EC-Site-Token, Authorization' );
        header( 'Access-Control-Max-Age: 3600' );
        status_header( 204 );
        exit;
    }

    public function send_cors_on_response( $value, $server ) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if ( strpos( $uri, '/' . $this->namespace . '/' ) === false &&
             strpos( $_GET['rest_route'] ?? '', '/' . $this->namespace . '/' ) === false ) {
            return $value;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ( $origin && $this->origin_matches_any_token( $origin ) ) {
            header( 'Access-Control-Allow-Origin: ' . $origin );
            header( 'Vary: Origin' );
            header( 'Access-Control-Allow-Headers: Content-Type, X-EC-Site-Token, Authorization' );
        }
        return $value;
    }

    /**
     * Does this origin appear in ANY enabled token's allow-list?
     * (Used for sending CORS headers before token-specific validation.)
     */
    private function origin_matches_any_token( $origin ) {
        $settings = wp_parse_args( get_option( 'ec_settings', [] ), EC_Settings::defaults() );
        if ( empty( $settings['rest_enabled'] ) ) return false;
        $tokens = is_array( $settings['rest_site_tokens'] ?? null ) ? $settings['rest_site_tokens'] : [];
        foreach ( $tokens as $t ) {
            if ( empty( $t['enabled'] ) ) continue;
            $origins = is_array( $t['origins'] ?? null ) ? $t['origins'] : [];
            if ( in_array( '*', $origins, true ) ) return true;
            if ( in_array( $origin, $origins, true ) ) return true;
        }
        return false;
    }

    /* -------------------------------------------------------------- */
    /*  Token validation (permission_callback)                         */
    /* -------------------------------------------------------------- */
    public function check_token( WP_REST_Request $request ) {
        $settings = wp_parse_args( get_option( 'ec_settings', [] ), EC_Settings::defaults() );
        if ( empty( $settings['rest_enabled'] ) ) {
            return new WP_Error( 'rest_disabled', 'REST API is disabled.', [ 'status' => 503 ] );
        }

        $token = $request->get_header( 'x-ec-site-token' );
        if ( ! $token ) $token = (string) $request->get_param( 'site_token' );
        $token = trim( (string) $token );

        if ( $token === '' ) {
            return new WP_Error( 'rest_no_token', 'Site token is required.', [ 'status' => 401 ] );
        }

        $info = EC_Settings::find_rest_token( $token );
        if ( ! $info ) {
            return new WP_Error( 'rest_bad_token', 'Invalid or disabled site token.', [ 'status' => 403 ] );
        }

        // Origin check (when an Origin header was sent)
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ( $origin !== '' ) {
            $origins = is_array( $info['origins'] ?? null ) ? $info['origins'] : [];
            $ok = in_array( '*', $origins, true ) || in_array( $origin, $origins, true );
            if ( ! $ok ) {
                return new WP_Error( 'rest_origin_blocked',
                    'Origin not allowed for this site token.', [ 'status' => 403 ] );
            }
        }
        return true;
    }

    /* -------------------------------------------------------------- */
    /*  GET /config                                                    */
    /* -------------------------------------------------------------- */
    public function rest_get_config( WP_REST_Request $request ) {
        $settings = wp_parse_args( get_option( 'ec_settings', [] ), EC_Settings::defaults() );

        // Build label map including custom services
        $labels = EC_Settings::get_labels( $settings );
        foreach ( EC_Settings::get_custom_services( $settings ) as $cs ) {
            $labels[ 'custom_' . $cs['slug'] ] = $cs['label'];
        }

        return rest_ensure_response( [
            'pricing'        => $this->pricing_for_widget( EC_Settings::get_pricing( $settings ) ),
            'services'       => EC_Settings::get_enabled_services( $settings ),
            'customServices' => $this->custom_services_for_widget( EC_Settings::get_custom_services( $settings ) ),
            'labels'         => $labels,
            'sqft'           => $this->sqft_for_widget( $settings ),
            'images'         => [
                'interior' => $settings['image_interior'] ?? '',
                'exterior' => $settings['image_exterior'] ?? '',
                'cabinet'  => $settings['image_cabinet']  ?? '',
            ],
            'colors'         => $this->colors_for_widget( $settings ),
            'enableZipField'     => ! empty( $settings['enable_zip_field'] ),
            'calendar'           => $settings['calendar_embed_url'] ?? '',
            'calendarEnabled'    => ! empty( $settings['enable_calendar'] ),
            // Widget-specific embed wins over the shortcode's embed for REST
            // consumers, so external sites can have their own booking widget.
            'calendarCustomHtml' => ! empty( $settings['widget_calendar_custom_iframe'] )
                ? $settings['widget_calendar_custom_iframe']
                : ( $settings['calendar_custom_iframe'] ?? '' ),
            'cta'                => [
                'label'  => $settings['cta_button_label'] ?? '',
                'url'    => $settings['cta_button_url']   ?? '',
                'newTab' => ! empty( $settings['cta_button_new_tab'] ),
            ],
            'consent'        => [
                'smsText'   => $labels['sms_consent']   ?? '',
                'helpPhone' => $settings['sms_help_phone'] ?? '',
                'privacyUrl'=> $settings['privacy_url']    ?? '',
            ],
            'disclaimers'    => [
                'form'    => $settings['form_disclaimer']    ?? '',
                'results' => $settings['results_disclaimer'] ?? '',
            ],
            'pixel'          => [
                'type'       => $settings['pixel_type'] ?? 'none',
                'id'         => $settings['pixel_id']   ?? '',
                'customCode' => $settings['custom_pixel_code'] ?? '',
            ],
            'business'       => array_merge(
                EC_Settings::get_business( $settings ),
                [ 'enabled' => ! empty( $settings['enable_schema'] ) ]
            ),
        ] );
    }

    /**
     * Build the sqft ranges in the array-pair shape the widget expects.
     */
    private function sqft_for_widget( $s ) {
        return [
            'interior' => [
                'small'      => [ (int) ( $s['interior_small_sqft_min']  ?? 0   ), (int) ( $s['interior_small_sqft_max']  ?? 150 ) ],
                'medium'     => [ (int) ( $s['interior_medium_sqft_min'] ?? 151 ), (int) ( $s['interior_medium_sqft_max'] ?? 250 ) ],
                'large'      => [ (int) ( $s['interior_large_sqft_min']  ?? 251 ), (int) ( $s['interior_large_sqft_max']  ?? 400 ) ],
                'xlarge'     => [ (int) ( $s['interior_xlarge_sqft_min'] ?? 401 ), (int) ( $s['interior_xlarge_sqft_max'] ?? 600 ) ],
                'xlargeOpen' => ! empty( $s['interior_xlarge_open_ended'] ),
            ],
            'exterior' => [
                'small'     => [ (int) ( $s['exterior_small_sqft_min']  ?? 0   ), (int) ( $s['exterior_small_sqft_max']  ?? 1500 ) ],
                'medium'    => [ (int) ( $s['exterior_medium_sqft_min'] ?? 1501 ), (int) ( $s['exterior_medium_sqft_max'] ?? 2500 ) ],
                'large'     => [ (int) ( $s['exterior_large_sqft_min']  ?? 2501 ), (int) ( $s['exterior_large_sqft_max']  ?? 4000 ) ],
                'largeOpen' => ! empty( $s['exterior_large_open_ended'] ),
            ],
        ];
    }

    /**
     * Resolve colors to real hex values for REST consumers.
     *
     * Avada palette references (`avada:N`) resolve to either a real hex from
     * the DB OR a CSS variable like `var(--awb-color1)`. CSS variables only
     * work on pages where Avada's stylesheet is loaded — they break on
     * external (non-WP) embed sites. So if we end up with a `var(...)`
     * reference, fall back to the plugin's default hex instead.
     */
    private function colors_for_widget( $settings ) {
        $resolve = function( $raw, $fallback ) {
            $val = class_exists( 'EC_Avada' )
                ? EC_Avada::resolve( $raw, $fallback )
                : $raw;
            // If it's a CSS variable reference, the external page can't render it.
            if ( is_string( $val ) && stripos( $val, 'var(' ) !== false ) {
                return $fallback;
            }
            return $val;
        };

        return [
            'primary'    => $resolve( $settings['primary_color']   ?? '', '#3a8ea8' ),
            'secondary'  => $resolve( $settings['secondary_color'] ?? '', '#2c3e50' ),
            'buttonText' => $resolve( $settings['button_text']     ?? '', '#ffffff' ),
            'border'     => '#a9b3c6',
        ];
    }

    /**
     * Convert PHP pricing structure (snake_case keys) into the camelCase
     * shape the widget's calc engine reads.
     */
    private function pricing_for_widget( $p ) {
        $out = [];

        // ---- Interior ----
        if ( isset( $p['interior'] ) ) {
            $i = $p['interior'];
            $out['interior'] = [
                'smallRoom'    => (float) ( $i['small_room']   ?? 0 ),
                'mediumRoom'   => (float) ( $i['medium_room']  ?? 0 ),
                'largeRoom'    => (float) ( $i['large_room']   ?? 0 ),
                'xlargeRoom'   => (float) ( $i['xlarge_room']  ?? 0 ),
                'entryDoor'    => (float) ( $i['entry_door']   ?? 0 ),
                'closetDoor'   => (float) ( $i['closet_door']  ?? 0 ),
                'ceilingMult'  => (float) ( $i['ceiling_mult'] ?? 0 ),
                'trimMult'     => (float) ( $i['trim_mult']    ?? 0 ),
                'condition'    => isset( $i['condition'] ) ? $i['condition'] : (object) [],
                'rangePct'     => (float) ( $i['range_pct']    ?? 25 ),
                'customQuestions' => isset( $i['custom_questions'] ) ? $i['custom_questions'] : [],
            ];
        }

        // ---- Exterior ----
        if ( isset( $p['exterior'] ) ) {
            $e = $p['exterior'];

            // Materials → array form: { slug, label, multiplier, enabled }
            $materials = [];
            if ( isset( $e['materials'] ) && is_array( $e['materials'] ) ) {
                foreach ( $e['materials'] as $m ) {
                    $materials[] = [
                        'slug'       => $m['slug']  ?? '',
                        'label'      => $m['label'] ?? '',
                        'multiplier' => (float) ( $m['multiplier'] ?? 1 ),
                        'enabled'    => true, // get_enabled_materials() already filtered
                    ];
                }
            }

            $out['exterior'] = [
                'base'         => isset( $e['base'] ) ? $e['base'] : (object) [],
                'materials'    => $materials,
                'material'     => isset( $e['material'] ) ? $e['material'] : (object) [], // legacy fallback
                'singleGarage' => (float) ( $e['single_garage'] ?? 0 ),
                'doubleGarage' => (float) ( $e['double_garage'] ?? 0 ),
                'shutter'      => (float) ( $e['shutter']       ?? 0 ),
                'trimMult'     => (float) ( $e['trim_mult']     ?? 0 ),
                'gutterMult'   => (float) ( $e['gutter_mult']   ?? 0 ),
                'condition'    => isset( $e['condition'] ) ? $e['condition'] : (object) [],
                'rangePct'     => (float) ( $e['range_pct']     ?? 25 ),
                'customQuestions' => isset( $e['custom_questions'] ) ? $e['custom_questions'] : [],
            ];
        }

        // ---- Cabinet ----
        if ( isset( $p['cabinet'] ) ) {
            $c = $p['cabinet'];
            $out['cabinet'] = [
                'base'      => (float) ( $c['base']   ?? 0 ),
                'door'      => (float) ( $c['door']   ?? 0 ),
                'drawer'    => (float) ( $c['drawer'] ?? 0 ),
                'island'    => (float) ( $c['island'] ?? 0 ),
                'condition' => isset( $c['condition'] ) ? $c['condition'] : (object) [],
                'rangePct'  => (float) ( $c['range_pct'] ?? 25 ),
                'customQuestions' => isset( $c['custom_questions'] ) ? $c['custom_questions'] : [],
            ];
        }

        return $out;
    }

    /**
     * Convert snake_case PHP custom-service keys to the camelCase shape the
     * widget expects.
     */
    private function custom_services_for_widget( $services ) {
        $out = [];
        foreach ( $services as $svc ) {
            $out[] = [
                'slug'                => $svc['slug']     ?? '',
                'label'               => $svc['label']    ?? '',
                'image'               => $svc['image']    ?? '',
                'enabled'             => ! empty( $svc['enabled'] ),
                'pricePerSqftEnabled' => ! empty( $svc['price_per_sqft_enabled'] ),
                'pricePerSqft'        => (float) ( $svc['price_per_sqft']    ?? 0 ),
                'priceMultiplier'     => (float) ( $svc['price_multiplier']  ?? 0 ),
                'fieldLabel'          => $svc['field_label'] ?? '',
                'rangePct'            => (float) ( $svc['range_pct'] ?? 25 ),
                'conditionEnabled'    => ! empty( $svc['condition_enabled'] ),
                'conditions'          => $svc['conditions']       ?? [],
                'customQuestions'     => $svc['custom_questions'] ?? [],
            ];
        }
        return $out;
    }

    /* -------------------------------------------------------------- */
    /*  POST /submit                                                   */
    /* -------------------------------------------------------------- */
    public function rest_submit( WP_REST_Request $request ) {
        $input  = $request->get_params();
        $calc   = EC_Calculator::instance();
        $result = $calc->process_submission( $input );

        if ( empty( $result['success'] ) ) {
            return new WP_Error(
                'rest_submit_failed',
                $result['message'] ?? 'Submission failed.',
                [ 'status' => 400 ]
            );
        }

        return rest_ensure_response( [
            'success'  => true,
            'estimate' => $result['estimate'] ?? null,
            'contact'  => $result['contact']  ?? null,
            'schema'   => $result['schema']   ?? null,
        ] );
    }
}
