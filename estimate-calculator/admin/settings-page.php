<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Render a single Site Token row for the REST API tab.
 */
if ( ! function_exists( 'ec_render_site_token_row' ) ) {
    function ec_render_site_token_row( $i, $t ) {
        $name_base = 'ec_settings[rest_site_tokens][' . $i . ']';
        $enabled   = ! empty( $t['enabled'] );
        $origins   = is_array( $t['origins'] ?? null ) ? implode( "\n", $t['origins'] ) : '';
        ?>
        <tr class="ec-site-token-row">
            <td>
                <input type="checkbox" name="<?php echo esc_attr( $name_base ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> />
            </td>
            <td>
                <input type="text" name="<?php echo esc_attr( $name_base ); ?>[label]" value="<?php echo esc_attr( $t['label'] ?? '' ); ?>" placeholder="e.g. Client A site" class="regular-text" />
                <?php if ( ! empty( $t['created_at'] ) ) : ?>
                    <br><small style="color:#888;font-size:11px">Created: <?php echo esc_html( $t['created_at'] ); ?></small>
                <?php endif; ?>
            </td>
            <td style="min-width:240px">
                <input type="text" class="ec-token-input" name="<?php echo esc_attr( $name_base ); ?>[token]" value="<?php echo esc_attr( $t['token'] ?? '' ); ?>" style="width:100%;font-family:monospace;font-size:12px;background:#f6f7f7" spellcheck="false" autocomplete="off" />
                <input type="hidden" name="<?php echo esc_attr( $name_base ); ?>[created_at]" value="<?php echo esc_attr( $t['created_at'] ?? '' ); ?>" />
                <div style="margin-top:4px;display:flex;gap:6px">
                    <button type="button" class="button button-small ec-regen-token">Regenerate</button>
                    <button type="button" class="button button-small ec-copy-token">Copy</button>
                </div>
                <p class="description" style="font-size:11px;margin-top:4px">Leave blank when adding a row to auto-generate one on save.</p>
            </td>
            <td>
                <textarea name="<?php echo esc_attr( $name_base ); ?>[origins]" rows="3" style="width:100%;font-family:monospace;font-size:12px" placeholder="https://client-site.com&#10;https://www.client-site.com"><?php echo esc_textarea( $origins ); ?></textarea>
            </td>
            <td><button type="button" class="button ec-remove-site-token">Remove</button></td>
        </tr>
        <?php
    }
}

/**
 * Render a single Custom Service row in the admin repeater.
 */
if ( ! function_exists( 'ec_render_custom_service_row' ) ) {
    function ec_render_custom_service_row( $i, $cs ) {
        $name_base = 'ec_settings[custom_services][' . $i . ']';
        $enabled   = ! empty( $cs['enabled'] );
        $sqft_on   = ! isset( $cs['price_per_sqft_enabled'] ) ? true : ! empty( $cs['price_per_sqft_enabled'] );
        $cond_on   = ! isset( $cs['condition_enabled'] )      ? true : ! empty( $cs['condition_enabled'] );

        // Build the conditions repeater data. Prefer new list, fall back to legacy.
        $conditions = [];
        if ( ! empty( $cs['conditions'] ) && is_array( $cs['conditions'] ) ) {
            $conditions = $cs['conditions'];
        } elseif ( ! empty( $cs['condition'] ) && is_array( $cs['condition'] ) ) {
            $known_labels = [
                'like_new' => 'Like New', 'light_wear' => 'Light Wear',
                'moderate_wear' => 'Moderate Wear', 'heavy_wear' => 'Heavy Wear',
            ];
            foreach ( $cs['condition'] as $slug => $mult ) {
                $conditions[] = [
                    'slug' => $slug,
                    'label' => $known_labels[ $slug ] ?? ucwords( str_replace( '_', ' ', $slug ) ),
                    'multiplier' => $mult,
                ];
            }
        }
        if ( empty( $conditions ) ) {
            $conditions = [
                [ 'slug' => 'like_new',      'label' => 'Like New',      'multiplier' => 1.0 ],
                [ 'slug' => 'light_wear',    'label' => 'Light Wear',    'multiplier' => 1.1 ],
                [ 'slug' => 'moderate_wear', 'label' => 'Moderate Wear', 'multiplier' => 1.2 ],
                [ 'slug' => 'heavy_wear',    'label' => 'Heavy Wear',    'multiplier' => 1.3 ],
            ];
        }
        ?>
        <tr class="ec-custom-service-row">
            <td>
                <input type="checkbox" name="<?php echo esc_attr( $name_base ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> />
            </td>
            <td>
                <input type="text" class="ec-cs-image-url" name="<?php echo esc_attr( $name_base ); ?>[image]" value="<?php echo esc_attr( $cs['image'] ?? '' ); ?>" placeholder="Image URL" style="width:100%;font-size:11px" />
                <button type="button" class="button button-small ec-cs-upload" style="margin-top:4px">Upload</button>
                <?php if ( ! empty( $cs['image'] ) ) : ?>
                    <br><img src="<?php echo esc_url( $cs['image'] ); ?>" style="max-width:90px;margin-top:4px;border-radius:4px" />
                <?php endif; ?>
            </td>
            <td>
                <input type="text" name="<?php echo esc_attr( $name_base ); ?>[label]" value="<?php echo esc_attr( $cs['label'] ?? '' ); ?>" placeholder="Service name (e.g. Deck Staining)" class="regular-text" />
                <br><input type="text" name="<?php echo esc_attr( $name_base ); ?>[slug]" value="<?php echo esc_attr( $cs['slug'] ?? '' ); ?>" placeholder="slug (auto)" style="margin-top:4px;font-size:11px;color:#666;width:100%" />
                <br><input type="text" name="<?php echo esc_attr( $name_base ); ?>[field_label]" value="<?php echo esc_attr( $cs['field_label'] ?? '' ); ?>" placeholder="Field label (e.g. 'Square Footage' or 'Rooms')" style="margin-top:4px;font-size:11px;color:#666;width:100%" />
            </td>
            <td class="ec-pricing-cell">
                <label style="font-size:12px;display:block;margin-bottom:6px">
                    <input type="checkbox" class="ec-toggle-sqft" name="<?php echo esc_attr( $name_base ); ?>[price_per_sqft_enabled]" value="1" <?php checked( $sqft_on ); ?> />
                    Use price per sq ft
                </label>
                <div class="ec-sqft-input" style="<?php echo $sqft_on ? '' : 'opacity:0.45'; ?>">
                    <small>Price / sq ft:</small><br>
                    $<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name_base ); ?>[price_per_sqft]" value="<?php echo esc_attr( $cs['price_per_sqft'] ?? 0 ); ?>" style="width:80px" />
                </div>
                <div class="ec-mult-input" style="margin-top:6px;<?php echo $sqft_on ? 'opacity:0.45' : ''; ?>">
                    <small>Price multiplier:</small><br>
                    $<input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name_base ); ?>[price_multiplier]" value="<?php echo esc_attr( $cs['price_multiplier'] ?? 0 ); ?>" style="width:80px" />
                </div>
                <p class="description" style="font-size:10px;margin-top:6px">
                    When sq ft mode is <em>off</em>:
                    <br>• If condition is also <em>off</em>: total = input × <strong>Price multiplier</strong>.
                    <br>• If condition is <em>on</em>: total = input × <strong>condition multiplier</strong> (Price multiplier is bypassed).
                </p>
            </td>
            <td><input type="number" step="0.01" min="0" max="100" name="<?php echo esc_attr( $name_base ); ?>[range_pct]" value="<?php echo esc_attr( $cs['range_pct'] ?? 25 ); ?>" style="width:80px" /> %</td>
            <td><button type="button" class="button ec-remove-custom-service">Remove</button></td>
        </tr>
        <tr class="ec-custom-service-condrow">
            <td></td>
            <td colspan="5">
                <details<?php echo $cond_on ? ' open' : ''; ?> style="background:#fafafa;padding:8px 12px;border:1px solid #e2e4e7;border-radius:4px">
                    <summary style="cursor:pointer;font-weight:600;color:#0073aa">Condition Multipliers (4 tiers)</summary>
                    <label style="display:block;margin:10px 0 6px;font-size:12px">
                        <input type="checkbox" class="ec-toggle-condition" name="<?php echo esc_attr( $name_base ); ?>[condition_enabled]" value="1" <?php checked( $cond_on ); ?> />
                        Apply condition multiplier
                    </label>
                    <div class="ec-condition-inputs" style="<?php echo $cond_on ? '' : 'opacity:0.45'; ?>">
                        <table class="widefat ec-service-conditions-table" style="margin-top:4px">
                            <thead>
                                <tr>
                                    <th>Label (shown to user)</th>
                                    <th style="width:160px">Slug</th>
                                    <th style="width:120px">Multiplier</th>
                                    <th style="width:90px">Remove</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ( $conditions as $ci => $c ) : ?>
                                <tr class="ec-service-condition-row">
                                    <td><input type="text" name="<?php echo esc_attr( $name_base ); ?>[conditions][<?php echo (int) $ci; ?>][label]" value="<?php echo esc_attr( $c['label'] ?? '' ); ?>" placeholder="e.g. Like New" style="width:100%" /></td>
                                    <td><input type="text" name="<?php echo esc_attr( $name_base ); ?>[conditions][<?php echo (int) $ci; ?>][slug]" value="<?php echo esc_attr( $c['slug'] ?? '' ); ?>" placeholder="auto" style="width:100%;font-size:11px;color:#666" /></td>
                                    <td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name_base ); ?>[conditions][<?php echo (int) $ci; ?>][multiplier]" value="<?php echo esc_attr( $c['multiplier'] ?? 1.0 ); ?>" style="width:90px" /></td>
                                    <td><button type="button" class="button ec-remove-service-condition">Remove</button></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top:8px">
                            <button type="button" class="button button-small ec-add-service-condition" data-service-index="<?php echo (int) $i; ?>">+ Add Condition</button>
                        </p>

                        <script type="text/template" class="ec-service-condition-row-template" data-service-index="<?php echo (int) $i; ?>">
                            <tr class="ec-service-condition-row">
                                <td><input type="text" name="<?php echo esc_attr( $name_base ); ?>[conditions][__INDEX__][label]" value="" placeholder="e.g. Brand New" style="width:100%" /></td>
                                <td><input type="text" name="<?php echo esc_attr( $name_base ); ?>[conditions][__INDEX__][slug]" value="" placeholder="auto" style="width:100%;font-size:11px;color:#666" /></td>
                                <td><input type="number" step="0.01" min="0" name="<?php echo esc_attr( $name_base ); ?>[conditions][__INDEX__][multiplier]" value="1.0" style="width:90px" /></td>
                                <td><button type="button" class="button ec-remove-service-condition">Remove</button></td>
                            </tr>
                        </script>
                    </div>
                    <p class="description" style="margin:8px 0 0;font-size:11px">Add, rename, edit or remove condition tiers. Slug is auto-generated from the label if blank. When the toggle above is off, the dropdown won't appear on the front end.</p>
                </details>
            </td>
        </tr>
        <?php
    }
}

