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
        initEngagement();
        initCommentToggles();
        initGalleryLightbox();
    });

    function csrfToken() {
        var m = document.querySelector('meta[name="csrf-token"]');
        return m ? m.getAttribute('content') : '';
    }

    function appUrl(path) {
        var m = document.querySelector('meta[name="app-base"]');
        var base = m ? m.getAttribute('content') : '/';
        return base.replace(/\/$/, '') + '/' + path.replace(/^\//, '');
    }

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
                target.innerHTML = '';
                var files = input.files ? Array.prototype.slice.call(input.files) : [];
                files.forEach(function (file, i) {
                    if (!/^image\//.test(file.type)) { return; }
                    var url = URL.createObjectURL(file);
                    var img = document.createElement('img');
                    img.src = url;
                    img.alt = 'Selected image preview';
                    img.style.cssText = 'height:92px;width:92px;border-radius:10px;object-fit:cover;border:1px solid var(--kl-border)';
                    if (i === 0 && input.multiple) { img.title = 'Cover'; img.style.outline = '2px solid var(--kl-primary)'; }
                    target.appendChild(img);
                });
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
            var id    = btn.getAttribute('data-share-id');
            if (id) { recordShare(id); }
            if (navigator.share) {
                navigator.share({ title: title, url: url }).catch(function () {});
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(function () {
                    var original = btn.innerHTML;
                    btn.textContent = 'Link copied ✓';
                    setTimeout(function () { btn.innerHTML = original; }, 1800);
                });
            }
        });
    }

    function recordShare(newsId) {
        var body = new URLSearchParams();
        body.set('action', 'share');
        body.set('news_id', newsId);
        body.set('channel', 'web');
        body.set('_csrf', csrfToken());
        fetch(appUrl('engage.php'), {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        }).catch(function () {});
    }

    /* ---------------------------------------------------------------------
       Like / Save (AJAX)
       --------------------------------------------------------------------- */
    function initEngagement() {
        var wrap = document.querySelector('[data-engage]');
        if (!wrap) { return; }
        var newsId = wrap.getAttribute('data-engage');

        function post(action, cb) {
            var body = new URLSearchParams();
            body.set('action', action);
            body.set('news_id', newsId);
            body.set('_csrf', csrfToken());
            fetch(appUrl('engage.php'), {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
                body: body
            }).then(function (r) { return r.json(); }).then(cb).catch(function () {});
        }

        var likeBtn = wrap.querySelector('[data-like]');
        if (likeBtn) {
            likeBtn.addEventListener('click', function () {
                post('like', function (res) {
                    if (!res.success) {
                        if (res.data && res.data.login) { window.location.href = appUrl('login.php'); }
                        return;
                    }
                    var liked = res.data.liked;
                    likeBtn.classList.toggle('btn-emerald', liked);
                    likeBtn.classList.toggle('btn-outline-emerald', !liked);
                    likeBtn.setAttribute('aria-pressed', liked ? 'true' : 'false');
                    var lbl = likeBtn.querySelector('[data-like-label]');
                    var cnt = likeBtn.querySelector('[data-like-count]');
                    if (lbl) { lbl.textContent = liked ? 'Liked' : 'Like'; }
                    if (cnt) { cnt.textContent = res.data.count; }
                });
            });
        }

        var saveBtn = wrap.querySelector('[data-save]');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () {
                post('save', function (res) {
                    if (!res.success) {
                        if (res.data && res.data.login) { window.location.href = appUrl('login.php'); }
                        return;
                    }
                    var saved = res.data.saved;
                    saveBtn.classList.toggle('btn-emerald', saved);
                    saveBtn.classList.toggle('btn-outline-emerald', !saved);
                    saveBtn.setAttribute('aria-pressed', saved ? 'true' : 'false');
                    var lbl = saveBtn.querySelector('[data-save-label]');
                    if (lbl) { lbl.textContent = saved ? 'Saved' : 'Save'; }
                });
            });
        }
    }

    /* ---------------------------------------------------------------------
       Comment reply / report toggles
       --------------------------------------------------------------------- */
    function initCommentToggles() {
        document.addEventListener('click', function (ev) {
            var reply = ev.target.closest('[data-reply-toggle]');
            var report = ev.target.closest('[data-report-toggle]');
            if (reply) {
                var f = document.querySelector('[data-reply-form="' + reply.getAttribute('data-reply-toggle') + '"]');
                if (f) { f.classList.toggle('d-none'); var t = f.querySelector('textarea'); if (t) { t.focus(); } }
            } else if (report) {
                var rf = document.querySelector('[data-report-form="' + report.getAttribute('data-report-toggle') + '"]');
                if (rf) { rf.classList.toggle('d-none'); }
            }
        });
    }

    /* ---------------------------------------------------------------------
       Simple gallery lightbox (uses a Bootstrap modal if available)
       --------------------------------------------------------------------- */
    function initGalleryLightbox() {
        var links = document.querySelectorAll('[data-gallery]');
        if (!links.length) { return; }

        var overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;' +
            'align-items:center;justify-content:center;z-index:2000;cursor:zoom-out;padding:1rem';
        var big = document.createElement('img');
        big.style.cssText = 'max-width:96%;max-height:92%;border-radius:8px';
        overlay.appendChild(big);
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function () { overlay.style.display = 'none'; });

        Array.prototype.forEach.call(links, function (a) {
            a.addEventListener('click', function (ev) {
                ev.preventDefault();
                big.src = a.getAttribute('href');
                overlay.style.display = 'flex';
            });
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
