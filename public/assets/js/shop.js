/**
 * Storefront: slider de portada, modo claro/oscuro, filtros en movil y ficha.
 *
 * Sin dependencias y sin build. Todo esta escrito de forma defensiva: si un
 * componente no esta en la pagina, su modulo no hace nada. Si el JS falla o
 * esta desactivado, la web sigue siendo navegable (los filtros son enlaces,
 * las diapositivas se ven una detras de otra y el modo lo decide la tienda).
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* =====================================================================
       SLIDER DE PORTADA
       ===================================================================== */
    var slider = document.querySelector('[data-slider]');
    if (slider) {
        initSlider(slider);
    }

    function initSlider(root) {
        var stage = root.querySelector('[data-slider-stage]');
        var slides = Array.prototype.slice.call(root.querySelectorAll('[data-slider-slide]'));
        var dots = Array.prototype.slice.call(root.querySelectorAll('[data-slider-dot]'));
        var progress = root.querySelector('.hero-controls');

        if (!stage || slides.length === 0) {
            return;
        }

        var index = 0;
        var timer = null;
        var rafId = null;
        var startedAt = 0;
        var duration = 6500; // ms por diapositiva
        var paused = false;

        function render() {
            slides.forEach(function (slide, i) {
                var active = i === index;
                slide.classList.toggle('is-active', active);
                if (active) {
                    slide.removeAttribute('hidden');
                } else {
                    slide.setAttribute('hidden', '');
                }
            });
            dots.forEach(function (dot, i) {
                var active = i === index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-selected', active ? 'true' : 'false');
            });
        }

        function goTo(next, userAction) {
            var total = slides.length;
            index = ((next % total) + total) % total;
            render();
            restart(userAction);
        }

        function next(step) {
            goTo(index + step, true);
        }

        /* ---------- rotacion automatica ---------- */
        function tick() {
            if (paused) {
                return;
            }
            var elapsed = Date.now() - startedAt;
            var ratio = Math.min(1, elapsed / duration);
            if (progress) {
                progress.style.setProperty('--progress', (ratio * 100).toFixed(1) + '%');
            }
            if (ratio >= 1) {
                startedAt = Date.now();
                goTo(index + 1);
                return;
            }
            rafId = window.requestAnimationFrame(tick);
        }

        function restart() {
            if (rafId) {
                window.cancelAnimationFrame(rafId);
                rafId = null;
            }
            startedAt = Date.now();
            if (progress) {
                progress.style.setProperty('--progress', '0%');
            }
            if (!reduceMotion && slides.length > 1) {
                rafId = window.requestAnimationFrame(tick);
            }
        }

        function pause() {
            paused = true;
            if (rafId) {
                window.cancelAnimationFrame(rafId);
                rafId = null;
            }
        }

        function resume() {
            if (!paused) {
                return;
            }
            paused = false;
            restart();
        }

        /* ---------- controles ---------- */
        dots.forEach(function (dot, i) {
            dot.addEventListener('click', function () { goTo(i, true); });
        });

        var prevBtn = root.querySelector('[data-slider-prev]');
        var nextBtn = root.querySelector('[data-slider-next]');
        if (prevBtn) {
            prevBtn.addEventListener('click', function () { next(-1); });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function () { next(1); });
        }

        // Flechas del teclado cuando el foco esta dentro del slider.
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { e.preventDefault(); next(-1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); next(1); }
        });

        // Se pausa al interactuar (raton o teclado) y al cambiar de pestana.
        root.addEventListener('mouseenter', pause);
        root.addEventListener('mouseleave', resume);
        root.addEventListener('focusin', pause);
        root.addEventListener('focusout', resume);
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) { pause(); } else { resume(); }
        });

        /* ---------- deslizar con el dedo ---------- */
        var touchX = null;
        var touchY = null;
        stage.addEventListener('touchstart', function (e) {
            if (e.touches.length !== 1) { return; }
            touchX = e.touches[0].clientX;
            touchY = e.touches[0].clientY;
            pause();
        }, { passive: true });

        stage.addEventListener('touchend', function (e) {
            if (touchX === null) { return; }
            var t = e.changedTouches[0];
            var dx = t.clientX - touchX;
            var dy = t.clientY - touchY;
            touchX = null;
            touchY = null;
            if (Math.abs(dx) > 45 && Math.abs(dx) > Math.abs(dy)) {
                next(dx < 0 ? 1 : -1);
            }
            resume();
        }, { passive: true });

        render();
        restart();
    }

    /* =====================================================================
       MODO CLARO / OSCURO
       ===================================================================== */
    var schemeToggle = document.querySelector('[data-scheme-toggle]');
    if (schemeToggle) {
        initSchemeToggle(schemeToggle);
    }

    function initSchemeToggle(button) {
        var root = document.documentElement;
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function current() {
            var scheme = root.getAttribute('data-color-scheme') || 'light';
            if (scheme === 'auto') {
                return media.matches ? 'dark' : 'light';
            }
            return scheme === 'dark' ? 'dark' : 'light';
        }

        function paint() {
            button.setAttribute('aria-pressed', current() === 'dark' ? 'true' : 'false');
        }

        button.addEventListener('click', function () {
            var next = current() === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-color-scheme', next);
            try {
                window.localStorage.setItem('tienda.color-scheme', next);
            } catch (e) { /* Modo privado: la eleccion solo dura la sesion. */ }
            paint();
        });

        media.addEventListener('change', paint);
        paint();
    }

    /* =====================================================================
       FILTROS EN MOVIL (panel lateral)
       ===================================================================== */
    var filtersToggle = document.querySelector('[data-filters-toggle]');
    var catalogSection = document.querySelector('.catalog-section');

    if (filtersToggle && catalogSection) {
        initFiltersDrawer(filtersToggle, catalogSection);
    }

    function initFiltersDrawer(toggle, section) {
        var close = section.querySelector('[data-filters-close]');
        var aside = section.querySelector('.catalog-aside');

        // El boton solo tiene sentido con JS: se muestra y se pasa a modo panel.
        toggle.removeAttribute('hidden');
        section.classList.add('is-drawer');

        function open() {
            section.classList.add('is-filters-open');
            document.body.classList.add('no-scroll');
            toggle.setAttribute('aria-expanded', 'true');
            if (close) { close.focus(); }
        }

        function shut() {
            section.classList.remove('is-filters-open');
            document.body.classList.remove('no-scroll');
            toggle.setAttribute('aria-expanded', 'false');
        }

        toggle.setAttribute('aria-expanded', 'false');
        toggle.addEventListener('click', function () {
            if (section.classList.contains('is-filters-open')) { shut(); } else { open(); }
        });

        if (close) {
            close.addEventListener('click', shut);
        }

        // Clic en el fondo oscuro (el ::before del aside): cierra el panel.
        if (aside) {
            aside.addEventListener('click', function (e) {
                if (e.target === aside && section.classList.contains('is-filters-open')) {
                    shut();
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && section.classList.contains('is-filters-open')) {
                shut();
                toggle.focus();
            }
        });

        // Al pasar a escritorio el panel pierde sentido: se limpia el estado.
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1100) { shut(); }
        });
    }

    /* =====================================================================
       FORMULARIO DE FILTROS
       ===================================================================== */
    var filtersForm = document.querySelector('[data-filters-form]');
    if (filtersForm) {
        var catSelect = filtersForm.querySelector('select[name="cat"]');
        var subcatInput = filtersForm.querySelector('input[name="subcat"]');

        // Cambiar de categoria invalida la subcategoria elegida: si no, al
        // enviar el formulario viajarian las dos y mandaria la antigua.
        if (catSelect && subcatInput) {
            catSelect.addEventListener('change', function () {
                subcatInput.value = '';
            });
        }
    }

    /* =====================================================================
       ORDEN DEL LISTADO
       ===================================================================== */
    var sortSelect = document.querySelector('[data-sort-select]');
    if (sortSelect) {
        sortSelect.addEventListener('change', function () {
            if (sortSelect.value) {
                window.location.href = sortSelect.value;
            }
        });
    }

    /* =====================================================================
       GALERIA DE PRODUCTO
       ===================================================================== */
    var gallery = document.getElementById('gallery');
    if (gallery) {
        initGallery(gallery);
    }

    function initGallery(root) {
        var stage = document.getElementById('gallery-stage');
        var rail = document.getElementById('gallery-track');
        var thumbsList = document.getElementById('gallery-thumbs');

        // Se trabaja siempre con los elementos que siguen vivos
        var slides = rail ? Array.prototype.slice.call(rail.querySelectorAll('.gallery-slide')) : [];
        var thumbs = thumbsList ? Array.prototype.slice.call(thumbsList.querySelectorAll('li')) : [];

        if (!rail || slides.length === 0) {
            return;
        }

        var current = 0;
        var lightboxOpen = false;

        /* ---------- render ---------- */
        function total() {
            return slides.length;
        }

        function goTo(index, scrollThumb) {
            if (index < 0 || index >= total()) {
                return;
            }
            current = index;

            // Cada diapositiva ocupa el 100% del escenario, asi que el
            // desplazamiento es (100 / n)% del ancho de la pista.
            rail.style.transform = 'translateX(-' + (index * 100 / total()) + '%)';

            thumbs.forEach(function (thumb, k) {
                thumb.classList.toggle('is-active', k === index);
            });

            var thumb = thumbs[index];
            if (scrollThumb && thumb && thumb.scrollIntoView) {
                thumb.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
            }
        }

        function step(delta) {
            if (total() < 2) {
                return;
            }
            goTo((current + delta + total()) % total(), true);
        }

        /* ---------- fotos que no existen ---------- */
        function drop(index) {
            var slide = slides[index];
            var thumb = thumbs[index];

            if (slide && slide.parentNode) {
                slide.parentNode.removeChild(slide);
            }
            if (thumb && thumb.parentNode) {
                thumb.parentNode.removeChild(thumb);
            }
            if (slides[index]) { slides.splice(index, 1); }
            if (thumbs[index]) { thumbs.splice(index, 1); }

            if (total() === 0) {
                root.classList.add('is-empty');
                return;
            }
            if (total() < 2 && thumbsList) {
                thumbsList.style.display = 'none';
            }
            goTo(Math.min(current, total() - 1), false);
        }

        // Quita del carrusel una diapositiva que no tiene imagen disponible
        function removeSlide(slide) {
            var index = slides.indexOf(slide);
            if (index !== -1) {
                drop(index);
            }
        }

        slides.forEach(function (slide) {
            var img = slide.querySelector('img');
            if (img) {
                img.addEventListener('error', function () { removeSlide(slide); });
            }
        });

        // Segunda pasada (sobre una copia, porque al quitar elementos los
        // indices se desplazan): imagenes que ya habian fallado antes de
        // registrar el listener.
        slides.slice().forEach(function (slide) {
            var img = slide.querySelector('img');
            if (img && img.complete && img.naturalWidth === 0) {
                removeSlide(slide);
            }
        });

        /* ---------- miniaturas ---------- */
        // Si la version reducida (tamano c) no existe pero la grande si, la
        // miniatura reutiliza la imagen grande en lugar de mostrar un icono roto.
        thumbs.forEach(function (thumb) {
            var img = thumb.querySelector('img');
            if (!img) { return; }

            img.addEventListener('error', function onThumbError() {
                img.removeEventListener('error', onThumbError);

                var index = thumbs.indexOf(thumb);
                var slide = index !== -1 ? slides[index] : null;
                var big = slide ? slide.querySelector('img') : null;

                if (big && big.src) {
                    img.src = big.src;
                } else {
                    thumb.style.visibility = 'hidden';
                }
            });
        });

        // Las miniaturas se mantienen sincronizadas con las diapositivas (al
        // retirar una foto se eliminan ambas), por lo que el indice vivo de la
        // miniatura coincide con el de su diapositiva.
        thumbs.forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                var index = thumbs.indexOf(thumb);
                if (index !== -1) {
                    goTo(index, false);
                }
            });
        });

        /* ---------- teclado ---------- */
        document.addEventListener('keydown', function (e) {
            if (lightboxOpen) { return; }
            var tag = (e.target && e.target.tagName) || '';
            if (/^(INPUT|TEXTAREA|SELECT)$/.test(tag)) { return; }
            if (e.key === 'ArrowLeft') { step(-1); }
            if (e.key === 'ArrowRight') { step(1); }
        });

        /* ---------- deslizar con el dedo ---------- */
        var touchX = null;
        var touchY = null;
        if (stage) {
            stage.addEventListener('touchstart', function (e) {
                if (e.touches.length !== 1) { return; }
                touchX = e.touches[0].clientX;
                touchY = e.touches[0].clientY;
            }, { passive: true });

            stage.addEventListener('touchend', function (e) {
                if (touchX === null) { return; }
                var t = e.changedTouches[0];
                var dx = t.clientX - touchX;
                var dy = t.clientY - touchY;
                touchX = null;
                touchY = null;
                if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) {
                    step(dx < 0 ? 1 : -1);
                }
            }, { passive: true });
        }

        /* ---------- visor ampliado ---------- */
        var lightbox = document.getElementById('lightbox');
        var lightboxImage = document.getElementById('lightbox-image');
        var lightboxPos = document.getElementById('lightbox-position');
        var lightboxTotal = document.getElementById('lightbox-total');
        var zoomIndex = 0;

        function renderLightbox() {
            var slide = slides[zoomIndex];
            var img = slide ? slide.querySelector('img') : null;
            if (!img || !lightboxImage) { return; }
            lightboxImage.src = img.dataset.zoom || img.src;
            if (lightboxPos) { lightboxPos.textContent = String(zoomIndex + 1); }
            if (lightboxTotal) { lightboxTotal.textContent = String(total()); }
        }

        function openLightbox() {
            if (!lightbox) { return; }
            zoomIndex = current;
            renderLightbox();
            lightbox.hidden = false;
            lightboxOpen = true;
            document.body.classList.add('no-scroll');
        }

        function closeLightbox() {
            if (!lightbox) { return; }
            lightbox.hidden = true;
            lightboxOpen = false;
            document.body.classList.remove('no-scroll');
            if (lightboxImage) { lightboxImage.src = ''; }
        }

        if (stage) {
            stage.addEventListener('click', function (e) {
                if (e.target && e.target.tagName === 'IMG') { openLightbox(); }
            });
        }

        if (lightbox) {
            var closeBtn = document.getElementById('lightbox-close');
            if (closeBtn) { closeBtn.addEventListener('click', closeLightbox); }

            lightbox.addEventListener('click', function (e) {
                if (e.target === lightbox) { closeLightbox(); }
            });

            lightbox.querySelectorAll('.lightbox-nav').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    var delta = parseInt(btn.getAttribute('data-step'), 10) || 1;
                    if (total() < 2) { return; }
                    zoomIndex = (zoomIndex + delta + total()) % total();
                    renderLightbox();
                    goTo(zoomIndex, true);
                });
            });

            document.addEventListener('keydown', function (e) {
                if (!lightboxOpen) { return; }
                if (e.key === 'Escape') { closeLightbox(); }
                if (e.key === 'ArrowLeft') { zoomIndex = (zoomIndex - 1 + total()) % total(); renderLightbox(); goTo(zoomIndex, true); }
                if (e.key === 'ArrowRight') { zoomIndex = (zoomIndex + 1) % total(); renderLightbox(); goTo(zoomIndex, true); }
            });
        }

        goTo(0, false);
    }

    /* =====================================================================
       DESPLEGABLES DE LA FICHA
       ===================================================================== */
    var specSummary = document.getElementById('spec-summary');
    var specToggle = document.getElementById('spec-toggle');
    if (specSummary && specToggle) {
        specToggle.addEventListener('click', function () {
            var expanded = specSummary.classList.toggle('is-expanded');
            var label = specToggle.querySelector('span') || specToggle;
            label.textContent = expanded ? 'Ver menos' : 'Ver mas';
            specToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });
    }

    var shortToggle = document.getElementById('shortdesc-toggle');
    if (shortToggle) {
        var items = document.querySelectorAll('#ficha-shortdesc .ficha-shortdesc-list li');
        shortToggle.addEventListener('click', function () {
            var expanded = shortToggle.getAttribute('aria-expanded') === 'true';
            Array.prototype.slice.call(items, 4).forEach(function (li) {
                li.style.display = expanded ? 'none' : 'list-item';
            });
            shortToggle.textContent = expanded ? 'Ver mas' : 'Ver menos';
            shortToggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        });
    }

    /* =====================================================================
       SELECTOR DE CANTIDAD
       ===================================================================== */
    var qty = document.getElementById('ficha-qty');
    if (qty) {
        document.querySelectorAll('.qty-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var delta = parseInt(btn.getAttribute('data-step'), 10) || 1;
                var value = parseInt(qty.value, 10) || 1;
                qty.value = Math.max(1, value + delta);
            });
        });
        qty.addEventListener('input', function () {
            qty.value = qty.value.replace(/[^0-9]/g, '');
        });
    }

})();

