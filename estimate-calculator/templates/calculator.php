<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$services  = EC_Settings::get_enabled_services( $settings );
$labels    = EC_Settings::get_labels( $settings );
$business  = EC_Settings::get_business( $settings );

/**
 * Render the custom-questions block for a given service.
 * Outputs a <div> with one .ec-field per enabled question.
 */
if ( ! function_exists( 'ec_render_custom_questions' ) ) {
    function ec_render_custom_questions( $service ) {
        $questions = EC_Settings::get_custom_questions( $service );
        if ( empty( $questions ) ) return;

        echo '<h3 class="ec-section-heading">Additional Details</h3>';
        foreach ( $questions as $q ) {
            $name  = 'custom_' . $q['slug'];
            $label = esc_html( $q['label'] );
            if ( ( $q['type'] ?? '' ) === 'yesno' ) {
                echo '<div class="ec-field">';
                echo '<label>' . $label . ' <span class="ec-req">*</span></label>';
                echo '<select name="' . esc_attr( $name ) . '" required data-required-msg="' . esc_attr( $q['label'] ) . ' is required.">';
                echo '<option value="" disabled selected>Select Yes or No</option>';
                echo '<option value="yes">Yes</option>';
                echo '<option value="no">No</option>';
                echo '</select>';
                echo '</div>';
            } elseif ( ( $q['type'] ?? '' ) === 'number' ) {
                echo '<div class="ec-field">';
                echo '<label>' . $label . ' <span class="ec-req">*</span></label>';
                echo '<input type="number" min="0" value="0" name="' . esc_attr( $name ) . '" required data-required-msg="' . esc_attr( $q['label'] ) . ' is required (enter 0 if none)." />';
                echo '</div>';
            } elseif ( ( $q['type'] ?? '' ) === 'select' ) {
                $req      = ! empty( $q['required'] );
                $opts     = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : [];
                echo '<div class="ec-field">';
                echo '<label>' . $label . ( $req ? ' <span class="ec-req">*</span>' : '' ) . '</label>';
                echo '<select name="' . esc_attr( $name ) . '"' . ( $req ? ' required data-required-msg="' . esc_attr( $q['label'] ) . ' is required."' : '' ) . '>';
                echo '<option value="' . ( $req ? '' : '' ) . '"' . ( $req ? ' disabled' : '' ) . ' selected>-- Select --</option>';
                foreach ( $opts as $opt ) {
                    echo '<option value="' . esc_attr( $opt['value'] ?? '' ) . '">' . esc_html( $opt['label'] ?? '' ) . '</option>';
                }
                echo '</select>';
                echo '</div>';
            } elseif ( ( $q['type'] ?? '' ) === 'percent_select' ) {
                $req  = ! empty( $q['required'] );
                $opts = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : [];
                echo '<div class="ec-field">';
                echo '<label>' . $label . ( $req ? ' <span class="ec-req">*</span>' : '' ) . '</label>';
                echo '<select name="' . esc_attr( $name ) . '"' . ( $req ? ' required data-required-msg="' . esc_attr( $q['label'] ) . ' is required."' : '' ) . '>';
                echo '<option value="" disabled selected>-- Select --</option>';
                foreach ( $opts as $opt ) {
                    echo '<option value="' . esc_attr( $opt['value'] ?? '' ) . '">' . esc_html( $opt['label'] ?? '' ) . '</option>';
                }
                echo '</select>';
                echo '</div>';
            }
        }
    }
}

// Pixel initialization
$pixel_type = $settings['pixel_type'] ?? 'none';
$pixel_id   = $settings['pixel_id'] ?? '';
$pixel_html = EC_Tracking::get_pixel_init_html( $pixel_type, $pixel_id );
if ( $pixel_html ) {
    echo $pixel_html;
}

// Google Online Estimate / Schema.org structured data
if ( $business['schema_on'] ) {
    $service_schema = EC_Schema::build_service_schema( $settings, $services, $labels );
    echo EC_Schema::render( $service_schema );
}
?>