/**
 * Render the Custom Questions repeater inside a pricing tab.
 */
if ( ! function_exists( 'ec_render_custom_questions_admin' ) ) {
    function ec_render_custom_questions_admin( $service, $questions ) {
        $service     = preg_replace( '/[^a-z]/', '', strtolower( $service ) );
        $field_base  = 'ec_settings[' . $service . '_custom_questions]';
        $list        = is_array( $questions ) ? $questions : [];
        ?>
        <tr>
            <th colspan="2">
                <strong>Custom Questions</strong>
                <p class="description" style="margin-top:6px;font-weight:normal">
                    Add your own questions that alter the estimate. <em>Yes/No</em> questions have separate values for each answer and can either <strong>add</strong> a flat amount or <strong>multiply</strong> the subtotal. <em>Number</em> questions multiply the user's count by your per-unit value.
                </p>
            </th>
        </tr>
        <tr><td colspan="2">
            <table class="widefat ec-questions-table" data-service="<?php echo esc_attr( $service ); ?>">
                <thead>
                    <tr>
                        <th style="width:70px">Enabled</th>
                        <th>Question Label</th>
                        <th style="width:120px">Type</th>
                        <th>Values</th>
                        <th style="width:90px">Remove</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ( ! empty( $list ) ) {
                    foreach ( $list as $i => $q ) {
                        ec_render_question_row( $service, $i, $q );
                    }
                }
                ?>
                </tbody>
            </table>
            <p style="margin-top:10px">
                <button type="button" class="button button-secondary ec-add-question" data-service="<?php echo esc_attr( $service ); ?>">+ Add Question</button>
            </p>

            <script type="text/template" id="ec-question-row-template-<?php echo esc_attr( $service ); ?>">
                <?php ec_render_question_row( $service, '__INDEX__', [
                    'enabled' => 1, 'slug' => '', 'label' => '', 'type' => 'yesno',
                    'yes_value' => 0, 'no_value' => 0, 'op' => 'add', 'unit_value' => 0,
                ] ); ?>
            </script>
        </td></tr>
        <?php
    }
}

/**
 * Render a single question row.
 */
