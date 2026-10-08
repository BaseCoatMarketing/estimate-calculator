<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class EC_Thankyou {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'estimate_thankyou', [ $this, 'render' ] );
    }

    public function render( $atts ) {
        $low     = (int) ( $_GET['low'] ?? 0 );
        $high    = (int) ( $_GET['high'] ?? 0 );
        $service = sanitize_text_field( $_GET['service'] ?? '' );

        $settings = wp_parse_args(
            get_option( 'ec_settings', [] ),
            EC_Settings::defaults()
        );

        $calendar_url = $settings['calendar_embed_url'] ?? '';
        $labels       = EC_Settings::get_labels( $settings );

        // Map service key to label
        $service_label = '';
        if ( $service && isset( $labels[ $service ] ) ) {
            $service_label = $labels[ $service ];
        }

        ob_start();
        include EC_PLUGIN_DIR . 'templates/thankyou.php';
        return ob_get_clean();
    }
}
