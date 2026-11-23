/**
 * Consentimiento de cookies de la tienda.
 *
 * - Cookie propia `id_consent`: 180 dias, versionada, Path=/, SameSite=Lax,
 *   Secure en HTTPS y host-only (solo se envia Domain si la configuracion lo
 *   trae expresamente; por defecto esta vacia).
 * - No carga analitica ni crea iframes de terceros sin consentimiento.
 * - Los formularios funcionan sin JavaScript; este script solo mejora el
 *   dialogo y la carga condicional de servicios.
 * - Sin dependencias externas.
 */
(function () {
    'use strict';

    var cfg = window.idcConsentConfig || {};
    var COOKIE_NAME = cfg.cookieName || 'id_consent';
    var COOKIE_VERSION = String(cfg.version || '1');
    var COOKIE_DAYS = parseInt(cfg.days, 10) || 180;
    var COOKIE_DOMAIN = cfg.cookieDomain || '';
    var ANALYTICS_ID = String(cfg.analyticsId || '');
    var ANALYTICS_RE = /^G-[A-Z0-9]{4,20}$/;

    var state = normalizeState(window.idcConsentState || readConsent() || emptyState());
    var banner = null;
    var modal = null;
    var lastFocused = null;
    var analyticsLoaded = false;

    function emptyState() {
        return { decided: false, analytics: false, marketing: false, external: false };
    }

    function normalizeState(raw) {
        if (!raw || !raw.decided) {
            return emptyState();
        }

        return {
            decided: true,
            analytics: !!raw.analytics,
            marketing: !!raw.marketing,
            external: !!raw.external
        };
    }

    function escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function readConsent() {
        try {
            var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + escapeRegExp(COOKIE_NAME) + '=([^;]+)'));
            if (!match) {
                return null;
            }

            var value = decodeURIComponent(match[1]);
            var version = value.match(/(?:^|\|)v:([^|]+)/);
            if (!version || version[1] !== COOKIE_VERSION) {
                return null;
            }

            return {
                decided: true,
                analytics: /(^|\|)a:1(\||$)/.test(value),
                marketing: /(^|\|)m:1(\||$)/.test(value),
                external: /(^|\|)e:1(\||$)/.test(value)
            };
        } catch (error) {
            return null;
        }
    }

    function writeConsent(next) {
        var value = 'a:' + (next.analytics ? '1' : '0') +
            '|m:' + (next.marketing ? '1' : '0') +
            '|e:' + (next.external ? '1' : '0') +
            '|v:' + COOKIE_VERSION;

        var parts = [
            COOKIE_NAME + '=' + encodeURIComponent(value),
            'path=/',
            'max-age=' + (COOKIE_DAYS * 24 * 60 * 60),
            'SameSite=Lax'
        ];

        if (window.location.protocol === 'https:') {
            parts.push('Secure');
        }
        if (COOKIE_DOMAIN) {
            parts.push('domain=' + COOKIE_DOMAIN);
        }

        document.cookie = parts.join('; ');
    }

    function expireCookie(name, path, domain) {
        var parts = [
            name + '=',
            'path=' + (path || '/'),
            'max-age=0',
            'SameSite=Lax'
        ];

        if (window.location.protocol === 'https:') {
            parts.push('Secure');
        }
        if (domain) {
            parts.push('domain=' + domain);
        }

        document.cookie = parts.join('; ');
    }

    function getCookieDomainVariants() {
        var host = window.location.hostname;
        var variants = [host, '.' + host];
        var parts = host.split('.');

        if (parts.length > 2) {
            var base = parts.slice(-2).join('.');
            variants.push(base, '.' + base);
        }

        return variants.filter(function (value, index, array) {
            return value && array.indexOf(value) === index;
        });
    }

    /**
     * Borra solo cookies conocidas de analitica/marketing al retirar permisos.
     * No se toca ninguna cookie antigua del sistema anterior: no existe en esta
     * plataforma. Las cookies de dominios de terceros no se pueden borrar desde
     * el documento propio; por eso, al revocar, se recarga la pagina.
     */
    function clearKnownTrackingCookies() {
        var known = /^(_ga|_gid|_gat|_gcl|_fbp|__utm)/i;
        var names = document.cookie.split(';').map(function (entry) {
            return entry.split('=')[0].replace(/^\s+|\s+$/g, '');
        });
        var domains = getCookieDomainVariants();

        names.forEach(function (name) {
            if (!name || !known.test(name)) {
                return;
            }

            expireCookie(name, '/');
            domains.forEach(function (domain) {
                expireCookie(name, '/', domain);
            });
        });
    }

    function applyBodyClasses() {
        var body = document.body;
        if (!body) {
            return;
        }

        body.classList.toggle('idc-consent-decided', !!state.decided);
        body.classList.toggle('idc-consent-analytics', !!state.analytics);
        body.classList.toggle('idc-consent-marketing', !!state.marketing);
        body.classList.toggle('idc-consent-external', !!state.external);
    }

    function setLiveStatus(message) {
        var live = document.getElementById('idc-live-status');
        if (live) {
            live.textContent = message;
        }
    }

    function showBanner() {
        if (!banner) {
            return;
        }
        banner.hidden = false;
        document.body.classList.add('idc-banner-open');
    }

    function hideBanner() {
        if (!banner) {
            return;
        }
        banner.hidden = true;
        document.body.classList.remove('idc-banner-open');
    }

    function syncForm() {
        var analytics = document.getElementById('idc-consent-analytics');
        var marketing = document.getElementById('idc-consent-marketing');
        var external = document.getElementById('idc-consent-external');

        if (analytics) {
            analytics.checked = !!state.analytics;
        }
        if (marketing) {
            marketing.checked = !!state.marketing;
        }
        if (external) {
            external.checked = !!state.external;
        }
    }

    function focusFallback() {
        var candidates = document.querySelectorAll('[data-idc-open]');
        for (var i = 0; i < candidates.length; i += 1) {
            if (candidates[i].getClientRects().length > 0) {
                candidates[i].focus();
                return;
            }
        }
    }

    function isFocusable(element) {
        return !!element && element.getClientRects().length > 0 &&
            !element.disabled && element.getAttribute('aria-hidden') !== 'true';
    }

    function getFocusable(container) {
        var nodes = container.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])');
        return Array.prototype.filter.call(nodes, isFocusable);
    }

    function openModal(trigger) {
        if (!modal) {
            return;
        }

        lastFocused = trigger || document.activeElement;
        syncForm();
        modal.hidden = false;
        document.body.classList.add('idc-modal-open');

        var dialog = modal.querySelector('.idc-modal__dialog');
        if (dialog) {
            dialog.focus();
        }
    }

    function closeModal(skipFocus) {
        if (!modal || modal.hidden) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('idc-modal-open');

        if (skipFocus) {
            return;
        }

        if (lastFocused && lastFocused.getClientRects().length > 0) {
            lastFocused.focus();
        } else {
            focusFallback();
        }
    }

    function loadAnalytics() {
        if (analyticsLoaded || !state.analytics || !ANALYTICS_ID || !ANALYTICS_RE.test(ANALYTICS_ID)) {
            return;
        }

        analyticsLoaded = true;

        if (typeof window.gtag !== 'function') {
            window.dataLayer = window.dataLayer || [];
            window.gtag = function () {
                window.dataLayer.push(arguments);
            };

            window.gtag('consent', 'default', {
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
                analytics_storage: 'denied',
                functionality_storage: 'granted',
                security_storage: 'granted',
                wait_for_update: 500
            });
        }

        window.gtag('consent', 'update', {
            analytics_storage: 'granted',
            ad_storage: state.marketing ? 'granted' : 'denied',
            ad_user_data: state.marketing ? 'granted' : 'denied',
            ad_personalization: state.marketing ? 'granted' : 'denied'
        });
        window.gtag('js', new Date());

        var script = document.createElement('script');
        script.async = true;
        script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(ANALYTICS_ID);
        document.head.appendChild(script);

        window.gtag('config', ANALYTICS_ID);
    }

    function safeExternalUrl(value) {
        if (!value) {
            return '';
        }

        try {
            var parsed = new URL(value, window.location.href);
            return parsed.protocol === 'https:' ? parsed.href : '';
        } catch (error) {
            return '';
        }
    }

    function renderEmbed(container) {
        if (!container || container.getAttribute('data-idc-embed-loaded') === '1') {
            return;
        }

        var src = safeExternalUrl(container.getAttribute('data-idc-src'));
        if (!src) {
            return;
        }

        var iframe = document.createElement('iframe');
        iframe.src = src;
        iframe.title = container.getAttribute('data-idc-title') || 'Contenido externo';
        iframe.loading = 'lazy';
        iframe.referrerPolicy = 'strict-origin-when-cross-origin';
        iframe.allowFullscreen = true;

        if (container.getAttribute('data-idc-allow')) {
            iframe.setAttribute('allow', container.getAttribute('data-idc-allow'));
        }
        if (container.getAttribute('data-idc-iframe-class')) {
            iframe.className = container.getAttribute('data-idc-iframe-class');
        }
        if (container.getAttribute('data-idc-iframe-id')) {
            iframe.id = container.getAttribute('data-idc-iframe-id');
        }

        container.innerHTML = '';
        container.appendChild(iframe);
        container.setAttribute('data-idc-embed-loaded', '1');
    }

    function renderEmbeds(force) {
        var nodes = document.querySelectorAll('[data-idc-embed]');
        Array.prototype.forEach.call(nodes, function (container) {
            var category = container.getAttribute('data-idc-embed') || 'external';
            if (force || state[category]) {
                renderEmbed(container);
            }
        });
    }

    function save(next) {
        var previous = normalizeState(state);
        var updated = normalizeState({
            decided: true,
            analytics: !!next.analytics,
            marketing: !!next.marketing,
            external: !!next.external
        });

        state = updated;
        window.idcConsentState = updated;

        writeConsent(updated);
        applyBodyClasses();
        syncForm();

        var revoked = previous.decided && (
            (previous.analytics && !updated.analytics) ||
            (previous.marketing && !updated.marketing) ||
            (previous.external && !updated.external)
        );

        closeModal(true);
        hideBanner();

        if (revoked) {
            clearKnownTrackingCookies();
            setLiveStatus('Preferencias guardadas. Recargando la pagina para detener los servicios retirados.');
            window.location.reload();
            return;
        }

        if (updated.analytics) {
            loadAnalytics();
        }
        if (updated.external) {
            renderEmbeds();
        }

        setLiveStatus('Preferencias de cookies guardadas.');
        focusFallback();
    }

    function bindEvents() {
        document.addEventListener('click', function (event) {
            var target = event.target;

            var embedButton = target.closest ? target.closest('[data-idc-embed-load]') : null;
            if (embedButton) {
                event.preventDefault();
                var embed = embedButton.closest('[data-idc-embed]');
                if (embed) {
                    renderEmbed(embed);
                }
                return;
            }

            var openTrigger = target.closest ? target.closest('[data-idc-open]') : null;
            if (openTrigger) {
                event.preventDefault();
                openModal(openTrigger);
                return;
            }

            var closeTrigger = target.closest ? target.closest('[data-idc-close]') : null;
            if (closeTrigger) {
                event.preventDefault();
                closeModal(false);
                return;
            }

            var actionButton = target.closest ? target.closest('[data-idc-action]') : null;
            if (!actionButton) {
                return;
            }

            var action = actionButton.getAttribute('data-idc-action');
            event.preventDefault();

            if (action === 'accept') {
                save({ analytics: true, marketing: true, external: true });
            } else if (action === 'reject') {
                save({ analytics: false, marketing: false, external: false });
            } else if (action === 'configure') {
                openModal(actionButton);
            } else if (action === 'save') {
                save({
                    analytics: !!(document.getElementById('idc-consent-analytics') || {}).checked,
                    marketing: !!(document.getElementById('idc-consent-marketing') || {}).checked,
                    external: !!(document.getElementById('idc-consent-external') || {}).checked
                });
            }
        });

        document.addEventListener('keydown', function (event) {
            if (!modal || modal.hidden) {
                return;
            }

            if (event.key === 'Escape' || event.keyCode === 27) {
                event.preventDefault();
                closeModal(false);
                return;
            }

            if (event.key !== 'Tab' && event.keyCode !== 9) {
                return;
            }

            var dialog = modal.querySelector('.idc-modal__dialog');
            var focusable = getFocusable(dialog);
            if (!focusable.length) {
                return;
            }

            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            var active = document.activeElement;
            var insideDialog = dialog && dialog.contains(active);

            if (event.shiftKey) {
                if (active === first || active === dialog || !insideDialog) {
                    event.preventDefault();
                    last.focus();
                }
            } else if (active === last || !insideDialog) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    function init() {
        banner = document.getElementById('idc-cookie-banner');
        modal = document.getElementById('idc-cookie-modal');

        applyBodyClasses();
        syncForm();
        bindEvents();

        if (state.decided) {
            hideBanner();
        } else {
            showBanner();
        }

        if (state.analytics) {
            loadAnalytics();
        }
        if (state.external) {
            renderEmbeds();
        }
    }

    window.IDConsent = {
        has: function (category) {
            return !!state[category];
        },
        get: function () {
            return {
                decided: !!state.decided,
                analytics: !!state.analytics,
                marketing: !!state.marketing,
                external: !!state.external
            };
        },
        open: function () {
            openModal(document.activeElement);
        },
        close: function () {
            closeModal(false);
        },
        save: save,
        acceptAll: function () {
            save({ analytics: true, marketing: true, external: true });
        },
        rejectAll: function () {
            save({ analytics: false, marketing: false, external: false });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
