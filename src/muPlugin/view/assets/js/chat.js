/**
 * Preview pane + chat sidebar chrome.
 *
 * Address bar / iframe live here. Chat transcript + send belong to
 * agent.bundle.js — do not bind those controls again.
 */
(function () {
    var urlInput  = document.getElementById('pi-browser-url');
    var goBtn     = document.getElementById('pi-browser-go');
    var reloadBtn = document.getElementById('pi-browser-reload');
    var iframe    = document.getElementById('pi-chat-iframe');
    var layout    = document.getElementById('pi-chat-app');
    var sidebar   = document.getElementById('pi-chat-sidebar');
    var resizer   = document.getElementById('pi-chat-resizer');

    var WIDTH_KEY  = 'pi-chat-sidebar-width';
    var HEIGHT_KEY = 'pi-chat-sidebar-height';
    var DEFAULT_WIDTH = 420;
    var MIN_WIDTH = 280;
    var MIN_BROWSER = 280;
    var MIN_HEIGHT_PCT = 30;
    var MAX_HEIGHT_PCT = 75;

    function isStacked() {
        return window.matchMedia('(max-width: 700px)').matches;
    }

    function clamp(value, min, max) {
        return Math.min(max, Math.max(min, value));
    }

    function applyWidth(px) {
        if (!layout || !sidebar) {
            return;
        }
        var max = Math.max(MIN_WIDTH, window.innerWidth - MIN_BROWSER);
        var width = clamp(Math.round(px), MIN_WIDTH, Math.min(Math.floor(window.innerWidth * 0.7), max));
        layout.style.setProperty('--pi-sidebar-width', width + 'px');
        sidebar.style.flexBasis = width + 'px';
        sidebar.style.width = width + 'px';
        sidebar.style.height = '';
        return width;
    }

    function applyHeightPct(pct) {
        if (!layout || !sidebar) {
            return;
        }
        var height = clamp(Math.round(pct * 10) / 10, MIN_HEIGHT_PCT, MAX_HEIGHT_PCT);
        layout.style.setProperty('--pi-sidebar-height', height + '%');
        sidebar.style.flexBasis = height + '%';
        sidebar.style.height = height + '%';
        sidebar.style.width = '100%';
        return height;
    }

    function restoreSize() {
        if (!layout || !sidebar) {
            return;
        }

        if (isStacked()) {
            var savedH = parseFloat(localStorage.getItem(HEIGHT_KEY) || '');
            applyHeightPct(isFinite(savedH) ? savedH : 55);
            return;
        }

        var savedW = parseFloat(localStorage.getItem(WIDTH_KEY) || '');
        applyWidth(isFinite(savedW) ? savedW : DEFAULT_WIDTH);
    }

    function bindResizer() {
        if (!layout || !sidebar || !resizer) {
            return;
        }

        var dragging = false;

        function onPointerMove(event) {
            if (!dragging) {
                return;
            }

            if (isStacked()) {
                var pct = ((window.innerHeight - event.clientY) / window.innerHeight) * 100;
                applyHeightPct(pct);
            } else {
                var width = window.innerWidth - event.clientX;
                applyWidth(width);
            }
        }

        function stopDrag() {
            if (!dragging) {
                return;
            }
            dragging = false;
            layout.classList.remove('is-resizing');

            if (isStacked()) {
                var h = parseFloat(getComputedStyle(layout).getPropertyValue('--pi-sidebar-height'));
                if (isFinite(h)) {
                    localStorage.setItem(HEIGHT_KEY, String(h));
                }
            } else {
                var w = parseFloat(getComputedStyle(layout).getPropertyValue('--pi-sidebar-width'));
                if (isFinite(w)) {
                    localStorage.setItem(WIDTH_KEY, String(w));
                }
            }

            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('pointerup', stopDrag);
            window.removeEventListener('pointercancel', stopDrag);
        }

        resizer.addEventListener('pointerdown', function (event) {
            if (event.button !== undefined && event.button !== 0) {
                return;
            }
            event.preventDefault();
            dragging = true;
            layout.classList.add('is-resizing');
            try {
                resizer.setPointerCapture(event.pointerId);
            } catch (e) {
                // Older browsers may not support capture; window listeners still work.
            }
            window.addEventListener('pointermove', onPointerMove);
            window.addEventListener('pointerup', stopDrag);
            window.addEventListener('pointercancel', stopDrag);
        });

        resizer.addEventListener('keydown', function (event) {
            var step = event.shiftKey ? 40 : 16;
            if (isStacked()) {
                var currentH = parseFloat(getComputedStyle(layout).getPropertyValue('--pi-sidebar-height')) || 55;
                if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    localStorage.setItem(HEIGHT_KEY, String(applyHeightPct(currentH + 2)));
                } else if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    localStorage.setItem(HEIGHT_KEY, String(applyHeightPct(currentH - 2)));
                }
                return;
            }

            var currentW = parseFloat(getComputedStyle(layout).getPropertyValue('--pi-sidebar-width')) || DEFAULT_WIDTH;
            if (event.key === 'ArrowLeft') {
                event.preventDefault();
                localStorage.setItem(WIDTH_KEY, String(applyWidth(currentW + step)));
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                localStorage.setItem(WIDTH_KEY, String(applyWidth(currentW - step)));
            }
        });

        window.addEventListener('resize', restoreSize);
        restoreSize();
    }

    bindResizer();

    if (!iframe || !urlInput) {
        return;
    }

    function normalizeUrl(value) {
        value = (value || '').trim();
        if (!value) {
            return '';
        }
        if (/^https?:\/\//i.test(value)) {
            return value;
        }
        if (value.charAt(0) === '/') {
            return window.location.origin + value;
        }
        return 'https://' + value;
    }

    function isBrowserBusy() {
        return !!(layout && layout.querySelector('.pi-chat-browser.is-busy'));
    }

    function navigate() {
        if (isBrowserBusy()) {
            return;
        }
        var target = normalizeUrl(urlInput.value);
        if (!target) {
            return;
        }
        urlInput.value = target;
        iframe.src = target;
    }

    if (goBtn) {
        goBtn.addEventListener('click', navigate);
    }

    urlInput.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            navigate();
        }
    });

    if (reloadBtn) {
        reloadBtn.addEventListener('click', function () {
            if (isBrowserBusy()) {
                return;
            }
            // Re-assigning .src re-fetches even when the URL is unchanged,
            // which is the point after the agent has edited plugin files.
            iframe.src = iframe.src;
        });
    }

    iframe.addEventListener('load', function () {
        try {
            var href = iframe.contentWindow.location.href;
            if (href && href !== 'about:blank') {
                urlInput.value = href;
            }
        } catch (e) {
            // Cross-origin iframe — location isn't readable, keep the
            // address bar as the user last set it.
        }
    });
})();
