<?php
/**
 * Plugin Name: Estimate Calculator
 * Plugin URI:  https://basecoatmarketing.com
 * Description: Multi-service estimate calculator with GoHighLevel integration, calendar booking, and tracking pixels. Customizable per client.
 * Version:     1.19.0
 * Author:      Basecoat Marketing
 * License:     GPL-2.0+
 * Text Domain: estimate-calc
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'EC_VERSION', '1.19.0' );
define( 'EC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ------------------------------------------------------------------ */
/*  Autoload includes                                                  */
/* ------------------------------------------------------------------ */
require_once EC_PLUGIN_DIR . 'includes/class-ec-settings.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-calculator.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-ghl.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-tracking.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-thankyou.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-schema.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-calendar.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-avada.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-rest.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-lead-source.php';
require_once EC_PLUGIN_DIR . 'includes/class-ec-meta-capi.php';

/* ------------------------------------------------------------------ */
/*  Activation / Deactivation                                          */
/* ------------------------------------------------------------------ */
register_activation_hook( __FILE__, 'ec_activate' );
function ec_activate() {
    // Set default options on first activation
    if ( false === get_option( 'ec_settings' ) ) {
        update_option( 'ec_settings', EC_Settings::defaults() );
    }
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'ec_deactivate' );
function ec_deactivate() {
    flush_rewrite_rules();
}

/* ------------------------------------------------------------------ */
/*  Initialize                                                         */
/* ------------------------------------------------------------------ */
add_action( 'plugins_loaded', 'ec_init' );
function ec_init() {
    EC_Settings::instance();
    EC_Calculator::instance();
    EC_Thankyou::instance();
    EC_Calendar::instance();
    EC_REST::instance();
}

/* ------------------------------------------------------------------ */
/*  Enqueue public assets                                              */
/*                                                                     */
/*  CSS and JS load independently so the calculator works whether       */
/*  the user uses the shortcode OR pastes raw HTML into a Custom Code   */
/*  / HTML block. Detection is layered:                                 */
/*    1. Shortcode presence (cleanest)                                  */
/*    2. HTML markers in post content (id="ec-calculator", etc.)        */
/*    3. "Force load assets" toggle in settings (sitewide override)     */
/* ------------------------------------------------------------------ */
add_action( 'wp_enqueue_scripts', 'ec_enqueue_assets' );
function ec_enqueue_assets() {

    $settings    = wp_parse_args( get_option( 'ec_settings', [] ), EC_Settings::defaults() );
    $force_load  = ! empty( $settings['force_load_assets'] );

    // ---- Detect via shortcodes OR rendered HTML markers ----
    $has_calc = $has_thankyou = $has_calendar = false;
    global $post;

    if ( is_a( $post, 'WP_Post' ) ) {
        $content = $post->post_content;

        // Shortcodes
        $sc_calc     = has_shortcode( $content, 'estimate_calculator' );
        $sc_thankyou = has_shortcode( $content, 'estimate_thankyou' );
        $sc_calendar = has_shortcode( $content, 'estimate_calendar' );

        // HTML markers — set by templates so raw-HTML embeds are auto-detected
        $html_calc     = ( strpos( $content, 'id="ec-calculator"' )      !== false
                       || strpos( $content, "id='ec-calculator'" )      !== false
                       || strpos( $content, 'class="ec-calculator"' )    !== false );
        $html_thankyou = ( strpos( $content, 'class="ec-thankyou"' )     !== false );
        $html_calendar = ( strpos( $content, 'class="ec-standalone-calendar"' ) !== false
                       || strpos( $content, 'ec-calendar-embed' )        !== false );

        $has_calc     = $sc_calc     || $html_calc;
        $has_thankyou = $sc_thankyou || $html_thankyou;
        $has_calendar = $sc_calendar || $html_calendar;
    }

    $need_css = $force_load || $has_calc || $has_thankyou || $has_calendar;
    $need_js  = $force_load || $has_calc || $has_thankyou;

    // ---- CSS — split out so it loads on any page that has the markup ----
    if ( $need_css ) {
        wp_enqueue_style(
            'ec-calculator',
            EC_PLUGIN_URL . 'public/css/calculator.css',
            [],
            EC_VERSION
        );
    }

    // ---- JS — only when interactive calculator/thankyou is present ----
    if ( $need_js ) {
        wp_enqueue_script(
            'ec-calculator',
            EC_PLUGIN_URL . 'public/js/calculator.js',
            [],
            EC_VERSION,
            true
        );

        // Build a flat label map including custom services
        $label_map = EC_Settings::get_labels( $settings );
        foreach ( EC_Settings::get_custom_services( $settings ) as $cs ) {
            $label_map[ 'custom_' . $cs['slug'] ] = $cs['label'];
        }

        wp_localize_script( 'ec-calculator', 'ecData', [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'ec_submit' ),
            'pricing'     => EC_Settings::get_pricing( $settings ),
            'services'    => EC_Settings::get_enabled_services( $settings ),
            'labels'      => $label_map,
            'calendarUrl' => $settings['calendar_embed_url'] ?? '',
            'pixel'       => $settings['pixel_id'] ?? '',
            'pixelType'   => $settings['pixel_type'] ?? '',
            'business'    => EC_Settings::get_business( $settings ),
        ] );
    }
}
