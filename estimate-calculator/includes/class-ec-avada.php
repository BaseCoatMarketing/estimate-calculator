<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Avada theme integration.
 *
 * Detects if Avada (or Avada Website Builder) is active and exposes the
 * theme's global color palette so plugin settings can hook into it.
 *
 * Color storage format:
 *   - Hex values  → "#3a8ea8"  (custom color)
 *   - Avada refs  → "avada:1"  (resolves to fusion_options[color_1] at render time)
 *
 * If Avada is later disabled, "avada:N" values fall back to a sensible default.
 */
class EC_Avada {

    /**
     * Detect whether Avada exposes the modern --awb-colorN CSS variables.
     * True for Avada 7.0+ which uses CSS custom properties in fusion_options
     * (we saw values like "var(--awb-color1)" in your installation).
     */
    public static function has_awb_css_vars() {
        if ( ! self::is_active() ) return false;

        // Quick check: scan a handful of known fusion_options for "var(--awb-color"
        $opts = get_option( 'fusion_options', [] );
        if ( ! is_array( $opts ) ) return false;
        $sample_keys = [
            'flyout_menu_background_color', 'footer_text_color', 'footer_link_color',
            'slidingbar_text_color', 'copyright_text_color',
        ];
        foreach ( $sample_keys as $k ) {
            $v = $opts[ $k ] ?? '';
            if ( is_string( $v ) && stripos( $v, 'var(--awb-color' ) !== false ) {
                return true;
            }
        }
        // Final check: any value mentions the AWB color variable
        foreach ( $opts as $v ) {
            if ( is_string( $v ) && stripos( $v, 'var(--awb-color' ) !== false ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect Avada theme / Fusion Builder.
     */
    public static function is_active() {
        return class_exists( 'Avada' )
            || class_exists( 'Fusion_Builder_Plugin' )
            || function_exists( 'fusion_get_option' )
            || function_exists( 'Avada' )
            || ( function_exists( 'wp_get_theme' ) && stripos( (string) wp_get_theme()->get( 'Name' ), 'Avada' ) !== false );
    }

    /**
     * Return the Avada color palette as [ label => css_value ].
     * Looks across many storage formats Avada has used over the years.
     *
     * Note: For modern Avada (7.0+) we ALWAYS expose --awb-color1 through
     * --awb-color8 as CSS-variable references. The browser resolves them at
     * render time using whatever Avada has set live, so we never need to find
     * the actual hex values.
     */
    public static function get_palette() {
        if ( ! self::is_active() ) return [];

        $palette = [];

        // ---- 0. Modern Avada (7.0+) — always expose CSS variables ----
        // The values are output as CSS custom properties on <body> by Avada.
        // We reference them directly so Avada controls the actual color.
        if ( self::has_awb_css_vars() ) {
            for ( $i = 1; $i <= 8; $i++ ) {
                $palette[ 'Avada Color ' . $i ] = 'var(--awb-color' . $i . ')';
            }
        }

        // ---- 1. Avada Studio / AWB Global Color Palette (try multiple key names) ----
        $studio_keys = [ 'awb_color_palette', 'awb-color-palette', 'awb_global_colors', 'awb-global-colors' ];
        foreach ( $studio_keys as $sk ) {
            $studio = get_option( $sk, null );
            if ( is_string( $studio ) ) {
                $maybe_decoded = json_decode( $studio, true );
                if ( is_array( $maybe_decoded ) ) $studio = $maybe_decoded;
            }
            if ( is_array( $studio ) ) {
                foreach ( $studio as $entry ) {
                    if ( is_array( $entry ) && ! empty( $entry['color'] ) && self::is_valid_color( $entry['color'] ) ) {
                        $label = ! empty( $entry['name'] ) ? $entry['name'] : ( ! empty( $entry['slug'] ) ? $entry['slug'] : 'Studio Color' );
                        if ( ! isset( $palette[ $label ] ) ) {
                            $palette[ $label ] = $entry['color'];
                        }
                    }
                }
            }
        }

        // ---- 2. Fusion Color Palette class (newer Avada) ----
        if ( empty( $palette ) && class_exists( 'Fusion_Color_Palette' ) ) {
            try {
                $reflection = new ReflectionClass( 'Fusion_Color_Palette' );
                if ( $reflection->hasMethod( 'get_palette' ) ) {
                    $method = $reflection->getMethod( 'get_palette' );
                    if ( $method->isStatic() ) {
                        $colors = $method->invoke( null );
                        if ( is_array( $colors ) ) {
                            foreach ( $colors as $key => $val ) {
                                if ( is_string( $val ) && self::is_valid_color( $val ) ) {
                                    $palette[ $key ] = $val;
                                }
                            }
                        }
                    }
                }
            } catch ( Exception $e ) { /* ignore */ }
        }

        // ---- 3. fusion_options direct read — try ALL known patterns ----
        if ( empty( $palette ) ) {
            $opts = get_option( 'fusion_options', [] );
            if ( is_array( $opts ) ) {

                // Numeric slot patterns (cover every Avada version naming)
                $patterns = [
                    'color_%d',
                    'palette_color_%d',
                    'awb_color%d',
                    'awb_color_%d',
                    'global_color_%d',
                    'color_picker_%d',
                    'palette_%d',
                    'fusion_color_%d',
                    'theme_color_%d',
                    'custom_color_%d',
                    'preset_color_%d',
                ];
                foreach ( $patterns as $pattern ) {
                    for ( $i = 1; $i <= 12; $i++ ) {
                        $key = sprintf( $pattern, $i );
                        $val = $opts[ $key ] ?? '';
                        if ( is_array( $val ) && isset( $val['color'] ) ) $val = $val['color'];
                        if ( $val && self::is_valid_color( $val ) ) {
                            $label = 'Color ' . $i;
                            // Avoid overwriting already-found palette entries with the same label
                            $unique = $label;
                            $n = 2;
                            while ( isset( $palette[ $unique ] ) && $palette[ $unique ] !== $val ) {
                                $unique = $label . ' (' . $n++ . ')';
                            }
                            if ( ! isset( $palette[ $unique ] ) ) {
                                $palette[ $unique ] = $val;
                            }
                        }
                    }
                }

                // Named primary colors that are in nearly every Avada install
                $named_keys = [
                    'Primary'        => 'primary_color',
                    'Link'           => 'link_color',
                    'Body Text'      => 'body_typography',
                    'Heading'        => 'h1_typography',
                    'Header BG'      => 'header_bg_color',
                    'Footer BG'      => 'footer_bg_color',
                    'Sidebar BG'     => 'sidebar_bg_color',
                    'Button BG'      => 'button_accent_color',
                    'Button Hover'   => 'button_accent_hover_color',
                ];
                foreach ( $named_keys as $label => $key ) {
                    $val = $opts[ $key ] ?? '';
                    if ( is_array( $val ) && isset( $val['color'] ) ) {
                        $val = $val['color'];
                    }
                    if ( $val && self::is_valid_color( $val ) ) {
                        if ( ! isset( $palette[ $label ] ) ) {
                            $palette[ $label ] = $val;
                        }
                    }
                }
            }
        }

        // ---- 4. fusion_get_option fallback (uses Avada's resolution chain) ----
        if ( empty( $palette ) && function_exists( 'fusion_get_option' ) ) {
            $tries = [
                'Color 1' => 'color_1', 'Color 2' => 'color_2', 'Color 3' => 'color_3',
                'Color 4' => 'color_4', 'Color 5' => 'color_5', 'Color 6' => 'color_6',
                'Color 7' => 'color_7', 'Color 8' => 'color_8',
                'Primary' => 'primary_color', 'Link' => 'link_color',
            ];
            foreach ( $tries as $label => $key ) {
                $val = fusion_get_option( $key );
                if ( is_array( $val ) && isset( $val['color'] ) ) $val = $val['color'];
                if ( $val && self::is_valid_color( $val ) ) {
                    $palette[ $label ] = $val;
                }
            }
        }

        // ---- 5. Last resort — scan fusion_options for any color-like values ----
        // (Keys may not contain "color" — Avada uses palette_X, fusion_X, awb_X, etc.)
        // (Values may be hex, rgb, rgba, hsl — all acceptable.)
        if ( empty( $palette ) ) {
            $opts = get_option( 'fusion_options', [] );
            if ( is_array( $opts ) ) {
                $found = 0;
                $key_hints = [ 'color', 'palette', 'awb_', 'fusion_color', 'theme_skin', 'accent', 'preset' ];

                foreach ( $opts as $key => $val ) {
                    if ( $found >= 16 ) break;

                    // Handle array-shaped values (typography options, etc.)
                    if ( is_array( $val ) && isset( $val['color'] ) ) {
                        $val = $val['color'];
                    }
                    if ( ! is_string( $val ) || ! self::is_valid_color( $val ) ) continue;

                    // Check if the key looks color-related
                    $key_lower = strtolower( $key );
                    $matches_hint = false;
                    foreach ( $key_hints as $hint ) {
                        if ( strpos( $key_lower, $hint ) !== false ) { $matches_hint = true; break; }
                    }
                    if ( ! $matches_hint ) continue;

                    $label = self::pretty_key( $key );
                    if ( ! isset( $palette[ $label ] ) ) {
                        $palette[ $label ] = $val;
                        $found++;
                    }
                }
            }
        }

        // ---- 6. Theme mods (Avada Customizer integration) ----
        if ( empty( $palette ) ) {
            // Try common WP customizer storage for the active theme
            $theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
            if ( $theme ) {
                $theme_slug = $theme->get_stylesheet();
                $mods = get_option( 'theme_mods_' . $theme_slug, [] );
                if ( is_array( $mods ) ) {
                    $found = 0;
                    foreach ( $mods as $key => $val ) {
                        if ( $found >= 8 ) break;
                        if ( is_array( $val ) && isset( $val['color'] ) ) $val = $val['color'];
                        if ( ! is_string( $val ) || ! self::is_valid_color( $val ) ) continue;
                        if ( strpos( strtolower( $key ), 'color' ) === false &&
                             strpos( strtolower( $key ), 'palette' ) === false ) continue;
                        $label = self::pretty_key( $key );
                        if ( ! isset( $palette[ $label ] ) ) {
                            $palette[ $label ] = $val;
                            $found++;
                        }
                    }
                }
            }
        }

        // Filter out fully-transparent rgba(...,0) entries — they're useless as a brand color
        $palette = array_filter( $palette, function( $v ) {
            return ! preg_match( '/rgba?\([^)]*,\s*0\s*\)$/i', trim( (string) $v ) );
        } );

        return $palette;
    }

    /**
     * Convert an option key like "primary_color" to "Primary Color".
     */
    private static function pretty_key( $key ) {
        $key = str_replace( [ '_', '-' ], ' ', $key );
        return ucwords( trim( $key ) );
    }

    /**
     * Resolve a stored color value to a final CSS color string.
     *
     * @param string $value    Stored value ("#xxx" or "avada:KEY").
     * @param string $fallback Hex to use if $value can't be resolved.
     * @return string A valid CSS color value.
     */
    public static function resolve( $value, $fallback = '#3a8ea8' ) {
        $value = trim( (string) $value );

        if ( strpos( $value, 'avada:' ) === 0 ) {
            $key = substr( $value, 6 );
            $palette = self::get_palette();

            // Direct match by key
            if ( isset( $palette[ $key ] ) ) {
                return $palette[ $key ];
            }
            // Numeric fallback — match by index for backwards compat with "avada:1" stored values
            if ( ctype_digit( $key ) ) {
                $palette_values = array_values( $palette );
                $idx = (int) $key - 1;
                if ( isset( $palette_values[ $idx ] ) ) {
                    return $palette_values[ $idx ];
                }
            }
            return $fallback;
        }

        if ( self::is_valid_color( $value ) ) {
            return $value;
        }

        return $fallback;
    }

    /**
     * Validate a color value (hex, rgb, rgba, hsl, named CSS color, or CSS var).
     */
    public static function is_valid_color( $v ) {
        $v = trim( (string) $v );
        if ( $v === '' ) return false;
        if ( preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v ) ) return true;
        if ( preg_match( '/^rgba?\(/i', $v ) ) return true;
        if ( preg_match( '/^hsla?\(/i', $v ) ) return true;
        if ( stripos( $v, 'var(' ) === 0 ) return true;
        // Strict named CSS color list — replaces the loose alphabetic match
        // that was accepting layout values like "top", "none", "left", "wide".
        return self::is_named_css_color( $v );
    }

    private static function is_named_css_color( $v ) {
        static $names = null;
        if ( $names === null ) {
            $names = [
                'aliceblue','antiquewhite','aqua','aquamarine','azure','beige','bisque',
                'black','blanchedalmond','blue','blueviolet','brown','burlywood','cadetblue',
                'chartreuse','chocolate','coral','cornflowerblue','cornsilk','crimson','cyan',
                'darkblue','darkcyan','darkgoldenrod','darkgray','darkgreen','darkgrey',
                'darkkhaki','darkmagenta','darkolivegreen','darkorange','darkorchid','darkred',
                'darksalmon','darkseagreen','darkslateblue','darkslategray','darkslategrey',
                'darkturquoise','darkviolet','deeppink','deepskyblue','dimgray','dimgrey',
                'dodgerblue','firebrick','floralwhite','forestgreen','fuchsia','gainsboro',
                'ghostwhite','gold','goldenrod','gray','green','greenyellow','grey','honeydew',
                'hotpink','indianred','indigo','ivory','khaki','lavender','lavenderblush',
                'lawngreen','lemonchiffon','lightblue','lightcoral','lightcyan',
                'lightgoldenrodyellow','lightgray','lightgreen','lightgrey','lightpink',
                'lightsalmon','lightseagreen','lightskyblue','lightslategray','lightslategrey',
                'lightsteelblue','lightyellow','lime','limegreen','linen','magenta','maroon',
                'mediumaquamarine','mediumblue','mediumorchid','mediumpurple','mediumseagreen',
                'mediumslateblue','mediumspringgreen','mediumturquoise','mediumvioletred',
                'midnightblue','mintcream','mistyrose','moccasin','navajowhite','navy',
                'oldlace','olive','olivedrab','orange','orangered','orchid','palegoldenrod',
                'palegreen','paleturquoise','palevioletred','papayawhip','peachpuff','peru',
                'pink','plum','powderblue','purple','rebeccapurple','red','rosybrown',
                'royalblue','saddlebrown','salmon','sandybrown','seagreen','seashell','sienna',
                'silver','skyblue','slateblue','slategray','slategrey','snow','springgreen',
                'steelblue','tan','teal','thistle','tomato','transparent','turquoise','violet',
                'wheat','white','whitesmoke','yellow','yellowgreen','currentcolor','inherit',
            ];
        }
        return in_array( strtolower( $v ), $names, true );
    }

    /**
     * Render the picker UI for a single color field.
     * - If Avada is active: dropdown of palette colors + custom picker (synced via JS)
     * - If not:             plain HTML5 color input
     */
    public static function render_picker( $name, $value, $default_hex = '#3a8ea8' ) {
        $is_avada = self::is_active();
        $palette  = $is_avada ? self::get_palette() : [];

        // Determine current selection mode
        $is_avada_ref = strpos( (string) $value, 'avada:' ) === 0;
        $current_key  = $is_avada_ref ? substr( $value, 6 ) : '';
        $current_hex  = self::resolve( $value, $default_hex );

        echo '<div class="ec-color-picker" data-name="' . esc_attr( $name ) . '">';

        if ( $is_avada && ! empty( $palette ) ) {
            echo '<select class="ec-avada-select" style="margin-right:8px;vertical-align:middle">';
            echo '<option value="custom"' . selected( ! $is_avada_ref, true, false ) . '>Custom color</option>';
            foreach ( $palette as $key => $css_value ) {
                $option_value = 'avada:' . $key;
                $is_var_ref   = stripos( $css_value, 'var(' ) === 0;
                // For CSS-var palette entries, label them clearly. The hex preview
                // will be resolved live by JS via getComputedStyle.
                $label_text   = $is_var_ref ? $key : $key . ' (' . $css_value . ')';
                echo '<option value="' . esc_attr( $option_value ) . '"'
                    . selected( $current_key, (string) $key, false )
                    . ' data-css="' . esc_attr( $css_value ) . '"'
                    . ( $is_var_ref ? ' data-css-var="1"' : '' )
                    . '>'
                    . esc_html( $label_text )
                    . '</option>';
            }
            echo '</select>';
        }

        // Hidden input that holds the actual stored value (hex / "avada:KEY")
        echo '<input type="hidden" class="ec-color-stored" name="ec_settings[' . esc_attr( $name ) . ']" value="' . esc_attr( $value ?: $default_hex ) . '" />';

        // Visible color picker — shows resolved hex (or default for var() refs; JS updates it after load)
        echo '<input type="color" class="ec-color-visual" value="' . esc_attr( $current_hex ) . '" style="vertical-align:middle" />';

        if ( ! $is_avada ) {
            echo ' <span class="description" style="color:#888;font-size:12px">Install Avada to use the theme palette.</span>';
        } elseif ( empty( $palette ) ) {
            echo ' <span class="description" style="color:#c0392b;font-size:12px">Avada detected, but no palette colors were found.</span>';
        }

        echo '</div>';
    }

    /**
     * Admin-only debug helper: returns a description of what was found in fusion_options
     * so we can troubleshoot why the palette wasn't loaded.
     */
    public static function debug_dump() {
        if ( ! current_user_can( 'manage_options' ) ) return '';

        $info = [];
        $info[] = 'Avada active: ' . ( self::is_active() ? 'yes' : 'no' );
        $info[] = 'fusion_get_option exists: ' . ( function_exists( 'fusion_get_option' ) ? 'yes' : 'no' );
        $info[] = 'Fusion_Color_Palette class: ' . ( class_exists( 'Fusion_Color_Palette' ) ? 'yes' : 'no' );

        $awb_palette = get_option( 'awb_color_palette', null );
        $info[] = 'awb_color_palette option: ' . ( is_array( $awb_palette ) ? count( $awb_palette ) . ' entries' : 'not set' );

        $opts = get_option( 'fusion_options', [] );
        $info[] = 'fusion_options total keys: ' . ( is_array( $opts ) ? count( $opts ) : 'not set' );

        if ( is_array( $opts ) ) {
            // Find ALL keys whose value looks like a color (regardless of key name)
            $color_value_keys = [];
            foreach ( $opts as $k => $v ) {
                $check = $v;
                if ( is_array( $check ) && isset( $check['color'] ) ) $check = $check['color'];
                if ( is_string( $check ) && self::is_valid_color( $check ) ) {
                    $color_value_keys[ $k ] = $check;
                }
            }

            if ( ! empty( $color_value_keys ) ) {
                $info[] = '';
                $info[] = '== Keys in fusion_options whose VALUES look like colors (' . count( $color_value_keys ) . ' total) ==';
                $shown = 0;
                foreach ( $color_value_keys as $k => $v ) {
                    if ( $shown >= 60 ) {
                        $info[] = '...and ' . ( count( $color_value_keys ) - 60 ) . ' more.';
                        break;
                    }
                    $info[] = '  ' . $k . ' = ' . $v;
                    $shown++;
                }
            } else {
                $info[] = 'No values that look like colors found in fusion_options.';
            }
        }

        // Show theme_mods options too
        if ( function_exists( 'wp_get_theme' ) ) {
            $theme_slug = wp_get_theme()->get_stylesheet();
            $mods = get_option( 'theme_mods_' . $theme_slug, [] );
            if ( is_array( $mods ) && ! empty( $mods ) ) {
                $mod_colors = [];
                foreach ( $mods as $k => $v ) {
                    $check = $v;
                    if ( is_array( $check ) && isset( $check['color'] ) ) $check = $check['color'];
                    if ( is_string( $check ) && self::is_valid_color( $check ) ) {
                        $mod_colors[ $k ] = $check;
                    }
                }
                if ( ! empty( $mod_colors ) ) {
                    $info[] = '';
                    $info[] = '== theme_mods_' . $theme_slug . ' color-like values (' . count( $mod_colors ) . ') ==';
                    foreach ( $mod_colors as $k => $v ) {
                        $info[] = '  ' . $k . ' = ' . $v;
                    }
                }
            }
        }

        $resolved = self::get_palette();
        $info[] = 'Resolved palette: ' . ( ! empty( $resolved ) ? count( $resolved ) . ' colors' : 'empty' );

        return implode( "\n", $info );
    }

    /**
     * Sanitize a stored color value coming from POST.
     */
    public static function sanitize( $value, $default = '#3a8ea8' ) {
        $value = trim( (string) $value );

        if ( strpos( $value, 'avada:' ) === 0 ) {
            // Allow any non-empty key (string label or numeric index)
            $key = sanitize_text_field( substr( $value, 6 ) );
            if ( $key !== '' ) {
                return 'avada:' . $key;
            }
            return $default;
        }

        if ( self::is_valid_color( $value ) ) {
            return $value;
        }

        return $default;
    }
}
