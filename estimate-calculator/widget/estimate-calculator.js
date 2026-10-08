/*!
 * Estimate Calculator Widget
 * Standalone embeddable version — works on any HTML page.
 * Compatible with GoHighLevel Private Integration API V2.
 *
 * Usage:
 *   <div id="estimate-calc"></div>
 *   <script src="https://yourdomain.com/estimate-calculator.js"></script>
 *   <script>EstimateCalculator.init({ ...config });</script>
 *
 * License: MIT
 * Version: 1.1.0
 */
(function(window, document) {
    'use strict';

    /* ============================================================== */
    /*  Default configuration                                          */
    /* ============================================================== */
    var DEFAULTS = {
        container: '#estimate-calc',
        endpoint:  '', // your proxy.php URL or WP admin-ajax.php URL

        // If using WordPress AJAX endpoint
        nonce:     '',
        action:    'ec_submit',

        // WordPress REST API mode — for external (non-PHP) sites that want
        // the WordPress plugin to drive the calc + GHL flow.
        // When wpRestUrl + siteToken are set:
        //   - endpoint defaults to `${wpRestUrl}/submit`
        //   - if fetchConfig is true, the widget GETs `${wpRestUrl}/config`
        //     and merges the returned config (pricing, services, labels, etc.)
        //     before rendering, so admin changes in WP propagate instantly.
        wpRestUrl:   '', // e.g. 'https://wp.example.com/wp-json/ec/v1'
        siteToken:   '', // the token generated in WP admin → External Sites
        fetchConfig: false,

        // Built-in services to enable
        services:  ['interior', 'exterior', 'cabinet'],

        // A/B test landing page variant tag. Sent to GHL as the custom field
        //   {{contact.lp_variant}}
        // Can also be passed via URL: ?lp_variant=landingpage_a — URL wins.
        // Typical values: 'landingpage_a', 'landingpage_b', or any custom slug.
        lpVariant: '',

        // Show a required "ZIP Code" field on the contact step.
        // When true, the value is sent to GHL as the contact's postalCode.
        enableZipField: false,

        // Custom services. Each entry:
        //   { slug, label, image, enabled, pricePerSqft, rangePct,
        //     condition: { like_new, light_wear, moderate_wear, heavy_wear },
        //     customQuestions }
        // Front-end shows them on the service-selection step alongside built-ins.
        // Calculation:
        //   subtotal = sqft × pricePerSqft + customQuestion adjustments
        //   total    = subtotal × condition multiplier
        //   low/high = total ±rangePct%
        customServices: [],

        // Display labels
        labels: {
            interior: 'Interior Painting',
            exterior: 'Exterior Painting',
            cabinet:  'Cabinet Painting',
            heading:  'What type of estimate do you need?'
        },

        // Service images (URLs) — optional
        images: {
            interior: '',
            exterior: '',
            cabinet:  ''
        },

        // Branding
        colors: {
            primary:    '#3a8ea8',
            secondary:  '#2c3e50',
            buttonText: '#ffffff',
            border:     '#a9b3c6'
        },

        // GHL booking calendar embed URL — shown after submission.
        // Leave empty (or set calendarEnabled: false) to hide the calendar and
        // show the fallback CTA button instead (if configured).
        calendar: '',
        calendarEnabled: true,
        // Custom iframe / embed code (Calendly, Acuity, etc.).
        // When set AND calendarEnabled !== false, this OVERRIDES the GHL `calendar` URL.
        // Accepts a string of HTML — typically the full <iframe>...</iframe> markup.
        calendarCustomHtml: '',

        // Fallback CTA button — only renders if no calendar is shown.
        cta: {
            label:   'Schedule Your Estimate',
            url:     '',     // leave empty to hide the fallback button entirely
            newTab:  false
        },

        // Tracking pixel
        pixel: {
            type: 'none', // 'facebook' | 'google' | 'tiktok' | 'custom' | 'none'
            id:   '',
            customCode: '' // only for type: 'custom'
        },

        // SMS consent / legal
        consent: {
            smsText:    'I consent to receive SMS notifications, alerts, and occasional marketing messages from the company. Message frequency varies. Message and data rates may apply. Text HELP to {phone} for assistance. Reply STOP at any time to unsubscribe.',
            helpPhone:  '',
            privacyUrl: ''
            // Note: termsUrl intentionally removed — most clients don't have ToS pages.
        },

        // Disclaimer copy
        disclaimers: {
            form:    'This calculator provides a ballpark estimate based on your inputs. Final pricing will be confirmed after an on-site or virtual walkthrough. For the most accurate quote, schedule a free estimate with our team.',
            results: 'This calculator provides a ballpark estimate based on your inputs. Final pricing will be confirmed after an on-site or virtual walkthrough.'
        },

        // Business info — used for Schema.org / Google Online Estimate JSON-LD
        business: {
            enabled:      true,          // set false to disable schema output
            type:         'HomeAndConstructionBusiness',
            name:         '',
            phone:        '',
            email:        '',
            url:          '',            // defaults to window.location.origin
            logo:         '',
            street:       '',
            city:         '',
            region:       '',
            postal:       '',
            country:      'US',
            areaServed:   '',
            currency:     'USD'
        },

        // Square foot ranges (editable per client)
        sqft: {
            interior: {
                small:       [0, 150],
                medium:      [151, 250],
                large:       [251, 400],
                xlarge:      [401, 600],
                xlargeOpen:  true   // appends "+" to the range label
            },
            exterior: {
                small:      [0, 1500],
                medium:     [1501, 2500],
                large:      [2501, 4000],
                largeOpen:  true    // appends "+" to the largest range label
            }
        },

        // Pricing (all customizable)
        pricing: {
            interior: {
                smallRoom:    270,
                mediumRoom:   2000,
                largeRoom:    2050,
                xlargeRoom:   950,
                entryDoor:    200,
                closetDoor:   150,
                ceilingMult:  0.6,
                trimMult:     0.5,
                condition:    {
                    like_new: 1.0,
                    light_wear: 1.1,
                    moderate_wear: 1.2,
                    heavy_wear: 1.3
                },
                rangePct: 25,  // ±% variance from total (e.g. 25 = ±25%)
                // Custom questions that alter the calc.
                //   { enabled, slug, label, type: 'yesno' | 'number',
                //     yes_value, no_value, op: 'add' | 'multiply'  ← yesno
                //     unit_value                                    ← number
                //   }
                customQuestions: []
            },
            exterior: {
                base: { small: 5000, medium: 9000, large: 12000 },
                // Materials can be toggled, edited, and new ones can be added.
                // Each entry: { slug, label, multiplier, enabled }
                // Only enabled materials render in the dropdown.
                materials: [
                    { slug: 'wood',     label: 'Wood',         multiplier: 1.0,  enabled: true },
                    { slug: 'stucco',   label: 'Stucco',       multiplier: 1.15, enabled: true },
                    { slug: 'brick',    label: 'Brick',        multiplier: 1.1,  enabled: true },
                    { slug: 'aluminum', label: 'Aluminum',     multiplier: 1.2,  enabled: true },
                    { slug: 'laminate', label: 'Laminate',     multiplier: 1.1,  enabled: true },
                    { slug: 'hardie',   label: 'Hardie Board', multiplier: 1.0,  enabled: true }
                ],
                singleGarage: 300,
                doubleGarage: 450,
                shutter:      70,    // per-unit cost (count × this)
                trimMult:     0.2,
                gutterMult:   0.08,
                condition:    {
                    like_new: 1.0,
                    light_wear: 1.1,
                    moderate_wear: 1.2,
                    heavy_wear: 1.3
                },
                rangePct: 25,
                customQuestions: []
            },
            cabinet: {
                base:        800,
                door:        120,
                drawer:      65,
                island:      1150,
                condition:   {
                    like_new: 1.0,
                    light_wear: 1.1,
                    moderate_wear: 1.2,
                    heavy_wear: 1.3
                },
                rangePct: 25,
                customQuestions: []
            }
        },

        // Optional callbacks
        onSubmit:   null, // function(data, estimate) { }
        onResults:  null, // function(estimate) { }
        onError:    null  // function(errorMessage) { }
    };

    /* ============================================================== */
    /*  Utility helpers                                                */
    /* ============================================================== */
    function deepMerge(target, source) {
        if (typeof source !== 'object' || source === null) return target;
        Object.keys(source).forEach(function(key) {
            if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
                target[key] = deepMerge(target[key] || {}, source[key]);
            } else {
                target[key] = source[key];
            }
        });
        return target;
    }

    function fmtRange(arr, openEnded) {
        var s = arr[0].toLocaleString() + '-' + arr[1].toLocaleString();
        return openEnded ? s + '+' : s;
    }
    function fmtRangeUpTo(max) {
        return 'Up to ' + Number(max).toLocaleString();
    }
    function fmtPlus(min) {
        return Number(min).toLocaleString() + '+';
    }

    function fmtCurrency(n) {
        return '$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    function getUtm() {
        var params = new URLSearchParams(window.location.search);
        var utm = {};
        ['utm_source','utm_medium','utm_campaign','utm_term','utm_content'].forEach(function(k) {
            var v = params.get(k);
            if (v) utm[k] = v;
        });
        return utm;
    }

    /**
     * Read a cookie value by name. Used to fetch _fbp / _fbc that the
     * Meta browser pixel writes when present.
     */
    function readCookie(name) {
        try {
            var match = ('; ' + document.cookie).split('; ' + name + '=');
            if (match.length === 2) return decodeURIComponent(match.pop().split(';').shift());
        } catch (e) {}
        return '';
    }

    /**
     * Collect every tracking signal we have for source attribution.
     * Click IDs from the URL, _fbp / _fbc cookies, document.referrer, landing URL.
     */
    function getTracking() {
        var params = new URLSearchParams(window.location.search);
        var t = {};

        // Click IDs from URL
        ['fbclid', 'gclid', 'gbraid', 'wbraid', 'msclkid', 'ttclid', 'li_fat_id'].forEach(function(k) {
            var v = params.get(k);
            if (v) t[k] = v;
        });

        // Landing-page A/B variant tag — URL param takes priority
        var lpv = params.get('lp_variant');
        if (lpv) t.lp_variant = lpv;

        // Meta pixel browser cookies
        var fbp = readCookie('_fbp');
        if (fbp) t.fbp = fbp;
        var fbc = readCookie('_fbc');
        if (fbc) t.fbc = fbc;
        // If we have fbclid but no fbc cookie yet, synthesise one for CAPI
        if (!t.fbc && t.fbclid) {
            t.fbc = 'fb.1.' + Date.now() + '.' + t.fbclid;
        }

        if (document.referrer) t.referrer = document.referrer;
        t.landing_page_url = window.location.href;

        return t;
    }

    /* ============================================================== */
    /*  Calculation Engine                                             */
    /* ============================================================== */
    /**
     * Apply enabled custom-questions to a running subtotal.
     */
    function applyCustomQuestions(subtotal, data, questions) {
        if (!Array.isArray(questions) || !questions.length) return subtotal;
        for (var i = 0; i < questions.length; i++) {
            var q = questions[i];
            if (!q || !q.enabled) continue;
            var field = 'custom_' + q.slug;
            var val   = data[field];

            if (q.type === 'yesno') {
                var apply = (val === 'yes') ? Number(q.yes_value) || 0 : Number(q.no_value) || 0;
                if (q.op === 'multiply') {
                    if (apply > 0) subtotal *= apply;
                } else {
                    subtotal += apply;
                }
            } else if (q.type === 'number') {
                var count = parseInt(val, 10) || 0;
                var per   = Number(q.unit_value) || 0;
                subtotal += count * per;
            } else if (q.type === 'percent_select') {
                var opts = Array.isArray(q.options) ? q.options : [];
                for (var oi = 0; oi < opts.length; oi++) {
                    if (String(opts[oi].value) === String(val)) {
                        var pct = Number(opts[oi].percent) || 0;
                        subtotal += subtotal * (pct / 100);
                        break;
                    }
                }
            }
            // 'select' type intentionally ignored — GHL-only sync, no calc impact.
        }
        return subtotal;
    }

    /**
     * Custom-service pricing rules:
     *   1) sqft + condition  → subtotal = qty × pricePerSqft;     total = subtotal × cond_mult
     *   2) sqft only         → subtotal = qty × pricePerSqft;     total = subtotal
     *   3) multiplier only   → subtotal = qty × priceMultiplier;  total = subtotal
     *   4) multiplier + cond → subtotal = qty × cond_mult        (priceMultiplier bypassed)
     */
    function calcCustomService(data, svc) {
        var qty = parseInt(data.sqft, 10) || 0;

        var useSqft = (svc.pricePerSqftEnabled === undefined) ? true : !!svc.pricePerSqftEnabled;
        var condOn  = (svc.conditionEnabled    === undefined) ? true : !!svc.conditionEnabled;

        // Resolve condition multiplier — prefer new `conditions` list,
        // fall back to legacy `condition` object, then default 4-tier.
        var condMult = 1;
        if (condOn) {
            var condition = data.condition || '';
            var found = false;

            if (Array.isArray(svc.conditions) && svc.conditions.length) {
                for (var k = 0; k < svc.conditions.length; k++) {
                    if (svc.conditions[k].slug === condition) {
                        var m = Number(svc.conditions[k].multiplier);
                        condMult = isNaN(m) ? 1 : m;
                        found = true;
                        break;
                    }
                }
            }
            if (!found && svc.condition && typeof svc.condition === 'object') {
                var legacy = Number(svc.condition[condition]);
                if (!isNaN(legacy)) condMult = legacy;
            }
        }

        var subtotal;
        var applyCondAfter = condOn;

        if (useSqft) {
            subtotal = qty * (Number(svc.pricePerSqft) || 0);
        } else {
            if (condOn) {
                // Multiplier + condition: condition multiplier acts as the per-unit rate
                subtotal = qty * condMult;
                applyCondAfter = false;
            } else {
                subtotal = qty * (Number(svc.priceMultiplier) || 0);
            }
        }

        subtotal = applyCustomQuestions(subtotal, data, svc.customQuestions || []);
        var total = applyCondAfter ? (subtotal * condMult) : subtotal;

        var pct = Number(svc.rangePct);
        if (isNaN(pct)) pct = 25;
        var variance = total * (pct / 100);
        return {
            total: Math.round(total * 100) / 100,
            low:   Math.max(0, Math.round(total - variance)),
            high:  Math.max(0, Math.round(total + variance))
        };
    }

    var Calc = {
        interior: function(data, p) {
            var small        = Number(data.small_rooms)  || 0;
            var medium       = Number(data.medium_rooms) || 0;
            var large        = Number(data.large_rooms)  || 0;
            var xlarge       = Number(data.xlarge_rooms) || 0;
            var entryDoors   = Number(data.entry_doors)  || 0;
            var closetDoors  = Number(data.closet_doors) || 0;
            var condition    = data.condition || 'like_new';

            var roomTotal = (small * p.smallRoom)
                          + (medium * p.mediumRoom)
                          + (large * p.largeRoom)
                          + (xlarge * p.xlargeRoom);

            var addon = 0;
            if (data.ceilings) addon += roomTotal * p.ceilingMult;
            if (data.trim)     addon += roomTotal * p.trimMult;

            var doorTotal = (entryDoors * p.entryDoor) + (closetDoors * p.closetDoor);
            var subtotal  = roomTotal + addon + doorTotal;

            // Apply admin-defined custom questions
            subtotal = applyCustomQuestions(subtotal, data, p.customQuestions);

            var condMult  = p.condition[condition] || 1;
            var total     = subtotal * condMult;

            // Range — ±% variance from total
            var variance = total * (p.rangePct / 100);
            var low  = Math.round(total - variance);
            var high = Math.round(total + variance);

            return {
                total: Math.round(total * 100) / 100,
                low:   Math.max(0, low),
                high:  Math.max(0, high)
            };
        },

        exterior: function(data, p) {
            var homeSize = data.home_size || 'small';
            var material = data.material || 'wood';
            var singleG  = Number(data.single_garage) || 0;
            var doubleG  = Number(data.double_garage) || 0;
            var shutters = Number(data.shutters) || 0;
            var condition = data.condition || 'like_new';

            var base = p.base[homeSize] || p.base.small;

            // Find multiplier from materials array (enabled only); fall back to legacy `material` map.
            var matMult = 1;
            if (Array.isArray(p.materials)) {
                for (var i = 0; i < p.materials.length; i++) {
                    if (p.materials[i].enabled && p.materials[i].slug === material) {
                        matMult = Number(p.materials[i].multiplier) || 1;
                        break;
                    }
                }
            } else if (p.material && p.material[material]) {
                matMult = Number(p.material[material]) || 1;
            }

            var subtotal = base * matMult;

            if (data.ext_trim)    subtotal += base * p.trimMult;
            if (data.ext_gutters) subtotal += base * p.gutterMult;

            // Per-unit add-ons
            subtotal += singleG * p.singleGarage;
            subtotal += doubleG * p.doubleGarage;
            subtotal += shutters * p.shutter;

            // Apply admin-defined custom questions
            subtotal = applyCustomQuestions(subtotal, data, p.customQuestions);

            var condMult = p.condition[condition] || 1;
            var total = subtotal * condMult;

            // Range — ±% variance from total
            var variance = total * (p.rangePct / 100);
            var low  = Math.round(total - variance);
            var high = Math.round(total + variance);

            return {
                total: Math.round(total * 100) / 100,
                low:   Math.max(0, low),
                high:  Math.max(0, high)
            };
        },

        cabinet: function(data, p) {
            var doors   = Number(data.cab_doors)   || 0;
            var drawers = Number(data.cab_drawers) || 0;
            var island  = data.has_island === 'yes';
            var condition = data.condition || 'like_new';

            var total = p.base + (doors * p.door) + (drawers * p.drawer);
            if (island) total += p.island;

            // Apply admin-defined custom questions
            total = applyCustomQuestions(total, data, p.customQuestions);

            var condMult = p.condition[condition] || 1;
            total *= condMult;

            // Range — ±% variance from total
            var variance = total * (p.rangePct / 100);
            var low  = Math.round(total - variance);
            var high = Math.round(total + variance);

            return {
                total: Math.round(total * 100) / 100,
                low:   Math.max(0, low),
                high:  Math.max(0, high)
            };
        }
    };

    /* ============================================================== */
    /*  Inline CSS (injected into the page)                            */
    /* ============================================================== */
    function injectStyles(cfg) {
        if (document.getElementById('ec-widget-styles')) return;
        var c = cfg.colors;
        var css = `
.ec-widget { --ec-primary:${c.primary};--ec-secondary:${c.secondary};--ec-btn-text:${c.buttonText};--ec-border:${c.border};font-family:'Inter','Roboto',-apple-system,BlinkMacSystemFont,sans-serif;max-width:680px;margin:0 auto;padding:30px;background:#fff;border-radius:8px;box-shadow:0 2px 20px rgba(0,0,0,.08);box-sizing:border-box;color:#2c3e50}
.ec-widget *,.ec-widget *::before,.ec-widget *::after{box-sizing:border-box}
.ec-widget h2{font-size:1.5rem;font-weight:700;color:var(--ec-secondary);margin:0 0 24px;text-align:center}
.ec-widget h3{font-size:1.4rem;font-weight:700;color:var(--ec-secondary);margin:0 0 8px}
.ec-widget .ec-section-heading{font-size:1rem;font-weight:700;color:var(--ec-secondary);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--ec-primary);padding-bottom:6px;margin:24px 0 16px}
.ec-widget .ec-note{font-size:.85rem;color:#6c757d;font-style:italic;margin:8px 0 0}
.ec-widget .ec-form-disclaimer{margin-top:16px;padding:12px 14px;background:#f8f9fa;border-left:3px solid var(--ec-primary);border-radius:4px;font-size:.85rem;color:#495057;line-height:1.5}
.ec-widget .ec-form-disclaimer p{margin:0}
.ec-widget .ec-thank-heading{font-size:2rem!important;margin:0 0 8px!important}
.ec-widget .ec-thank-sub{font-size:1.05rem;color:#6c757d;margin:0 0 28px}
.ec-widget .ec-asterisk{color:var(--ec-primary);font-size:1.2rem;align-self:flex-start;font-weight:700}
.ec-widget p{margin:0 0 12px}
.ec-progress{height:6px;background:#e9ecef;border-radius:3px;margin-bottom:30px;overflow:hidden}
.ec-progress-bar{height:100%;background:var(--ec-primary);border-radius:3px;transition:width .4s ease}
.ec-step{display:none}
.ec-step.active{display:block;animation:ecFadeIn .3s ease}
@keyframes ecFadeIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.ec-service-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:20px}
.ec-service-card{display:flex;flex-direction:column;align-items:center;gap:12px;padding:24px 16px;background:#f8f9fa;border:2px solid #e9ecef;border-radius:8px;cursor:pointer;transition:all .2s;font-size:1rem;font-weight:600;color:var(--ec-secondary);font-family:inherit}
.ec-service-card:hover{border-color:var(--ec-primary);background:#f0f7fa;transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.ec-service-card img{width:100%;max-width:160px;height:120px;object-fit:cover;border-radius:6px}
.ec-service-icon{width:80px;height:80px;border-radius:50%;background:var(--ec-primary);opacity:.2}
.ec-field{margin-bottom:20px}
.ec-field label{display:block;font-weight:600;color:var(--ec-secondary);margin-bottom:6px;font-size:.95rem}
.ec-field label small{font-weight:400;color:#6c757d}
.ec-field input[type=text],.ec-field input[type=email],.ec-field input[type=tel],.ec-field input[type=number],.ec-field select{width:100%;padding:12px 14px;border:1px solid var(--ec-border);border-radius:5px;font-size:1rem;font-family:inherit;color:#000;background:#fff;transition:border-color .2s}
.ec-field input:focus,.ec-field select:focus{outline:0;border-color:var(--ec-primary);box-shadow:0 0 0 3px rgba(58,142,168,.15)}
.ec-field input::placeholder{color:#8c8c8c}
.ec-checkbox-group{display:flex;gap:20px;flex-wrap:wrap}
.ec-checkbox-group label{display:flex;align-items:center;gap:8px;font-weight:500;cursor:pointer}
.ec-checkbox-group input[type=checkbox]{width:18px;height:18px;accent-color:var(--ec-primary)}
.ec-consent label{display:flex;align-items:flex-start;gap:10px;font-weight:400;font-size:.85rem;color:#6c757d;line-height:1.4}
.ec-consent input[type=checkbox]{margin-top:3px;flex-shrink:0}
.ec-consent a{color:var(--ec-primary)}
.ec-req{color:#e74c3c}
.ec-actions{display:flex;gap:12px;margin-top:24px}
.ec-btn{padding:14px 28px;border:none;border-radius:5px;font-size:1rem;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit}
.ec-btn-next,.ec-btn-submit{background:var(--ec-primary);color:var(--ec-btn-text);flex:1}
.ec-btn-next:hover,.ec-btn-submit:hover{opacity:.9;transform:translateY(-1px)}
.ec-btn-back{background:#e9ecef;color:var(--ec-secondary)}
.ec-btn-back:hover{background:#dee2e6}
.ec-btn:disabled{opacity:.6;cursor:not-allowed}
.ec-error{margin-top:16px;padding:12px 16px;background:#fdf0f0;border:1px solid #e74c3c;border-radius:5px;color:#c0392b;font-size:.9rem}
.ec-loading{display:flex;align-items:center;gap:12px;margin-top:16px;color:var(--ec-primary);font-weight:500}
.ec-spinner{width:24px;height:24px;border:3px solid #e9ecef;border-top-color:var(--ec-primary);border-radius:50%;animation:ecSpin .6s linear infinite}
@keyframes ecSpin{to{transform:rotate(360deg)}}
.ec-estimate-result{text-align:center;padding:40px 30px;background:#fff;border-radius:12px;box-shadow:0 2px 20px rgba(0,0,0,.08);margin-bottom:30px}
.ec-service-name{font-size:.9rem;font-weight:600;color:var(--ec-primary);text-transform:uppercase;letter-spacing:1px;margin:0 0 8px}
.ec-estimate-result h2{font-size:1.8rem;margin:0 0 30px}
.ec-range-display{display:flex;align-items:center;justify-content:center;gap:24px;margin-bottom:24px}
.ec-range-label{display:block;font-size:.85rem;font-weight:600;color:#6c757d;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.ec-range-value{font-size:2.4rem;font-weight:800;color:var(--ec-primary)}
.ec-range-separator{font-size:2rem;color:#ccc}
.ec-disclaimer{font-size:.85rem;color:#999;margin:0}
.ec-booking-section{text-align:center;margin-top:30px}
.ec-booking-section p{color:#6c757d;margin:0 0 20px}
.ec-calendar-embed{border-radius:12px;overflow:visible;box-shadow:0 2px 20px rgba(0,0,0,.08)}
.ec-calendar-embed iframe{display:block}
.ec-widget .ec-custom-embed{width:100%;min-height:400px}
.ec-widget .ec-custom-embed iframe{width:100%;border:0;display:block}
.ec-widget .ec-cta-section{padding:30px 24px;background:#fff;border-radius:12px;box-shadow:0 2px 20px rgba(0,0,0,.08)}
.ec-widget .ec-btn-cta{display:inline-block;background:var(--ec-primary);color:var(--ec-btn-text);padding:16px 32px;border-radius:6px;font-size:1.05rem;font-weight:700;text-decoration:none;transition:all .2s;border:none;cursor:pointer}
.ec-widget .ec-btn-cta:hover{opacity:.92;transform:translateY(-1px);color:var(--ec-btn-text);text-decoration:none}
@media (max-width:600px){.ec-widget{padding:20px 16px}.ec-service-grid{grid-template-columns:1fr}.ec-range-display{flex-direction:column;gap:12px}.ec-range-separator{display:none}.ec-range-value{font-size:1.8rem}.ec-actions{flex-direction:column}}
        `;
        var style = document.createElement('style');
        style.id = 'ec-widget-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    /* ============================================================== */
    /*  HTML Templates                                                 */
    /* ============================================================== */
    function renderTemplate(cfg) {
        var labels = cfg.labels;
        var sqft = cfg.sqft;
        var services = cfg.services;

        function serviceCard(svc) {
            var imgUrl = cfg.images[svc] || '';
            var imgHtml = imgUrl
                ? '<img src="' + imgUrl + '" alt="' + labels[svc] + '">'
                : '<div class="ec-service-icon"></div>';
            return '<button type="button" class="ec-service-card" data-service="' + svc + '">' +
                   imgHtml + '<span>' + labels[svc] + '</span></button>';
        }

        // Built-in service cards only — skip `custom_*` entries so we don't
        // render each custom service twice (once with a missing image from
        // this loop, then again with its real image from the loop below).
        var servicesHtml = services
            .filter(function(s) { return s && s.indexOf('custom_') !== 0; })
            .map(serviceCard)
            .join('');

        // Append custom service cards (uses their own image + label)
        var customSvcs = Array.isArray(cfg.customServices) ? cfg.customServices : [];
        for (var csi = 0; csi < customSvcs.length; csi++) {
            var cs = customSvcs[csi];
            if (!cs.enabled || !cs.slug || !cs.label) continue;
            var csImg = cs.image
                ? '<img src="' + cs.image + '" alt="' + cs.label + '">'
                : '<div class="ec-service-icon"></div>';
            servicesHtml += '<button type="button" class="ec-service-card" data-service="custom_' + cs.slug + '">' +
                            csImg + '<span>' + cs.label + '</span></button>';
        }

        // Helper: build HTML for a service's enabled custom questions.
        function customQuestionsHtml(service) {
            var pricing = cfg.pricing[service] || {};
            var qs = Array.isArray(pricing.customQuestions) ? pricing.customQuestions : [];
            var html = '';
            var rendered = 0;
            for (var i = 0; i < qs.length; i++) {
                var q = qs[i];
                if (!q.enabled || !q.slug || !q.label) continue;
                if (rendered === 0) html += '<h3 class="ec-section-heading">Additional Details</h3>';
                rendered++;
                var name = 'custom_' + q.slug;
                var msg  = q.label + ' is required.';
                if (q.type === 'yesno') {
                    html +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + ' <span class="ec-req">*</span></label>' +
                        '<select name="' + name + '" required data-required-msg="' + msg + '">' +
                        '<option value="" disabled selected>Select Yes or No</option>' +
                        '<option value="yes">Yes</option>' +
                        '<option value="no">No</option>' +
                        '</select></div>';
                } else if (q.type === 'number') {
                    html +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + ' <span class="ec-req">*</span></label>' +
                        '<input type="number" min="0" value="0" name="' + name + '" required data-required-msg="' + q.label + ' is required (enter 0 if none)." />' +
                        '</div>';
                } else if (q.type === 'select') {
                    var req  = !!q.required;
                    var opts = Array.isArray(q.options) ? q.options : [];
                    var optsHtml = '<option value="" disabled selected>-- Select --</option>';
                    for (var oi = 0; oi < opts.length; oi++) {
                        var opt = opts[oi];
                        optsHtml += '<option value="' + (opt.value || '') + '">' + (opt.label || '') + '</option>';
                    }
                    html +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + (req ? ' <span class="ec-req">*</span>' : '') + '</label>' +
                        '<select name="' + name + '"' + (req ? ' required data-required-msg="' + q.label + ' is required."' : '') + '>' +
                        optsHtml +
                        '</select></div>';
                } else if (q.type === 'percent_select') {
                    var pReq  = !!q.required;
                    var pOpts = Array.isArray(q.options) ? q.options : [];
                    var pOptsHtml = '<option value="" disabled selected>-- Select --</option>';
                    for (var pOi = 0; pOi < pOpts.length; pOi++) {
                        var pOpt = pOpts[pOi];
                        pOptsHtml += '<option value="' + (pOpt.value || '') + '">' + (pOpt.label || '') + '</option>';
                    }
                    html +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + (pReq ? ' <span class="ec-req">*</span>' : '') + '</label>' +
                        '<select name="' + name + '"' + (pReq ? ' required data-required-msg="' + q.label + ' is required."' : '') + '>' +
                        pOptsHtml +
                        '</select></div>';
                }
            }
            return html;
        }

        var interiorQHtml = customQuestionsHtml('interior');
        var exteriorQHtml = customQuestionsHtml('exterior');
        var cabinetQHtml  = customQuestionsHtml('cabinet');

        // Build step blocks for every enabled custom service
        var customServiceStepsHtml = '';
        for (var ci2 = 0; ci2 < customSvcs.length; ci2++) {
            var sv = customSvcs[ci2];
            if (!sv.enabled || !sv.slug || !sv.label) continue;

            // Inline custom-questions HTML for this service
            var svQs = Array.isArray(sv.customQuestions) ? sv.customQuestions : [];
            var svQHtml = '';
            var renderedQ = 0;
            for (var qi = 0; qi < svQs.length; qi++) {
                var q = svQs[qi];
                if (!q.enabled || !q.slug || !q.label) continue;
                if (renderedQ === 0) svQHtml += '<h3 class="ec-section-heading">Additional Details</h3>';
                renderedQ++;
                var qn = 'custom_' + q.slug;
                var qmsg = q.label + ' is required.';
                if (q.type === 'yesno') {
                    svQHtml +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + ' <span class="ec-req">*</span></label>' +
                        '<select name="' + qn + '" required data-required-msg="' + qmsg + '">' +
                        '<option value="" disabled selected>Select Yes or No</option>' +
                        '<option value="yes">Yes</option><option value="no">No</option>' +
                        '</select></div>';
                } else if (q.type === 'number') {
                    svQHtml +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + ' <span class="ec-req">*</span></label>' +
                        '<input type="number" min="0" value="0" name="' + qn + '" required data-required-msg="' + q.label + ' is required (enter 0 if none)." />' +
                        '</div>';
                } else if (q.type === 'select') {
                    var svReq  = !!q.required;
                    var svOpts = Array.isArray(q.options) ? q.options : [];
                    var svOptsHtml = '<option value="" disabled selected>-- Select --</option>';
                    for (var svOi = 0; svOi < svOpts.length; svOi++) {
                        var svOpt = svOpts[svOi];
                        svOptsHtml += '<option value="' + (svOpt.value || '') + '">' + (svOpt.label || '') + '</option>';
                    }
                    svQHtml +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + (svReq ? ' <span class="ec-req">*</span>' : '') + '</label>' +
                        '<select name="' + qn + '"' + (svReq ? ' required data-required-msg="' + q.label + ' is required."' : '') + '>' +
                        svOptsHtml +
                        '</select></div>';
                } else if (q.type === 'percent_select') {
                    var svPReq  = !!q.required;
                    var svPOpts = Array.isArray(q.options) ? q.options : [];
                    var svPOptsHtml = '<option value="" disabled selected>-- Select --</option>';
                    for (var svPOi = 0; svPOi < svPOpts.length; svPOi++) {
                        var svPOpt = svPOpts[svPOi];
                        svPOptsHtml += '<option value="' + (svPOpt.value || '') + '">' + (svPOpt.label || '') + '</option>';
                    }
                    svQHtml +=
                        '<div class="ec-field">' +
                        '<label>' + q.label + (svPReq ? ' <span class="ec-req">*</span>' : '') + '</label>' +
                        '<select name="' + qn + '"' + (svPReq ? ' required data-required-msg="' + q.label + ' is required."' : '') + '>' +
                        svPOptsHtml +
                        '</select></div>';
                }
            }

            var svUseSqft   = (sv.pricePerSqftEnabled === undefined) ? true : !!sv.pricePerSqftEnabled;
            var svCondOn    = (sv.conditionEnabled    === undefined) ? true : !!sv.conditionEnabled;
            var defLabel    = svUseSqft ? 'Square Footage' : 'Quantity';
            var fieldLabel  = sv.fieldLabel ? sv.fieldLabel : defLabel;
            var placeholder = svUseSqft ? 'Enter total sq ft' : 'Enter quantity';

            var conditionField = '';
            if (svCondOn) {
                // Build the list: prefer the new `conditions` array, fall back to
                // legacy `condition` object, otherwise emit the default 4 tiers.
                var condList = [];
                if (Array.isArray(sv.conditions) && sv.conditions.length) {
                    condList = sv.conditions;
                } else if (sv.condition && typeof sv.condition === 'object') {
                    var legacyLabels = {
                        like_new: 'Like New', light_wear: 'Light Wear',
                        moderate_wear: 'Moderate Wear', heavy_wear: 'Heavy Wear'
                    };
                    for (var slug in sv.condition) {
                        if (!Object.prototype.hasOwnProperty.call(sv.condition, slug)) continue;
                        condList.push({
                            slug: slug,
                            label: legacyLabels[slug] || slug.replace(/_/g, ' '),
                            multiplier: sv.condition[slug]
                        });
                    }
                }
                if (!condList.length) {
                    condList = [
                        { slug: 'like_new',      label: 'Like New',      multiplier: 1.0 },
                        { slug: 'light_wear',    label: 'Light Wear',    multiplier: 1.1 },
                        { slug: 'moderate_wear', label: 'Moderate Wear', multiplier: 1.2 },
                        { slug: 'heavy_wear',    label: 'Heavy Wear',    multiplier: 1.3 }
                    ];
                }

                var opts = '';
                for (var ci3 = 0; ci3 < condList.length; ci3++) {
                    opts += '<option value="' + condList[ci3].slug + '">' + condList[ci3].label + '</option>';
                }
                conditionField =
                    '<div class="ec-field"><label>Current Condition <span class="ec-req">*</span></label>' +
                    '<select name="condition" required data-required-msg="Current condition is required.">' +
                    '<option value="" disabled selected>Select condition</option>' +
                    opts +
                    '</select></div>';
            }

            customServiceStepsHtml +=
                '<div class="ec-step" data-step="2" data-service="custom_' + sv.slug + '">' +
                '<h2>' + sv.label + ' Estimate</h2>' +
                '<div class="ec-field"><label>' + fieldLabel + ' <span class="ec-req">*</span></label>' +
                '<input type="number" name="sqft" min="0" value="0" required data-required-msg="' + fieldLabel + ' is required." placeholder="' + placeholder + '" />' +
                '</div>' +
                conditionField +
                svQHtml +
                '<div class="ec-actions"><button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button><button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button></div>' +
                '</div>';
        }

        // Build SMS consent text — replace {phone} or strip the help sentence if no phone
        var smsText = cfg.consent.smsText || '';
        if (cfg.consent.helpPhone) {
            smsText = smsText.replace('{phone}', cfg.consent.helpPhone);
        } else {
            smsText = smsText.replace(' Text HELP to {phone} for assistance.', '');
        }

        var consentHtml = smsText;
        if (cfg.consent.privacyUrl) {
            consentHtml += ' <a href="' + cfg.consent.privacyUrl + '" target="_blank">Privacy Policy</a>';
        }

        // Decide which follow-up block to render. Precedence:
        // custom HTML > GHL calendar URL > CTA button > none
        var calendarsAllowed = cfg.calendarEnabled !== false;
        var customOn  = calendarsAllowed && !!(cfg.calendarCustomHtml && cfg.calendarCustomHtml.trim().length);
        var calendarOn = !customOn && calendarsAllowed && !!cfg.calendar;
        var ctaOn      = !customOn && !calendarOn && !!(cfg.cta && cfg.cta.url);

        var calendarHtml = '';
        if (customOn) {
            calendarHtml =
                '<div class="ec-booking-section">' +
                '<h3>Ready to schedule your estimate?</h3>' +
                '<p>Pick a time that works for you.</p>' +
                '<div class="ec-calendar-embed ec-custom-embed">' +
                cfg.calendarCustomHtml +
                '</div></div>';
        } else if (calendarOn) {
            calendarHtml =
                '<div class="ec-booking-section">' +
                '<h3>Ready to schedule your estimate?</h3>' +
                '<p>Book a time that works for you and we\'ll come out to give you an exact quote.</p>' +
                '<div class="ec-calendar-embed">' +
                '<iframe id="ec-calendar-iframe" data-src="' + cfg.calendar + '" style="border:none;width:100%;min-height:700px;overflow-y:auto" scrolling="yes" title="Book Your Estimate"></iframe>' +
                '</div></div>';
        } else if (ctaOn) {
            var target = cfg.cta.newTab ? ' target="_blank" rel="noopener"' : '';
            calendarHtml =
                '<div class="ec-booking-section ec-cta-section">' +
                '<h3>Ready to schedule your estimate?</h3>' +
                '<p>Click below and we\'ll get you scheduled.</p>' +
                '<a href="' + cfg.cta.url + '" class="ec-btn ec-btn-cta"' + target + '>' +
                (cfg.cta.label || 'Schedule Your Estimate') +
                '</a>' +
                '</div>';
        }

        return (
'<div class="ec-widget">' +
  '<div class="ec-progress"><div class="ec-progress-bar" style="width:10%"></div></div>' +
  '<div class="ec-error" style="display:none"></div>' +

  // Step 1
  '<div class="ec-step active" data-step="1">' +
    '<h2>' + labels.heading + '</h2>' +
    '<div class="ec-service-grid">' + servicesHtml + '</div>' +
  '</div>' +

  // Step 2 Interior
  (services.indexOf('interior') > -1 ?
  '<div class="ec-step" data-step="2" data-service="interior">' +
    '<h2>' + labels.interior + ' Estimate</h2>' +

    '<h3 class="ec-section-heading">Room Count by Size</h3>' +
    '<div class="ec-field"><label>Small Rooms <small>(' + fmtRange(sqft.interior.small) + ' sq ft)</small> <span class="ec-req">*</span></label><input type="number" name="small_rooms" min="0" value="0" required data-required-msg="Number of small rooms is required."></div>' +
    '<div class="ec-field"><label>Medium Rooms <small>(' + fmtRange(sqft.interior.medium) + ' sq ft)</small> <span class="ec-req">*</span></label><input type="number" name="medium_rooms" min="0" value="0" required data-required-msg="Number of medium rooms is required."></div>' +
    '<div class="ec-field"><label>Large Rooms <small>(' + fmtRange(sqft.interior.large) + ' sq ft)</small> <span class="ec-req">*</span></label><input type="number" name="large_rooms" min="0" value="0" required data-required-msg="Number of large rooms is required."></div>' +
    '<div class="ec-field"><label>Extra Large Rooms <small>(' + fmtRange(sqft.interior.xlarge, sqft.interior.xlargeOpen) + ' sq ft)</small> <span class="ec-req">*</span></label><input type="number" name="xlarge_rooms" min="0" value="0" required data-required-msg="Number of extra large rooms is required."></div>' +

    '<h3 class="ec-section-heading">Additional Interior Items</h3>' +
    '<div class="ec-field"><div class="ec-checkbox-group"><label><input type="checkbox" name="ceilings" value="1"> Ceilings</label><label><input type="checkbox" name="trim" value="1"> Trim</label></div></div>' +

    '<h3 class="ec-section-heading">Doors</h3>' +
    '<div class="ec-field"><label>Entry Doors <small>(garage, backyard, front)</small> <span class="ec-req">*</span></label><input type="number" name="entry_doors" min="0" value="0" required data-required-msg="Entry door count is required."></div>' +
    '<div class="ec-field"><label>Closet / Interior Doors <span class="ec-req">*</span></label><input type="number" name="closet_doors" min="0" value="0" required data-required-msg="Closet/interior door count is required."></div>' +

    '<div class="ec-field"><label>Current Interior Condition <span class="ec-req">*</span></label><select name="condition" required data-required-msg="Current interior condition is required."><option value="" disabled selected>Select condition</option><option value="like_new">Like New</option><option value="light_wear">Light Wear</option><option value="moderate_wear">Moderate Wear</option><option value="heavy_wear">Heavy Wear</option></select></div>' +

    interiorQHtml +

    '<div class="ec-actions"><button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button><button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button></div>' +
  '</div>' : '') +

  // Step 2 Exterior
  (services.indexOf('exterior') > -1 ?
  '<div class="ec-step" data-step="2" data-service="exterior">' +
    '<h2>' + labels.exterior + ' Estimate</h2>' +

    '<h3 class="ec-section-heading">Home Size (Square Footage)</h3>' +
    '<div class="ec-field"><label>Home Size <span class="ec-req">*</span></label><select name="home_size" required data-required-msg="Home size is required."><option value="" disabled selected>Select home size</option>' +
      '<option value="small">Small (' + fmtRangeUpTo(sqft.exterior.small[1]) + ' sq ft)</option>' +
      '<option value="medium">Medium (' + fmtRange(sqft.exterior.medium) + ' sq ft)</option>' +
      '<option value="large">Large (' + fmtRange(sqft.exterior.large, sqft.exterior.largeOpen) + ' sq ft)</option>' +
    '</select></div>' +

    '<h3 class="ec-section-heading">Siding</h3>' +
    '<div class="ec-field"><label>Primary Siding Material <span class="ec-req">*</span></label><select name="material" required data-required-msg="Primary siding material is required.">' +
      '<option value="" disabled selected>Select siding material</option>' +
      (function() {
          var opts = '';
          var mats = (cfg.pricing && cfg.pricing.exterior && Array.isArray(cfg.pricing.exterior.materials))
              ? cfg.pricing.exterior.materials : [];
          for (var i = 0; i < mats.length; i++) {
              var m = mats[i];
              if (!m.enabled) continue;
              opts += '<option value="' + m.slug + '">' + m.label + '</option>';
          }
          return opts;
      })() +
    '</select></div>' +

    '<h3 class="ec-section-heading">Garage Doors</h3>' +
    '<div class="ec-field"><label>Single Garage Doors <span class="ec-req">*</span></label><input type="number" name="single_garage" min="0" value="0" required data-required-msg="Single garage door count is required."></div>' +
    '<div class="ec-field"><label>Double Garage Doors <span class="ec-req">*</span></label><input type="number" name="double_garage" min="0" value="0" required data-required-msg="Double garage door count is required."></div>' +

    '<h3 class="ec-section-heading">Shutters</h3>' +
    '<div class="ec-field"><label>Number of Shutters <span class="ec-req">*</span></label><input type="number" name="shutters" min="0" value="0" required data-required-msg="Shutter count is required (enter 0 if none)."></div>' +

    '<h3 class="ec-section-heading">Additional Exterior Items</h3>' +
    '<div class="ec-field"><div class="ec-checkbox-group"><label><input type="checkbox" name="ext_trim" value="1"> Trim</label><label><input type="checkbox" name="ext_gutters" value="1"> Gutters</label></div><p class="ec-note">We\'ll inspect for wood rot on site.</p></div>' +

    '<div class="ec-field"><label>Current Exterior Condition <span class="ec-req">*</span></label><select name="condition" required data-required-msg="Current exterior condition of the home is required."><option value="" disabled selected>Select condition</option><option value="like_new">Like New (new or recently painted, no visible wear)</option><option value="light_wear">Light Wear (minor fading or dirt, minimal prep needed)</option><option value="moderate_wear">Moderate Wear (peeling paint, small cracks, or surface damage)</option><option value="heavy_wear">Heavy Wear (extensive peeling, exposed substrate, or repairs needed)</option></select></div>' +

    exteriorQHtml +

    '<div class="ec-actions"><button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button><button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button></div>' +
  '</div>' : '') +

  // Step 2 Cabinet
  (services.indexOf('cabinet') > -1 ?
  '<div class="ec-step" data-step="2" data-service="cabinet">' +
    '<h2>' + labels.cabinet + ' Estimate</h2>' +
    '<div class="ec-field"><label>Cabinet Doors (total count) <span class="ec-req">*</span></label><input type="number" name="cab_doors" min="0" value="0" required data-required-msg="Cabinet door count is required."></div>' +
    '<div class="ec-field"><label>Cabinet Drawers (total count) <span class="ec-req">*</span></label><input type="number" name="cab_drawers" min="0" value="0" required data-required-msg="Cabinet drawer count is required."></div>' +
    '<div class="ec-field"><label>Kitchen Island Included <span class="ec-req">*</span></label><select name="has_island" required data-required-msg="Please indicate whether a kitchen island is included."><option value="" disabled selected>Select Yes or No</option><option value="yes">Yes</option><option value="no">No</option></select></div>' +
    '<div class="ec-field"><label>Current Cabinet Condition <span class="ec-req">*</span></label><select name="condition" required data-required-msg="Current cabinet condition is required."><option value="" disabled selected>Select condition</option><option value="like_new">Like New (new build or painted within the last 1–2 years, no visible flaws)</option><option value="light_wear">Light Wear (minor scuffs or marks, little to no prep needed)</option><option value="moderate_wear">Moderate Wear (noticeable imperfections, moderate scuffs or marks, or minor repair needed)</option><option value="heavy_wear">Heavy Wear (significant damage, large or deep scratches, major repair needed, or extensive prep required)</option></select></div>' +

    cabinetQHtml +

    '<div class="ec-actions"><button type="button" class="ec-btn ec-btn-back" data-goto="1">Back</button><button type="button" class="ec-btn ec-btn-next" data-goto="contact">Next</button></div>' +
  '</div>' : '') +

  // Step 2 Custom Services (one block per enabled custom service)
  customServiceStepsHtml +

  // Step 3 Contact
  '<div class="ec-step" data-step="contact">' +
    '<h2>Your Information</h2>' +
    '<div class="ec-field"><label>Full Name <span class="ec-req">*</span></label><input type="text" name="full_name" required placeholder="John Smith"></div>' +
    '<div class="ec-field"><label>Email <span class="ec-req">*</span></label><input type="email" name="email" required placeholder="john@example.com"></div>' +
    '<div class="ec-field"><label>Phone <span class="ec-req">*</span></label><input type="tel" name="phone" required placeholder="(555) 123-4567"></div>' +
    (cfg.enableZipField
        ? '<div class="ec-field"><label>ZIP Code <span class="ec-req">*</span></label><input type="text" name="zip_code" required inputmode="numeric" pattern="[A-Za-z0-9 \\-]{3,10}" placeholder="12345" data-required-msg="ZIP code is required."></div>'
        : ''
    ) +
    '<div class="ec-field ec-consent"><label><input type="checkbox" name="terms" required data-required-msg="Consent is required to move forward."><span><span class="ec-req">*</span> ' + consentHtml + '</span></label></div>' +
    (cfg.disclaimers.form ? '<div class="ec-form-disclaimer"><p>' + cfg.disclaimers.form + '</p></div>' : '') +
    '<div class="ec-actions"><button type="button" class="ec-btn ec-btn-back" data-goto="2">Back</button><button type="button" class="ec-btn ec-btn-submit">Get Estimate</button></div>' +
    '<div class="ec-loading" style="display:none"><div class="ec-spinner"></div><span>Calculating your estimate...</span></div>' +
  '</div>' +

  // Step 4 Results — updated copy: "Thank you! Your instant {service} estimate is ready."
  '<div class="ec-step" data-step="results">' +
    '<div class="ec-estimate-result">' +
      '<h2 class="ec-thank-heading">Thank you!</h2>' +
      '<p class="ec-thank-sub">Your instant estimate for <span data-result="service-inline"></span> is ready.</p>' +
      '<div class="ec-range-display">' +
        '<div><span class="ec-range-label">Low</span><span class="ec-range-value" data-result="low">$0</span></div>' +
        '<div class="ec-range-separator">–</div>' +
        '<div><span class="ec-range-label">High</span><span class="ec-range-value" data-result="high">$0</span></div>' +
        '<span class="ec-asterisk">*</span>' +
      '</div>' +
      '<p class="ec-disclaimer">* ' + (cfg.disclaimers.results || '') + '</p>' +
    '</div>' +
    calendarHtml +
    '<div class="ec-actions" style="justify-content:center;margin-top:24px"><button type="button" class="ec-btn ec-btn-back" data-goto="1">Start New Estimate</button></div>' +
  '</div>' +

'</div>'
        );
    }

    /* ============================================================== */
    /*  Schema.org / Google Online Estimate (JSON-LD)                  */
    /* ============================================================== */
    function buildProvider(b) {
        var p = { '@type': b.type || 'HomeAndConstructionBusiness' };
        if (b.name)  p.name = b.name;
        if (b.url)   p.url = b.url;
        if (b.phone) p.telephone = b.phone;
        if (b.email) p.email = b.email;
        if (b.logo)  p.logo = b.logo;

        if (b.street || b.city || b.region || b.postal) {
            var addr = { '@type': 'PostalAddress' };
            if (b.street) addr.streetAddress   = b.street;
            if (b.city)   addr.addressLocality = b.city;
            if (b.region) addr.addressRegion   = b.region;
            if (b.postal) addr.postalCode      = b.postal;
            if (b.country) addr.addressCountry = b.country;
            p.address = addr;
        }
        if (b.areaServed) p.areaServed = b.areaServed;
        return p;
    }

    function buildServiceSchema(cfg) {
        var b = cfg.business;
        var pageUrl = b.url || window.location.href;
        var offerItems = cfg.services.map(function(svc) {
            return {
                '@type': 'Offer',
                'itemOffered': {
                    '@type': 'Service',
                    'name': cfg.labels[svc] || svc,
                    'serviceType': cfg.labels[svc] || svc
                },
                'priceCurrency': b.currency || 'USD',
                'priceSpecification': {
                    '@type': 'PriceSpecification',
                    'priceCurrency': b.currency || 'USD',
                    'price': '0',
                    'description': 'Free online estimate'
                }
            };
        });

        var schema = {
            '@context': 'https://schema.org',
            '@type': 'Service',
            'name': (b.name ? b.name + ' — ' : '') + 'Online Painting Estimate',
            'serviceType': 'Painting',
            'provider': buildProvider(b),
            'url': pageUrl,
            'potentialAction': {
                '@type': 'QuoteAction',
                'name': 'Get a free online estimate',
                'target': {
                    '@type': 'EntryPoint',
                    'urlTemplate': pageUrl,
                    'actionPlatform': [
                        'https://schema.org/DesktopWebPlatform',
                        'https://schema.org/MobileWebPlatform'
                    ]
                },
                'result': {
                    '@type': 'Reservation',
                    'name': 'Painting Estimate',
                    'description': 'A price range estimate for painting services.'
                }
            },
            'hasOfferCatalog': {
                '@type': 'OfferCatalog',
                'name': 'Painting Services',
                'itemListElement': offerItems
            }
        };
        if (b.areaServed) schema.areaServed = b.areaServed;
        return schema;
    }

    function buildOfferSchema(cfg, service, estimate) {
        var b = cfg.business;
        var label = cfg.labels[service] || service;
        return {
            '@context': 'https://schema.org',
            '@type': 'Offer',
            'name': label + ' Estimate',
            'description': 'Estimated price range for ' + label.toLowerCase() + ' based on project details.',
            'seller': buildProvider(b),
            'itemOffered': {
                '@type': 'Service',
                'name': label,
                'serviceType': label
            },
            'priceSpecification': {
                '@type': 'PriceSpecification',
                'priceCurrency': b.currency || 'USD',
                'minPrice': Number(estimate.low),
                'maxPrice': Number(estimate.high),
                'valueAddedTaxIncluded': false
            },
            'availability': 'https://schema.org/InStock',
            'validFrom': new Date().toISOString()
        };
    }

    function injectSchema(id, data) {
        try {
            var existing = document.getElementById(id);
            if (existing) existing.remove();
            var s = document.createElement('script');
            s.type = 'application/ld+json';
            s.id = id;
            s.textContent = JSON.stringify(data);
            document.head.appendChild(s);
        } catch (e) { /* swallow */ }
    }

    /* ============================================================== */
    /*  Pixel Helpers                                                  */
    /* ============================================================== */
    function installPixel(cfg) {
        var t = cfg.pixel.type;
        var id = cfg.pixel.id;
        if (!t || t === 'none') return;

        if (t === 'facebook' && id && !window.fbq) {
            (function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)})(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            window.fbq('init', id);
            window.fbq('track', 'PageView');
        } else if (t === 'google' && id && !window.gtag) {
            var tagId = id.split('/')[0];
            var s = document.createElement('script');
            s.async = true;
            s.src = 'https://www.googletagmanager.com/gtag/js?id=' + tagId;
            document.head.appendChild(s);
            window.dataLayer = window.dataLayer || [];
            window.gtag = function(){window.dataLayer.push(arguments);};
            window.gtag('js', new Date());
            window.gtag('config', tagId);
        } else if (t === 'tiktok' && id && !window.ttq) {
            !function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{};ttq._i[e]=[];ttq._i[e]._u=i;ttq._t=ttq._t||{};ttq._t[e+"_"+n]=1;var o=document.createElement("script");o.type="text/javascript";o.async=!0;o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(id);ttq.page();}(window,document,'ttq');
        } else if (t === 'custom' && cfg.pixel.customCode) {
            var div = document.createElement('div');
            div.innerHTML = cfg.pixel.customCode;
            var scripts = div.querySelectorAll('script');
            scripts.forEach(function(s) {
                var ns = document.createElement('script');
                if (s.src) ns.src = s.src;
                else ns.textContent = s.textContent;
                document.head.appendChild(ns);
            });
        }
    }

    function fireConversion(cfg) {
        var t = cfg.pixel.type;
        var id = cfg.pixel.id;
        if (!t || t === 'none') return;
        try {
            if (t === 'facebook' && typeof window.fbq === 'function') {
                window.fbq('track', 'Lead');
            } else if (t === 'google' && typeof window.gtag === 'function') {
                window.gtag('event', 'conversion', { send_to: id });
            } else if (t === 'tiktok' && window.ttq) {
                window.ttq.track('SubmitForm');
            }
        } catch (e) { /* swallow */ }
    }

    /* ============================================================== */
    /*  Main Widget Factory                                            */
    /* ============================================================== */
    function createWidget(cfg) {
        var container = typeof cfg.container === 'string'
            ? document.querySelector(cfg.container)
            : cfg.container;

        if (!container) {
            console.error('[EstimateCalculator] Container not found:', cfg.container);
            return;
        }

        injectStyles(cfg);
        installPixel(cfg);

        // Google Online Estimate — inject Service schema on page load
        if (cfg.business && cfg.business.enabled !== false) {
            injectSchema('ec-service-schema', buildServiceSchema(cfg));
        }

        container.innerHTML = renderTemplate(cfg);

        var root        = container.querySelector('.ec-widget');
        var progressBar = root.querySelector('.ec-progress-bar');
        var errorEl     = root.querySelector('.ec-error');
        var loadingEl   = root.querySelector('.ec-loading');
        var submitBtn   = root.querySelector('.ec-btn-submit');

        var selectedService = '';

        /* --- Step navigation --- */
        function showStep(stepId, svc) {
            root.querySelectorAll('.ec-step').forEach(function(s) { s.classList.remove('active'); });
            var target;
            if (stepId === 'results') {
                target = root.querySelector('.ec-step[data-step="results"]');
                setProgress(100);
            } else if (stepId === 'contact') {
                target = root.querySelector('.ec-step[data-step="contact"]');
                setProgress(75);
            } else if (stepId === '2' || stepId === 2) {
                target = root.querySelector('.ec-step[data-step="2"][data-service="' + (svc || selectedService) + '"]');
                setProgress(40);
            } else {
                target = root.querySelector('.ec-step[data-step="1"]');
                setProgress(10);
            }
            if (target) {
                target.classList.add('active');
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function setProgress(pct) { if (progressBar) progressBar.style.width = pct + '%'; }

        /* --- Service selection --- */
        root.querySelectorAll('.ec-service-card').forEach(function(card) {
            card.addEventListener('click', function() {
                selectedService = this.getAttribute('data-service');
                showStep(2, selectedService);
            });
        });

        /* --- Next/Back --- */
        root.addEventListener('click', function(e) {
            var next = e.target.closest('.ec-btn-next');
            if (next) {
                if (!validateServiceStep(next)) return;
                showStep(next.getAttribute('data-goto'));
                return;
            }
            var back = e.target.closest('.ec-btn-back');
            if (back) {
                var from = back.closest('.ec-step');
                if (from && from.getAttribute('data-step') === 'results') {
                    resetForm();
                }
                showStep(back.getAttribute('data-goto'));
            }
        });

        /* --- Validation --- */
        function validateServiceStep(btn) {
            var step = btn.closest('.ec-step');
            var fields = step.querySelectorAll('[required]');
            for (var i = 0; i < fields.length; i++) {
                var f = fields[i];
                var val = (f.value || '').toString().trim();
                var invalid = !val;
                if (f.tagName === 'INPUT' && f.type === 'number') {
                    invalid = (val === '' || isNaN(Number(val)));
                }
                if (invalid) {
                    f.focus();
                    f.style.borderColor = '#e74c3c';
                    showError(f.getAttribute('data-required-msg') || 'This field is required.');
                    return false;
                }
                f.style.borderColor = '';
            }
            hideError();
            return true;
        }

        function validateContact() {
            var step = root.querySelector('.ec-step[data-step="contact"]');
            var name  = step.querySelector('input[name="full_name"]');
            var email = step.querySelector('input[name="email"]');
            var phone = step.querySelector('input[name="phone"]');
            var zip   = step.querySelector('input[name="zip_code"]'); // only present when enabled
            var terms = step.querySelector('input[name="terms"]');
            var errors = [];
            if (!name.value.trim()) errors.push('Full name is required.');
            if (!email.value.trim() || !email.value.includes('@')) errors.push('Valid email is required.');
            if (!phone.value.trim()) errors.push('Phone number is required.');
            if (zip && !zip.value.trim()) errors.push('ZIP code is required.');
            if (!terms.checked) errors.push('Consent is required to move forward.');
            if (errors.length) { showError(errors.join(' ')); return false; }
            hideError();
            return true;
        }

        function showError(msg) {
            if (errorEl) { errorEl.textContent = msg; errorEl.style.display = 'block'; }
            if (cfg.onError) cfg.onError(msg);
        }
        function hideError() { if (errorEl) errorEl.style.display = 'none'; }

        /* --- Gather data --- */
        function gatherData() {
            var data = { service: selectedService };
            var svcStep = root.querySelector('.ec-step[data-step="2"][data-service="' + selectedService + '"]');
            if (svcStep) {
                svcStep.querySelectorAll('input, select').forEach(function(el) {
                    if (!el.name) return;
                    if (el.type === 'checkbox') data[el.name] = el.checked ? '1' : '';
                    else data[el.name] = el.value;
                });
            }
            var cStep = root.querySelector('.ec-step[data-step="contact"]');
            if (cStep) {
                cStep.querySelectorAll('input').forEach(function(el) {
                    if (!el.name || el.type === 'checkbox') return;
                    data[el.name] = el.value;
                });
            }
            // Add UTMs
            var utm = getUtm();
            for (var k in utm) data[k] = utm[k];
            // Add all other tracking signals — click IDs, fbp/fbc, referrer, landing URL
            var tracking = getTracking();
            for (var tk in tracking) {
                if (!(tk in data) || !data[tk]) data[tk] = tracking[tk];
            }
            // Landing-page variant: URL ?lp_variant=... wins; otherwise config value is used
            if (!data.lp_variant && cfg.lpVariant) {
                data.lp_variant = String(cfg.lpVariant);
            }

            // Build a slug → GHL-field-key map for any select-type custom questions
            // that specify their own ghl_field_key. Proxy uses this to route answers.
            var svcCfg = cfg.pricing && cfg.pricing[selectedService];
            var cfMap  = {};
            function isSelectLike(t) { return t === 'select' || t === 'percent_select'; }
            // For percent_select answers, replace the internal value with the option's
            // human-readable label so GHL shows something meaningful.
            function relabelPercentPick(qq) {
                if (qq.type !== 'percent_select' || !Array.isArray(qq.options)) return;
                var f = 'custom_' + qq.slug;
                if (!data[f]) return;
                for (var oi = 0; oi < qq.options.length; oi++) {
                    if (String(qq.options[oi].value) === String(data[f])) {
                        data[f] = qq.options[oi].label || data[f];
                        return;
                    }
                }
            }
            if (svcCfg && Array.isArray(svcCfg.customQuestions)) {
                for (var qi2 = 0; qi2 < svcCfg.customQuestions.length; qi2++) {
                    var qq = svcCfg.customQuestions[qi2];
                    if (!qq || !qq.enabled || !qq.slug) continue;
                    if (isSelectLike(qq.type) && qq.ghl_field_key) cfMap[qq.slug] = qq.ghl_field_key;
                    relabelPercentPick(qq);
                }
            }
            // Also inspect this service's own customServices entry (custom services)
            if (selectedService && selectedService.indexOf('custom_') === 0 && Array.isArray(cfg.customServices)) {
                var csSlug = selectedService.substring(7);
                for (var csi3 = 0; csi3 < cfg.customServices.length; csi3++) {
                    var csv = cfg.customServices[csi3];
                    if (csv && csv.slug === csSlug && Array.isArray(csv.customQuestions)) {
                        for (var qi3 = 0; qi3 < csv.customQuestions.length; qi3++) {
                            var qq2 = csv.customQuestions[qi3];
                            if (!qq2 || !qq2.enabled || !qq2.slug) continue;
                            if (isSelectLike(qq2.type) && qq2.ghl_field_key) cfMap[qq2.slug] = qq2.ghl_field_key;
                            relabelPercentPick(qq2);
                        }
                    }
                }
            }
            if (Object.keys(cfMap).length) {
                // Flatten into custom_field_map[slug]=ghl_key form fields
                for (var slugKey in cfMap) {
                    data['custom_field_map[' + slugKey + ']'] = cfMap[slugKey];
                }
            }

            return data;
        }

        /* --- Submit --- */
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                if (!validateContact()) return;
                submitBtn.disabled = true;
                if (loadingEl) loadingEl.style.display = 'flex';
                hideError();

                var data = gatherData();
                var estimate;
                if (data.service && data.service.indexOf('custom_') === 0) {
                    // Custom service: look up its config
                    var slug = data.service.substring(7);
                    var svc = null;
                    var arr = Array.isArray(cfg.customServices) ? cfg.customServices : [];
                    for (var ci = 0; ci < arr.length; ci++) {
                        // Note: match by slug ONLY — if a user picked this service on
                        // step 1, it must be enabled. Some transports (JSON.decode etc)
                        // can turn `enabled: true` into truthy non-boolean values that
                        // an `&& arr[ci].enabled` check may mis-handle in edge cases.
                        if (arr[ci].slug === slug) { svc = arr[ci]; break; }
                    }
                    if (!svc) {
                        console.error('[EstimateCalculator] No custom service config found for slug "' + slug + '".');
                        console.error('  Available customServices:', arr.map(function(x) { return x.slug; }));
                    } else {
                        console.log('[EstimateCalculator] Custom service calc → slug:', slug,
                            '| sqft:', data.sqft,
                            '| pricePerSqftEnabled:', svc.pricePerSqftEnabled,
                            '| pricePerSqft:', svc.pricePerSqft,
                            '| priceMultiplier:', svc.priceMultiplier,
                            '| conditionEnabled:', svc.conditionEnabled,
                            '| condition:', data.condition,
                            '| rangePct:', svc.rangePct);
                    }
                    estimate = svc ? calcCustomService(data, svc) : { total: 0, low: 0, high: 0 };
                    if (svc && (estimate.total === 0 || estimate.high === 0)) {
                        console.warn('[EstimateCalculator] Custom service calc returned 0. Check that pricePerSqft (or priceMultiplier if sqft mode is off, or condition multiplier if conditions are on) has a non-zero value in the plugin admin.');
                    }
                } else {
                    var pricing = cfg.pricing[data.service];
                    estimate = Calc[data.service](data, pricing);
                }

                if (cfg.onSubmit) cfg.onSubmit(data, estimate);

                // Post to endpoint (GHL proxy / WP AJAX)
                if (cfg.endpoint) {
                    var body = new URLSearchParams();
                    body.append('action', cfg.action || 'ec_submit');
                    if (cfg.nonce) body.append('nonce', cfg.nonce);
                    body.append('estimate_low',   estimate.low);
                    body.append('estimate_high',  estimate.high);
                    body.append('estimate_total', estimate.total);
                    for (var k in data) body.append(k, data[k]);

                    var submitHeaders = { 'Content-Type': 'application/x-www-form-urlencoded' };
                    if (cfg.siteToken) {
                        submitHeaders['X-EC-Site-Token'] = cfg.siteToken;
                    }
                    fetch(cfg.endpoint, {
                        method: 'POST',
                        headers: submitHeaders,
                        body: body.toString()
                    })
                    .then(function(r) { return r.json().catch(function() { return {}; }); })
                    .then(function(json) {
                        if (loadingEl) loadingEl.style.display = 'none';
                        fireConversion(cfg);
                        showResults(estimate, data.service);
                        if (cfg.onResults) cfg.onResults(estimate);
                    })
                    .catch(function(err) {
                        // Even if backend fails, still show estimate to user
                        if (loadingEl) loadingEl.style.display = 'none';
                        fireConversion(cfg);
                        showResults(estimate, data.service);
                        if (cfg.onError) cfg.onError('Submission saved locally but backend sync failed.');
                    });
                } else {
                    // No endpoint — client-side only (good for demos)
                    if (loadingEl) loadingEl.style.display = 'none';
                    fireConversion(cfg);
                    showResults(estimate, data.service);
                    if (cfg.onResults) cfg.onResults(estimate);
                }
            });
        }

        /* --- Show results --- */
        function showResults(estimate, service) {
            var label = (function() {
                if (cfg.labels[service]) return cfg.labels[service];
                if (service && service.indexOf('custom_') === 0) {
                    var slug = service.substring(7);
                    var arr = Array.isArray(cfg.customServices) ? cfg.customServices : [];
                    for (var li = 0; li < arr.length; li++) {
                        if (arr[li].slug === slug) return arr[li].label;
                    }
                }
                return service;
            })();
            var inline = root.querySelector('[data-result="service-inline"]');
            if (inline) inline.textContent = label;
            root.querySelector('[data-result="low"]').textContent  = fmtCurrency(estimate.low);
            root.querySelector('[data-result="high"]').textContent = fmtCurrency(estimate.high);

            // Google Online Estimate — inject Offer schema for this estimate
            if (cfg.business && cfg.business.enabled !== false) {
                injectSchema('ec-offer-schema', buildOfferSchema(cfg, service, estimate));
            }

            var iframe = root.querySelector('#ec-calendar-iframe');
            if (iframe && iframe.dataset.src && iframe.getAttribute('src') !== iframe.dataset.src) {
                iframe.setAttribute('src', iframe.dataset.src);
            }
            showStep('results');
        }

        /* --- Reset --- */
        function resetForm() {
            root.querySelectorAll('.ec-step[data-step="2"] input, .ec-step[data-step="2"] select').forEach(function(el) {
                if (el.type === 'checkbox') el.checked = false;
                else if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else if (el.type === 'number') el.value = '0';
            });
            root.querySelectorAll('.ec-step[data-step="contact"] input').forEach(function(el) {
                if (el.type === 'checkbox') el.checked = false;
                else el.value = '';
            });
            if (submitBtn) submitBtn.disabled = false;
            selectedService = '';
            hideError();
        }

        return {
            reset: resetForm,
            showStep: showStep,
            getConfig: function() { return cfg; }
        };
    }

    /* ============================================================== */
    /*  Public API                                                     */
    /* ============================================================== */
    /**
     * Normalise a wpRestUrl by stripping trailing slash.
     */
    function trimSlash(s) { return (s || '').replace(/\/+$/, ''); }

    /**
     * Render the widget once the final config is ready. Returns the instance.
     */
    function bootstrap(cfg) {
        // If WP REST mode is configured, set the submit endpoint automatically
        if (cfg.wpRestUrl && cfg.siteToken && !cfg.endpoint) {
            cfg.endpoint = trimSlash(cfg.wpRestUrl) + '/submit';
        }
        return createWidget(cfg);
    }

    /**
     * Fetch config from /wp-json/ec/v1/config and merge into the runtime config.
     * Server-supplied values overwrite defaults, but the user's userConfig
     * still wins over the server (so per-site overrides like custom colors stick).
     */
    function fetchAndInit(userConfig, EC) {
        var url = trimSlash(userConfig.wpRestUrl) + '/config';

        console.log('[EstimateCalculator] Fetching config from:', url);
        console.log('[EstimateCalculator] Current page origin:', window.location.origin);
        console.log('[EstimateCalculator] Using site token (first 8 chars):', (userConfig.siteToken || '').slice(0, 8) + '…');

        return fetch(url, {
            method: 'GET',
            headers: {
                'X-EC-Site-Token': userConfig.siteToken,
                'Accept':          'application/json'
            }
        })
        .then(function(res) {
            if (!res.ok) {
                // Try to read the WP error body for a clearer message
                return res.json().catch(function() { return {}; }).then(function(body) {
                    var code = body.code || 'http_' + res.status;
                    var msg  = body.message || ('Config fetch failed: HTTP ' + res.status);
                    var err  = new Error(msg);
                    err.code   = code;
                    err.status = res.status;
                    err.body   = body;
                    throw err;
                });
            }
            return res.json();
        })
        .then(function(remote) {
            console.log('[EstimateCalculator] Config loaded successfully ✓', remote);
            console.log('[EstimateCalculator] remote.sqft:',   remote && remote.sqft);
            console.log('[EstimateCalculator] remote.colors:', remote && remote.colors);

            // When fetchConfig is on, WP is the source of truth — remote wins
            // over inline userConfig. The user's userConfig still wins for
            // CONNECTION/UI-host details (container, REST URL, token, callbacks)
            // and any keys passed via `userOverrides` for explicit local overrides.
            //
            // Merge order:
            //   1. DEFAULTS as baseline
            //   2. userConfig (so placeholder values are there as fallback)
            //   3. remote (overwrites everything controlled by the WP plugin)
            //   4. user's connection-only fields (always preserved)
            //   5. userConfig.userOverrides (intentional local overrides)
            var cfg = deepMerge(JSON.parse(JSON.stringify(DEFAULTS)), userConfig);
            cfg = deepMerge(cfg, remote || {});

            // Always keep user's connection/host settings
            var connectionKeys = [
                'container', 'wpRestUrl', 'siteToken', 'fetchConfig',
                'endpoint', 'nonce', 'action',
                'onSubmit', 'onResults', 'onError'
            ];
            connectionKeys.forEach(function(k) {
                if (userConfig[k] !== undefined) cfg[k] = userConfig[k];
            });

            // Intentional local overrides win over remote
            if (userConfig.userOverrides) {
                cfg = deepMerge(cfg, userConfig.userOverrides);
            }

            // Verify final merged values
            console.log('[EstimateCalculator] FINAL cfg.sqft:',   cfg.sqft);
            console.log('[EstimateCalculator] FINAL cfg.colors:', cfg.colors);
            console.log('[EstimateCalculator] FINAL cfg.pricing.exterior.base:', cfg.pricing && cfg.pricing.exterior && cfg.pricing.exterior.base);

            var instance = bootstrap(cfg);
            if (instance) EC.instances.push(instance);
            return instance;
        })
        .catch(function(err) {
            console.error('[EstimateCalculator] ✗ Config fetch failed.');
            console.error('  Error:', err);

            if (err && err.status === 401) {
                console.error('  → 401 Unauthorized: site token is missing or empty.');
            } else if (err && err.code === 'rest_disabled') {
                console.error('  → REST API is DISABLED in WP. Go to Estimate Calc → External Sites → check "Enable REST endpoints" → Save.');
            } else if (err && err.code === 'rest_bad_token') {
                console.error('  → The siteToken in your config does not match any enabled token in WP admin.');
                console.error('     Check Estimate Calc → External Sites in WP — token must be present AND its "Enabled" checkbox ticked.');
            } else if (err && err.code === 'rest_origin_blocked') {
                console.error('  → Your origin "' + window.location.origin + '" is not in the token\'s allowed origins list.');
                console.error('     Add it in WP admin → Estimate Calc → External Sites → your token row → Allowed Origins.');
            } else if (err && err.message && /Failed to fetch|NetworkError|CORS/i.test(err.message)) {
                console.error('  → Network or CORS error. Likely causes:');
                console.error('     1) Your origin "' + window.location.origin + '" is not in the allowed origins for this token.');
                console.error('     2) The WP site is unreachable from here (check the wpRestUrl).');
                console.error('     3) You are testing from a file:// URL — open this page over http:// or https:// instead.');
                console.error('        Origin "null" (file://) is not allowed by CORS — serve the page from a local web server.');
            }

            // Fall back to user config only so the widget at least renders
            var cfg = deepMerge(JSON.parse(JSON.stringify(DEFAULTS)), userConfig || {});
            var instance = bootstrap(cfg);
            if (instance) EC.instances.push(instance);
            return instance;
        });
    }

    var EstimateCalculator = {
        version: '1.12.0',
        instances: [],

        init: function(userConfig) {
            userConfig = userConfig || {};

            // WordPress REST mode with auto-config fetch
            if (userConfig.fetchConfig && userConfig.wpRestUrl && userConfig.siteToken) {
                return fetchAndInit(userConfig, this);
            }

            var cfg = deepMerge(JSON.parse(JSON.stringify(DEFAULTS)), userConfig);
            var instance = bootstrap(cfg);
            if (instance) this.instances.push(instance);
            return instance;
        },

        /** Expose the calculation engine for standalone use */
        calculate: function(service, data, pricing) {
            if (!Calc[service]) return null;
            return Calc[service](data, pricing);
        }
    };

    // Expose globally
    window.EstimateCalculator = EstimateCalculator;

    // Auto-init if there's a <script data-auto-init> tag
    document.addEventListener('DOMContentLoaded', function() {
        var autoScript = document.querySelector('script[data-ec-auto-init]');
        if (autoScript) {
            try {
                var inline = autoScript.getAttribute('data-ec-config');
                if (inline) EstimateCalculator.init(JSON.parse(inline));
            } catch(e) {
                console.error('[EstimateCalculator] Invalid data-ec-config JSON', e);
            }
        }
    });

})(window, document);