/* =====================================================================
   AREA DE CLIENTE (carrito, cuenta y cierre del pedido)

   Mejoras sin dependencias: sin JavaScript todo sigue funcionando (los
   formularios se envian igual), aqui solo se evitan sustos.
   ===================================================================== */
(function () {
    'use strict';

    /* Confirmacion en los formularios marcados con data-confirm. */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    /* "Facturar a otros datos": muestra u oculta los campos de facturacion. */
    var toggle = document.querySelector('[data-bill-toggle]');
    var fields = document.querySelector('[data-bill-fields]');
    if (toggle && fields) {
        var sync = function () {
            fields.hidden = toggle.checked;
            fields.querySelectorAll('input').forEach(function (input) {
                input.required = !toggle.checked && input.name === 'bill_name';
            });
        };
        toggle.addEventListener('change', sync);
        sync();
    }

    /* Cantidad del carrito: al cambiarla se envia el formulario solo. */
    var cartForm = document.querySelector('.cart-table') ? document.querySelector('.cart-table').closest('form') : null;
    if (cartForm) {
        cartForm.querySelectorAll('input.cart-qty').forEach(function (input) {
            input.addEventListener('change', function () {
                cartForm.submit();
            });
        });
    }
})();

/* =====================================================================
   NAVEGACION: Menú Compacto y Menú Catálogo

   Los dos pintan el mismo arbol y comparten el mismo panel. Aqui solo se
   resuelve el comportamiento:
     - Compacto (escritorio): la barra abre el megamenu al pasar el raton o al
       pulsar la flecha de la categoria (el enlace sigue navegando).
     - Catalogo (escritorio): «Todas las categorias» abre el panel con fondo
       oscuro, columna de categorias a la izquierda y grupos/destinos a la
       derecha.
     - Movil (<1024 px): en los dos casos, menu lateral por niveles con boton
       «Volver».
   Sin JavaScript el arbol sigue siendo navegable: los enlaces de los tres
   niveles estan en el HTML.
   ===================================================================== */
