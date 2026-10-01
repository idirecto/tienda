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
        var progress = root.querySelector('[data-slider-progress]');

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

    /* =====================================================================
       MENU DE CATEGORIAS DEL CATALOGO (megamenu)
       ===================================================================== */
    var catMenu = document.getElementById('catalog-menu');
    var catTrigger = document.getElementById('catalog-menu-trigger');
    if (catMenu && catTrigger) {
        initCatalogMenu(catMenu, catTrigger);
    }

    function initCatalogMenu(menu, trigger) {
        var buttons = Array.prototype.slice.call(menu.querySelectorAll('[data-catmenu-cat]'));
        var panes = Array.prototype.slice.call(menu.querySelectorAll('[data-catmenu-pane]'));
        var lastFocus = null;

        function isMobileView() {
            return window.matchMedia('(max-width: 991px)').matches;
        }

        function select(index) {
            buttons.forEach(function (btn, i) {
                btn.parentNode.classList.toggle('is-active', i === index);
                btn.setAttribute('aria-selected', i === index ? 'true' : 'false');
            });
            panes.forEach(function (pane, i) {
                pane.classList.toggle('is-active', i === index);
            });
            if (panes[index]) {
                panes[index].scrollTop = 0;
            }
            // En movil, pulsar una categoria entra en sus subcategorias.
            if (isMobileView()) {
                menu.classList.add('is-groups');
            }
        }

        function open() {
            lastFocus = document.activeElement;
            menu.hidden = false;
            menu.classList.remove('is-groups');
            trigger.setAttribute('aria-expanded', 'true');
            document.body.classList.add('no-scroll');
            var close = menu.querySelector('[data-catmenu-close]');
            if (close) { close.focus(); }
        }

        function close() {
            menu.hidden = true;
            menu.classList.remove('is-groups');
            trigger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('no-scroll');
            if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
        }

        trigger.addEventListener('click', function (e) {
            e.preventDefault();
            if (menu.hidden) { open(); } else { close(); }
        });

        menu.addEventListener('click', function (e) {
            if (e.target.closest('[data-catmenu-close]')) { e.preventDefault(); close(); return; }
            if (e.target.closest('[data-catmenu-back]')) { e.preventDefault(); menu.classList.remove('is-groups'); return; }

            var btn = e.target.closest('[data-catmenu-cat]');
            if (btn) {
                e.preventDefault();
                select(parseInt(btn.getAttribute('data-catmenu-cat'), 10) || 0);
            }
        });

        // Con raton, cambiar de categoria al pasar por encima (como los
        // megamenus clasicos); en tactil solo al pulsar.
        if (window.matchMedia('(hover: hover)').matches) {
            buttons.forEach(function (btn) {
                btn.addEventListener('mouseenter', function () {
                    select(parseInt(btn.getAttribute('data-catmenu-cat'), 10) || 0);
                });
            });
        }

        document.addEventListener('keydown', function (e) {
            if (menu.hidden) { return; }

            if (e.key === 'Escape') {
                // Escape: en movil vuelve a las categorias; si no, cierra.
                if (isMobileView() && menu.classList.contains('is-groups')) {
                    menu.classList.remove('is-groups');
                } else {
                    close();
                }
                return;
            }

            // Flechas arriba/abajo para recorrer las categorias (patron tablist).
            var current = buttons.indexOf(document.activeElement);
            if (current !== -1 && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault();
                var step = e.key === 'ArrowDown' ? 1 : -1;
                var next = (current + step + buttons.length) % buttons.length;
                select(next);
                buttons[next].focus();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 992) { menu.classList.remove('is-groups'); }
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
