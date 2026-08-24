(function () {
    'use strict';

    var STORAGE_KEY = 'px_cookie_consent';
    var notice = document.getElementById('cookie-notice');
    var acceptBtn = document.getElementById('cookie-accept');
    var gaId = document.body.dataset.gaId || '';

    function hasConsent() {
        try {
            return localStorage.getItem(STORAGE_KEY) === 'accepted';
        } catch (e) {
            return false;
        }
    }

    function hideNotice() {
        if (notice) {
            notice.classList.add('is-hidden');
            notice.setAttribute('aria-hidden', 'true');
        }
    }

    function loadGa4(measurementId) {
        if (!measurementId) {
            return;
        }

        window.dataLayer = window.dataLayer || [];
        window.gtag = function gtag() {
            window.dataLayer.push(arguments);
        };
        window.gtag('js', new Date());
        window.gtag('config', measurementId, { anonymize_ip: true });

        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(measurementId);
        document.head.appendChild(script);
    }

    function acceptCookies() {
        try {
            localStorage.setItem(STORAGE_KEY, 'accepted');
        } catch (e) {
            /* ignore quota / private mode */
        }

        hideNotice();
        loadGa4(gaId);
    }

    if (hasConsent()) {
        hideNotice();
        loadGa4(gaId);
    } else if (acceptBtn) {
        acceptBtn.addEventListener('click', acceptCookies);
    }

    var embed = document.getElementById('x-timeline-embed');
    var fallback = document.getElementById('x-timeline-fallback');

    function showFallback() {
        if (embed) {
            embed.classList.add('is-hidden');
        }
        if (fallback) {
            fallback.hidden = false;
        }
    }

    if (fallback) {
        fallback.hidden = true;
    }

    window.addEventListener('load', function () {
        window.setTimeout(function () {
            var iframe = embed ? embed.querySelector('iframe') : null;
            if (!iframe) {
                showFallback();
            }
        }, 8000);
    });
})();
