/**
 * Storefront: rotacion del hero y galeria de la ficha de producto.
 *
 * La galeria reproduce el comportamiento de idirecto (un carrusel que se
 * desliza lateralmente y se navega con las miniaturas, sin flechas), pero
 * anade dos mejoras:
 *   - si una foto no existe (error 404) se retira del carrusel y de la fila
 *     de miniaturas, de modo que nunca se ve un hueco ni un icono roto;
 *   - se puede ampliar la imagen y deslizar con el dedo.
 */
(function () {
    'use strict';

    /* =====================================================================
       HERO: rotacion de banners
       ===================================================================== */
    var track = document.querySelector('.hero-track');
    if (track) {
        var heroSlides = track.querySelectorAll('.hero-slide');
        if (heroSlides.length > 1) {
            var heroIndex = 0;
            heroSlides.forEach(function (slide, i) {
                slide.style.display = i === 0 ? 'flex' : 'none';
            });
            setInterval(function () {
                heroSlides[heroIndex].style.display = 'none';
                heroIndex = (heroIndex + 1) % heroSlides.length;
                heroSlides[heroIndex].style.display = 'flex';
            }, 6000);
        }
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
            label.textContent = expanded ? 'Ver menos' : 'Ver más';
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
            shortToggle.textContent = expanded ? 'Ver más' : 'Ver menos';
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
