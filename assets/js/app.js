/* =============================================================================
   KhuntaLocal — Front-end behaviour
   Progressive enhancement only: every page works without JS; this adds the
   multi-step form, image preview, share buttons and small niceties.
   ============================================================================= */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initMultiStepForm();
        initImagePreview();
        initShareButtons();
        initAutoDismiss();
    });

    /* ---------------------------------------------------------------------
       Multi-step submit form
       --------------------------------------------------------------------- */
    function initMultiStepForm() {
        var form = document.querySelector('[data-multistep]');
        if (!form) { return; }

        // Enables the stepped experience; without JS all panels show stacked.
        form.classList.add('js-multistep');

        var panels = Array.prototype.slice.call(form.querySelectorAll('.kl-step-panel'));
        var steps  = Array.prototype.slice.call(document.querySelectorAll('.kl-step'));
        var current = 0;

        function show(index) {
            current = Math.max(0, Math.min(index, panels.length - 1));
            panels.forEach(function (p, i) { p.classList.toggle('is-active', i === current); });
            steps.forEach(function (s, i) {
                s.classList.toggle('is-active', i === current);
                s.classList.toggle('is-done', i < current);
            });
            if (current === panels.length - 1) { buildPreview(); }
            window.scrollTo({ top: form.offsetTop - 80, behavior: 'smooth' });
        }

        function validatePanel(panel) {
            var ok = true;
            var fields = panel.querySelectorAll('[required]');
            Array.prototype.forEach.call(fields, function (field) {
                if (!field.value || !String(field.value).trim()) {
                    field.classList.add('is-invalid');
                    ok = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            return ok;
        }

        form.addEventListener('click', function (ev) {
            var next = ev.target.closest('[data-step-next]');
            var prev = ev.target.closest('[data-step-prev]');
            if (next) {
                ev.preventDefault();
                if (validatePanel(panels[current])) { show(current + 1); }
            } else if (prev) {
                ev.preventDefault();
                show(current - 1);
            }
        });

        function buildPreview() {
            var target = form.querySelector('[data-preview]');
            if (!target) { return; }
            var get = function (name) {
                var el = form.querySelector('[name="' + name + '"]');
                if (!el) { return ''; }
                if (el.tagName === 'SELECT' && el.selectedIndex >= 0) {
                    return el.options[el.selectedIndex].text;
                }
                return el.value || '';
            };
            var esc = function (s) {
                var d = document.createElement('div'); d.textContent = s; return d.innerHTML;
            };
            target.innerHTML =
                '<h3 class="kl-card__title mb-2" style="-webkit-line-clamp:3">' + esc(get('title')) + '</h3>' +
                '<div class="d-flex flex-wrap gap-2 mb-3 text-muted-2 small">' +
                    '<span>📂 ' + esc(get('category_id')) + '</span>' +
                    '<span>🌐 ' + esc(get('language_code')) + '</span>' +
                    '<span>📍 ' + esc(get('location_id')) + '</span>' +
                '</div>' +
                '<p class="kl-card__excerpt" style="-webkit-line-clamp:6">' + esc(get('summary') || get('body')) + '</p>';
        }

        show(0);
    }

    /* ---------------------------------------------------------------------
       Image preview for the optional cover upload
       --------------------------------------------------------------------- */
    function initImagePreview() {
        var inputs = document.querySelectorAll('[data-image-preview]');
        Array.prototype.forEach.call(inputs, function (input) {
            input.addEventListener('change', function () {
                var targetSel = input.getAttribute('data-image-preview');
                var target = document.querySelector(targetSel);
                if (!target) { return; }
                var file = input.files && input.files[0];
                if (!file) { target.innerHTML = ''; return; }
                if (!/^image\//.test(file.type)) { return; }
                var url = URL.createObjectURL(file);
                target.innerHTML = '<img src="' + url + '" alt="Selected image preview" ' +
                    'style="max-width:100%;border-radius:12px;max-height:260px;object-fit:cover">';
            });
        });
    }

    /* ---------------------------------------------------------------------
       Share buttons (Web Share API with clipboard fallback)
       --------------------------------------------------------------------- */
    function initShareButtons() {
        document.addEventListener('click', function (ev) {
            var btn = ev.target.closest('[data-share]');
            if (!btn) { return; }
            ev.preventDefault();
            var url   = btn.getAttribute('data-share-url') || window.location.href;
            var title = btn.getAttribute('data-share-title') || document.title;
            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    btn.setAttribute('data-copied', '1');
                    var original = btn.textContent;
                    btn.textContent = 'Link copied ✓';
                    setTimeout(function () { btn.textContent = original; }, 1800);
                });
            }
        });
    }

    /* ---------------------------------------------------------------------
       Auto-dismiss flash messages
       --------------------------------------------------------------------- */
    function initAutoDismiss() {
        var flashes = document.querySelectorAll('[data-autodismiss]');
        Array.prototype.forEach.call(flashes, function (el) {
            setTimeout(function () {
                el.style.transition = 'opacity .4s ease';
                el.style.opacity = '0';
                setTimeout(function () { el.remove(); }, 450);
            }, 5000);
        });
    }
})();
