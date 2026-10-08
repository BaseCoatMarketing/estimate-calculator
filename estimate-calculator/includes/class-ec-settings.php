<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EC_Settings {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_assets' ] );
    }

    /* -------------------------------------------------------------- */
    /*  Defaults                                                       */
    /* -------------------------------------------------------------- */
    public static function defaults() {
        return [
            // GHL Connection
            'ghl_api_key'        => '',
            'ghl_location_id'    => '',
            'ghl_calendar_id'    => '',
            'ghl_pipeline_id'    => '',
            'ghl_stage_id'       => '',

            // Calendar
            'enable_calendar'    => 1,
            'calendar_embed_url' => '',
            // Custom iframe / embed code (overrides GHL Calendar URL when filled).
            // Accepts the full <iframe>...</iframe> markup from Calendly, Acuity,
            // Square Appointments, SimplyBook, etc.
            'calendar_custom_iframe' => '',

            // Fallback CTA button — shown ONLY when no calendar is configured.
            // Useful for sites that prefer to link users to a separate "Schedule" page.
            'cta_button_label'   => 'Schedule Your Estimate',
            'cta_button_url'     => '',
            'cta_button_new_tab' => 0,

            // Always load plugin CSS/JS on every page (use this when embedding the
            // calculator via raw HTML / Avada Custom Code / similar — without the shortcode).
            'force_load_assets'  => 0,

            // REST API for external (non-PHP) sites embedding the widget.
            // Each token has its own allow-list of origins.
            'rest_enabled'       => 0,
            'rest_site_tokens'   => [], // [ { label, token, origins[], enabled, created_at } ]

            // Widget-specific calendar embed (used ONLY for external sites
            // consuming the widget via REST). Overrides the standard
            // `calendar_custom_iframe` and `calendar_embed_url` for widget
            // consumers when set. Leave empty to reuse the main calendar.
            'widget_calendar_custom_iframe' => '',

            // ---- Lead Source Tracking & Server-Side Conversion APIs ----
            'lead_tracking_enabled' => 1,
            // Meta Conversions API
            'meta_capi_enabled'       => 0,
            'meta_capi_pixel_id'      => '',
            'meta_capi_access_token'  => '',
            'meta_capi_test_code'     => '',

            // Tracking
            'pixel_id'           => '',
            'pixel_type'         => 'facebook', // facebook | google | tiktok | custom
            'custom_pixel_code'  => '',

            // Thank-you page
            'thankyou_page_id'   => 0,

            // Enabled built-in services
            'enable_interior'    => 1,
            'enable_exterior'    => 1,
            'enable_cabinet'     => 1,

            // Show a ZIP / Postal Code field on the contact step.
            // When enabled, the field is required and sent to GHL as postalCode.
            'enable_zip_field'   => 0,

            // Custom services repeater. Each entry shape:
            //   slug, label, image, enabled,
            //   price_per_sqft, range_pct,
            //   custom_questions (optional array; see *_custom_questions)
            'custom_services'    => [],

            // Service labels (customizable per client)
            'label_interior'     => 'Interior Painting',
            'label_exterior'     => 'Exterior Painting',
            'label_cabinet'      => 'Cabinet Painting',

            // Service images
            'image_interior'     => '',
            'image_exterior'     => '',
            'image_cabinet'      => '',

            // SMS consent text
            'sms_consent_text'   => 'I consent to receive SMS notifications, alerts, and occasional marketing messages from the company. Message frequency varies. Message and data rates may apply. Text HELP to {phone} for assistance. Reply STOP at any time to unsubscribe.',
            'sms_help_phone'     => '',
            'privacy_url'        => '',

            // Disclaimer shown on form & results
            'form_disclaimer'    => 'This calculator provides a ballpark estimate based on your inputs. Final pricing will be confirmed after an on-site or virtual walkthrough. For the most accurate quote, schedule a free estimate with our team.',
            'results_disclaimer' => 'This calculator provides a ballpark estimate based on your inputs. Final pricing will be confirmed after an on-site or virtual walkthrough.',

            // ---- INTERIOR SQ FT RANGES ----
            'interior_small_sqft_min'     => 0,
            'interior_small_sqft_max'     => 150,
            'interior_medium_sqft_min'    => 151,
            'interior_medium_sqft_max'    => 250,
            'interior_large_sqft_min'     => 251,
            'interior_large_sqft_max'     => 400,
            'interior_xlarge_sqft_min'    => 401,
            'interior_xlarge_sqft_max'    => 600,
            'interior_xlarge_open_ended'  => 1, // shows as "401-600+"

            // ---- INTERIOR PRICING ----
            'interior_small_room_price'    => 270,
            'interior_medium_room_price'   => 2000,
            'interior_large_room_price'    => 2050,
            'interior_xlarge_room_price'   => 950,
            'interior_entry_door_price'    => 200,
            'interior_closet_door_price'   => 150,
            'interior_ceiling_multiplier'  => 0.6,
            'interior_trim_multiplier'     => 0.5,
            // 4-tier condition multipliers
            'interior_cond_like_new'       => 1.0,
            'interior_cond_light_wear'     => 1.1,
            'interior_cond_moderate_wear'  => 1.2,
            'interior_cond_heavy_wear'     => 1.3,
            'interior_range_pct'           => 25, // ±% variance from total

            // Custom questions repeater — admins can add their own questions that
            // alter the calculation. Each entry shape:
            //   slug, label, type ('yesno' | 'number'),
            //   yes_value, no_value, op ('add' | 'multiply')   ← yesno only
            //   unit_value                                      ← number only
            //   enabled
            'interior_custom_questions'    => [],

            // ---- EXTERIOR SQ FT RANGES ----
            'exterior_small_sqft_min'     => 0,
            'exterior_small_sqft_max'     => 1500,
            'exterior_medium_sqft_min'    => 1501,
            'exterior_medium_sqft_max'    => 2500,
            'exterior_large_sqft_min'     => 2501,
            'exterior_large_sqft_max'     => 4000,
            'exterior_large_open_ended'   => 1, // shows as "2,501+"

            // ---- EXTERIOR PRICING ----
            'exterior_small_base'         => 5000,
            'exterior_medium_base'        => 9000,
            'exterior_large_base'         => 12000,

            // Materials are stored as a repeatable list so admins can
            // toggle, edit, add, and remove entries from the admin UI.
            'exterior_materials'          => [
                [ 'slug' => 'wood',     'label' => 'Wood',         'multiplier' => 1.0,  'enabled' => 1 ],
                [ 'slug' => 'stucco',   'label' => 'Stucco',       'multiplier' => 1.15, 'enabled' => 1 ],
                [ 'slug' => 'brick',    'label' => 'Brick',        'multiplier' => 1.1,  'enabled' => 1 ],
                [ 'slug' => 'aluminum', 'label' => 'Aluminum',     'multiplier' => 1.2,  'enabled' => 1 ],
                [ 'slug' => 'laminate', 'label' => 'Laminate',     'multiplier' => 1.1,  'enabled' => 1 ],
                [ 'slug' => 'hardie',   'label' => 'Hardie Board', 'multiplier' => 1.0,  'enabled' => 1 ],
            ],

            'exterior_single_garage'      => 300,
            'exterior_double_garage'      => 450,
            'exterior_shutter_price'      => 70,  // per-unit cost (count × this)
            'exterior_trim_multiplier'    => 0.2,
            'exterior_gutter_multiplier'  => 0.08,
            // 4-tier condition multipliers
            'exterior_cond_like_new'      => 1.0,
            'exterior_cond_light_wear'    => 1.1,
            'exterior_cond_moderate_wear' => 1.2,
            'exterior_cond_heavy_wear'    => 1.3,
            'exterior_range_pct'          => 25, // ±% variance from total

            // Custom questions repeater (see interior_custom_questions for shape)
            'exterior_custom_questions'   => [],

            // ---- CABINET PRICING ----
            'cabinet_base_price'          => 800,
            'cabinet_door_price'          => 120,
            'cabinet_drawer_price'        => 65,
            'cabinet_island_price'        => 1150,
            // 4-tier condition multipliers
            'cabinet_cond_like_new'       => 1.0,
            'cabinet_cond_light_wear'     => 1.1,
            'cabinet_cond_moderate_wear'  => 1.2,
            'cabinet_cond_heavy_wear'     => 1.3,
            'cabinet_range_pct'           => 25, // ±% variance from total

            // Custom questions repeater (see interior_custom_questions for shape)
            'cabinet_custom_questions'    => [],

            // Branding
            'primary_color'      => '#3a8ea8',
            'secondary_color'    => '#2c3e50',
            'button_text'        => '#ffffff',

            // ---- BUSINESS INFO (Schema.org / Google Online Estimate) ----
            'business_name'        => '',
            'business_phone'       => '',
            'business_email'       => '',
            'business_url'         => '',
            'business_logo'        => '',
            'business_type'        => 'HomeAndConstructionBusiness', // Schema.org type
            'business_street'      => '',
            'business_city'        => '',
            'business_region'      => '',
            'business_postal'      => '',
            'business_country'     => 'US',
            'business_area_served' => '',
            'price_currency'       => 'USD',
            'enable_schema'        => 1,
        ];
    }

    /* -------------------------------------------------------------- */
    /*  Helpers for frontend                                           */
    /* -------------------------------------------------------------- */
    public static function get_pricing( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );

        return [
            'interior' => [
                'small_room'    => (float) $s['interior_small_room_price'],
                'medium_room'   => (float) $s['interior_medium_room_price'],
                'large_room'    => (float) $s['interior_large_room_price'],
                'xlarge_room'   => (float) $s['interior_xlarge_room_price'],
                'entry_door'    => (float) $s['interior_entry_door_price'],
                'closet_door'   => (float) $s['interior_closet_door_price'],
                'ceiling_mult'  => (float) $s['interior_ceiling_multiplier'],
                'trim_mult'     => (float) $s['interior_trim_multiplier'],
                'condition'     => [
                    'like_new'      => (float) $s['interior_cond_like_new'],
                    'light_wear'    => (float) $s['interior_cond_light_wear'],
                    'moderate_wear' => (float) $s['interior_cond_moderate_wear'],
                    'heavy_wear'    => (float) $s['interior_cond_heavy_wear'],
                ],
                'range_pct'        => (float) $s['interior_range_pct'],
                'custom_questions' => self::get_custom_questions( 'interior', $s ),
            ],
            'exterior' => [
                'base' => [
                    'small'  => (float) $s['exterior_small_base'],
                    'medium' => (float) $s['exterior_medium_base'],
                    'large'  => (float) $s['exterior_large_base'],
                ],
                // Build slug → multiplier map from the materials array (enabled only).
                'material'  => self::get_material_map( $s ),
                'materials' => self::get_enabled_materials( $s ),
                'single_garage'  => (float) $s['exterior_single_garage'],
                'double_garage'  => (float) $s['exterior_double_garage'],
                'shutter'        => (float) $s['exterior_shutter_price'],
                'trim_mult'      => (float) $s['exterior_trim_multiplier'],
                'gutter_mult'    => (float) $s['exterior_gutter_multiplier'],
                'condition'      => [
                    'like_new'      => (float) $s['exterior_cond_like_new'],
                    'light_wear'    => (float) $s['exterior_cond_light_wear'],
                    'moderate_wear' => (float) $s['exterior_cond_moderate_wear'],
                    'heavy_wear'    => (float) $s['exterior_cond_heavy_wear'],
                ],
                'range_pct'        => (float) $s['exterior_range_pct'],
                'custom_questions' => self::get_custom_questions( 'exterior', $s ),
            ],
            'cabinet' => [
                'base'         => (float) $s['cabinet_base_price'],
                'door'         => (float) $s['cabinet_door_price'],
                'drawer'       => (float) $s['cabinet_drawer_price'],
                'island'       => (float) $s['cabinet_island_price'],
                'condition'    => [
                    'like_new'      => (float) $s['cabinet_cond_like_new'],
                    'light_wear'    => (float) $s['cabinet_cond_light_wear'],
                    'moderate_wear' => (float) $s['cabinet_cond_moderate_wear'],
                    'heavy_wear'    => (float) $s['cabinet_cond_heavy_wear'],
                ],
                'range_pct'        => (float) $s['cabinet_range_pct'],
                'custom_questions' => self::get_custom_questions( 'cabinet', $s ),
            ],
        ];
    }

    public static function get_enabled_services( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );
        $out = [];
        if ( ! empty( $s['enable_interior'] ) ) $out[] = 'interior';
        if ( ! empty( $s['enable_exterior'] ) ) $out[] = 'exterior';
        if ( ! empty( $s['enable_cabinet'] ) )  $out[] = 'cabinet';

        // Append custom services with prefixed slugs (custom_<slug>)
        foreach ( self::get_custom_services( $s ) as $svc ) {
            $out[] = 'custom_' . $svc['slug'];
        }
        return $out;
    }

    public static function get_labels( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );
        // Format SMS consent — replace {phone} placeholder
        $sms = $s['sms_consent_text'];
        if ( $s['sms_help_phone'] ) {
            $sms = str_replace( '{phone}', $s['sms_help_phone'], $sms );
        } else {
            $sms = str_replace( ' Text HELP to {phone} for assistance.', '', $sms );
        }

        return [
            'interior' => $s['label_interior'],
            'exterior' => $s['label_exterior'],
            'cabinet'  => $s['label_cabinet'],
            'image_interior' => $s['image_interior'],
            'image_exterior' => $s['image_exterior'],
            'image_cabinet'  => $s['image_cabinet'],
            'sms_consent'    => $sms,
            'sms_phone'      => $s['sms_help_phone'],
            'privacy_url'    => $s['privacy_url'],
            'form_disclaimer'    => $s['form_disclaimer'],
            'results_disclaimer' => $s['results_disclaimer'],
            // Interior sq ft ranges (with optional open-ended "+")
            'interior_small_sqft'  => self::fmt_range( $s['interior_small_sqft_min'], $s['interior_small_sqft_max'] ),
            'interior_medium_sqft' => self::fmt_range( $s['interior_medium_sqft_min'], $s['interior_medium_sqft_max'] ),
            'interior_large_sqft'  => self::fmt_range( $s['interior_large_sqft_min'], $s['interior_large_sqft_max'] ),
            'interior_xlarge_sqft' => self::fmt_range( $s['interior_xlarge_sqft_min'], $s['interior_xlarge_sqft_max'], ! empty( $s['interior_xlarge_open_ended'] ) ),
            // Exterior sq ft ranges (Small uses "Up to", Large can be open-ended)
            'exterior_small_sqft'  => 'Up to ' . number_format( $s['exterior_small_sqft_max'] ),
            'exterior_medium_sqft' => self::fmt_range( $s['exterior_medium_sqft_min'], $s['exterior_medium_sqft_max'] ),
            'exterior_large_sqft'  => self::fmt_range( $s['exterior_large_sqft_min'], $s['exterior_large_sqft_max'], ! empty( $s['exterior_large_open_ended'] ) ),
        ];
    }

    /**
     * Returns enabled exterior materials as a flat array.
     */
    public static function get_enabled_materials( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );

        $materials = is_array( $s['exterior_materials'] ?? null ) ? $s['exterior_materials'] : $d['exterior_materials'];

        $out = [];
        foreach ( $materials as $m ) {
            if ( empty( $m['slug'] ) ) continue;
            if ( empty( $m['enabled'] ) ) continue;
            $out[] = [
                'slug'       => sanitize_key( $m['slug'] ),
                'label'      => isset( $m['label'] ) ? (string) $m['label'] : ucfirst( $m['slug'] ),
                'multiplier' => isset( $m['multiplier'] ) ? (float) $m['multiplier'] : 1.0,
            ];
        }
        return $out;
    }

    /**
     * Returns slug → multiplier map for enabled materials.
     */
    public static function get_material_map( $s = null ) {
        $map = [];
        foreach ( self::get_enabled_materials( $s ) as $m ) {
            $map[ $m['slug'] ] = $m['multiplier'];
        }
        return $map;
    }

    private static function fmt_range( $min, $max, $open_ended = false ) {
        $r = number_format( $min ) . '-' . number_format( $max );
        if ( $open_ended ) $r .= '+';
        return $r;
    }

    /**
     * Business info used for Schema.org structured data.
     * Compatible with Google Online Estimate / Services.
     */
    public static function get_business( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );
        return [
            'name'        => $s['business_name'],
            'phone'       => $s['business_phone'],
            'email'       => $s['business_email'],
            'url'         => $s['business_url'] ?: home_url( '/' ),
            'logo'        => $s['business_logo'],
            'type'        => $s['business_type'] ?: 'HomeAndConstructionBusiness',
            'street'      => $s['business_street'],
            'city'        => $s['business_city'],
            'region'      => $s['business_region'],
            'postal'      => $s['business_postal'],
            'country'     => $s['business_country'] ?: 'US',
            'area_served' => $s['business_area_served'],
            'currency'    => $s['price_currency'] ?: 'USD',
            'schema_on'   => ! empty( $s['enable_schema'] ),
        ];
    }

    /**
     * Returns which post-submission block to render.
     *
     * Precedence (first match wins):
     *  - 'custom_iframe' → Custom embed code is set (Calendly/Acuity/etc.)
     *  - 'calendar'      → GHL calendar enabled AND URL set
     *  - 'cta'           → Fallback CTA button URL set
     *  - 'none'          → Nothing configured
     *
     * The "Enable Calendar Embed" toggle controls BOTH custom iframe and GHL —
     * unchecking it forces the fallback CTA path.
     */
    public static function get_followup_mode( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );

        $calendar_enabled = ! empty( $s['enable_calendar'] );

        if ( $calendar_enabled && ! empty( trim( $s['calendar_custom_iframe'] ) ) ) {
            return 'custom_iframe';
        }
        if ( $calendar_enabled && ! empty( trim( $s['calendar_embed_url'] ) ) ) {
            return 'calendar';
        }
        if ( ! empty( trim( $s['cta_button_url'] ) ) ) {
            return 'cta';
        }
        return 'none';
    }

    /**
     * Sanitize the custom_services repeater.
     */
    public static function sanitize_custom_services( $input ) {
        if ( ! is_array( $input ) ) return [];

        $clean = [];
        $seen = [];
        foreach ( $input as $row ) {
            if ( ! is_array( $row ) ) continue;

            $label = isset( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';
            if ( $label === '' ) continue;

            $slug = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
            if ( $slug === '' ) $slug = sanitize_key( $label );
            if ( $slug === '' ) continue;

            // Prevent collision with built-in service slugs
            $reserved = [ 'interior', 'exterior', 'cabinet' ];
            if ( in_array( $slug, $reserved, true ) ) {
                $slug = $slug . '-custom';
            }

            // Ensure unique slug
            $base = $slug;
            $n = 2;
            while ( in_array( $slug, $seen, true ) ) {
                $slug = $base . '-' . $n++;
            }
            $seen[] = $slug;

            // Condition multipliers — now a fully editable repeater list.
            // Default seed (used when nothing else is set):
            $default_conditions = [
                [ 'slug' => 'like_new',      'label' => 'Like New',      'multiplier' => 1.0 ],
                [ 'slug' => 'light_wear',    'label' => 'Light Wear',    'multiplier' => 1.1 ],
                [ 'slug' => 'moderate_wear', 'label' => 'Moderate Wear', 'multiplier' => 1.2 ],
                [ 'slug' => 'heavy_wear',    'label' => 'Heavy Wear',    'multiplier' => 1.3 ],
            ];

            if ( isset( $row['conditions'] ) && is_array( $row['conditions'] ) ) {
                $conditions = self::sanitize_service_conditions( $row['conditions'], $default_conditions );
            } elseif ( isset( $row['condition'] ) && is_array( $row['condition'] ) && ! empty( $row['condition'] ) ) {
                // Legacy migration: old fixed 4-tier associative array
                $known_labels = [
                    'like_new' => 'Like New', 'light_wear' => 'Light Wear',
                    'moderate_wear' => 'Moderate Wear', 'heavy_wear' => 'Heavy Wear',
                ];
                $conditions = [];
                foreach ( $row['condition'] as $slug => $val ) {
                    $slug = sanitize_key( $slug );
                    if ( $slug === '' ) continue;
                    $conditions[] = [
                        'slug'       => $slug,
                        'label'      => $known_labels[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) ),
                        'multiplier' => (float) $val,
                    ];
                }
                if ( empty( $conditions ) ) $conditions = $default_conditions;
            } else {
                $conditions = $default_conditions;
            }

            // Checkbox handling: when unchecked, the browser submits NOTHING for these
            // fields — so a missing key MUST be interpreted as 0 (unchecked),
            // never as "fall back to default". Otherwise these toggles can never be
            // turned off and re-saved.
            $sqft_enabled = ! empty( $row['price_per_sqft_enabled'] ) ? 1 : 0;
            $cond_enabled = ! empty( $row['condition_enabled'] )      ? 1 : 0;

            $entry = [
                'slug'                  => $slug,
                'label'                 => $label,
                'image'                 => esc_url_raw( $row['image'] ?? '' ),
                'enabled'               => ! empty( $row['enabled'] ) ? 1 : 0,

                // Pricing mode
                'price_per_sqft_enabled'=> $sqft_enabled,
                'price_per_sqft'        => isset( $row['price_per_sqft'] )   ? (float) $row['price_per_sqft']   : 0,
                'price_multiplier'      => isset( $row['price_multiplier'] ) ? (float) $row['price_multiplier'] : 0,
                'field_label'           => isset( $row['field_label'] ) ? trim( wp_strip_all_tags( (string) $row['field_label'] ) ) : '',

                'range_pct'             => isset( $row['range_pct'] ) ? (float) $row['range_pct'] : 25,

                // Condition multiplier toggle + repeater list
                'condition_enabled'     => $cond_enabled,
                'conditions'            => $conditions,

                'custom_questions'      => isset( $row['custom_questions'] ) && is_array( $row['custom_questions'] )
                    ? self::sanitize_custom_questions( $row['custom_questions'] )
                    : [],
            ];
            $clean[] = $entry;
        }
        return $clean;
    }

    /**
     * Return enabled custom services.
     */
    public static function get_custom_services( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );

        $list = is_array( $s['custom_services'] ?? null ) ? $s['custom_services'] : [];
        $out = [];
        foreach ( $list as $svc ) {
            if ( empty( $svc['enabled'] ) ) continue;
            if ( empty( $svc['slug'] ) || empty( $svc['label'] ) ) continue;
            $out[] = $svc;
        }
        return $out;
    }

    /**
     * Sanitize the REST API site-tokens repeater.
     */
    public static function sanitize_rest_tokens( $input ) {
        if ( ! is_array( $input ) ) return [];

        $clean = [];
        $seen  = [];
        foreach ( $input as $row ) {
            if ( ! is_array( $row ) ) continue;

            // Pull the candidate token; allow only safe chars.
            $token = isset( $row['token'] ) ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $row['token'] ) : '';
            $label = isset( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';

            // Origins can come in as a textarea string OR array
            $origins_raw = $row['origins'] ?? '';
            if ( is_string( $origins_raw ) ) {
                $origins = preg_split( '/[\r\n,]+/', $origins_raw );
            } elseif ( is_array( $origins_raw ) ) {
                $origins = $origins_raw;
            } else {
                $origins = [];
            }
            $origins = array_map( 'trim', (array) $origins );
            $origins = array_filter( $origins, function( $o ) {
                if ( $o === '*' ) return true;
                return preg_match( '~^https?://[^\s/]+$~i', $o ) === 1;
            } );
            $origins = array_values( array_unique( $origins ) );

            // Skip ONLY when the row is completely empty (no token, no label, no origins).
            // This prevents accidentally dropping rows from the saved data.
            if ( $token === '' && $label === '' && empty( $origins ) ) {
                continue;
            }

            // Auto-generate a secure token server-side if the row arrived without one.
            // This is the safety net for when the client-side JS generator fails
            // (browser quirk, copy/paste, etc.) — the row still saves.
            if ( strlen( $token ) < 16 ) {
                $token = self::generate_rest_token();
            }

            // De-duplicate within this save
            if ( in_array( $token, $seen, true ) ) continue;
            $seen[] = $token;

            $clean[] = [
                'label'      => $label !== '' ? $label : 'Site',
                'token'      => $token,
                'origins'    => $origins,
                'enabled'    => ! empty( $row['enabled'] ) ? 1 : 0,
                'created_at' => ! empty( $row['created_at'] )
                    ? sanitize_text_field( (string) $row['created_at'] )
                    : current_time( 'Y-m-d H:i:s' ),
            ];
        }
        return $clean;
    }

    /**
     * Server-side fallback token generator (used when the client-side JS one
     * doesn't make it through). Returns a 32-char URL-safe token.
     */
    public static function generate_rest_token() {
        if ( function_exists( 'random_bytes' ) ) {
            try {
                return rtrim( strtr( base64_encode( random_bytes( 24 ) ), '+/', '-_' ), '=' );
            } catch ( Exception $e ) { /* fall through */ }
        }
        return wp_generate_password( 32, false, false );
    }

    /**
     * Find a stored token entry by value. Returns null if not found or disabled.
     */
    public static function find_rest_token( $token ) {
        if ( ! is_string( $token ) || strlen( $token ) < 16 ) return null;
        $settings = wp_parse_args( get_option( 'ec_settings', [] ), self::defaults() );
        $tokens   = is_array( $settings['rest_site_tokens'] ?? null ) ? $settings['rest_site_tokens'] : [];
        foreach ( $tokens as $t ) {
            if ( empty( $t['enabled'] ) ) continue;
            if ( empty( $t['token'] ) ) continue;
            if ( hash_equals( (string) $t['token'], $token ) ) {
                return $t;
            }
        }
        return null;
    }

    /**
     * Sanitize a per-service conditions repeater list.
     * Each entry: { slug, label, multiplier }
     */
    public static function sanitize_service_conditions( $input, $defaults = [] ) {
        if ( ! is_array( $input ) || empty( $input ) ) return $defaults;

        $clean = [];
        $seen  = [];
        foreach ( $input as $row ) {
            if ( ! is_array( $row ) ) continue;

            $label = isset( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';
            if ( $label === '' ) continue;

            $slug = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
            if ( $slug === '' ) $slug = sanitize_key( $label );
            if ( $slug === '' ) continue;

            // Unique slug
            $base = $slug; $n = 2;
            while ( in_array( $slug, $seen, true ) ) {
                $slug = $base . '-' . $n++;
            }
            $seen[] = $slug;

            $clean[] = [
                'slug'       => $slug,
                'label'      => $label,
                'multiplier' => isset( $row['multiplier'] ) ? (float) $row['multiplier'] : 1.0,
            ];
        }
        return empty( $clean ) ? $defaults : $clean;
    }

    /**
     * Find a single custom service by slug (only returns enabled).
     */
    public static function get_custom_service( $slug, $s = null ) {
        foreach ( self::get_custom_services( $s ) as $svc ) {
            if ( $svc['slug'] === $slug ) return $svc;
        }
        return null;
    }

    /**
     * Sanitize a per-service custom_questions repeater array.
     * Shape per entry:
     *   slug, label, type ('yesno'|'number'),
     *   yes_value, no_value, op ('add'|'multiply')   ← yesno
     *   unit_value                                    ← number
     *   enabled
     */
    public static function sanitize_custom_questions( $input ) {
        if ( ! is_array( $input ) ) return [];

        $clean = [];
        $seen_slugs = [];
        foreach ( $input as $row ) {
            if ( ! is_array( $row ) ) continue;

            $label = isset( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';
            if ( $label === '' ) continue;

            $type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'yesno';
            if ( ! in_array( $type, [ 'yesno', 'number', 'select', 'percent_select' ], true ) ) $type = 'yesno';

            // Slug — derived from label if blank
            $slug = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
            if ( $slug === '' ) $slug = sanitize_key( $label );
            if ( $slug === '' ) continue;

            // Ensure unique slug within this service
            $base_slug = $slug;
            $n = 2;
            while ( in_array( $slug, $seen_slugs, true ) ) {
                $slug = $base_slug . '-' . $n++;
            }
            $seen_slugs[] = $slug;

            $entry = [
                'slug'    => $slug,
                'label'   => $label,
                'type'    => $type,
                'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
            ];

            if ( $type === 'yesno' ) {
                $entry['yes_value'] = isset( $row['yes_value'] ) ? (float) $row['yes_value'] : 0;
                $entry['no_value']  = isset( $row['no_value'] )  ? (float) $row['no_value']  : 0;
                $op = isset( $row['op'] ) ? sanitize_key( $row['op'] ) : 'add';
                $entry['op'] = in_array( $op, [ 'add', 'multiply' ], true ) ? $op : 'add';
            } elseif ( $type === 'number' ) {
                $entry['unit_value'] = isset( $row['unit_value'] ) ? (float) $row['unit_value'] : 0;
            } elseif ( $type === 'select' ) {
                // Select-only question — does NOT affect the calculation.
                // Renders as a dropdown on the front end and syncs the picked
                // value to GHL as `ghl_field_key` (or the default question key).
                $entry['options']       = self::sanitize_select_options( $row['options'] ?? [] );
                $entry['ghl_field_key'] = isset( $row['ghl_field_key'] ) ? sanitize_key( $row['ghl_field_key'] ) : '';
                $entry['required']      = ! empty( $row['required'] ) ? 1 : 0;
            } elseif ( $type === 'percent_select' ) {
                // Multi-option dropdown where each option adjusts the final
                // subtotal by its own percentage. E.g. -10 → 10% discount,
                // 15 → 15% surcharge, 0 → no change.
                $entry['options']       = self::sanitize_percent_options( $row['options'] ?? [] );
                $entry['ghl_field_key'] = isset( $row['ghl_field_key'] ) ? sanitize_key( $row['ghl_field_key'] ) : '';
                $entry['required']      = ! empty( $row['required'] ) ? 1 : 0;
            }

            $clean[] = $entry;
        }
        return $clean;
    }

    /**
     * Sanitize a select-question's options list.
     *
     * Accepts either:
     *   - Array of { value, label } objects (from form submissions built via JS)
     *   - Multi-line string where each line is "value|label" or just "label"
     */
    public static function sanitize_select_options( $options_raw ) {
        // Accept a textarea string: "value|label" per line, or bare labels
        if ( ! is_array( $options_raw ) ) {
            $lines = preg_split( '/[\r\n]+/', (string) $options_raw );
            $options_raw = [];
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( $line === '' ) continue;
                if ( strpos( $line, '|' ) !== false ) {
                    list( $v, $l ) = explode( '|', $line, 2 );
                    $options_raw[] = [ 'value' => trim( $v ), 'label' => trim( $l ) ];
                } else {
                    $options_raw[] = [ 'value' => sanitize_title( $line ), 'label' => $line ];
                }
            }
        }

        $clean = [];
        $seen  = [];
        foreach ( $options_raw as $opt ) {
            if ( ! is_array( $opt ) ) continue;
            $label = isset( $opt['label'] ) ? trim( wp_strip_all_tags( (string) $opt['label'] ) ) : '';
            if ( $label === '' ) continue;
            $value = isset( $opt['value'] ) ? sanitize_text_field( (string) $opt['value'] ) : '';
            if ( $value === '' ) $value = sanitize_title( $label );
            if ( in_array( $value, $seen, true ) ) continue;
            $seen[] = $value;
            $clean[] = [ 'value' => $value, 'label' => $label ];
        }
        return $clean;
    }

    /**
     * Sanitize a percent-select question's options list.
     *
     * Accepts either:
     *   - Array of { value, label, percent } objects
     *   - Multi-line string. Each line supports:
     *       "value|label|percent"     (3 parts)
     *       "label|percent"           (2 parts — value auto-derived)
     */
    public static function sanitize_percent_options( $options_raw ) {
        if ( ! is_array( $options_raw ) ) {
            $lines = preg_split( '/[\r\n]+/', (string) $options_raw );
            $options_raw = [];
            foreach ( $lines as $line ) {
                $line = trim( $line );
                if ( $line === '' ) continue;
                $parts = array_map( 'trim', explode( '|', $line ) );
                if ( count( $parts ) >= 3 ) {
                    $options_raw[] = [
                        'value'   => $parts[0],
                        'label'   => $parts[1],
                        'percent' => $parts[2],
                    ];
                } elseif ( count( $parts ) === 2 ) {
                    $options_raw[] = [
                        'value'   => sanitize_title( $parts[0] ),
                        'label'   => $parts[0],
                        'percent' => $parts[1],
                    ];
                }
            }
        }

        $clean = [];
        $seen  = [];
        foreach ( $options_raw as $opt ) {
            if ( ! is_array( $opt ) ) continue;
            $label = isset( $opt['label'] ) ? trim( wp_strip_all_tags( (string) $opt['label'] ) ) : '';
            if ( $label === '' ) continue;
            $value = isset( $opt['value'] ) ? sanitize_text_field( (string) $opt['value'] ) : '';
            if ( $value === '' ) $value = sanitize_title( $label );
            if ( in_array( $value, $seen, true ) ) continue;
            $seen[] = $value;
            $clean[] = [
                'value'   => $value,
                'label'   => $label,
                'percent' => isset( $opt['percent'] ) ? (float) $opt['percent'] : 0,
            ];
        }
        return $clean;
    }

    /**
     * Return enabled custom questions for a given service.
     *
     * @param string $service 'interior' | 'exterior' | 'cabinet'
     * @param array  $s       Optional settings array
     * @return array
     */
    public static function get_custom_questions( $service, $s = null ) {
        if ( ! in_array( $service, [ 'interior', 'exterior', 'cabinet' ], true ) ) return [];

        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $d = self::defaults();
        $s = wp_parse_args( $s, $d );

        $key = $service . '_custom_questions';
        $list = is_array( $s[ $key ] ?? null ) ? $s[ $key ] : [];

        $out = [];
        foreach ( $list as $q ) {
            if ( empty( $q['enabled'] ) ) continue;
            if ( empty( $q['slug'] ) || empty( $q['label'] ) ) continue;
            $out[] = $q;
        }
        return $out;
    }

    /**
     * Sanitize the exterior materials repeater array.
     * Falls back to defaults if input is missing/empty.
     */
    public static function sanitize_materials( $input, $defaults ) {
        if ( ! is_array( $input ) || empty( $input ) ) {
            return $defaults;
        }

        $clean = [];
        $seen_slugs = [];
        foreach ( $input as $row ) {
            if ( ! is_array( $row ) ) continue;

            $label = isset( $row['label'] ) ? trim( wp_strip_all_tags( (string) $row['label'] ) ) : '';
            if ( $label === '' ) continue;

            // Slug: prefer provided, else derived from label
            $slug = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
            if ( $slug === '' ) {
                $slug = sanitize_key( $label );
            }
            if ( $slug === '' ) continue;

            // Ensure unique slug
            $base_slug = $slug;
            $n = 2;
            while ( in_array( $slug, $seen_slugs, true ) ) {
                $slug = $base_slug . '-' . $n++;
            }
            $seen_slugs[] = $slug;

            $clean[] = [
                'slug'       => $slug,
                'label'      => $label,
                'multiplier' => isset( $row['multiplier'] ) ? (float) $row['multiplier'] : 1.0,
                'enabled'    => ! empty( $row['enabled'] ) ? 1 : 0,
            ];
        }

        // If everything got stripped, fall back to defaults so the UX never breaks.
        return empty( $clean ) ? $defaults : $clean;
    }

    /**
     * Restricted sanitizer for iframe/embed HTML when the user doesn't have
     * unfiltered_html. Allows the tags/attrs needed by mainstream booking
     * providers (Calendly, Acuity, SimplyBook, Square Appointments, etc.).
     */
    public static function sanitize_embed_html( $html ) {
        $allowed = [
            'iframe' => [
                'src' => true, 'width' => true, 'height' => true,
                'frameborder' => true, 'scrolling' => true, 'allowfullscreen' => true,
                'allow' => true, 'loading' => true, 'referrerpolicy' => true,
                'sandbox' => true, 'style' => true, 'title' => true,
                'name' => true, 'id' => true, 'class' => true,
            ],
            'div'    => [ 'class' => true, 'id' => true, 'style' => true, 'data-url' => true ],
            'span'   => [ 'class' => true, 'id' => true, 'style' => true ],
            'a'      => [ 'href' => true, 'class' => true, 'target' => true, 'rel' => true ],
            'script' => [ 'src' => true, 'async' => true, 'defer' => true, 'type' => true, 'id' => true ],
            'link'   => [ 'href' => true, 'rel' => true, 'type' => true ],
        ];
        return trim( (string) wp_kses( $html, $allowed ) );
    }

    public static function get_thankyou_url( $s = null ) {
        if ( ! $s ) $s = get_option( 'ec_settings', self::defaults() );
        $page_id = (int) ( $s['thankyou_page_id'] ?? 0 );
        if ( $page_id > 0 ) {
            return get_permalink( $page_id );
        }
        return '';
    }

    /* -------------------------------------------------------------- */
    /*  Admin menu                                                     */
    /* -------------------------------------------------------------- */
    public function add_menu() {
        add_menu_page(
            'Estimate Calculator',
            'Estimate Calc',
            'manage_options',
            'estimate-calculator',
            [ $this, 'render_settings_page' ],
            'dashicons-calculator',
            30
        );
    }

    public function admin_assets( $hook ) {
        if ( 'toplevel_page_estimate-calculator' !== $hook ) return;
        wp_enqueue_style( 'ec-admin', EC_PLUGIN_URL . 'admin/admin.css', [], EC_VERSION );
        wp_enqueue_media(); // for image picker
        wp_enqueue_script( 'ec-admin', EC_PLUGIN_URL . 'admin/admin.js', [ 'jquery' ], EC_VERSION, true );
    }

    /* -------------------------------------------------------------- */
    /*  Register settings                                              */
    /* -------------------------------------------------------------- */
    public function register_settings() {
        register_setting( 'ec_settings_group', 'ec_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ $this, 'sanitize' ],
        ] );
    }

    public function sanitize( $input ) {
        $d = self::defaults();
        $clean = [];

        // Diagnostic — store what was submitted for rest_site_tokens so the admin
        // can see what made it through PHP/WordPress vs. what got saved. Useful
        // for spotting max_input_vars truncation and similar form issues.
        set_transient( 'ec_last_submit_tokens', [
            'received_rest_site_tokens' => $input['rest_site_tokens'] ?? null,
            'received_rest_enabled'     => $input['rest_enabled']     ?? null,
            'time'                      => current_time( 'mysql' ),
        ], MINUTE_IN_SECONDS * 30 );

        // Strings
        $strings = [
            'ghl_api_key', 'ghl_location_id', 'ghl_calendar_id',
            'ghl_pipeline_id', 'ghl_stage_id', 'calendar_embed_url',
            'pixel_id', 'pixel_type', 'custom_pixel_code',
            'label_interior', 'label_exterior', 'label_cabinet',
            'image_interior', 'image_exterior', 'image_cabinet',
            'sms_consent_text', 'sms_help_phone',
            'privacy_url',
            'primary_color', 'secondary_color', 'button_text',
            // CTA fallback button
            'cta_button_label', 'cta_button_url',
            // Meta CAPI
            'meta_capi_pixel_id', 'meta_capi_access_token', 'meta_capi_test_code',
            // Business / schema
            'business_name', 'business_phone', 'business_email', 'business_url',
            'business_logo', 'business_type', 'business_street', 'business_city',
            'business_region', 'business_postal', 'business_country',
            'business_area_served', 'price_currency',
        ];

        // Textarea disclaimers
        $clean['form_disclaimer']    = sanitize_textarea_field( $input['form_disclaimer']    ?? $d['form_disclaimer'] );
        $clean['results_disclaimer'] = sanitize_textarea_field( $input['results_disclaimer'] ?? $d['results_disclaimer'] );

        // Exterior materials — repeater array
        $clean['exterior_materials'] = self::sanitize_materials( $input['exterior_materials'] ?? [], $d['exterior_materials'] );

        // Per-service custom questions — repeater arrays
        $clean['interior_custom_questions'] = self::sanitize_custom_questions( $input['interior_custom_questions'] ?? [] );
        $clean['exterior_custom_questions'] = self::sanitize_custom_questions( $input['exterior_custom_questions'] ?? [] );
        $clean['cabinet_custom_questions']  = self::sanitize_custom_questions( $input['cabinet_custom_questions']  ?? [] );

        // Custom services — repeater array
        $clean['custom_services'] = self::sanitize_custom_services( $input['custom_services'] ?? [] );

        // REST site tokens — repeater array
        $clean['rest_site_tokens'] = self::sanitize_rest_tokens( $input['rest_site_tokens'] ?? [] );

        // Custom iframe HTML — allow iframe/script/div markup from booking providers.
        // Admins with unfiltered_html cap can paste anything; otherwise use a
        // restricted KSES allowed list that still covers Calendly/Acuity/etc.
        $custom_iframe = $input['calendar_custom_iframe'] ?? '';
        if ( current_user_can( 'unfiltered_html' ) ) {
            $clean['calendar_custom_iframe'] = trim( (string) $custom_iframe );
        } else {
            $clean['calendar_custom_iframe'] = self::sanitize_embed_html( $custom_iframe );
        }

        // Widget-only custom calendar embed (REST consumers)
        $widget_custom_iframe = $input['widget_calendar_custom_iframe'] ?? '';
        if ( current_user_can( 'unfiltered_html' ) ) {
            $clean['widget_calendar_custom_iframe'] = trim( (string) $widget_custom_iframe );
        } else {
            $clean['widget_calendar_custom_iframe'] = self::sanitize_embed_html( $widget_custom_iframe );
        }
        foreach ( $strings as $key ) {
            $clean[ $key ] = sanitize_text_field( $input[ $key ] ?? $d[ $key ] );
        }

        // Color fields — allow hex OR "avada:N" reference
        if ( class_exists( 'EC_Avada' ) ) {
            $clean['primary_color']   = EC_Avada::sanitize( $input['primary_color']   ?? $d['primary_color'],   $d['primary_color'] );
            $clean['secondary_color'] = EC_Avada::sanitize( $input['secondary_color'] ?? $d['secondary_color'], $d['secondary_color'] );
            $clean['button_text']     = EC_Avada::sanitize( $input['button_text']     ?? $d['button_text'],     $d['button_text'] );
        }
        // Allow HTML in custom pixel
        $clean['custom_pixel_code'] = wp_kses_post( $input['custom_pixel_code'] ?? '' );

        // Regular integers — missing key falls back to default
        $ints = [ 'thankyou_page_id' ];
        foreach ( $ints as $key ) {
            $clean[ $key ] = (int) ( $input[ $key ] ?? $d[ $key ] );
        }

        // Checkbox booleans — when unchecked, the browser submits nothing.
        // We MUST default missing keys to 0 (not to the original default value),
        // otherwise an unchecked box can never actually be saved as "off".
        $checkboxes = [
            'enable_interior', 'enable_exterior', 'enable_cabinet',
            'enable_zip_field',
            'enable_schema', 'enable_calendar',
            'cta_button_new_tab', 'force_load_assets',
            'rest_enabled',
            'lead_tracking_enabled', 'meta_capi_enabled',
            'interior_xlarge_open_ended', 'exterior_large_open_ended',
        ];
        foreach ( $checkboxes as $key ) {
            $clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
        }

        // Floats (all pricing). Skip:
        //   - any default that is an array (e.g. exterior_materials — the repeater)
        //   - already-handled keys ($clean already has them)
        $floats = array_filter( array_keys( $d ), function( $k ) use ( $d, $clean ) {
            $is_pricing_key = ( strpos( $k, 'interior_' ) === 0
                             || strpos( $k, 'exterior_' ) === 0
                             || strpos( $k, 'cabinet_' ) === 0 );
            if ( ! $is_pricing_key ) return false;
            if ( is_array( $d[ $k ] ) ) return false;
            if ( array_key_exists( $k, $clean ) ) return false;
            return true;
        } );
        foreach ( $floats as $key ) {
            $clean[ $key ] = (float) ( $input[ $key ] ?? $d[ $key ] );
        }

        return $clean;
    }

    /* -------------------------------------------------------------- */
    /*  Render admin page                                              */
    /* -------------------------------------------------------------- */
    public function render_settings_page() {
        $s = wp_parse_args( get_option( 'ec_settings', [] ), self::defaults() );
        include EC_PLUGIN_DIR . 'admin/settings-page.php';
    }
}