(function () {
    'use strict';

    var root = document.querySelector('[data-menu-nav]');
    if (!root) { return; }

    var panel = root.querySelector('.mn-panel');
    if (!panel) { return; }

    var style = root.getAttribute('data-menu-style') || 'catalogo';
    var isModal = panel.getAttribute('data-mn-modal') === 'true';

    var barItems = Array.prototype.slice.call(root.querySelectorAll('.mn-bar-item'));
    var openers = Array.prototype.slice.call(root.querySelectorAll('[data-mn-open]'));
    var mobileOpen = document.querySelector('[data-mn-mobile-open]');
    var tabs = Array.prototype.slice.call(panel.querySelectorAll('[data-mn-cat-tab]'));
    var panes = Array.prototype.slice.call(panel.querySelectorAll('[data-mn-pane]'));
    var backBtns = Array.prototype.slice.call(panel.querySelectorAll('[data-mn-back]'));
    var titleEl = panel.querySelector('[data-mn-title]');
    var sideTitle = titleEl ? titleEl.textContent : '';

    var lastFocus = null;
    var current = 0;
    var openTimer = null;
    var closeTimer = null;
    var promoLoaded = {};
    var isOpen = false;

    function isMobile() {
        return window.matchMedia('(max-width: 1023.98px)').matches;
    }

    function catLabel(index) {
        var label = tabs[index] ? tabs[index].querySelector('.mn-cat-label') : null;
        return label ? label.textContent.trim() : '';
    }

    /* ---------------------------------------------------------------
       Contenido promocional del panel (banner, marcas y destacados).
       Se pide una sola vez por categoria: la primera carga de la pagina
       no paga las consultas de las 14 categorias.
       --------------------------------------------------------------- */
    function loadPromo(index) {
        var pane = panes[index];
        if (!pane) { return; }

        var box = pane.querySelector('[data-menu-panel]');
        if (!box) { return; }

        var url = box.getAttribute('data-menu-panel-url');
        var key = box.getAttribute('data-menu-panel');
        if (!url || promoLoaded[key]) { return; }
        promoLoaded[key] = true;

        if (!window.fetch) { return; }

        window.fetch(url, { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (data) {
                if (data && typeof data.html === 'string') {
                    box.innerHTML = data.html;
                }
            })
            .catch(function () { /* Si falla, el panel se queda sin bloque promocional. */ });
    }

    /* ---------------------------------------------------------------
       Seleccion de categoria (pestana del panel)
       --------------------------------------------------------------- */
    function select(index, focusTab) {
        if (index < 0 || index >= tabs.length) { return; }
        current = index;

        tabs.forEach(function (tab, i) {
            var active = i === index;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.setAttribute('aria-expanded', active ? 'true' : 'false');
        });

        panes.forEach(function (pane, i) {
            var active = i === index;
            pane.classList.toggle('is-active', active);
            pane.hidden = !active;
        });

        if (titleEl && isMobile() && panel.classList.contains('is-steps')) {
            titleEl.textContent = catLabel(index) || sideTitle;
        }

        loadPromo(index);

        if (focusTab && tabs[index]) {
            tabs[index].focus();
        }
    }

    /* ---------------------------------------------------------------
       Abrir / cerrar
       --------------------------------------------------------------- */
    function openPanel(index, opener, moveFocus) {
        window.clearTimeout(closeTimer);

        lastFocus = opener || document.activeElement;
        isOpen = true;
        panel.hidden = false;
        panel.classList.add('is-open');
        // La clase del body NO puede llamarse `mn-open`: ese nombre ya lo usa el
        // boton del Menu Catalogo (`.mn-trigger .mn-open`) y el body acababa con
        // display:inline-flex y fondo de marca, que descolocaba la pagina entera.
        document.body.classList.add('mn-panel-open');

        if (isMobile()) {
            panel.classList.remove('is-steps');
            if (titleEl) { titleEl.textContent = sideTitle; }
        }

        select(typeof index === 'number' ? index : current, false);

        // Solo el disparador de la categoria activa queda «expandido»: en el
        // Menu Compacto los demas botones no senalan ninguna subcategoria.
        openers.forEach(function (el) {
            var elIndex = parseInt(el.getAttribute('data-mn-open'), 10) || 0;
            var active = openers.length === 1 || elIndex === current;
            el.setAttribute('aria-expanded', active ? 'true' : 'false');
        });
        if (mobileOpen) { mobileOpen.setAttribute('aria-expanded', 'true'); }

        if (moveFocus) {
            if (tabs[current]) { tabs[current].focus(); }
        }
    }

    function closePanel(returnFocus) {
        if (!isOpen) { return; }
        isOpen = false;
        panel.classList.remove('is-open', 'is-steps');
        panel.hidden = true;
        document.body.classList.remove('mn-panel-open');
        setBack(false);

        barItems.forEach(function (item) { item.classList.remove('is-open'); });
        openers.forEach(function (el) { el.setAttribute('aria-expanded', 'false'); });
        if (mobileOpen) { mobileOpen.setAttribute('aria-expanded', 'false'); }

        if (returnFocus && lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function setBack(visible) {
        backBtns.forEach(function (btn) { btn.hidden = !visible; });
    }

    function goToCats() {
        panel.classList.remove('is-steps');
        setBack(false);
        if (titleEl) { titleEl.textContent = sideTitle; }
    }

    function goToSteps() {
        panel.classList.add('is-steps');
        setBack(true);
        if (titleEl) { titleEl.textContent = catLabel(current) || sideTitle; }
        if (!panel.hidden) { loadPromo(current); }
    }

    /* ---------------------------------------------------------------
       Disparadores
       --------------------------------------------------------------- */
    openers.forEach(function (opener) {
        var index = parseInt(opener.getAttribute('data-mn-open'), 10) || 0;

        opener.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (isOpen && current === index) {
                closePanel(true);
                return;
            }
            openPanel(index, opener, true);
        });

        opener.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                openPanel(index, opener, true);
            }
        });
    });

    if (mobileOpen) {
        mobileOpen.addEventListener('click', function (e) {
            e.preventDefault();
            // Sin esto, el manejador de «clic fuera» del documento veria el clic
            // (el boton vive en la cabecera, fuera del contenedor del menu) y
            // cerraria el cajon en el mismo clic que lo abre.
            e.stopPropagation();
            if (isOpen) { closePanel(true); return; }
            openPanel(current, mobileOpen, true);
        });
    }

    /* Escritorio: el raton abre y cierra el megamenu (Menu Compacto). */
    if (style === 'compacto' && window.matchMedia('(hover: hover)').matches) {
        barItems.forEach(function (item, index) {
            item.addEventListener('mouseenter', function () {
                if (isMobile()) { return; }
                window.clearTimeout(closeTimer);
                openTimer = window.setTimeout(function () {
                    openPanel(index, null, false);
                    item.classList.add('is-open');
                }, 110);
            });

            item.addEventListener('mouseleave', function () {
                if (isMobile()) { return; }
                window.clearTimeout(openTimer);
                closeTimer = window.setTimeout(function () {
                    if (panel.contains(document.activeElement)) { return; }
                    closePanel(false);
                }, 260);
            });
        });

        panel.addEventListener('mouseenter', function () { window.clearTimeout(closeTimer); });
        panel.addEventListener('mouseleave', function () {
            if (isMobile()) { return; }
            closeTimer = window.setTimeout(function () {
                if (panel.contains(document.activeElement)) { return; }
                closePanel(false);
            }, 260);
        });
    }

    /* Pestanas del panel */
    tabs.forEach(function (tab) {
        var index = parseInt(tab.getAttribute('data-mn-cat-tab'), 10) || 0;

        tab.addEventListener('click', function () {
            select(index, false);
            if (isMobile()) { goToSteps(); }
        });

        tab.addEventListener('mouseenter', function () {
            if (isMobile() || style !== 'catalogo') { return; }
            select(index, false);
        });
    });

    /* Volver (solo movil) y cerrar */
    panel.querySelectorAll('[data-mn-back]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            goToCats();
            if (tabs[current]) { tabs[current].focus(); }
        });
    });

    panel.querySelectorAll('[data-mn-close]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            closePanel(true);
        });
    });

    /* Clic fuera: en movil el cajon se cierra; en escritorio tambien. */
    document.addEventListener('click', function (e) {
        if (!isOpen) { return; }
        if (panel.contains(e.target)) { return; }
        if (root.contains(e.target)) { return; }
        // Los disparadores del menu pueden vivir fuera del contenedor (el boton
        // del menu lateral esta en la cabecera): no cuentan como «clic fuera».
        if (e.target.closest && e.target.closest('[data-mn-open], [data-mn-mobile-open], [data-mn-more-btn], .mn-more-panel')) {
            return;
        }
        closePanel(false);
    });

    /* Teclado: Escape y navegacion por flechas entre categorias */
    document.addEventListener('keydown', function (e) {
        if (!isOpen) { return; }

        if (e.key === 'Escape') {
            e.preventDefault();
            if (isMobile() && panel.classList.contains('is-steps')) {
                goToCats();
                if (tabs[current]) { tabs[current].focus(); }
            } else {
                closePanel(true);
            }
            return;
        }

        var index = tabs.indexOf(document.activeElement);
        if (index !== -1 && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            e.preventDefault();
            var step = e.key === 'ArrowDown' ? 1 : -1;
            var next = (index + step + tabs.length) % tabs.length;
            select(next, true);
            return;
        }

        /* En el panel modal (Menu Catalogo) el foco no debe salirse. */
        if (isModal && e.key === 'Tab') {
            var focusables = panel.querySelectorAll(
                'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            var list = Array.prototype.filter.call(focusables, function (el) {
                return el.offsetParent !== null || el === document.activeElement;
            });
            if (!list.length) { return; }

            var first = list[0];
            var last = list[list.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    /* «Mas categorias» (Menu Compacto) */
    var moreBtn = root.querySelector('[data-mn-more-btn]');
    var morePanel = root.querySelector('.mn-more-panel');
    if (moreBtn && morePanel) {
        moreBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = moreBtn.getAttribute('aria-expanded') === 'true';
            moreBtn.setAttribute('aria-expanded', open ? 'false' : 'true');
            morePanel.hidden = open;
        });

        document.addEventListener('click', function (e) {
            if (morePanel.hidden) { return; }
            if (root.contains(e.target)) { return; }
            morePanel.hidden = true;
            moreBtn.setAttribute('aria-expanded', 'false');
        });
    }

    /* Al cambiar de escritorio/movil se cierra: evita estados a medias. */
    var wasMobile = isMobile();
    window.addEventListener('resize', function () {
        var nowMobile = isMobile();
        if (nowMobile !== wasMobile) {
            wasMobile = nowMobile;
            closePanel(false);
        }
    });

    /* =====================================================================
       BUSCADOR EN VIVO
       ---------------------------------------------------------------------
       Mismo comportamiento que el buscador de PuntoByZE: al enfocar o teclear
       se abre un panel con resultados, filtros por subcategoria y marca y un
       enlace a la busqueda completa. Los resultados los pinta el SERVIDOR
       (tarjetas del tema) para no duplicar maquetacion ni colores: aqui solo
       se inserta el HTML y se gestionan las facetas.
       Sin JavaScript el formulario de la cabecera sigue funcionando: envia a
       /catalogo?q= como siempre.
       ===================================================================== */
    var searchRoot = document.getElementById('shop-search');
    var searchPageInput = document.getElementById('search-q');
    if (searchRoot && searchPageInput) {
        initLiveSearch(searchRoot, searchPageInput);
    }

    function initLiveSearch(root, pageInput) {
        var endpoint = root.dataset.searchEndpoint || '';
        var pageUrl = root.dataset.searchPage || '';
        var els = {
            input: root.querySelector('#shop-search-input'),
            close: root.querySelector('#shop-search-close'),
            count: root.querySelector('#shop-search-count'),
            filters: root.querySelector('#shop-search-filters'),
            results: root.querySelector('#shop-search-results'),
            foot: root.querySelector('#shop-search-foot'),
            all: root.querySelector('#shop-search-all')
        };
        if (!endpoint || !els.input || !els.results) { return; }

        var MIN_CHARS = 2;
        var state = {
            query: '',
            subs: [],
            brands: [],
            timer: null,
            controller: null,
            ignoreUntil: 0,
            open: false,
            last: null,
            // Al cerrar se devuelve el foco al campo de la cabecera; ese foco es
            // programatico y no debe reabrir el panel (ver closePanel).
            closing: false
        };

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;')
                .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        function sync(value) {
            state.query = value;
            if (pageInput.value !== value) { pageInput.value = value; }
            if (els.input.value !== value) { els.input.value = value; }
        }

        function showIdle() {
            els.results.innerHTML = '<p class="shop-search-hint">Escribe al menos ' + MIN_CHARS + ' caracteres para buscar</p>';
            if (els.filters) { els.filters.innerHTML = '<p class="shop-search-hint">Busca por nombre, marca, referencia o EAN</p>'; }
            if (els.count) { els.count.textContent = ''; }
            if (els.foot) { els.foot.hidden = true; }
        }

        function openPanel() {
            if (state.open) { return; }
            state.open = true;
            state.ignoreUntil = Date.now() + 400;
            root.hidden = false;
            root.setAttribute('aria-hidden', 'false');
            root.classList.add('is-open');
            document.body.classList.add('shop-search-open');
            sync(pageInput.value);
            els.input.focus();
            if (state.query.trim().length >= MIN_CHARS) { schedule(); } else { showIdle(); }
        }

        function closePanel(returnFocus) {
            if (!state.open) { return; }
            state.open = false;
            root.classList.remove('is-open');
            root.hidden = true;
            root.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('shop-search-open');
            if (returnFocus) {
                // Devolver el foco al campo de la cabecera es correcto, pero su
                // listener de `focus` no debe confundirlo con el usuario: si lo
                // hiciera, el panel se reabriria al instante y ni Escape ni el
                // boton de cerrar lo cerrarian.
                state.closing = true;
                pageInput.focus();
                state.closing = false;
            }
        }

        function buildUrl() {
            var url = endpoint + '?q=' + encodeURIComponent(state.query.trim());
            state.subs.forEach(function (id) { url += '&s[]=' + encodeURIComponent(id); });
            state.brands.forEach(function (id) { url += '&m[]=' + encodeURIComponent(id); });
            return url;
        }

        function chip(type, id, label, count, selected) {
            return '<button type="button" class="shop-search-chip' + (selected ? ' is-active' : '') + '"' +
                ' data-type="' + type + '" data-id="' + escapeHtml(id) + '" aria-pressed="' + (selected ? 'true' : 'false') + '">' +
                escapeHtml(label) +
                (count ? ' <span>' + count + '</span>' : '') +
                '</button>';
        }

        function renderFilters(data) {
            if (!els.filters) { return; }
            var subs = data.subcategorias || [];
            var brands = data.marcas || [];
            if (!subs.length && !brands.length) {
                els.filters.innerHTML = '<p class="shop-search-hint">Sin filtros para esta busqueda</p>';
                return;
            }
            var html = '';
            if (subs.length) {
                html += '<div class="shop-search-facet"><h3>Subcategorias</h3><div class="shop-search-chips">';
                subs.forEach(function (s) { html += chip('s', s.id, s.label, s.count, s.selected); });
                html += '</div></div>';
            }
            if (brands.length) {
                html += '<div class="shop-search-facet"><h3>Marcas</h3><div class="shop-search-chips">';
                brands.forEach(function (b) { html += chip('m', b.value, b.label, b.count, b.selected); });
                html += '</div></div>';
            }
            els.filters.innerHTML = html;
        }

        function render(data) {
            state.last = data;
            if (els.count) {
                els.count.textContent = data.total
                    ? data.total + (data.total === 1 ? ' resultado' : ' resultados')
                    : '';
            }
            els.results.innerHTML = data.html
                ? data.html
                : '<p class="shop-search-empty">No encontramos productos para esa busqueda.</p>';
            renderFilters(data);

            if (els.foot) {
                els.foot.hidden = !data.verTodos || !data.total;
            }
            if (els.all) {
                els.all.href = data.verTodos || pageUrl;
            }
        }

        function fetchResults() {
            var query = state.query.trim();
            if (query.length < MIN_CHARS) { showIdle(); return; }

            if (state.controller) { state.controller.abort(); }
            state.controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;

            els.results.innerHTML = '<p class="shop-search-hint">Buscando productos...</p>';

            var options = { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' };
            if (state.controller) { options.signal = state.controller.signal; }

            window.fetch(buildUrl(), options).then(function (response) {
                return response.json();
            }).then(function (data) {
                if (data && typeof data.total !== 'undefined') { render(data); }
            }).catch(function (error) {
                if (error && error.name === 'AbortError') { return; }
                els.results.innerHTML = '<p class="shop-search-empty">No se pudo buscar. Intentalo de nuevo.</p>';
            });
        }

        function schedule() {
            clearTimeout(state.timer);
            state.timer = setTimeout(fetchResults, 260);
        }

        function toggle(type, id) {
            var list = type === 's' ? state.subs : state.brands;
            var key = String(id);
            var index = list.indexOf(key);
            if (index === -1) { list.push(key); } else { list.splice(index, 1); }
            fetchResults();
        }

        function pageLink() {
            var url = pageUrl + '?q=' + encodeURIComponent(state.query.trim());
            if (state.subs.length) { url += '&subcat=' + encodeURIComponent(state.subs[0]); }
            return url;
        }

        // El foco programatico que devuelve closePanel no cuenta: solo abre el
        // panel el foco del usuario (clic, tabulador o teclear en el campo).
        pageInput.addEventListener('focus', function () {
            if (state.closing) { return; }
            openPanel();
        });
        pageInput.addEventListener('click', openPanel);
        // Si el navegador deja el foco puesto al cargar (o el usuario escribe
        // sin haber hecho clic), teclear abre el panel y sigue la busqueda.
        pageInput.addEventListener('input', function () {
            openPanel();
            if (els.input.value !== pageInput.value) {
                if (pageInput.value.trim() !== state.query.trim()) {
                    state.subs = [];
                    state.brands = [];
                }
                sync(pageInput.value);
            }
            schedule();
        });

        els.input.addEventListener('input', function () {
            if (els.input.value.trim() !== state.query.trim()) {
                state.subs = [];
                state.brands = [];
            }
            sync(els.input.value);
            schedule();
        });

        els.input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closePanel(true);
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                window.location.href = (state.last && state.last.verTodos) ? state.last.verTodos : pageLink();
            }
        });

        // Un unico boton en el panel: cierra todo el buscador (no hay boton de
        // borrar propio; para vaciar el texto se usa la X nativa del campo o el
        // teclado). Al cerrar se conserva la busqueda, por si el usuario vuelve.
        if (els.close) {
            els.close.addEventListener('click', function () { closePanel(true); });
        }

        if (els.filters) {
            els.filters.addEventListener('click', function (e) {
                var button = e.target.closest ? e.target.closest('.shop-search-chip') : null;
                if (!button) { return; }
                e.preventDefault();
                toggle(button.dataset.type, button.dataset.id);
            });
        }

        document.addEventListener('mousedown', function (e) {
            if (!state.open || Date.now() < state.ignoreUntil) { return; }
            if (root.contains(e.target)) { return; }
            if (e.target === pageInput || pageInput.contains(e.target)) { return; }
            if (e.target.closest && e.target.closest('.search')) { return; }
            closePanel(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && state.open) { closePanel(true); }
        });
    }
})();
