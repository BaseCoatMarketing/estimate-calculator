(function($) {
    'use strict';

    // Tab switching
    $(document).on('click', '.ec-tab', function(e) {
        e.preventDefault();
        var target = $(this).attr('href');

        $('.ec-tab').removeClass('active');
        $(this).addClass('active');

        $('.ec-tab-content').removeClass('active');
        $(target).addClass('active');
    });

    // Image upload button
    $(document).on('click', '.ec-upload-btn', function(e) {
        e.preventDefault();
        var $input = $(this).prev('.ec-image-url');

        var frame = wp.media({
            title: 'Select Image',
            button: { text: 'Use Image' },
            multiple: false
        });

        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
        });

        frame.open();
    });

    /* ============================================================== */
    /*  Avada-aware color picker sync                                  */
    /* ============================================================== */
    /**
     * Resolve a CSS color (hex / rgb / rgba / var(...)) to a #rrggbb hex
     * by letting the browser compute it via getComputedStyle on the frontend
     * preview iframe. Falls back to the default if it can't be computed.
     */
    function resolveCssToHex(cssValue) {
        var probe = document.createElement('span');
        probe.style.color = cssValue;
        // Use the WP frontend body if available so --awb-colorN vars resolve;
        // otherwise fall back to admin body (won't have the vars but won't error).
        document.body.appendChild(probe);
        var computed = getComputedStyle(probe).color;
        document.body.removeChild(probe);

        // computed is "rgb(r, g, b)" or "rgba(r, g, b, a)" — convert to hex
        var m = computed.match(/^rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!m) return null;
        var hex = '#' + [m[1], m[2], m[3]].map(function(n) {
            var h = parseInt(n, 10).toString(16);
            return h.length === 1 ? '0' + h : h;
        }).join('');
        return hex;
    }

    // When the Avada dropdown changes:
    //   - "custom"  → enable visual picker, set stored input from picker hex
    //   - "avada:K" → store the key and lock the visual picker. If the slot is
    //                 a CSS var (var(--awb-colorN)), resolve it via getComputedStyle
    //                 so the swatch reflects the real color.
    $(document).on('change', '.ec-color-picker .ec-avada-select', function() {
        var $wrap   = $(this).closest('.ec-color-picker');
        var $stored = $wrap.find('.ec-color-stored');
        var $visual = $wrap.find('.ec-color-visual');
        var $opt    = $(this).find('option:selected');
        var val     = $(this).val();

        if (val === 'custom') {
            $stored.val($visual.val());
            $visual.prop('disabled', false);
            return;
        }

        var cssValue = $opt.data('css');
        var isVar    = $opt.data('css-var') === 1 || $opt.data('css-var') === '1';
        var hex      = isVar ? resolveCssToHex(cssValue) : cssValue;
        if (hex) {
            // <input type="color"> only accepts #rrggbb format
            if (/^#[0-9a-f]{6}$/i.test(hex)) $visual.val(hex);
        }
        $stored.val(val);
        $visual.prop('disabled', true);
    });

    // On admin page load, resolve all current Avada-var swatches so each picker
    // shows the actual live color instead of the fallback default.
    $(function() {
        $('.ec-color-picker').each(function() {
            var $wrap   = $(this);
            var $select = $wrap.find('.ec-avada-select');
            var $visual = $wrap.find('.ec-color-visual');
            if (!$select.length || $select.val() === 'custom') return;

            var $opt   = $select.find('option:selected');
            var isVar  = $opt.data('css-var') === 1 || $opt.data('css-var') === '1';
            if (!isVar) return;

            var cssVal = $opt.data('css');
            var hex    = resolveCssToHex(cssVal);
            if (hex && /^#[0-9a-f]{6}$/i.test(hex)) {
                $visual.val(hex);
            }
        });
    });

    // When the user picks a custom hex color:
    $(document).on('input change', '.ec-color-picker .ec-color-visual', function() {
        var $wrap   = $(this).closest('.ec-color-picker');
        var $stored = $wrap.find('.ec-color-stored');
        var $select = $wrap.find('.ec-avada-select');

        // If the visual picker is being used directly, switch to "Custom"
        if ($select.length && $select.val() !== 'custom') {
            $select.val('custom');
        }
        $stored.val($(this).val());
    });

    // Initial state — disable visual picker if currently set to an Avada slot
    $('.ec-color-picker').each(function() {
        var $select = $(this).find('.ec-avada-select');
        var $visual = $(this).find('.ec-color-visual');
        if ($select.length && $select.val() && $select.val() !== 'custom') {
            $visual.prop('disabled', true);
        }
    });

    /* ============================================================== */
    /*  Materials repeater (Exterior Pricing tab)                      */
    /* ============================================================== */
    function nextMaterialIndex() {
        var max = -1;
        $('#ec-materials-table tbody tr').each(function() {
            $(this).find('input').each(function() {
                var name = $(this).attr('name') || '';
                var m = name.match(/\[exterior_materials\]\[(\d+)\]/);
                if (m) {
                    var n = parseInt(m[1], 10);
                    if (n > max) max = n;
                }
            });
        });
        return max + 1;
    }

    $(document).on('click', '#ec-add-material', function(e) {
        e.preventDefault();
        var tpl = $('#ec-material-row-template').html();
        var idx = nextMaterialIndex();
        var html = tpl.replace(/__INDEX__/g, idx);
        $('#ec-materials-table tbody').append(html);
        $('#ec-materials-table tbody tr:last-child input[name$="[label]"]').focus();
    });

    $(document).on('click', '.ec-remove-material', function(e) {
        e.preventDefault();
        var $row = $(this).closest('tr');
        if ($('#ec-materials-table tbody tr').length <= 1) {
            // Keep at least one row; just blank it out instead of removing
            $row.find('input[type="text"], input[type="number"]').val('');
            $row.find('input[type="checkbox"]').prop('checked', false);
            return;
        }
        $row.remove();
    });

    /* ============================================================== */
    /*  Site Tokens (REST API)                                         */
    /* ============================================================== */
    function generateSiteToken() {
        var chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        var out = '';
        // Prefer crypto API for entropy when available
        if (window.crypto && window.crypto.getRandomValues) {
            var arr = new Uint8Array(32);
            window.crypto.getRandomValues(arr);
            for (var i = 0; i < arr.length; i++) {
                out += chars[arr[i] % chars.length];
            }
        } else {
            for (var j = 0; j < 32; j++) {
                out += chars[Math.floor(Math.random() * chars.length)];
            }
        }
        return out;
    }

    function nextSiteTokenIndex() {
        var max = -1;
        $('.ec-site-tokens-table tbody tr').each(function() {
            $(this).find('input, textarea').each(function() {
                var name = $(this).attr('name') || '';
                var m = name.match(/\[rest_site_tokens\]\[(\d+)\]/);
                if (m) {
                    var n = parseInt(m[1], 10);
                    if (n > max) max = n;
                }
            });
        });
        return max + 1;
    }

    $(document).on('click', '#ec-add-site-token', function(e) {
        e.preventDefault();
        var tpl   = $('#ec-site-token-row-template').html();
        var idx   = nextSiteTokenIndex();
        var token = generateSiteToken();
        var html  = tpl.replace(/__INDEX__/g, idx).replace(/__TOKEN__/g, token);
        $('.ec-site-tokens-table tbody').append(html);
        $('.ec-site-tokens-table tbody tr:last-child input[name$="[label]"]').focus();
    });

    $(document).on('click', '.ec-regen-token', function(e) {
        e.preventDefault();
        if (!confirm('Replace this token with a new one? The old token will stop working immediately.')) return;
        var $input = $(this).closest('td').find('.ec-token-input');
        $input.val(generateSiteToken());
    });

    $(document).on('click', '.ec-copy-token', function(e) {
        e.preventDefault();
        var $btn   = $(this);
        var $input = $btn.closest('td').find('.ec-token-input');
        var val    = $input.val();
        if (!val) return;
        // Use modern clipboard API with a textarea fallback
        if (navigator.clipboard) {
            navigator.clipboard.writeText(val).then(function() {
                $btn.text('Copied!').delay(900).queue(function(n) { $btn.text('Copy'); n(); });
            });
        } else {
            $input.prop('readonly', false).select();
            try { document.execCommand('copy'); $btn.text('Copied!'); } catch (err) {}
            $input.prop('readonly', true);
            setTimeout(function() { $btn.text('Copy'); }, 900);
        }
    });

    $(document).on('click', '.ec-remove-site-token', function(e) {
        e.preventDefault();
        if (!confirm('Remove this token? Any external site using it will stop working.')) return;
        $(this).closest('tr').remove();
    });

    /* ============================================================== */
    /*  Custom Services repeater                                       */
    /* ============================================================== */
    function nextCustomServiceIndex() {
        var max = -1;
        $('.ec-custom-services-table tbody tr').each(function() {
            $(this).find('input').each(function() {
                var name = $(this).attr('name') || '';
                var m = name.match(/\[custom_services\]\[(\d+)\]/);
                if (m) {
                    var n = parseInt(m[1], 10);
                    if (n > max) max = n;
                }
            });
        });
        return max + 1;
    }

    $(document).on('click', '#ec-add-custom-service', function(e) {
        e.preventDefault();
        var tpl = $('#ec-custom-service-row-template').html();
        var idx = nextCustomServiceIndex();
        $('.ec-custom-services-table tbody').append(tpl.replace(/__INDEX__/g, idx));
        $('.ec-custom-services-table tbody tr:last-child input[name$="[label]"]').focus();
    });

    $(document).on('click', '.ec-remove-custom-service', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    // Toggle visual feedback for sq ft mode vs price multiplier
    $(document).on('change', '.ec-toggle-sqft', function() {
        var $cell = $(this).closest('.ec-pricing-cell');
        var on = $(this).is(':checked');
        $cell.find('.ec-sqft-input').css('opacity', on ? '' : '0.45');
        $cell.find('.ec-mult-input').css('opacity', on ? '0.45' : '');
    });

    // Toggle visual feedback for condition multipliers
    $(document).on('change', '.ec-toggle-condition', function() {
        var $inputs = $(this).closest('details').find('.ec-condition-inputs');
        $inputs.css('opacity', $(this).is(':checked') ? '' : '0.45');
    });

    // Service conditions repeater: add row
    $(document).on('click', '.ec-add-service-condition', function(e) {
        e.preventDefault();
        var serviceIndex = $(this).data('service-index');
        var $tpl = $('.ec-service-condition-row-template[data-service-index="' + serviceIndex + '"]');
        var $table = $(this).closest('.ec-condition-inputs').find('.ec-service-conditions-table tbody');

        // Compute next index by scanning existing names
        var max = -1;
        $table.find('input').each(function() {
            var re = new RegExp('\\[custom_services\\]\\[' + serviceIndex + '\\]\\[conditions\\]\\[(\\d+)\\]');
            var m = ($(this).attr('name') || '').match(re);
            if (m) {
                var n = parseInt(m[1], 10);
                if (n > max) max = n;
            }
        });
        var idx = max + 1;

        var html = $tpl.html().replace(/__INDEX__/g, idx);
        $table.append(html);
    });

    // Service conditions repeater: remove row
    $(document).on('click', '.ec-remove-service-condition', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    // Image upload button (uses WP media library — wp_enqueue_media is already called)
    $(document).on('click', '.ec-cs-upload', function(e) {
        e.preventDefault();
        var $input = $(this).prev('.ec-cs-image-url');
        var frame = wp.media({
            title: 'Select Service Image',
            button: { text: 'Use Image' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
        });
        frame.open();
    });

    /* ============================================================== */
    /*  Custom Questions repeater (per service)                        */
    /* ============================================================== */
    function nextQuestionIndex(service) {
        var $table   = $('.ec-questions-table[data-service="' + service + '"]');
        // Pattern is supplied by the table (data-pattern); fall back to the
        // legacy "{service}_custom_questions" form for safety.
        var pattern  = $table.attr('data-pattern') || ('\\[' + service + '_custom_questions\\]\\[(\\d+)\\]');
        var re       = new RegExp(pattern);
        var max      = -1;
        $table.find('tbody tr').each(function() {
            $(this).find('input, select').each(function() {
                var name = $(this).attr('name') || '';
                var m = name.match(re);
                if (m) {
                    var n = parseInt(m[1], 10);
                    if (n > max) max = n;
                }
            });
        });
        return max + 1;
    }

    $(document).on('click', '.ec-add-question', function(e) {
        e.preventDefault();
        var service  = String($(this).data('service') || '');
        var idx      = nextQuestionIndex(service);
        var csMatch  = service.match(/^cs(\d+)$/);
        var tpl, html;
        if (csMatch) {
            // Shared template for all custom services. Replace __CSIDX__ with
            // this service's real cs index, then __QINDEX__ with the question
            // index. We never emit a per-row <script> template for custom
            // services (nested templates break the outer custom-service-row
            // template's HTML).
            tpl  = $('#ec-question-row-template-cs').html() || '';
            html = tpl.replace(/__CSIDX__/g, csMatch[1]).replace(/__QINDEX__/g, idx);
        } else {
            // Main services (interior/exterior/cabinet) — one template per service.
            tpl  = $('#ec-question-row-template-' + service).html() || '';
            html = tpl.replace(/__QINDEX__/g, idx).replace(/__INDEX__/g, idx);
        }
        $('.ec-questions-table[data-service="' + service + '"] tbody').append(html);
    });

    $(document).on('click', '.ec-remove-question', function(e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });

    // When the type changes, toggle which value group is visible
    $(document).on('change', '.ec-question-type', function() {
        var $row = $(this).closest('tr');
        var type = $(this).val();
        $row.attr('data-type', type);
        $row.find('.ec-q-yesno').toggle(type === 'yesno');
        $row.find('.ec-q-number').toggle(type === 'number');
        $row.find('.ec-q-select').toggle(type === 'select');
        $row.find('.ec-q-percent_select').toggle(type === 'percent_select');
    });

})(jQuery);
