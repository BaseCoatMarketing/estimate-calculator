<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
// Google Online Estimate schema for the completed estimate
$business = EC_Settings::get_business( $settings );
if ( $business['schema_on'] && $low > 0 && $high > 0 && $service_label ) {
    $offer_schema = EC_Schema::build_offer_schema( $settings, $service, $service_label, $low, $high );
    echo EC_Schema::render( $offer_schema );
}
?>

<?php
$ec_primary   = EC_Avada::resolve( $settings['primary_color'],   '#3a8ea8' );
$ec_secondary = EC_Avada::resolve( $settings['secondary_color'], '#2c3e50' );
$ec_btn_text  = EC_Avada::resolve( $settings['button_text'],     '#ffffff' );
?>
<div class="ec-thankyou" style="--ec-primary: <?php echo esc_attr( $ec_primary ); ?>; --ec-secondary: <?php echo esc_attr( $ec_secondary ); ?>; --ec-btn-text: <?php echo esc_attr( $ec_btn_text ); ?>;">

    <?php if ( $low > 0 && $high > 0 ) : ?>

        <?php
        $svc_text  = $service_label ? $service_label : 'Painting';
        $disclaimer = ! empty( $settings['results_disclaimer'] )
            ? $settings['results_disclaimer']
            : 'This calculator provides a ballpark estimate based on your inputs. Final pricing will be confirmed after an on-site or virtual walkthrough.';
        ?>
        <div class="ec-estimate-result">
            <h2 class="ec-thank-heading">Thank you!</h2>
            <p class="ec-thank-sub">Your instant estimate for <?php echo esc_html( $svc_text ); ?> is ready.</p>

            <div class="ec-range-display">
                <div class="ec-range-low">
                    <span class="ec-range-label">Low</span>
                    <span class="ec-range-value">$<?php echo number_format( $low ); ?></span>
                </div>
                <div class="ec-range-separator">&ndash;</div>
                <div class="ec-range-high">
                    <span class="ec-range-label">High</span>
                    <span class="ec-range-value">$<?php echo number_format( $high ); ?></span>
                </div>
                <span class="ec-asterisk">*</span>
            </div>

            <p class="ec-disclaimer">* <?php echo esc_html( $disclaimer ); ?></p>
        </div>

    <?php else : ?>

        <div class="ec-estimate-result">
            <h2>Thank You!</h2>
            <p>We've received your estimate request and will be in touch shortly.</p>
        </div>

    <?php endif; ?>

    <!-- Booking section: calendar OR CTA button -->
    <?php
    $followup   = EC_Settings::get_followup_mode( $settings );
    $cta_label  = $settings['cta_button_label'] ?? 'Schedule Your Estimate';
    $cta_url    = $settings['cta_button_url']   ?? '';
    $cta_target = ! empty( $settings['cta_button_new_tab'] ) ? ' target="_blank" rel="noopener"' : '';

    if ( $followup === 'custom_iframe' ) : ?>
        <div class="ec-booking-section">
            <h3>Ready to schedule your estimate?</h3>
            <p>Pick a time that works for you.</p>
            <div class="ec-calendar-embed ec-custom-embed">
                <?php echo $settings['calendar_custom_iframe']; // already sanitized on save ?>
            </div>
        </div>
    <?php elseif ( $followup === 'calendar' && $calendar_url ) : ?>
        <div class="ec-booking-section">
            <h3>Ready to schedule your estimate?</h3>
            <p>Book a time that works for you and we'll come out to give you an exact quote.</p>

            <div class="ec-calendar-embed">
                <iframe
                    src="<?php echo esc_url( $calendar_url ); ?>"
                    style="border:none; width:100%; min-height:700px; overflow-y:auto;"
                    scrolling="yes"
                    title="Book Your Estimate"
                ></iframe>
            </div>
        </div>
    <?php elseif ( $followup === 'cta' ) : ?>
        <div class="ec-booking-section ec-cta-section">
            <h3>Ready to schedule your estimate?</h3>
            <p>Click below and we'll get you scheduled.</p>
            <a href="<?php echo esc_url( $cta_url ); ?>" class="ec-btn ec-btn-cta"<?php echo $cta_target; ?>>
                <?php echo esc_html( $cta_label ); ?>
            </a>
        </div>
    <?php endif; ?>

</div>

<?php
// Fire pixel on thank-you page load (conversion event)
$pixel_type = $settings['pixel_type'] ?? 'none';
$pixel_id   = $settings['pixel_id'] ?? '';
$custom     = $settings['custom_pixel_code'] ?? '';
$fire_js    = EC_Tracking::get_pixel_fire_js( $pixel_type, $pixel_id, $custom );
if ( $fire_js ) : ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php echo $fire_js; ?>
});
</script>
<?php endif; ?>