<?php
$ec_primary   = EC_Avada::resolve( $settings['primary_color'],   '#3a8ea8' );
$ec_secondary = EC_Avada::resolve( $settings['secondary_color'], '#2c3e50' );
$ec_btn_text  = EC_Avada::resolve( $settings['button_text'],     '#ffffff' );
?>
<div class="ec-calculator" id="ec-calculator"
     style="--ec-primary: <?php echo esc_attr( $ec_primary ); ?>;
            --ec-secondary: <?php echo esc_attr( $ec_secondary ); ?>;
            --ec-btn-text: <?php echo esc_attr( $ec_btn_text ); ?>;">

    <!-- Progress Bar -->
    <div class="ec-progress">
        <div class="ec-progress-bar" id="ec-progress-bar" style="width: 10%;"></div>
    </div>

    <!-- Global error banner (validation messages from any step) -->
    <div class="ec-error" id="ec-error" style="display:none;"></div>

    <!-- ============================================================ -->
    <!-- STEP 1: Service Selection                                     -->
    <!-- ============================================================ -->
    <div class="ec-step active" data-step="1">
        <h2>What type of estimate do you need?</h2>
        <div class="ec-service-grid">
            <?php
            // Built-in services
            foreach ( $services as $svc ) :
                if ( strpos( $svc, 'custom_' ) === 0 ) continue; // handled below
            ?>
            <button type="button" class="ec-service-card" data-service="<?php echo esc_attr( $svc ); ?>">
                <?php if ( ! empty( $labels[ 'image_' . $svc ] ) ) : ?>
                    <img src="<?php echo esc_url( $labels[ 'image_' . $svc ] ); ?>" alt="<?php echo esc_attr( $labels[ $svc ] ); ?>" />
                <?php else : ?>
                    <div class="ec-service-icon ec-icon-<?php echo esc_attr( $svc ); ?>"></div>
                <?php endif; ?>
                <span><?php echo esc_html( $labels[ $svc ] ); ?></span>
            </button>
            <?php endforeach; ?>

            <?php
            // Custom services
            foreach ( EC_Settings::get_custom_services( $settings ) as $cs ) :
                $cs_service = 'custom_' . $cs['slug'];
            ?>
            <button type="button" class="ec-service-card" data-service="<?php echo esc_attr( $cs_service ); ?>">
                <?php if ( ! empty( $cs['image'] ) ) : ?>
                    <img src="<?php echo esc_url( $cs['image'] ); ?>" alt="<?php echo esc_attr( $cs['label'] ); ?>" />
                <?php else : ?>
                    <div class="ec-service-icon"></div>
                <?php endif; ?>
                <span><?php echo esc_html( $cs['label'] ); ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STEP 2: Interior Calculator                                   -->
    <!-- ============================================================ -->
    <div class="ec-step" data-step="2" data-service="interior">
        <h2><?php echo esc_html( $labels['interior'] ); ?> Estimate</h2>

        <h3 class="ec-section-heading">Room Count by Size</h3>

        <div class="ec-field">
            <label>Small Rooms <small>(<?php echo esc_html( $labels['interior_small_sqft'] ); ?> sq ft)</small> <span class="ec-req">*</span></label>
            <input type="number" name="small_rooms" min="0" value="0" required data-required-msg="Number of small rooms is required." />
        </div>

        <div class="ec-field">
            <label>Medium Rooms <small>(<?php echo esc_html( $labels['interior_medium_sqft'] ); ?> sq ft)</small> <span class="ec-req">*</span></label>
            <input type="number" name="medium_rooms" min="0" value="0" required data-required-msg="Number of medium rooms is required." />
        </div>

        <div class="ec-field">
            <label>Large Rooms <small>(<?php echo esc_html( $labels['interior_large_sqft'] ); ?> sq ft)</small> <span class="ec-req">*</span></label>
            <input type="number" name="large_rooms" min="0" value="0" required data-required-msg="Number of large rooms is required." />
        </div>

        <div class="ec-field">
            <label>Extra Large Rooms <small>(<?php echo esc_html( $labels['interior_xlarge_sqft'] ); ?> sq ft)</small> <span class="ec-req">*</span></label>
            <input type="number" name="xlarge_rooms" min="0" value="0" required data-required-msg="Number of extra large rooms is required." />
        </div>

        <h3 class="ec-section-heading">Additional Interior Items</h3>
        <div class="ec-field">
            <div class="ec-checkbox-group">
                <label><input type="checkbox" name="ceilings" value="1" /> Ceilings</label>
                <label><input type="checkbox" name="trim" value="1" /> Trim</label>
            </div>
        </div>

        <h3 class="ec-section-heading">Doors</h3>

        <div class="ec-field">
            <label>Entry Doors <small>(garage, backyard, front)</small> <span class="ec-req">*</span></label>
            <input type="number" name="entry_doors" min="0" value="0" required data-required-msg="Entry door count is required." />
        </div>

        <div class="ec-field">
            <label>Closet / Interior Doors <span class="ec-req">*</span></label>
            <input type="number" name="closet_doors" min="0" value="0" required data-required-msg="Closet/interior door count is required." />
        </div>

        <div class="ec-field">
            <label>Current Interior Condition <span class="ec-req">*</span></label>
            <select name="condition" required data-required-msg="Current interior condition is required.">
                <option value="" disabled selected>Select condition</option>
                <option value="like_new">Like New</option>
                <option value="light_wear">Light Wear</option>
                <option value="moderate_wear">Moderate Wear</option>
                <option value="heavy_wear">Heavy Wear</option>
            </select>
        </div>

        <?php ec_render_custom_questions( 'interior' ); ?>

        <div class="ec-actions">
            <button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button>
            <button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STEP 2: Exterior Calculator                                   -->
    <!-- ============================================================ -->
    <div class="ec-step" data-step="2" data-service="exterior">
        <h2><?php echo esc_html( $labels['exterior'] ); ?> Estimate</h2>

        <h3 class="ec-section-heading">Home Size (Square Footage)</h3>
        <div class="ec-field">
            <label>Home Size <span class="ec-req">*</span></label>
            <select name="home_size" required data-required-msg="Home size is required.">
                <option value="" disabled selected>Select home size</option>
                <option value="small">Small (Up to <?php echo esc_html( number_format( $settings['exterior_small_sqft_max'] ) ); ?> sq ft)</option>
                <option value="medium">Medium (<?php echo esc_html( $labels['exterior_medium_sqft'] ); ?> sq ft)</option>
                <option value="large">Large (<?php echo esc_html( $labels['exterior_large_sqft'] ); ?> sq ft)</option>
            </select>
        </div>

        <h3 class="ec-section-heading">Siding</h3>
        <div class="ec-field">
            <label>Primary Siding Material <span class="ec-req">*</span></label>
            <select name="material" required data-required-msg="Primary siding material is required.">
                <option value="" disabled selected>Select siding material</option>
                <?php foreach ( EC_Settings::get_enabled_materials( $settings ) as $mat ) : ?>
                    <option value="<?php echo esc_attr( $mat['slug'] ); ?>"><?php echo esc_html( $mat['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <h3 class="ec-section-heading">Garage Doors</h3>

        <div class="ec-field">
            <label>Single Garage Doors <span class="ec-req">*</span></label>
            <input type="number" name="single_garage" min="0" value="0" required data-required-msg="Single garage door count is required." />
        </div>

        <div class="ec-field">
            <label>Double Garage Doors <span class="ec-req">*</span></label>
            <input type="number" name="double_garage" min="0" value="0" required data-required-msg="Double garage door count is required." />
        </div>

        <h3 class="ec-section-heading">Shutters</h3>
        <div class="ec-field">
            <label>Number of Shutters <span class="ec-req">*</span></label>
            <input type="number" name="shutters" min="0" value="0" required data-required-msg="Shutter count is required (enter 0 if none)." />
        </div>

        <h3 class="ec-section-heading">Additional Exterior Items</h3>
        <div class="ec-field">
            <div class="ec-checkbox-group">
                <label><input type="checkbox" name="ext_trim" value="1" /> Trim</label>
                <label><input type="checkbox" name="ext_gutters" value="1" /> Gutters</label>
            </div>
            <p class="ec-note">We'll inspect for wood rot on site.</p>
        </div>

        <div class="ec-field">
            <label>Current Exterior Condition <span class="ec-req">*</span></label>
            <select name="condition" required data-required-msg="Current exterior condition of the home is required.">
                <option value="" disabled selected>Select condition</option>
                <option value="like_new">Like New (new or recently painted, no visible wear)</option>
                <option value="light_wear">Light Wear (minor fading or dirt, minimal prep needed)</option>
                <option value="moderate_wear">Moderate Wear (peeling paint, small cracks, or surface damage)</option>
                <option value="heavy_wear">Heavy Wear (extensive peeling, exposed substrate, or repairs needed)</option>
            </select>
        </div>

        <?php ec_render_custom_questions( 'exterior' ); ?>

        <div class="ec-actions">
            <button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button>
            <button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STEP 2: Cabinet Calculator                                    -->
    <!-- ============================================================ -->
    <div class="ec-step" data-step="2" data-service="cabinet">
        <h2><?php echo esc_html( $labels['cabinet'] ); ?> Estimate</h2>

        <div class="ec-field">
            <label>Cabinet Doors (total count) <span class="ec-req">*</span></label>
            <input type="number" name="cab_doors" min="0" value="0" required data-required-msg="Cabinet door count is required." />
        </div>

        <div class="ec-field">
            <label>Cabinet Drawers (total count) <span class="ec-req">*</span></label>
            <input type="number" name="cab_drawers" min="0" value="0" required data-required-msg="Cabinet drawer count is required." />
        </div>

        <div class="ec-field">
            <label>Kitchen Island Included <span class="ec-req">*</span></label>
            <select name="has_island" required data-required-msg="Please indicate whether a kitchen island is included.">
                <option value="" disabled selected>Select Yes or No</option>
                <option value="yes">Yes</option>
                <option value="no">No</option>
            </select>
        </div>

        <div class="ec-field">
            <label>Current Cabinet Condition <span class="ec-req">*</span></label>
            <select name="condition" required data-required-msg="Current cabinet condition is required.">
                <option value="" disabled selected>Select condition</option>
                <option value="like_new">Like New (new build or painted within the last 1–2 years, no visible flaws)</option>
                <option value="light_wear">Light Wear (minor scuffs or marks, little to no prep needed)</option>
                <option value="moderate_wear">Moderate Wear (noticeable imperfections, moderate scuffs or marks, or minor repair needed)</option>
                <option value="heavy_wear">Heavy Wear (significant damage, large or deep scratches, major repair needed, or extensive prep required)</option>
            </select>
        </div>

        <?php ec_render_custom_questions( 'cabinet' ); ?>

        <div class="ec-actions">
            <button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button>
            <button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STEP 2: Custom Services (one block per enabled service)        -->
    <!-- ============================================================ -->
    <?php foreach ( EC_Settings::get_custom_services( $settings ) as $cs ) :
        $cs_service = 'custom_' . $cs['slug'];
    ?>
    <div class="ec-step" data-step="2" data-service="<?php echo esc_attr( $cs_service ); ?>">
        <h2><?php echo esc_html( $cs['label'] ); ?> Estimate</h2>

        <?php
        $cs_use_sqft   = ! isset( $cs['price_per_sqft_enabled'] ) ? true : ! empty( $cs['price_per_sqft_enabled'] );
        $cs_cond_on    = ! isset( $cs['condition_enabled'] )      ? true : ! empty( $cs['condition_enabled'] );
        $default_label = $cs_use_sqft ? 'Square Footage' : 'Quantity';
        $field_label   = ! empty( $cs['field_label'] ) ? $cs['field_label'] : $default_label;
        $placeholder   = $cs_use_sqft ? 'Enter total sq ft' : 'Enter quantity';
        ?>
        <div class="ec-field">
            <label><?php echo esc_html( $field_label ); ?> <span class="ec-req">*</span></label>
            <input type="number" name="sqft" min="0" value="0" required data-required-msg="<?php echo esc_attr( $field_label ); ?> is required." placeholder="<?php echo esc_attr( $placeholder ); ?>" />
        </div>

        <?php
        // Build a list of conditions — prefer the new `conditions` repeater,
        // fall back to legacy `condition` map, then to the 4 defaults.
        $cs_conditions = [];
        if ( ! empty( $cs['conditions'] ) && is_array( $cs['conditions'] ) ) {
            $cs_conditions = $cs['conditions'];
        } elseif ( ! empty( $cs['condition'] ) && is_array( $cs['condition'] ) ) {
            $known_labels = [
                'like_new' => 'Like New', 'light_wear' => 'Light Wear',
                'moderate_wear' => 'Moderate Wear', 'heavy_wear' => 'Heavy Wear',
            ];
            foreach ( $cs['condition'] as $slug => $mult ) {
                $cs_conditions[] = [
                    'slug' => $slug,
                    'label' => $known_labels[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) ),
                    'multiplier' => $mult,
                ];
            }
        }
        ?>
        <?php if ( $cs_cond_on && ! empty( $cs_conditions ) ) : ?>
        <div class="ec-field">
            <label>Current Condition <span class="ec-req">*</span></label>
            <select name="condition" required data-required-msg="Current condition is required.">
                <option value="" disabled selected>Select condition</option>
                <?php foreach ( $cs_conditions as $c ) : ?>
                    <option value="<?php echo esc_attr( $c['slug'] ); ?>"><?php echo esc_html( $c['label'] ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php
        // Render this service's custom questions inline
        if ( ! empty( $cs['custom_questions'] ) ) {
            $rendered = 0;
            foreach ( $cs['custom_questions'] as $q ) {
                if ( empty( $q['enabled'] ) ) continue;
                if ( $rendered === 0 ) echo '<h3 class="ec-section-heading">Additional Details</h3>';
                $rendered++;
                $name  = 'custom_' . $q['slug'];
                $label = esc_html( $q['label'] );
                if ( ( $q['type'] ?? '' ) === 'yesno' ) {
                    echo '<div class="ec-field">';
                    echo '<label>' . $label . ' <span class="ec-req">*</span></label>';
                    echo '<select name="' . esc_attr( $name ) . '" required data-required-msg="' . esc_attr( $q['label'] ) . ' is required.">';
                    echo '<option value="" disabled selected>Select Yes or No</option>';
                    echo '<option value="yes">Yes</option>';
                    echo '<option value="no">No</option>';
                    echo '</select></div>';
                } elseif ( ( $q['type'] ?? '' ) === 'number' ) {
                    echo '<div class="ec-field">';
                    echo '<label>' . $label . ' <span class="ec-req">*</span></label>';
                    echo '<input type="number" min="0" value="0" name="' . esc_attr( $name ) . '" required data-required-msg="' . esc_attr( $q['label'] ) . ' is required (enter 0 if none)." />';
                    echo '</div>';
                } elseif ( ( $q['type'] ?? '' ) === 'select' ) {
                    $req  = ! empty( $q['required'] );
                    $opts = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : [];
                    echo '<div class="ec-field">';
                    echo '<label>' . $label . ( $req ? ' <span class="ec-req">*</span>' : '' ) . '</label>';
                    echo '<select name="' . esc_attr( $name ) . '"' . ( $req ? ' required data-required-msg="' . esc_attr( $q['label'] ) . ' is required."' : '' ) . '>';
                    echo '<option value="" disabled selected>-- Select --</option>';
                    foreach ( $opts as $opt ) {
                        echo '<option value="' . esc_attr( $opt['value'] ?? '' ) . '">' . esc_html( $opt['label'] ?? '' ) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                } elseif ( ( $q['type'] ?? '' ) === 'percent_select' ) {
                    $req  = ! empty( $q['required'] );
                    $opts = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : [];
                    echo '<div class="ec-field">';
                    echo '<label>' . $label . ( $req ? ' <span class="ec-req">*</span>' : '' ) . '</label>';
                    echo '<select name="' . esc_attr( $name ) . '"' . ( $req ? ' required data-required-msg="' . esc_attr( $q['label'] ) . ' is required."' : '' ) . '>';
                    echo '<option value="" disabled selected>-- Select --</option>';
                    foreach ( $opts as $opt ) {
                        echo '<option value="' . esc_attr( $opt['value'] ?? '' ) . '">' . esc_html( $opt['label'] ?? '' ) . '</option>';
                    }
                    echo '</select>';
                    echo '</div>';
                }
            }
        }
        ?>

        <div class="ec-actions">
            <button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button>
            <button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- ============================================================ -->
    <!-- STEP 3: Contact Information                                   -->
    <!-- ============================================================ -->
    <div class="ec-step" data-step="contact">
        <h2>Your Information</h2>

        <div class="ec-field">
            <label>Full Name <span class="ec-req">*</span></label>
            <input type="text" name="full_name" required placeholder="John Smith" />
        </div>

        <div class="ec-field">
            <label>Email <span class="ec-req">*</span></label>
            <input type="email" name="email" required placeholder="john@example.com" />
        </div>

        <div class="ec-field">
            <label>Phone <span class="ec-req">*</span></label>
            <input type="tel" name="phone" required placeholder="(555) 123-4567" />
        </div>

        <?php if ( ! empty( $settings['enable_zip_field'] ) ) : ?>
        <div class="ec-field">
            <label>ZIP Code <span class="ec-req">*</span></label>
            <input type="text" name="zip_code" required inputmode="numeric" pattern="[A-Za-z0-9 \-]{3,10}" placeholder="12345" data-required-msg="ZIP code is required." />
        </div>
        <?php endif; ?>

        <div class="ec-field ec-consent">
            <label>
                <input type="checkbox" name="terms" required data-required-msg="Consent is required to move forward." />
                <span class="ec-consent-text">
                    <span class="ec-req">*</span>
                    <?php echo esc_html( $labels['sms_consent'] ); ?>
                    <?php if ( $labels['privacy_url'] ) : ?>
                        <a href="<?php echo esc_url( $labels['privacy_url'] ); ?>" target="_blank">Privacy Policy</a>
                    <?php endif; ?>
                </span>
            </label>
        </div>

        <?php if ( ! empty( $labels['form_disclaimer'] ) ) : ?>
        <div class="ec-form-disclaimer">
            <p><?php echo esc_html( $labels['form_disclaimer'] ); ?></p>
        </div>
        <?php endif; ?>

        <!-- Hidden tracking fields (populated by JS at submit time) -->
        <input type="hidden" name="utm_source" />
        <input type="hidden" name="utm_medium" />
        <input type="hidden" name="utm_campaign" />
        <input type="hidden" name="utm_term" />
        <input type="hidden" name="utm_content" />
        <input type="hidden" name="fbclid" />
        <input type="hidden" name="fbp" />
        <input type="hidden" name="fbc" />
        <input type="hidden" name="gclid" />
        <input type="hidden" name="gbraid" />
        <input type="hidden" name="wbraid" />
        <input type="hidden" name="msclkid" />
        <input type="hidden" name="ttclid" />
        <input type="hidden" name="li_fat_id" />
        <input type="hidden" name="referrer" />
        <input type="hidden" name="landing_page_url" />
        <input type="hidden" name="lp_variant" />

        <div class="ec-actions">
            <button type="button" class="ec-btn ec-btn-back" data-goto="2">Back</button>
            <button type="button" class="ec-btn ec-btn-submit" id="ec-submit-btn">Get Estimate</button>
        </div>

        <div class="ec-loading" id="ec-loading" style="display:none;">
            <div class="ec-spinner"></div>
            <span>Calculating your estimate...</span>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- STEP 4: Inline Results + Booking Calendar                     -->
    <!-- ============================================================ -->
    <div class="ec-step" data-step="results">
        <div class="ec-estimate-result">
            <h2 class="ec-thank-heading">Thank you!</h2>
            <p class="ec-thank-sub">Your instant estimate for <span id="ec-result-service-inline"></span> is ready.</p>

            <div class="ec-range-display">
                <div class="ec-range-low">
                    <span class="ec-range-label">Low</span>
                    <span class="ec-range-value" id="ec-result-low">$0</span>
                </div>
                <div class="ec-range-separator">&ndash;</div>
                <div class="ec-range-high">
                    <span class="ec-range-label">High</span>
                    <span class="ec-range-value" id="ec-result-high">$0</span>
                </div>
                <span class="ec-asterisk">*</span>
            </div>

            <p class="ec-disclaimer">* <?php echo esc_html( $labels['results_disclaimer'] ); ?></p>
        </div>

        <?php
        $followup     = EC_Settings::get_followup_mode( $settings );
        $calendar_url = $settings['calendar_embed_url'] ?? '';
        $cta_label    = $settings['cta_button_label'] ?? 'Schedule Your Estimate';
        $cta_url      = $settings['cta_button_url']   ?? '';
        $cta_target   = ! empty( $settings['cta_button_new_tab'] ) ? ' target="_blank" rel="noopener"' : '';

        if ( $followup === 'custom_iframe' ) : ?>
        <div class="ec-booking-section">
            <h3>Ready to schedule your estimate?</h3>
            <p>Pick a time that works for you.</p>
            <div class="ec-calendar-embed ec-custom-embed">
                <?php echo $settings['calendar_custom_iframe']; // already sanitized on save ?>
            </div>
        </div>
        <?php elseif ( $followup === 'calendar' ) : ?>
        <div class="ec-booking-section">
            <h3>Ready to schedule your estimate?</h3>
            <p>Book a time that works for you and we'll come out to give you an exact quote.</p>
            <div class="ec-calendar-embed">
                <iframe
                    id="ec-calendar-iframe"
                    data-src="<?php echo esc_url( $calendar_url ); ?>"
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

        <div class="ec-actions" style="justify-content: center; margin-top: 24px;">
            <button type="button" class="ec-btn ec-btn-back" data-goto="1">Start New Estimate</button>
        </div>
    </div>

</div>