if ( ! function_exists( 'ec_render_question_row' ) ) {
    function ec_render_question_row( $service, $i, $q ) {
        $name_base = 'ec_settings[' . $service . '_custom_questions][' . $i . ']';
        $type      = $q['type'] ?? 'yesno';
        $enabled   = ! empty( $q['enabled'] );
        ?>
        <tr class="ec-question-row" data-type="<?php echo esc_attr( $type ); ?>">
            <td>
                <input type="checkbox" name="<?php echo esc_attr( $name_base ); ?>[enabled]" value="1" <?php checked( $enabled ); ?> />
            </td>
            <td>
                <input type="text" name="<?php echo esc_attr( $name_base ); ?>[label]" value="<?php echo esc_attr( $q['label'] ?? '' ); ?>" placeholder="e.g. Do you have French doors?" class="regular-text" />
                <br><input type="text" name="<?php echo esc_attr( $name_base ); ?>[slug]" value="<?php echo esc_attr( $q['slug'] ?? '' ); ?>" placeholder="slug (auto)" style="margin-top:4px;font-size:11px;color:#666" />
            </td>
            <td>
                <select name="<?php echo esc_attr( $name_base ); ?>[type]" class="ec-question-type">
                    <option value="yesno"  <?php selected( $type, 'yesno' );  ?>>Yes / No</option>
                    <option value="number" <?php selected( $type, 'number' ); ?>>Number</option>
                    <option value="select" <?php selected( $type, 'select' ); ?>>Select (GHL-only)</option>
                    <option value="percent_select" <?php selected( $type, 'percent_select' ); ?>>Percent Select (adjusts total by %)</option>
                </select>
            </td>
            <td>
                <div class="ec-q-values ec-q-yesno" style="<?php echo $type === 'yesno' ? '' : 'display:none'; ?>">
                    <label style="font-size:12px">Yes&nbsp;value: <input type="number" step="0.01" name="<?php echo esc_attr( $name_base ); ?>[yes_value]" value="<?php echo esc_attr( $q['yes_value'] ?? 0 ); ?>" style="width:90px" /></label>
                    <label style="font-size:12px;margin-left:8px">No&nbsp;value: <input type="number" step="0.01" name="<?php echo esc_attr( $name_base ); ?>[no_value]" value="<?php echo esc_attr( $q['no_value'] ?? 0 ); ?>" style="width:90px" /></label>
                    <br>
                    <label style="font-size:12px">Op:
                        <select name="<?php echo esc_attr( $name_base ); ?>[op]">
                            <option value="add"      <?php selected( $q['op'] ?? 'add', 'add' );      ?>>Add to subtotal</option>
                            <option value="multiply" <?php selected( $q['op'] ?? 'add', 'multiply' ); ?>>Multiply subtotal</option>
                        </select>
                    </label>
                </div>
                <div class="ec-q-values ec-q-number" style="<?php echo $type === 'number' ? '' : 'display:none'; ?>">
                    <label style="font-size:12px">Per-unit&nbsp;value: $<input type="number" step="0.01" name="<?php echo esc_attr( $name_base ); ?>[unit_value]" value="<?php echo esc_attr( $q['unit_value'] ?? 0 ); ?>" style="width:90px" /></label>
                    <br><span class="description" style="font-size:11px">Final contribution = user's count × per-unit value (added to subtotal).</span>
                </div>
                <div class="ec-q-values ec-q-select" style="<?php echo $type === 'select' ? '' : 'display:none'; ?>">
                    <?php
                    // Convert stored [{value, label}, …] back into "value|label" per-line text for editing
                    $opts_arr  = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : [];
                    $opts_text = '';
                    foreach ( $opts_arr as $opt ) {
                        $opts_text .= ( $opt['value'] ?? '' ) . '|' . ( $opt['label'] ?? '' ) . "\n";
                    }
                    $opts_text = trim( $opts_text );
                    ?>
                    <label style="font-size:12px;display:block;margin-bottom:4px">Options (one per line — <code>value|label</code> or just <code>label</code>):</label>
                    <textarea name="<?php echo esc_attr( $name_base ); ?>[options]" rows="4" style="width:100%;font-family:monospace;font-size:11px" placeholder="option_a|Option A&#10;option_b|Option B&#10;option_c|Option C"><?php echo esc_textarea( $opts_text ); ?></textarea>
                    <label style="font-size:12px;display:block;margin-top:8px">GHL custom field key:
                        <input type="text" name="<?php echo esc_attr( $name_base ); ?>[ghl_field_key]" value="<?php echo esc_attr( $q['ghl_field_key'] ?? '' ); ?>" placeholder="my_ghl_field_key" style="width:170px;font-family:monospace;font-size:11px" />
                    </label>
                    <label style="font-size:12px;display:block;margin-top:6px">
                        <input type="checkbox" name="<?php echo esc_attr( $name_base ); ?>[required]" value="1" <?php checked( ! empty( $q['required'] ) ); ?> /> Required field
                    </label>
                    <p class="description" style="font-size:11px;margin-top:4px">
                        Does NOT affect the calculation. Value is synced to the GHL custom field above.<br>
                        If the GHL key is left blank, syncs to <code>{service}_q_{slug}</code>.
                    </p>
                </div>
                <div class="ec-q-values ec-q-percent_select" style="<?php echo $type === 'percent_select' ? '' : 'display:none'; ?>">
                    <?php
                    // Convert stored [{value, label, percent}, …] back into "value|label|percent" per-line for editing
                    $popts_arr  = isset( $q['options'] ) && is_array( $q['options'] ) ? $q['options'] : [];
                    $popts_text = '';
                    foreach ( $popts_arr as $opt ) {
                        $popts_text .= ( $opt['value'] ?? '' ) . '|' . ( $opt['label'] ?? '' ) . '|' . ( isset( $opt['percent'] ) ? (float) $opt['percent'] : 0 ) . "\n";
                    }
                    $popts_text = trim( $popts_text );
                    ?>
                    <label style="font-size:12px;display:block;margin-bottom:4px">Options (one per line — <code>value|label|percent</code>, or <code>label|percent</code>):</label>
                    <textarea name="<?php echo esc_attr( $name_base ); ?>[options]" rows="4" style="width:100%;font-family:monospace;font-size:11px" placeholder="simple|Simple job|-10&#10;standard|Standard|0&#10;complex|Complex|15&#10;very_complex|Very Complex|30"><?php echo esc_textarea( $popts_text ); ?></textarea>
                    <label style="font-size:12px;display:block;margin-top:8px">GHL custom field key:
                        <input type="text" name="<?php echo esc_attr( $name_base ); ?>[ghl_field_key]" value="<?php echo esc_attr( $q['ghl_field_key'] ?? '' ); ?>" placeholder="my_ghl_field_key" style="width:170px;font-family:monospace;font-size:11px" />
                    </label>
                    <label style="font-size:12px;display:block;margin-top:6px">
                        <input type="checkbox" name="<?php echo esc_attr( $name_base ); ?>[required]" value="1" <?php checked( ! empty( $q['required'] ) ); ?> /> Required field
                    </label>
                    <p class="description" style="font-size:11px;margin-top:4px">
                        Each option adjusts the subtotal by its percent: <code>-10</code> = 10% discount, <code>15</code> = +15%, <code>0</code> = no change.<br>
                        The picked label is synced to GHL (uses the key above, or <code>{service}_q_{slug}</code> if blank).
                    </p>
                </div>
            </td>
            <td><button type="button" class="button ec-remove-question">Remove</button></td>
        </tr>
        <?php
    }
}
?>
<div class="wrap ec-admin-wrap">
    <h1>Estimate Calculator Settings</h1>

    <form method="post" action="options.php">
        <?php settings_fields( 'ec_settings_group' ); ?>

        <!-- Tabs -->
        <nav class="ec-tabs">
            <a href="#tab-ghl" class="ec-tab active">GHL Integration</a>
            <a href="#tab-tracking" class="ec-tab">Tracking &amp; Pixel</a>
            <a href="#tab-services" class="ec-tab">Services</a>
            <a href="#tab-interior" class="ec-tab">Interior Pricing</a>
            <a href="#tab-exterior" class="ec-tab">Exterior Pricing</a>
            <a href="#tab-cabinet" class="ec-tab">Cabinet Pricing</a>
            <a href="#tab-custom-services" class="ec-tab">Custom Services</a>
            <a href="#tab-external" class="ec-tab">External Sites</a>
            <a href="#tab-leadtrack" class="ec-tab">Lead Tracking</a>
            <a href="#tab-branding" class="ec-tab">Branding &amp; Labels</a>
            <a href="#tab-business" class="ec-tab">Business &amp; Schema</a>
            <a href="#tab-shortcodes" class="ec-tab">Shortcodes</a>
        </nav>

        <!-- GHL Integration -->
        <div id="tab-ghl" class="ec-tab-content active">
            <h2>GoHighLevel Connection (Private Integration API V2)</h2>
            <p class="description">
                This plugin uses the <strong>GHL Private Integration API V2.0</strong>. Create a Private Integration in the client's sub-account to get a secure, scoped access token.<br>
                <strong>Steps:</strong> GHL Sub-Account &rarr; Settings &rarr; Integrations &rarr; Private Integrations &rarr; Create &rarr; copy the <em>Access Token</em> and <em>Location ID</em>.<br>
                <strong>Never use agency-level API keys or grant clients agency admin access.</strong>
            </p>

            <table class="form-table">
                <tr>
                    <th><label for="ghl_api_key">Private Integration Access Token</label></th>
                    <td>
                        <input type="password" id="ghl_api_key" name="ec_settings[ghl_api_key]" value="<?php echo esc_attr( $s['ghl_api_key'] ); ?>" class="regular-text" autocomplete="off" />
                        <p class="description">Access token from the Private Integration. Found in Settings &rarr; Integrations &rarr; Private Integrations &rarr; your integration &rarr; Access Token. Scoped to this sub-account only.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ghl_location_id">Location ID</label></th>
                    <td>
                        <input type="text" id="ghl_location_id" name="ec_settings[ghl_location_id]" value="<?php echo esc_attr( $s['ghl_location_id'] ); ?>" class="regular-text" />
                        <p class="description">The GHL location/sub-account ID.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ghl_calendar_id">Calendar ID</label></th>
                    <td>
                        <input type="text" id="ghl_calendar_id" name="ec_settings[ghl_calendar_id]" value="<?php echo esc_attr( $s['ghl_calendar_id'] ); ?>" class="regular-text" />
                        <p class="description">The booking calendar ID for estimates. Found in Calendars &rarr; Calendar Settings &rarr; Calendar URL.</p>
                    </td>
                </tr>
                <tr>
                    <th>Enable Calendar Embed</th>
                    <td>
                        <label><input type="checkbox" name="ec_settings[enable_calendar]" value="1" <?php checked( $s['enable_calendar'], 1 ); ?> /> Show the GHL booking calendar after the user submits an estimate</label>
                        <p class="description">Uncheck this to skip the booking calendar entirely. If unchecked (or if no Calendar Embed URL is set), the fallback CTA button below will be shown instead.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="calendar_embed_url">Calendar Embed URL</label></th>
                    <td>
                        <input type="url" id="calendar_embed_url" name="ec_settings[calendar_embed_url]" value="<?php echo esc_attr( $s['calendar_embed_url'] ); ?>" class="regular-text" placeholder="https://link.yourdomain.com/widget/booking/XXXXXXX" />
                        <p class="description">Full GHL calendar widget URL. Leave blank if you don't have a GHL calendar.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="calendar_custom_iframe">Custom Iframe / Embed Code</label></th>
                    <td>
                        <textarea id="calendar_custom_iframe" name="ec_settings[calendar_custom_iframe]" rows="6" class="large-text code" placeholder="&lt;iframe src=&quot;https://calendly.com/your-link&quot; width=&quot;100%&quot; height=&quot;700&quot;&gt;&lt;/iframe&gt;"><?php echo esc_textarea( $s['calendar_custom_iframe'] ); ?></textarea>
                        <p class="description">
                            <strong>Optional.</strong> Paste any booking embed code here (Calendly, Acuity, SimplyBook, Square Appointments, etc.).<br>
                            <strong>When set, this overrides the GHL Calendar URL above.</strong><br>
                            Accepts <code>&lt;iframe&gt;</code>, <code>&lt;div&gt;</code>, and <code>&lt;script&gt;</code> tags from trusted providers.
                        </p>
                    </td>
                </tr>
            </table>

            <h3>How the booking section is chosen</h3>
            <ol style="line-height:1.6">
                <li>If "Enable Calendar Embed" is off → fallback CTA button shown (if configured), else nothing.</li>
                <li>If <strong>Custom Iframe / Embed Code</strong> is filled → uses that (overrides GHL URL).</li>
                <li>Else if <strong>Calendar Embed URL</strong> is filled → uses GHL calendar.</li>
                <li>Else → uses the fallback CTA button (if configured), else nothing.</li>
            </ol>

            <h2>Fallback CTA Button</h2>
            <p class="description">
                Shown <strong>only when no calendar is configured</strong> (calendar disabled OR Calendar Embed URL empty).<br>
                Useful for linking to a separate "Schedule Estimate" or "Contact" page.
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="cta_button_label">Button Label</label></th>
                    <td>
                        <input type="text" id="cta_button_label" name="ec_settings[cta_button_label]" value="<?php echo esc_attr( $s['cta_button_label'] ); ?>" class="regular-text" placeholder="Schedule Your Estimate" />
                    </td>
                </tr>
                <tr>
                    <th><label for="cta_button_url">Button Link URL</label></th>
                    <td>
                        <input type="url" id="cta_button_url" name="ec_settings[cta_button_url]" value="<?php echo esc_attr( $s['cta_button_url'] ); ?>" class="regular-text" placeholder="https://yoursite.com/contact" />
                        <p class="description">Where the button takes the user. Leave blank to hide the fallback button entirely.</p>
                    </td>
                </tr>
                <tr>
                    <th>Open in New Tab</th>
                    <td><label><input type="checkbox" name="ec_settings[cta_button_new_tab]" value="1" <?php checked( $s['cta_button_new_tab'], 1 ); ?> /> Open link in a new tab</label></td>
                </tr>
            </table>

            <h2>Opportunity / Pipeline</h2>
            <p class="description">
                To create an Opportunity for each estimate submission, enter the Pipeline ID and Stage ID below.<br>
                <strong>Both are required</strong> for opportunities to be created. Find them in GHL &rarr; Opportunities &rarr; Pipeline Settings.<br>
                The Pipeline ID is in the URL when viewing a pipeline. The Stage ID is in the URL when clicking a stage.
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="ghl_pipeline_id">Pipeline ID</label></th>
                    <td>
                        <input type="text" id="ghl_pipeline_id" name="ec_settings[ghl_pipeline_id]" value="<?php echo esc_attr( $s['ghl_pipeline_id'] ); ?>" class="regular-text" placeholder="e.g. aBcDeFgHiJkLmNoPqR" />
                        <p class="description">Required for opportunity creation. Found in Opportunities &rarr; your pipeline &rarr; look at the URL for the pipeline ID.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="ghl_stage_id">Stage ID</label></th>
                    <td>
                        <input type="text" id="ghl_stage_id" name="ec_settings[ghl_stage_id]" value="<?php echo esc_attr( $s['ghl_stage_id'] ); ?>" class="regular-text" placeholder="e.g. xYzAbCdEfGhIjKlMnO" />
                        <p class="description">Required for opportunity creation. The stage new estimate leads should land in (e.g. "New Lead").</p>
                    </td>
                </tr>
            </table>

            <h2>Required Private Integration Scopes</h2>
            <p class="description">When creating the Private Integration in GHL, ensure these scopes are enabled:</p>
            <ul style="list-style:disc; padding-left:20px; color:#23282d;">
                <li><code>contacts.write</code> — create/update contacts</li>
                <li><code>contacts.readonly</code> — lookup existing contacts</li>
                <li><code>opportunities.write</code> — create opportunities in pipelines</li>
                <li><code>locations.readonly</code> — validate location access</li>
                <li><code>calendars.readonly</code> — calendar embed (if using calendar widget)</li>
            </ul>
        </div>

        <!-- Tracking -->
        <div id="tab-tracking" class="ec-tab-content">
            <h2>Tracking &amp; Pixel</h2>
            <table class="form-table">
                <tr>
                    <th><label for="pixel_type">Pixel Type</label></th>
                    <td>
                        <select id="pixel_type" name="ec_settings[pixel_type]">
                            <option value="facebook" <?php selected( $s['pixel_type'], 'facebook' ); ?>>Facebook / Meta</option>
                            <option value="google" <?php selected( $s['pixel_type'], 'google' ); ?>>Google Ads</option>
                            <option value="tiktok" <?php selected( $s['pixel_type'], 'tiktok' ); ?>>TikTok</option>
                            <option value="custom" <?php selected( $s['pixel_type'], 'custom' ); ?>>Custom Code</option>
                            <option value="none" <?php selected( $s['pixel_type'], 'none' ); ?>>None</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="pixel_id">Pixel / Conversion ID</label></th>
                    <td>
                        <input type="text" id="pixel_id" name="ec_settings[pixel_id]" value="<?php echo esc_attr( $s['pixel_id'] ); ?>" class="regular-text" />
                        <p class="description">For Facebook: pixel ID. For Google: AW-XXXXXXXXX/conversion-label. For TikTok: pixel ID.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="custom_pixel_code">Custom Pixel Code</label></th>
                    <td>
                        <textarea id="custom_pixel_code" name="ec_settings[custom_pixel_code]" rows="6" class="large-text code"><?php echo esc_textarea( $s['custom_pixel_code'] ); ?></textarea>
                        <p class="description">If "Custom Code" is selected, paste your full tracking script here. Fired on calculator submission.</p>
                    </td>
                </tr>
            </table>

            <h2>Asset Loading</h2>
            <table class="form-table">
                <tr>
                    <th>Force Load Plugin Assets</th>
                    <td>
                        <label><input type="checkbox" name="ec_settings[force_load_assets]" value="1" <?php checked( $s['force_load_assets'], 1 ); ?> /> Always load the plugin's CSS &amp; JS on every page</label>
                        <p class="description">
                            <strong>Enable this if you embed the calculator using raw HTML</strong> (Avada Custom Code, WordPress HTML block, page builder code blocks, etc.) instead of the <code>[estimate_calculator]</code> shortcode.<br>
                            By default the plugin auto-detects shortcodes <em>and</em> rendered HTML markers (<code>id="ec-calculator"</code>, <code>class="ec-thankyou"</code>, etc.) — turn this on if detection fails for your setup.
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Thank-You Page</h2>
            <table class="form-table">
                <tr>
                    <th><label for="thankyou_page_id">Thank-You Page</label></th>
                    <td>
                        <?php
                        wp_dropdown_pages( [
                            'name'             => 'ec_settings[thankyou_page_id]',
                            'id'               => 'thankyou_page_id',
                            'selected'         => $s['thankyou_page_id'],
                            'show_option_none' => '-- Select a page --',
                            'option_none_value'=> 0,
                        ] );
                        ?>
                        <p class="description">Page with <code>[estimate_thankyou]</code> shortcode. Displays the estimate range and booking calendar.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Services -->
        <div id="tab-services" class="ec-tab-content">
            <h2>Enabled Services</h2>
            <p class="description">Toggle which estimate types are available for this client.</p>
            <table class="form-table">
                <tr>
                    <th>Interior Painting</th>
                    <td><label><input type="checkbox" name="ec_settings[enable_interior]" value="1" <?php checked( $s['enable_interior'], 1 ); ?> /> Enable</label></td>
                </tr>
                <tr>
                    <th>Exterior Painting</th>
                    <td><label><input type="checkbox" name="ec_settings[enable_exterior]" value="1" <?php checked( $s['enable_exterior'], 1 ); ?> /> Enable</label></td>
                </tr>
                <tr>
                    <th>Cabinet Painting</th>
                    <td><label><input type="checkbox" name="ec_settings[enable_cabinet]" value="1" <?php checked( $s['enable_cabinet'], 1 ); ?> /> Enable</label></td>
                </tr>
            </table>

            <h2>Contact Form Fields</h2>
            <table class="form-table">
                <tr>
                    <th>Show ZIP / Postal Code</th>
                    <td>
                        <label><input type="checkbox" name="ec_settings[enable_zip_field]" value="1" <?php checked( $s['enable_zip_field'], 1 ); ?> /> Ask for the user's ZIP code on the contact step</label>
                        <p class="description">When enabled, an additional required "ZIP Code" field appears on the last step of the form and its value is synced to GHL as the contact's <code>postalCode</code>.</p>
                    </td>
                </tr>
            </table>

            <h2>Service Images</h2>
            <p class="description">Upload images for each service card on the calculator.</p>
            <table class="form-table">
                <?php foreach ( [ 'interior', 'exterior', 'cabinet' ] as $svc ) : ?>
                <tr>
                    <th><?php echo ucfirst( $svc ); ?> Image</th>
                    <td>
                        <input type="text" name="ec_settings[image_<?php echo $svc; ?>]" value="<?php echo esc_attr( $s[ 'image_' . $svc ] ); ?>" class="regular-text ec-image-url" />
                        <button type="button" class="button ec-upload-btn">Upload</button>
                        <?php if ( $s[ 'image_' . $svc ] ) : ?>
                            <br><img src="<?php echo esc_url( $s[ 'image_' . $svc ] ); ?>" style="max-width:150px;margin-top:8px;" />
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <!-- Interior Pricing -->
        <div id="tab-interior" class="ec-tab-content">
            <h2>Interior Painting Pricing</h2>
            <h3>Room Size Ranges &amp; Prices</h3>
            <p class="description">Customize the square footage ranges and prices for each room size category.</p>
            <table class="form-table">
                <tr>
                    <th>Small Room</th>
                    <td>
                        <input type="number" step="1" name="ec_settings[interior_small_sqft_min]" value="<?php echo esc_attr( $s['interior_small_sqft_min'] ); ?>" style="width:80px" /> &ndash;
                        <input type="number" step="1" name="ec_settings[interior_small_sqft_max]" value="<?php echo esc_attr( $s['interior_small_sqft_max'] ); ?>" style="width:80px" /> sq ft
                        &nbsp;&nbsp; Price: $<input type="number" step="0.01" name="ec_settings[interior_small_room_price]" value="<?php echo esc_attr( $s['interior_small_room_price'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th>Medium Room</th>
                    <td>
                        <input type="number" step="1" name="ec_settings[interior_medium_sqft_min]" value="<?php echo esc_attr( $s['interior_medium_sqft_min'] ); ?>" style="width:80px" /> &ndash;
                        <input type="number" step="1" name="ec_settings[interior_medium_sqft_max]" value="<?php echo esc_attr( $s['interior_medium_sqft_max'] ); ?>" style="width:80px" /> sq ft
                        &nbsp;&nbsp; Price: $<input type="number" step="0.01" name="ec_settings[interior_medium_room_price]" value="<?php echo esc_attr( $s['interior_medium_room_price'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th>Large Room</th>
                    <td>
                        <input type="number" step="1" name="ec_settings[interior_large_sqft_min]" value="<?php echo esc_attr( $s['interior_large_sqft_min'] ); ?>" style="width:80px" /> &ndash;
                        <input type="number" step="1" name="ec_settings[interior_large_sqft_max]" value="<?php echo esc_attr( $s['interior_large_sqft_max'] ); ?>" style="width:80px" /> sq ft
                        &nbsp;&nbsp; Price: $<input type="number" step="0.01" name="ec_settings[interior_large_room_price]" value="<?php echo esc_attr( $s['interior_large_room_price'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th>Extra Large Room</th>
                    <td>
                        <input type="number" step="1" name="ec_settings[interior_xlarge_sqft_min]" value="<?php echo esc_attr( $s['interior_xlarge_sqft_min'] ); ?>" style="width:80px" /> &ndash;
                        <input type="number" step="1" name="ec_settings[interior_xlarge_sqft_max]" value="<?php echo esc_attr( $s['interior_xlarge_sqft_max'] ); ?>" style="width:80px" /> sq ft
                        &nbsp; <label><input type="checkbox" name="ec_settings[interior_xlarge_open_ended]" value="1" <?php checked( $s['interior_xlarge_open_ended'], 1 ); ?> /> open-ended (show "+")</label>
                        &nbsp;&nbsp; Price: $<input type="number" step="0.01" name="ec_settings[interior_xlarge_room_price]" value="<?php echo esc_attr( $s['interior_xlarge_room_price'] ); ?>" />
                    </td>
                </tr>
                <tr><th colspan="2"><strong>Door Prices</strong></th></tr>
                <tr><th>Entry Door (garage/backyard/front)</th><td><input type="number" step="0.01" name="ec_settings[interior_entry_door_price]" value="<?php echo esc_attr( $s['interior_entry_door_price'] ); ?>" /></td></tr>
                <tr><th>Closet / Interior Door</th><td><input type="number" step="0.01" name="ec_settings[interior_closet_door_price]" value="<?php echo esc_attr( $s['interior_closet_door_price'] ); ?>" /></td></tr>
                <tr><th>Ceiling Add-on Multiplier</th><td><input type="number" step="0.01" name="ec_settings[interior_ceiling_multiplier]" value="<?php echo esc_attr( $s['interior_ceiling_multiplier'] ); ?>" /><p class="description">Multiplied by room total and added</p></td></tr>
                <tr><th>Trim Add-on Multiplier</th><td><input type="number" step="0.01" name="ec_settings[interior_trim_multiplier]" value="<?php echo esc_attr( $s['interior_trim_multiplier'] ); ?>" /></td></tr>
                <tr><th colspan="2"><strong>Condition Multipliers (4 tiers)</strong></th></tr>
                <tr><th>Like New</th><td><input type="number" step="0.01" name="ec_settings[interior_cond_like_new]" value="<?php echo esc_attr( $s['interior_cond_like_new'] ); ?>" /></td></tr>
                <tr><th>Light Wear</th><td><input type="number" step="0.01" name="ec_settings[interior_cond_light_wear]" value="<?php echo esc_attr( $s['interior_cond_light_wear'] ); ?>" /></td></tr>
                <tr><th>Moderate Wear</th><td><input type="number" step="0.01" name="ec_settings[interior_cond_moderate_wear]" value="<?php echo esc_attr( $s['interior_cond_moderate_wear'] ); ?>" /></td></tr>
                <tr><th>Heavy Wear</th><td><input type="number" step="0.01" name="ec_settings[interior_cond_heavy_wear]" value="<?php echo esc_attr( $s['interior_cond_heavy_wear'] ); ?>" /></td></tr>
                <tr><th colspan="2"><strong>Estimate Range</strong></th></tr>
                <tr><th>Range Variance (%)</th><td><input type="number" step="0.01" min="0" max="100" name="ec_settings[interior_range_pct]" value="<?php echo esc_attr( $s['interior_range_pct'] ); ?>" /> <span class="description">e.g. 25 = ±25% from the total. Low = total &minus; %, High = total + %.</span></td></tr>
                <?php ec_render_custom_questions_admin( 'interior', $s['interior_custom_questions'] ?? [] ); ?>
            </table>
        </div>

        <!-- Exterior Pricing -->
        <div id="tab-exterior" class="ec-tab-content">
            <h2>Exterior Painting Pricing</h2>
            <h3>Home Size Ranges &amp; Base Prices</h3>
            <p class="description">Customize the square footage ranges and base prices for each home size category.</p>
            <table class="form-table">
                <tr>
                    <th>Small Home</th>
                    <td>
                        <input type="number" step="1" name="ec_settings[exterior_small_sqft_min]" value="<?php echo esc_attr( $s['exterior_small_sqft_min'] ); ?>" style="width:80px" /> &ndash;
                        <input type="number" step="1" name="ec_settings[exterior_small_sqft_max]" value="<?php echo esc_attr( $s['exterior_small_sqft_max'] ); ?>" style="width:80px" /> sq ft
                        &nbsp;&nbsp; Base Price: $<input type="number" step="0.01" name="ec_settings[exterior_small_base]" value="<?php echo esc_attr( $s['exterior_small_base'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th>Medium Home</th>
                    <td>
                        <input type="number" step="1" name="ec_settings[exterior_medium_sqft_min]" value="<?php echo esc_attr( $s['exterior_medium_sqft_min'] ); ?>" style="width:80px" /> &ndash;
                        <input type="number" step="1" name="ec_settings[exterior_medium_sqft_max]" value="<?php echo esc_attr( $s['exterior_medium_sqft_max'] ); ?>" style="width:80px" /> sq ft
                        &nbsp;&nbsp; Base Price: $<input type="number" step="0.01" name="ec_settings[exterior_medium_base]" value="<?php echo esc_attr( $s['exterior_medium_base'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th>Large Home</th>
                    <td>
                        <input type="number" step="1" name="ec_settings[exterior_large_sqft_min]" value="<?php echo esc_attr( $s['exterior_large_sqft_min'] ); ?>" style="width:80px" /> &ndash;
                        <input type="number" step="1" name="ec_settings[exterior_large_sqft_max]" value="<?php echo esc_attr( $s['exterior_large_sqft_max'] ); ?>" style="width:80px" /> sq ft
                        &nbsp; <label><input type="checkbox" name="ec_settings[exterior_large_open_ended]" value="1" <?php checked( $s['exterior_large_open_ended'], 1 ); ?> /> open-ended (show "+")</label>
                        &nbsp;&nbsp; Base Price: $<input type="number" step="0.01" name="ec_settings[exterior_large_base]" value="<?php echo esc_attr( $s['exterior_large_base'] ); ?>" />
                    </td>
                </tr>
                <tr>
                    <th colspan="2">
                        <strong>Material Multipliers</strong>
                        <p class="description" style="margin-top:6px;font-weight:normal">
                            Toggle materials on/off, edit labels and multipliers, or add your own.
                            Disabled materials won't appear in the dropdown on the front end.
                        </p>
                    </th>
                </tr>
                <tr><td colspan="2">
                    <table class="widefat ec-materials-table" id="ec-materials-table">
                        <thead>
                            <tr>
                                <th style="width:80px">Enabled</th>
                                <th>Label (shown to user)</th>
                                <th>Slug</th>
                                <th style="width:120px">Multiplier</th>
                                <th style="width:90px">Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $materials_list = ! empty( $s['exterior_materials'] ) && is_array( $s['exterior_materials'] )
                            ? $s['exterior_materials']
                            : EC_Settings::defaults()['exterior_materials'];

                        foreach ( $materials_list as $i => $m ) :
                            $slug = $m['slug'] ?? '';
                            $label = $m['label'] ?? '';
                            $mult = isset( $m['multiplier'] ) ? $m['multiplier'] : 1.0;
                            $enabled = ! empty( $m['enabled'] );
                        ?>
                            <tr class="ec-material-row">
                                <td><input type="checkbox" name="ec_settings[exterior_materials][<?php echo $i; ?>][enabled]" value="1" <?php checked( $enabled ); ?> /></td>
                                <td><input type="text" name="ec_settings[exterior_materials][<?php echo $i; ?>][label]" value="<?php echo esc_attr( $label ); ?>" class="regular-text" placeholder="e.g. Vinyl" /></td>
                                <td><input type="text" name="ec_settings[exterior_materials][<?php echo $i; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>" placeholder="auto" /></td>
                                <td><input type="number" step="0.01" min="0" name="ec_settings[exterior_materials][<?php echo $i; ?>][multiplier]" value="<?php echo esc_attr( $mult ); ?>" /></td>
                                <td><button type="button" class="button ec-remove-material">Remove</button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p style="margin-top:10px">
                        <button type="button" class="button button-secondary" id="ec-add-material">+ Add Material</button>
                    </p>

                    <!-- Template for new rows -->
                    <script type="text/template" id="ec-material-row-template">
                        <tr class="ec-material-row">
                            <td><input type="checkbox" name="ec_settings[exterior_materials][__INDEX__][enabled]" value="1" checked /></td>
                            <td><input type="text" name="ec_settings[exterior_materials][__INDEX__][label]" value="" class="regular-text" placeholder="e.g. Vinyl" /></td>
                            <td><input type="text" name="ec_settings[exterior_materials][__INDEX__][slug]" value="" placeholder="auto" /></td>
                            <td><input type="number" step="0.01" min="0" name="ec_settings[exterior_materials][__INDEX__][multiplier]" value="1.0" /></td>
                            <td><button type="button" class="button ec-remove-material">Remove</button></td>
                        </tr>
                    </script>
                </td></tr>
                <tr><th colspan="2"><strong>Add-on Prices</strong></th></tr>
                <tr><th>Single Garage Door</th><td><input type="number" step="0.01" name="ec_settings[exterior_single_garage]" value="<?php echo esc_attr( $s['exterior_single_garage'] ); ?>" /></td></tr>
                <tr><th>Double Garage Door</th><td><input type="number" step="0.01" name="ec_settings[exterior_double_garage]" value="<?php echo esc_attr( $s['exterior_double_garage'] ); ?>" /></td></tr>
                <tr><th>Shutter (per unit)</th><td>$<input type="number" step="0.01" name="ec_settings[exterior_shutter_price]" value="<?php echo esc_attr( $s['exterior_shutter_price'] ); ?>" /> <span class="description">Multiplied by the number of shutters entered by the user.</span></td></tr>
                <tr><th>Trim Multiplier</th><td><input type="number" step="0.01" name="ec_settings[exterior_trim_multiplier]" value="<?php echo esc_attr( $s['exterior_trim_multiplier'] ); ?>" /></td></tr>
                <tr><th>Gutter Multiplier</th><td><input type="number" step="0.01" name="ec_settings[exterior_gutter_multiplier]" value="<?php echo esc_attr( $s['exterior_gutter_multiplier'] ); ?>" /></td></tr>
                <tr><th colspan="2"><strong>Condition Multipliers (4 tiers)</strong></th></tr>
                <tr><th>Like New</th><td><input type="number" step="0.01" name="ec_settings[exterior_cond_like_new]" value="<?php echo esc_attr( $s['exterior_cond_like_new'] ); ?>" /></td></tr>
                <tr><th>Light Wear</th><td><input type="number" step="0.01" name="ec_settings[exterior_cond_light_wear]" value="<?php echo esc_attr( $s['exterior_cond_light_wear'] ); ?>" /></td></tr>
                <tr><th>Moderate Wear</th><td><input type="number" step="0.01" name="ec_settings[exterior_cond_moderate_wear]" value="<?php echo esc_attr( $s['exterior_cond_moderate_wear'] ); ?>" /></td></tr>
                <tr><th>Heavy Wear</th><td><input type="number" step="0.01" name="ec_settings[exterior_cond_heavy_wear]" value="<?php echo esc_attr( $s['exterior_cond_heavy_wear'] ); ?>" /></td></tr>
                <tr><th>Range Variance (%)</th><td><input type="number" step="0.01" min="0" max="100" name="ec_settings[exterior_range_pct]" value="<?php echo esc_attr( $s['exterior_range_pct'] ); ?>" /> <span class="description">e.g. 25 = ±25% from the total.</span></td></tr>
                <?php ec_render_custom_questions_admin( 'exterior', $s['exterior_custom_questions'] ?? [] ); ?>
            </table>
        </div>

        <!-- Cabinet Pricing -->
        <div id="tab-cabinet" class="ec-tab-content">
            <h2>Cabinet Painting Pricing</h2>
            <table class="form-table">
                <tr><th>Base Price</th><td><input type="number" step="0.01" name="ec_settings[cabinet_base_price]" value="<?php echo esc_attr( $s['cabinet_base_price'] ); ?>" /></td></tr>
                <tr><th>Per Door</th><td><input type="number" step="0.01" name="ec_settings[cabinet_door_price]" value="<?php echo esc_attr( $s['cabinet_door_price'] ); ?>" /></td></tr>
                <tr><th>Per Drawer</th><td><input type="number" step="0.01" name="ec_settings[cabinet_drawer_price]" value="<?php echo esc_attr( $s['cabinet_drawer_price'] ); ?>" /></td></tr>
                <tr><th>Kitchen Island Add-on</th><td><input type="number" step="0.01" name="ec_settings[cabinet_island_price]" value="<?php echo esc_attr( $s['cabinet_island_price'] ); ?>" /></td></tr>
                <tr><th colspan="2"><strong>Condition Multipliers (4 tiers)</strong></th></tr>
                <tr><th>Like New</th><td><input type="number" step="0.01" name="ec_settings[cabinet_cond_like_new]" value="<?php echo esc_attr( $s['cabinet_cond_like_new'] ); ?>" /></td></tr>
                <tr><th>Light Wear</th><td><input type="number" step="0.01" name="ec_settings[cabinet_cond_light_wear]" value="<?php echo esc_attr( $s['cabinet_cond_light_wear'] ); ?>" /></td></tr>
                <tr><th>Moderate Wear</th><td><input type="number" step="0.01" name="ec_settings[cabinet_cond_moderate_wear]" value="<?php echo esc_attr( $s['cabinet_cond_moderate_wear'] ); ?>" /></td></tr>
                <tr><th>Heavy Wear</th><td><input type="number" step="0.01" name="ec_settings[cabinet_cond_heavy_wear]" value="<?php echo esc_attr( $s['cabinet_cond_heavy_wear'] ); ?>" /></td></tr>
                <tr><th>Range Variance (%)</th><td><input type="number" step="0.01" min="0" max="100" name="ec_settings[cabinet_range_pct]" value="<?php echo esc_attr( $s['cabinet_range_pct'] ); ?>" /> <span class="description">e.g. 25 = ±25% from the total. Low = total &minus; %, High = total + %.</span></td></tr>
                <?php ec_render_custom_questions_admin( 'cabinet', $s['cabinet_custom_questions'] ?? [] ); ?>
            </table>
        </div>

        <!-- Branding -->
        <!-- Custom Services tab -->
        <div id="tab-custom-services" class="ec-tab-content">
            <h2>Custom Services</h2>
            <p class="description">
                Add additional services beyond Interior / Exterior / Cabinet. For each one, define the price per square foot and the range variance (%). On the front end, users enter the square footage and the calculator outputs <code>sqft × price/sqft ±range%</code>.<br>
                Each service can be enabled/disabled or removed at any time, and you can upload a custom image for the service card.
            </p>

            <table class="widefat ec-custom-services-table">
                <thead>
                    <tr>
                        <th style="width:70px">Enabled</th>
                        <th style="width:120px">Image</th>
                        <th>Service Label &amp; Slug</th>
                        <th style="width:130px">Price / sq ft ($)</th>
                        <th style="width:120px">Range (%)</th>
                        <th style="width:90px">Remove</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $cs_list = is_array( $s['custom_services'] ?? null ) ? $s['custom_services'] : [];
                if ( ! empty( $cs_list ) ) {
                    foreach ( $cs_list as $i => $cs ) {
                        ec_render_custom_service_row( $i, $cs );
                    }
                }
                ?>
                </tbody>
            </table>

            <p style="margin-top:10px">
                <button type="button" class="button button-secondary" id="ec-add-custom-service">+ Add Custom Service</button>
            </p>

            <script type="text/template" id="ec-custom-service-row-template">
                <?php ec_render_custom_service_row( '__INDEX__', [
                    'enabled' => 1, 'slug' => '', 'label' => '', 'image' => '',
                    'price_per_sqft' => 0, 'range_pct' => 25, 'custom_questions' => [],
                ] ); ?>
            </script>

            <p class="description" style="margin-top:20px;color:#666">
                <strong>Tip:</strong> Slug is auto-generated from the label if left blank. It's used internally for tracking — keep it letters, numbers, and dashes only.
            </p>
        </div>

        <!-- External Sites (REST API for the widget) -->
        <div id="tab-external" class="ec-tab-content">
            <h2>External Site Embeds (Widget REST API)</h2>
            <p class="description">
                Connect the standalone <strong>widget</strong> to this WordPress install from <strong>any external site</strong>, even if it doesn't support PHP and lives on a different server.<br>
                The widget can fetch the calculator config (pricing, services, labels, etc.) from this site and submit estimates that flow through this plugin's GHL integration.
            </p>

            <h3>Enable</h3>
            <table class="form-table">
                <tr>
                    <th>REST API</th>
                    <td>
                        <label><input type="checkbox" name="ec_settings[rest_enabled]" value="1" <?php checked( $s['rest_enabled'], 1 ); ?> /> Enable REST endpoints for external widget embeds</label>
                        <p class="description">When off, all <code>/wp-json/ec/v1/*</code> routes return <code>503 disabled</code>.</p>
                    </td>
                </tr>
            </table>

            <h3>Endpoints</h3>
            <p class="description">External sites use these two URLs:</p>
            <table class="widefat" style="max-width:780px">
                <thead><tr><th>Method</th><th>URL</th><th>What it does</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>GET</code></td>
                        <td><code><?php echo esc_html( rest_url( 'ec/v1/config' ) ); ?></code></td>
                        <td>Returns calc config (pricing, services, labels, colors, custom services, etc.)</td>
                    </tr>
                    <tr>
                        <td><code>POST</code></td>
                        <td><code><?php echo esc_html( rest_url( 'ec/v1/submit' ) ); ?></code></td>
                        <td>Processes a submission — calculates the estimate &amp; pushes to GHL</td>
                    </tr>
                </tbody>
            </table>
            <p class="description">Auth: send the site token via header <code>X-EC-Site-Token: &lt;token&gt;</code> or query/body param <code>site_token=&lt;token&gt;</code>.</p>

            <h3>Widget-Only Calendar Embed</h3>
            <p class="description">
                Optional. Paste an alternate booking calendar embed here that will be sent <strong>only to external sites consuming the widget via REST</strong>.<br>
                When set, this overrides the main <em>GHL Integration &rarr; Custom Iframe / Embed Code</em> (and calendar URL) for widget consumers.<br>
                Useful when you want a different calendar on your WP site (used by <code>[estimate_calculator]</code>) vs. what shows on client landing pages using the widget.
            </p>
            <table class="form-table">
                <tr>
                    <th><label for="widget_calendar_custom_iframe">Widget Custom Iframe / Embed Code</label></th>
                    <td>
                        <textarea id="widget_calendar_custom_iframe" name="ec_settings[widget_calendar_custom_iframe]" rows="6" class="large-text code" placeholder="&lt;iframe src=&quot;https://link.yourdomain.com/widget/booking/WIDGET_CAL_ID&quot; width=&quot;100%&quot; height=&quot;700&quot;&gt;&lt;/iframe&gt;"><?php echo esc_textarea( $s['widget_calendar_custom_iframe'] ); ?></textarea>
                        <p class="description">
                            Accepts <code>&lt;iframe&gt;</code>, <code>&lt;div&gt;</code>, and <code>&lt;script&gt;</code> tags from mainstream booking providers (GHL, Calendly, Acuity, SimplyBook, Square Appointments, etc.).<br>
                            Leave blank to reuse the main calendar embed for widget consumers.
                        </p>
                    </td>
                </tr>
            </table>

            <hr>

            <h3>Site Tokens</h3>
            <p class="description">Each token has its own allow-list of origins (CORS). Disable or remove a token to instantly revoke access for that client.</p>

            <table class="widefat ec-site-tokens-table">
                <thead>
                    <tr>
                        <th style="width:70px">Enabled</th>
                        <th>Label</th>
                        <th>Token</th>
                        <th>Allowed Origins<br><small style="font-weight:normal">(one per line; use <code>*</code> for any)</small></th>
                        <th style="width:90px">Remove</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $tokens_list = is_array( $s['rest_site_tokens'] ?? null ) ? $s['rest_site_tokens'] : [];
                if ( ! empty( $tokens_list ) ) {
                    foreach ( $tokens_list as $i => $t ) {
                        ec_render_site_token_row( $i, $t );
                    }
                } else {
                    // Render one empty row server-side so the form can save
                    // even if the "+ Add Site Token" JavaScript fails to fire.
                    ec_render_site_token_row( 0, [
                        'enabled'    => 1,
                        'label'      => '',
                        'token'      => EC_Settings::generate_rest_token(),
                        'origins'    => [],
                        'created_at' => '',
                    ] );
                }
                ?>
                </tbody>
            </table>

            <p style="margin-top:10px">
                <button type="button" class="button button-secondary" id="ec-add-site-token">+ Add Site Token</button>
            </p>

            <script type="text/template" id="ec-site-token-row-template">
                <?php ec_render_site_token_row( '__INDEX__', [
                    'enabled' => 1, 'label' => '', 'token' => '__TOKEN__',
                    'origins' => [], 'created_at' => '',
                ] ); ?>
            </script>

            <?php
            // Diagnostic — only shown to admins. Helps debug "tokens not saving" issues
            // by exposing exactly what the form submitted vs what was saved.
            $ec_last_submit = get_transient( 'ec_last_submit_tokens' );
            $ec_saved_tokens = is_array( $s['rest_site_tokens'] ?? null ) ? $s['rest_site_tokens'] : [];
            $ec_max_input_vars = (int) ini_get( 'max_input_vars' );
            ?>
            <details style="margin-top:30px;padding:12px;border:1px solid #e2e4e7;border-radius:4px;background:#fafafa">
                <summary style="cursor:pointer;font-weight:600;color:#0073aa">🔍 Diagnostic: what's being submitted &amp; saved?</summary>
                <div style="margin-top:12px;font-size:12px">
                    <p><strong>Server <code>max_input_vars</code>:</strong> <?php echo $ec_max_input_vars; ?>
                        <?php if ( $ec_max_input_vars > 0 && $ec_max_input_vars < 3000 ) : ?>
                            <span style="color:#b32d2e">⚠ Low — if your form has many custom services/conditions, fields near the bottom (including these tokens) may be truncated. Ask your host to raise this to 5000+.</span>
                        <?php endif; ?>
                    </p>
                    <p><strong>Last submission received <code>rest_site_tokens</code>:</strong></p>
                    <pre style="background:#fff;padding:8px;border:1px solid #ddd;max-height:200px;overflow:auto;font-size:11px"><?php
                        if ( $ec_last_submit ) {
                            echo esc_html( '@ ' . ( $ec_last_submit['time'] ?? 'unknown' ) . "\n" );
                            echo esc_html( print_r( $ec_last_submit['received_rest_site_tokens'], true ) );
                        } else {
                            echo '(nothing — submit the form to capture)';
                        }
                    ?></pre>
                    <p><strong>Currently saved in <code>ec_settings.rest_site_tokens</code>:</strong> <?php echo count( $ec_saved_tokens ); ?> token(s)</p>
                    <pre style="background:#fff;padding:8px;border:1px solid #ddd;max-height:200px;overflow:auto;font-size:11px"><?php echo esc_html( print_r( $ec_saved_tokens, true ) ); ?></pre>
                </div>
            </details>
        </div>

        <!-- Lead Tracking tab -->
        <div id="tab-leadtrack" class="ec-tab-content">
            <h2>Lead Source Tracking</h2>
            <p class="description">
                Detects where each lead came from (Meta paid, Google Ads, organic search, email, direct, etc.) and tags the GHL contact accordingly.<br>
                Captures click IDs (<code>fbclid</code>, <code>gclid</code>, <code>msclkid</code>, <code>ttclid</code>, <code>li_fat_id</code>), UTM parameters, referrer, and landing page URL.
            </p>

            <table class="form-table">
                <tr>
                    <th>Enable Lead Tracking</th>
                    <td>
                        <label><input type="checkbox" name="ec_settings[lead_tracking_enabled]" value="1" <?php checked( $s['lead_tracking_enabled'], 1 ); ?> /> Auto-detect lead source and add tags + custom fields to GHL contact</label>
                    </td>
                </tr>
            </table>

            <h3>Tags Added to GHL Contact</h3>
            <p class="description">For every submission these tag patterns are automatically added (in addition to your existing <code>service-*</code> tags):</p>
            <table class="widefat" style="max-width:760px">
                <thead><tr><th style="width:200px">Tag</th><th>Examples</th></tr></thead>
                <tbody>
                    <tr><td><code>lead-source-*</code></td><td><code>lead-source-meta-paid</code>, <code>lead-source-google-ads</code>, <code>lead-source-google-organic</code>, <code>lead-source-direct</code>, …</td></tr>
                    <tr><td><code>lead-channel-*</code></td><td><code>lead-channel-paid-social</code>, <code>lead-channel-paid-search</code>, <code>lead-channel-organic-search</code>, <code>lead-channel-direct</code>, …</td></tr>
                    <tr><td><code>utm-campaign-*</code></td><td><code>utm-campaign-spring-sale-2026</code></td></tr>
                    <tr><td><code>utm-medium-*</code></td><td><code>utm-medium-cpc</code>, <code>utm-medium-email</code></td></tr>
                    <tr><td><code>utm-source-*</code></td><td>For non-standard sources only</td></tr>
                </tbody>
            </table>

            <h3>Custom Fields Added to GHL Contact</h3>
            <p class="description">Create these custom fields in your GHL sub-account (Settings &rarr; Custom Fields) to capture the data:</p>
            <ul style="font-size:13px;line-height:1.7;list-style:disc;padding-left:20px;color:#23282d">
                <li><code>lead_source</code>, <code>lead_channel</code>, <code>lead_landing_page</code>, <code>lead_referrer</code></li>
                <li>UTMs: <code>utm_source</code>, <code>utm_medium</code>, <code>utm_campaign</code>, <code>utm_term</code>, <code>utm_content</code></li>
                <li>Meta: <code>fbclid</code>, <code>fbp</code>, <code>fbc</code></li>
                <li>Google: <code>gclid</code>, <code>gbraid</code>, <code>wbraid</code></li>
                <li>Other: <code>msclkid</code> (Bing), <code>ttclid</code> (TikTok), <code>li_fat_id</code> (LinkedIn)</li>
            </ul>

            <hr>

            <h2>Meta Conversions API (server-side Lead events)</h2>
            <p class="description">
                Sends a server-side <code>Lead</code> event to Meta alongside the browser pixel — improves attribution especially for iOS users, ad blockers, and post-iOS-14.5 environments.<br>
                Pairs with the existing browser pixel (set in <strong>Tracking &amp; Pixel</strong> tab) — Meta deduplicates using a shared event ID, so no double-counting.
            </p>

            <table class="form-table">
                <tr>
                    <th>Enable Meta CAPI</th>
                    <td>
                        <label><input type="checkbox" name="ec_settings[meta_capi_enabled]" value="1" <?php checked( $s['meta_capi_enabled'], 1 ); ?> /> Send server-side Lead events to Meta on submission</label>
                    </td>
                </tr>
                <tr>
                    <th><label for="meta_capi_pixel_id">Pixel ID</label></th>
                    <td>
                        <input type="text" id="meta_capi_pixel_id" name="ec_settings[meta_capi_pixel_id]" value="<?php echo esc_attr( $s['meta_capi_pixel_id'] ); ?>" class="regular-text" placeholder="123456789012345" />
                        <p class="description">Same Pixel ID as your browser-side pixel — Meta deduplicates by event ID.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="meta_capi_access_token">Access Token</label></th>
                    <td>
                        <input type="password" id="meta_capi_access_token" name="ec_settings[meta_capi_access_token]" value="<?php echo esc_attr( $s['meta_capi_access_token'] ); ?>" class="regular-text" autocomplete="off" />
                        <p class="description">Generate in Meta Events Manager &rarr; your Pixel &rarr; Settings &rarr; Conversions API &rarr; <strong>Generate access token</strong>.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="meta_capi_test_code">Test Event Code (optional)</label></th>
                    <td>
                        <input type="text" id="meta_capi_test_code" name="ec_settings[meta_capi_test_code]" value="<?php echo esc_attr( $s['meta_capi_test_code'] ); ?>" placeholder="TEST12345" />
                        <p class="description">When set, events are routed to <strong>Test Events</strong> in Meta Events Manager. Remove this in production.</p>
                    </td>
                </tr>
            </table>

            <h3>Google Ads tracking</h3>
            <p class="description">
                Google Ads Server-Side conversion API requires a Developer Token + complex OAuth — most agencies don't need it.<br>
                The plugin's existing <strong>Tracking &amp; Pixel</strong> tab already fires a browser-side <code>gtag</code> conversion event, which is enough for the vast majority of accounts.<br>
                The <code>gclid</code> / <code>gbraid</code> / <code>wbraid</code> are still captured and saved to GHL custom fields above, so they're available for offline conversion uploads to Google Ads via GHL workflows.
            </p>
        </div>

        <div id="tab-branding" class="ec-tab-content">
            <h2>Branding &amp; Labels</h2>
            <table class="form-table">
                <tr><th>Interior Label</th><td><input type="text" name="ec_settings[label_interior]" value="<?php echo esc_attr( $s['label_interior'] ); ?>" class="regular-text" /></td></tr>
                <tr><th>Exterior Label</th><td><input type="text" name="ec_settings[label_exterior]" value="<?php echo esc_attr( $s['label_exterior'] ); ?>" class="regular-text" /></td></tr>
                <tr><th>Cabinet Label</th><td><input type="text" name="ec_settings[label_cabinet]" value="<?php echo esc_attr( $s['label_cabinet'] ); ?>" class="regular-text" /></td></tr>
                <?php
                $avada_active = EC_Avada::is_active();
                $avada_note = $avada_active
                    ? 'Avada detected — pick from your theme\'s color palette or set a custom color. Custom colors are saved as hex; Avada colors stay in sync if you later change them in theme options.'
                    : 'Standard color pickers. Install Avada to enable the theme color palette.';
                ?>
                <tr>
                    <th colspan="2">
                        <p class="description" style="margin-top:0; font-style:italic;">
                            <?php echo esc_html( $avada_note ); ?>
                        </p>
                    </th>
                </tr>
                <tr><th>Primary Color</th><td><?php EC_Avada::render_picker( 'primary_color',   $s['primary_color'],   '#3a8ea8' ); ?></td></tr>
                <tr><th>Secondary Color</th><td><?php EC_Avada::render_picker( 'secondary_color', $s['secondary_color'], '#2c3e50' ); ?></td></tr>
                <tr><th>Button Text Color</th><td><?php EC_Avada::render_picker( 'button_text',    $s['button_text'],     '#ffffff' ); ?></td></tr>

                <?php if ( $avada_active && empty( EC_Avada::get_palette() ) ) : ?>
                <tr>
                    <th>Avada Debug</th>
                    <td>
                        <details>
                            <summary style="cursor:pointer;color:#0073aa">Click to expand &mdash; what we found in your Avada options</summary>
                            <pre style="background:#f5f5f5;padding:12px;border:1px solid #ccd0d4;border-radius:4px;font-size:12px;line-height:1.5;white-space:pre-wrap;max-height:400px;overflow:auto;margin-top:8px"><?php echo esc_html( EC_Avada::debug_dump() ); ?></pre>
                        </details>
                        <p class="description">Send this to support if your Avada palette isn't being detected.</p>
                    </td>
                </tr>
                <?php endif; ?>
            </table>

            <h2>Consent &amp; Legal</h2>
            <table class="form-table">
                <tr>
                    <th>SMS Consent Text</th>
                    <td>
                        <textarea name="ec_settings[sms_consent_text]" rows="4" class="large-text"><?php echo esc_textarea( $s['sms_consent_text'] ); ?></textarea>
                        <p class="description">Use <code>{phone}</code> as a placeholder — it will be replaced by the Help Phone value below. If Help Phone is empty, the "Text HELP to..." sentence is removed automatically.</p>
                    </td>
                </tr>
                <tr><th>Help Phone</th><td><input type="text" name="ec_settings[sms_help_phone]" value="<?php echo esc_attr( $s['sms_help_phone'] ); ?>" placeholder="555-123-4567" /></td></tr>
                <tr><th>Privacy Policy URL</th><td><input type="url" name="ec_settings[privacy_url]" value="<?php echo esc_attr( $s['privacy_url'] ); ?>" class="regular-text" /></td></tr>
            </table>

            <h2>Disclaimers</h2>
            <table class="form-table">
                <tr>
                    <th>Form Page Disclaimer</th>
                    <td>
                        <textarea name="ec_settings[form_disclaimer]" rows="3" class="large-text"><?php echo esc_textarea( $s['form_disclaimer'] ); ?></textarea>
                        <p class="description">Shown on the contact step before the user submits.</p>
                    </td>
                </tr>
                <tr>
                    <th>Results Page Disclaimer</th>
                    <td>
                        <textarea name="ec_settings[results_disclaimer]" rows="3" class="large-text"><?php echo esc_textarea( $s['results_disclaimer'] ); ?></textarea>
                        <p class="description">Shown beneath the estimate range. The leading asterisk "*" is added automatically.</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Business Info / Schema.org -->
        <div id="tab-business" class="ec-tab-content">
            <h2>Business Info (Google Online Estimate Schema)</h2>
            <p class="description">
                This information is used to output <strong>Schema.org structured data</strong> (JSON-LD) on the calculator and results pages.<br>
                Compliant with <a href="https://developers.google.com/search/docs/appearance/structured-data" target="_blank">Google's structured data spec</a> for <code>Service</code>, <code>Offer</code>, <code>PriceSpecification</code>, and <code>QuoteAction</code> (Online Estimate).<br>
                This helps your estimate tool surface in Google Search with rich results.
            </p>

            <table class="form-table">
                <tr>
                    <th>Enable Schema Output</th>
                    <td><label><input type="checkbox" name="ec_settings[enable_schema]" value="1" <?php checked( $s['enable_schema'], 1 ); ?> /> Output JSON-LD structured data on calculator &amp; results pages</label></td>
                </tr>
                <tr>
                    <th><label>Business Type</label></th>
                    <td>
                        <select name="ec_settings[business_type]">
                            <?php
                            $types = [
                                'HomeAndConstructionBusiness' => 'Home &amp; Construction Business',
                                'LocalBusiness'               => 'Local Business',
                                'Plumber'                     => 'Plumber',
                                'RoofingContractor'           => 'Roofing Contractor',
                                'HVACBusiness'                => 'HVAC Business',
                                'Electrician'                 => 'Electrician',
                                'GeneralContractor'           => 'General Contractor',
                                'HousePainter'                => 'House Painter',
                                'Locksmith'                   => 'Locksmith',
                                'MovingCompany'               => 'Moving Company',
                                'ProfessionalService'         => 'Professional Service',
                            ];
                            foreach ( $types as $value => $label ) {
                                echo '<option value="' . esc_attr( $value ) . '" ' . selected( $s['business_type'], $value, false ) . '>' . $label . '</option>';
                            }
                            ?>
                        </select>
                        <p class="description">Schema.org business type. Pick the closest match for your client.</p>
                    </td>
                </tr>
                <tr><th><label>Business Name</label></th><td><input type="text" name="ec_settings[business_name]" value="<?php echo esc_attr( $s['business_name'] ); ?>" class="regular-text" placeholder="e.g. All American Trade Work" /></td></tr>
                <tr><th><label>Business Phone</label></th><td><input type="text" name="ec_settings[business_phone]" value="<?php echo esc_attr( $s['business_phone'] ); ?>" class="regular-text" placeholder="e.g. +1 555-123-4567" /></td></tr>
                <tr><th><label>Business Email</label></th><td><input type="email" name="ec_settings[business_email]" value="<?php echo esc_attr( $s['business_email'] ); ?>" class="regular-text" /></td></tr>
                <tr><th><label>Business Website URL</label></th><td><input type="url" name="ec_settings[business_url]" value="<?php echo esc_attr( $s['business_url'] ); ?>" class="regular-text" placeholder="https://yourdomain.com" /></td></tr>
                <tr><th><label>Business Logo URL</label></th><td><input type="url" name="ec_settings[business_logo]" value="<?php echo esc_attr( $s['business_logo'] ); ?>" class="regular-text" /></td></tr>

                <tr><th colspan="2"><strong>Address (PostalAddress)</strong></th></tr>
                <tr><th>Street</th><td><input type="text" name="ec_settings[business_street]" value="<?php echo esc_attr( $s['business_street'] ); ?>" class="regular-text" /></td></tr>
                <tr><th>City</th><td><input type="text" name="ec_settings[business_city]" value="<?php echo esc_attr( $s['business_city'] ); ?>" class="regular-text" /></td></tr>
                <tr><th>State / Region</th><td><input type="text" name="ec_settings[business_region]" value="<?php echo esc_attr( $s['business_region'] ); ?>" placeholder="e.g. OR" /></td></tr>
                <tr><th>Postal Code</th><td><input type="text" name="ec_settings[business_postal]" value="<?php echo esc_attr( $s['business_postal'] ); ?>" /></td></tr>
                <tr><th>Country Code</th><td><input type="text" name="ec_settings[business_country]" value="<?php echo esc_attr( $s['business_country'] ); ?>" placeholder="US" maxlength="2" style="width:80px" /><p class="description">ISO 3166-1 alpha-2 (e.g., US, CA, GB).</p></td></tr>

                <tr><th colspan="2"><strong>Service Area &amp; Currency</strong></th></tr>
                <tr><th>Area Served</th><td><input type="text" name="ec_settings[business_area_served]" value="<?php echo esc_attr( $s['business_area_served'] ); ?>" class="regular-text" placeholder="e.g. Portland, OR metro area" /></td></tr>
                <tr><th>Price Currency</th><td><input type="text" name="ec_settings[price_currency]" value="<?php echo esc_attr( $s['price_currency'] ); ?>" placeholder="USD" maxlength="3" style="width:80px" /><p class="description">ISO 4217 (USD, CAD, EUR, GBP, etc.).</p></td></tr>
            </table>

            <h3>What gets output?</h3>
            <ul style="list-style:disc;padding-left:20px">
                <li><strong>On the calculator page:</strong> <code>Service</code> schema with <code>QuoteAction</code> (Google Online Estimate action), <code>OfferCatalog</code> listing enabled services, and a <code>LocalBusiness</code> provider node.</li>
                <li><strong>On the results step / thank-you page:</strong> <code>Offer</code> schema with <code>PriceSpecification</code> containing the <code>minPrice</code> and <code>maxPrice</code> range.</li>
            </ul>
            <p><a href="https://search.google.com/test/rich-results" target="_blank">Test your schema with Google Rich Results Test &rarr;</a></p>
        </div>

        <!-- Shortcodes reference -->
        <div id="tab-shortcodes" class="ec-tab-content">
            <h2>Shortcodes</h2>
            <table class="widefat">
                <thead><tr><th>Shortcode</th><th>Description</th><th>Usage</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>[estimate_calculator]</code></td>
                        <td>Full estimate calculator with service selection, form steps, and submission</td>
                        <td>Place on your estimate/quote page</td>
                    </tr>
                    <tr>
                        <td><code>[estimate_thankyou]</code></td>
                        <td>Displays the estimate range and booking calendar. Reads <code>?low=&amp;high=&amp;service=</code> from the URL.</td>
                        <td>Place on your thank-you page. Select it in the "Thank-You Page" setting above.</td>
                    </tr>
                    <tr>
                        <td><code>[estimate_calendar]</code></td>
                        <td>Embeds <strong>just</strong> the GHL booking calendar — no calculator, no form. Uses the Calendar Embed URL from settings.</td>
                        <td>Place on any page where you want a standalone booking widget (Contact page, header CTA page, etc.).</td>
                    </tr>
                </tbody>
            </table>

            <h3>[estimate_calendar] Attributes</h3>
            <p class="description">All optional. Examples:</p>
            <pre style="background:#f5f5f5;padding:12px;border-radius:4px;font-size:0.85rem;line-height:1.6;overflow-x:auto;">[estimate_calendar]

[estimate_calendar height="900"]

[estimate_calendar heading="Schedule Your Free Estimate" subheading="Pick a time that works for you."]

[estimate_calendar url="https://link.client.com/widget/booking/XYZ" width="100%" height="800"]

[estimate_calendar show_header="false"]</pre>

            <table class="widefat" style="margin-top:8px">
                <thead><tr><th>Attribute</th><th>Default</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>url</code></td><td><em>(uses plugin setting)</em></td><td>Override the GHL calendar URL for this specific embed.</td></tr>
                    <tr><td><code>height</code></td><td><code>700</code></td><td>Iframe minimum height in pixels.</td></tr>
                    <tr><td><code>width</code></td><td><code>100%</code></td><td>Iframe width. Accepts %, px, em, rem.</td></tr>
                    <tr><td><code>heading</code></td><td><code>Book Your Estimate</code></td><td>Heading shown above the calendar.</td></tr>
                    <tr><td><code>subheading</code></td><td><em>(empty)</em></td><td>Optional subheading text.</td></tr>
                    <tr><td><code>show_header</code></td><td><code>true</code></td><td>Set to <code>false</code> to hide the heading area entirely.</td></tr>
                </tbody>
            </table>

            <h2>Setup Checklist</h2>
            <ol>
                <li>Go to GHL sub-account &rarr; Settings &rarr; Business Profile &rarr; generate a <strong>sub-account API key</strong> (NOT agency admin)</li>
                <li>Enter API key and Location ID above</li>
                <li>Create or identify a booking calendar in GHL &rarr; Calendars. Copy the Calendar ID and embed URL.</li>
                <li>Create a page with <code>[estimate_calculator]</code> shortcode</li>
                <li>Create a page with <code>[estimate_thankyou]</code> shortcode and select it above</li>
                <li>Configure pricing for each service type</li>
                <li>Add pixel/tracking IDs if needed</li>
                <li>Test the full flow: calculator &rarr; submission &rarr; GHL contact created &rarr; pixel fires &rarr; thank-you page &rarr; book estimate</li>
            </ol>
        </div>

        <?php submit_button( 'Save Settings' ); ?>
    </form>
</div>
