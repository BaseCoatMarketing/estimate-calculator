(function() {
    'use strict';

    var root       = document.getElementById('ec-calculator');
    if (!root) return;

    var progress   = document.getElementById('ec-progress-bar');
    var errorEl    = document.getElementById('ec-error');
    var loadingEl  = document.getElementById('ec-loading');
    var submitBtn  = document.getElementById('ec-submit-btn');

    var selectedService = '';

    /* ============================================================== */
    /*  UTM passthrough                                                */
    /* ============================================================== */
    function readCookie(name) {
        try {
            var m = ('; ' + document.cookie).split('; ' + name + '=');
            if (m.length === 2) return decodeURIComponent(m.pop().split(';').shift());
        } catch (e) {}
        return '';
    }

    function captureTracking() {
        var params = new URLSearchParams(window.location.search);
        var fields = [
            'utm_source','utm_medium','utm_campaign','utm_term','utm_content',
            'fbclid','gclid','gbraid','wbraid','msclkid','ttclid','li_fat_id',
            'lp_variant'
        ];
        fields.forEach(function(key) {
            var val = params.get(key);
            if (val) {
                var el = root.querySelector('input[name="' + key + '"]');
                if (el) el.value = val;
            }
        });

        // Meta cookies (set by the browser pixel when active)
        var fbp = readCookie('_fbp');
        var fbc = readCookie('_fbc');
        var fbpEl = root.querySelector('input[name="fbp"]');
        var fbcEl = root.querySelector('input[name="fbc"]');
        if (fbpEl && fbp) fbpEl.value = fbp;
        if (fbcEl) {
            if (fbc) fbcEl.value = fbc;
            else if (params.get('fbclid')) fbcEl.value = 'fb.1.' + Date.now() + '.' + params.get('fbclid');
        }

        var refEl  = root.querySelector('input[name="referrer"]');
        var landEl = root.querySelector('input[name="landing_page_url"]');
        if (refEl  && document.referrer) refEl.value  = document.referrer;
        if (landEl) landEl.value = window.location.href;
    }
    captureTracking();

    /* ============================================================== */
    /*  Step navigation                                                */
    /* ============================================================== */
    function showStep(stepId, service) {
        var steps = root.querySelectorAll('.ec-step');
        for (var i = 0; i < steps.length; i++) {
            steps[i].classList.remove('active');
        }

        var target;
        if (stepId === 'results') {
            target = root.querySelector('.ec-step[data-step="results"]');
            setProgress(100);
        } else if (stepId === 'contact') {
            target = root.querySelector('.ec-step[data-step="contact"]');
            setProgress(75);
        } else if (stepId === '2' || stepId === 2) {
            var svc = service || selectedService;
            target = root.querySelector('.ec-step[data-step="2"][data-service="' + svc + '"]');
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

    function setProgress(pct) {
        if (progress) progress.style.width = pct + '%';
    }

    /* ============================================================== */
    /*  Service selection                                               */
    /* ============================================================== */
    var cards = root.querySelectorAll('.ec-service-card');
    for (var c = 0; c < cards.length; c++) {
        cards[c].addEventListener('click', function() {
            selectedService = this.getAttribute('data-service');
            showStep(2, selectedService);
        });
    }

    /* ============================================================== */
    /*  Next / Back buttons                                            */
    /* ============================================================== */
    root.addEventListener('click', function(e) {
        var btn = e.target.closest('.ec-btn-next');
        if (btn) {
            var goto = btn.getAttribute('data-goto');
            // Validate current step
            if (!validateCurrentStep(btn)) return;
            showStep(goto);
            return;
        }

        btn = e.target.closest('.ec-btn-back');
        if (btn) {
            var gotoBack = btn.getAttribute('data-goto');
            // If going back to step 1 from results, reset the form
            var fromStep = btn.closest('.ec-step');
            if (fromStep && fromStep.getAttribute('data-step') === 'results') {
                resetForm();
            }
            showStep(gotoBack);
        }
    });

    /* ============================================================== */
    /*  Validation                                                     */
    /* ============================================================== */
    function validateCurrentStep(btn) {
        var step = btn.closest('.ec-step');
        var fields = step.querySelectorAll('[required]');
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            var val = (f.value || '').toString().trim();
            var invalid = !val;
            if (f.tagName === 'INPUT' && f.type === 'number') {
                // Required number must have an explicit numeric entry (>=0)
                invalid = (val === '' || isNaN(Number(val)));
            }
            if (invalid) {
                f.focus();
                f.style.borderColor = '#e74c3c';
                var msg = f.getAttribute('data-required-msg') || 'This field is required.';
                showError(msg);
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
        var zip   = step.querySelector('input[name="zip_code"]'); // only exists when enabled
        var terms = step.querySelector('input[name="terms"]');

        var errors = [];
        if (!name.value.trim()) errors.push('Full name is required.');
        if (!email.value.trim() || !email.value.includes('@')) errors.push('Valid email is required.');
        if (!phone.value.trim()) errors.push('Phone number is required.');
        if (zip && !zip.value.trim()) errors.push('ZIP code is required.');
        if (!terms.checked) errors.push('Consent is required to move forward.');

        if (errors.length > 0) {
            showError(errors.join(' '));
            return false;
        }
        hideError();
        return true;
    }

    function showError(msg) {
        if (errorEl) {
            errorEl.textContent = msg;
            errorEl.style.display = 'block';
        }
    }
    function hideError() {
        if (errorEl) errorEl.style.display = 'none';
    }

    /* ============================================================== */
    /*  Gather form data                                               */
    /* ============================================================== */
    function gatherData() {
        var data = { service: selectedService };

        // Service-specific fields
        var serviceStep = root.querySelector('.ec-step[data-step="2"][data-service="' + selectedService + '"]');
        if (serviceStep) {
            var inputs = serviceStep.querySelectorAll('input, select');
            for (var i = 0; i < inputs.length; i++) {
                var el = inputs[i];
                if (!el.name) continue;
                if (el.type === 'checkbox') {
                    data[el.name] = el.checked ? '1' : '';
                } else {
                    data[el.name] = el.value;
                }
            }
        }

        // Contact fields
        var contactStep = root.querySelector('.ec-step[data-step="contact"]');
        if (contactStep) {
            var cInputs = contactStep.querySelectorAll('input');
            for (var j = 0; j < cInputs.length; j++) {
                var cEl = cInputs[j];
                if (!cEl.name || cEl.type === 'checkbox') continue;
                data[cEl.name] = cEl.value;
            }
        }

        return data;
    }

    /* ============================================================== */
    /*  Submit                                                         */
    /* ============================================================== */
    if (submitBtn) {
        submitBtn.addEventListener('click', function() {
            if (!validateContact()) return;

            submitBtn.disabled = true;
            if (loadingEl) loadingEl.style.display = 'flex';
            hideError();

            var data = gatherData();
            data.action = 'ec_submit';
            data.nonce  = ecData.nonce;

            // Build form body
            var body = new URLSearchParams();
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    body.append(key, data[key]);
                }
            }

            fetch(ecData.ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function(res) { return res.json(); })
            .then(function(json) {
                if (loadingEl) loadingEl.style.display = 'none';

                if (json.success && json.data && json.data.estimate) {
                    // Fire pixel
                    firePixel();

                    // Inject Google Online Estimate schema
                    if (json.data.schema) {
                        injectOfferSchema(json.data.schema);
                    }

                    // Populate inline results
                    showResults(json.data.estimate, data.service);
                } else {
                    showError(json.data && json.data.message ? json.data.message : 'Something went wrong. Please try again.');
                    submitBtn.disabled = false;
                }
            })
            .catch(function() {
                showError('Network error. Please try again.');
                submitBtn.disabled = false;
                if (loadingEl) loadingEl.style.display = 'none';
            });
        });
    }

    /* ============================================================== */
    /*  Show inline results                                            */
    /* ============================================================== */
    function showResults(estimate, service) {
        // Format currency
        function fmt(n) {
            return '$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 0 });
        }

        // Service label from ecData.labels (kept in original/title case)
        var serviceLabels = ecData.labels || {};
        var label = serviceLabels[service] || service;
        var inlineEl = document.getElementById('ec-result-service-inline');
        var lowEl    = document.getElementById('ec-result-low');
        var highEl   = document.getElementById('ec-result-high');

        if (inlineEl) inlineEl.textContent = label;
        if (lowEl)    lowEl.textContent = fmt(estimate.low);
        if (highEl)   highEl.textContent = fmt(estimate.high);

        // Load calendar iframe (lazy — only loads when results are shown)
        var iframe = document.getElementById('ec-calendar-iframe');
        if (iframe && iframe.dataset.src && iframe.getAttribute('src') !== iframe.dataset.src) {
            iframe.setAttribute('src', iframe.dataset.src);
        }

        // Show results step
        showStep('results');
    }

    /* ============================================================== */
    /*  Reset form for new estimate                                    */
    /* ============================================================== */
    function resetForm() {
        // Reset all inputs in service steps
        var serviceSteps = root.querySelectorAll('.ec-step[data-step="2"]');
        for (var i = 0; i < serviceSteps.length; i++) {
            var inputs = serviceSteps[i].querySelectorAll('input, select');
            for (var j = 0; j < inputs.length; j++) {
                if (inputs[j].type === 'checkbox') {
                    inputs[j].checked = false;
                } else if (inputs[j].tagName === 'SELECT') {
                    inputs[j].selectedIndex = 0;
                } else if (inputs[j].type === 'number') {
                    inputs[j].value = '0';
                }
            }
        }
        // Reset contact fields
        var contactStep = root.querySelector('.ec-step[data-step="contact"]');
        if (contactStep) {
            var cInputs = contactStep.querySelectorAll('input');
            for (var k = 0; k < cInputs.length; k++) {
                if (cInputs[k].type === 'checkbox') {
                    cInputs[k].checked = false;
                } else if (cInputs[k].type !== 'hidden') {
                    cInputs[k].value = '';
                }
            }
        }
        // Re-enable submit button
        if (submitBtn) submitBtn.disabled = false;
        selectedService = '';
        hideError();
    }

    /* ============================================================== */
    /*  Inject Offer JSON-LD (Google Online Estimate)                  */
    /* ============================================================== */
    function injectOfferSchema(schema) {
        try {
            // Remove previous dynamic offer schema if present
            var existing = document.getElementById('ec-offer-schema');
            if (existing) existing.remove();

            var s = document.createElement('script');
            s.type = 'application/ld+json';
            s.id   = 'ec-offer-schema';
            s.textContent = JSON.stringify(schema);
            document.head.appendChild(s);
        } catch (e) { /* swallow */ }
    }

    /* ============================================================== */
    /*  Pixel firing                                                   */
    /* ============================================================== */
    function firePixel() {
        var type = ecData.pixelType || '';
        var id   = ecData.pixel || '';

        if (!type || type === 'none' || !id) return;

        switch (type) {
            case 'facebook':
                if (typeof fbq === 'function') fbq('track', 'Lead');
                break;
            case 'google':
                if (typeof gtag === 'function') gtag('event', 'conversion', { 'send_to': id });
                break;
            case 'tiktok':
                if (typeof ttq !== 'undefined') ttq.track('SubmitForm');
                break;
        }
    }

})();
