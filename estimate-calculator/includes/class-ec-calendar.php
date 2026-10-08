<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Standalone GHL Calendar shortcode.
 *
 * Usage:
 *   [estimate_calendar]
 *   [estimate_calendar height="800"]
 *   [estimate_calendar url="https://link.client.com/widget/booking/XYZ" height="900" width="100%"]
 *   [estimate_calendar heading="Book Your Free Estimate" subheading="Pick a time that works for you."]
 *
 * Attributes (all optional):
 *   url         Override the calendar URL set in plugin settings.
 *   height      Iframe minimum height in pixels (default: 700).
 *   width       Iframe width (default: 100%).
 *   heading     Optional heading shown above the calendar.
 *   subheading  Optional subheading shown above the calendar.
 *   show_header Set to "false" to hide the heading/subheading area entirely.
 */
class EC_Calendar {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode( 'estimate_calendar', [ $this, 'render' ] );
    }

    public function render( $atts = [] ) {
        $atts = shortcode_atts( [
            'url'         => '',
            'height'      => '700',
            'width'       => '100%',
            'heading'     => 'Book Your Estimate',
            'subheading'  => '',
            'show_header' => 'true',
            'custom'      => '', // optional: set to "1" to force custom-iframe mode from plugin settings
        ], $atts, 'estimate_calendar' );

        $settings = wp_parse_args(
            get_option( 'ec_settings', [] ),
            EC_Settings::defaults()
        );

        // 1) Custom iframe (from settings) — overrides URL when present
        $custom_iframe = trim( (string) ( $settings['calendar_custom_iframe'] ?? '' ) );
        $use_custom    = ! empty( $custom_iframe ) && ( $atts['custom'] === '1' || empty( $atts['url'] ) );

        // 2) URL attribute → 3) plugin Calendar URL
        $calendar_url = trim( $atts['url'] );
        if ( empty( $calendar_url ) && ! $use_custom ) {
            $calendar_url = $settings['calendar_embed_url'] ?? '';
        }

        if ( empty( $custom_iframe ) && empty( $calendar_url ) ) {
            // No calendar configured — return nothing on the frontend, helpful note for admins
            if ( current_user_can( 'manage_options' ) ) {
                return '<div class="ec-calendar-warning" style="padding:16px;border:2px dashed #e74c3c;color:#c0392b;background:#fdf0f0;border-radius:6px;font-family:sans-serif">' .
                    '<strong>Estimate Calendar shortcode:</strong> No calendar URL configured. Set one in <em>Estimate Calc &rarr; GHL Integration &rarr; Calendar Embed URL</em>, or pass a <code>url</code> attribute on the shortcode.' .
                    '</div>';
            }
            return '';
        }

        // Sanitize numeric height
        $height = absint( $atts['height'] );
        if ( $height < 200 ) $height = 700;

        // Width — allow %, px, em, rem; default to 100%
        $width = preg_match( '/^[0-9]+(px|%|em|rem)?$/', $atts['width'] ) ? $atts['width'] : '100%';

        $show_header = strtolower( $atts['show_header'] ) !== 'false';

        ob_start();
        ?>
        <div class="ec-standalone-calendar">
            <?php if ( $show_header && ( $atts['heading'] || $atts['subheading'] ) ) : ?>
                <div class="ec-calendar-header">
                    <?php if ( $atts['heading'] ) : ?>
                        <h3><?php echo esc_html( $atts['heading'] ); ?></h3>
                    <?php endif; ?>
                    <?php if ( $atts['subheading'] ) : ?>
                        <p><?php echo esc_html( $atts['subheading'] ); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ec-calendar-embed<?php echo $use_custom ? ' ec-custom-embed' : ''; ?>">
                <?php if ( $use_custom ) : ?>
                    <?php echo $custom_iframe; // sanitized on save ?>
                <?php else : ?>
                    <iframe
                        src="<?php echo esc_url( $calendar_url ); ?>"
                        style="border:none; width:<?php echo esc_attr( $width ); ?>; min-height:<?php echo (int) $height; ?>px; overflow-y:auto;"
                        scrolling="yes"
                        title="Book Your Estimate"
                    ></iframe>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
