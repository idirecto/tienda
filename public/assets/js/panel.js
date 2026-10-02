/**
 * Panel: subida de imagenes por AJAX y utilidades de UI.
 * Requiere window.TIENDA = { base, csrf, uploadUrl } (definido en el layout).
 */
(function () {
    'use strict';

    var CFG = window.TIENDA || {};

    /** Tamanos legibles: 2,4 MB, 380 KB, 900 B. */
    function fmtBytes(bytes) {
        if (!bytes || bytes <= 0) { return ''; }
        if (bytes >= 1048576) { return (bytes / 1048576).toFixed(1).replace('.', ',') + ' MB'; }
        if (bytes >= 1024) { return Math.round(bytes / 1024) + ' KB'; }
        return bytes + ' B';
    }

    /**
     * Detalle del ahorro cuando el servidor ha reconvertido la imagen
     * (JPG 2,4 MB -> WEBP 380 KB, -84%).
     */
    function detalleOptimizacion(media) {
        if (!media || !media.optimized || !media.original_bytes) { return ''; }
        var origen = media.original_mime ? String(media.original_mime).replace('image/', '').toUpperCase() + ' ' : '';
        var texto = ' (' + origen + fmtBytes(media.original_bytes) + ' -> WEBP ' + fmtBytes(media.bytes);
        if (media.original_bytes > media.bytes) {
            texto += ', -' + Math.round((1 - (media.bytes / media.original_bytes)) * 100) + '%';
        }
        return texto + ')';
    }

    /**
     * Sube un fichero al endpoint de media y rellena los campos indicados.
     */
    function uploadFile(file, opts) {
        var status = opts.statusEl ? document.querySelector(opts.statusEl) : null;

        // Limite por tipo (banners 8 MB, resto 5 MB por defecto): se avisa antes
        // de subir nada, sin esperar al servidor.
        var maxBytes = Number(opts.maxBytes || 0);
        if (maxBytes > 0 && file.size > maxBytes) {
            var aviso = 'La imagen pesa ' + fmtBytes(file.size) + ' y el maximo es ' + fmtBytes(maxBytes) + '.';
            if (status) { status.textContent = 'Error: ' + aviso; }
            window.alert(aviso + ' Reduce la imagen o sube una mas ligera.');
            return Promise.reject(new Error(aviso));
        }

        var body = new FormData();
        body.append('file', file);
        body.append('folder', opts.folder || 'general');
        body.append('_token', CFG.csrf || '');

        if (status) { status.textContent = 'Subiendo...'; }

        return fetch(CFG.uploadUrl, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Respuesta invalida' }; }); })
        .then(function (data) {
            if (!data.ok) {
                throw new Error(data.error || 'Error al subir');
            }
            if (opts.urlTarget) {
                var urlInput = document.querySelector(opts.urlTarget);
                if (urlInput) { urlInput.value = data.media.url; }
            }
            if (opts.keyTarget) {
                var keyInput = document.querySelector(opts.keyTarget);
                if (keyInput) { keyInput.value = data.media.key; }
            }
            if (opts.idTarget) {
                var idInput = document.querySelector(opts.idTarget);
                if (idInput) { idInput.value = data.media.id; }
            }
            if (opts.preview) {
                var preview = document.querySelector(opts.preview);
                if (preview) {
                    preview.innerHTML = '<img src="' + data.media.url + '" alt="">';
                }
            }
            if (status) { status.textContent = 'Imagen subida correctamente' + detalleOptimizacion(data.media) + '.'; }
            return data.media;
        })
        .catch(function (err) {
            if (status) { status.textContent = 'Error: ' + err.message; }
            window.alert('No se pudo subir la imagen: ' + err.message);
            throw err;
        });
    }

    function initUploader(container) {
        var input = container.querySelector('input[type=file]');
        if (!input) { return; }

        var opts = {
            folder: input.getAttribute('data-folder') || container.getAttribute('data-folder') || 'general',
            maxBytes: input.getAttribute('data-max-bytes') || container.getAttribute('data-max-bytes') || 0,
            statusEl: input.getAttribute('data-status') || container.getAttribute('data-status'),
            urlTarget: input.getAttribute('data-url-target'),
            keyTarget: input.getAttribute('data-key-target'),
            idTarget: input.getAttribute('data-url-id'),
            preview: input.getAttribute('data-preview')
        };

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                uploadFile(input.files[0], opts);
            }
        });

        // Drag & drop
        ['dragenter', 'dragover'].forEach(function (evt) {
            container.addEventListener(evt, function (e) {
                e.preventDefault();
                container.classList.add('dragover');
            });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            container.addEventListener(evt, function (e) {
                e.preventDefault();
                container.classList.remove('dragover');
            });
        });
        container.addEventListener('drop', function (e) {
            var files = e.dataTransfer && e.dataTransfer.files;
            if (files && files[0]) {
                uploadFile(files[0], opts);
            }
        });
    }

    /* =====================================================================
       IDENTIDAD VISUAL: vista previa en vivo
       =====================================================================
       Cada cambio en el formulario de diseno pide al servidor el CSS de tokens
       que generaria esa configuracion (`/panel/diseno/tokens`, el mismo
       generador que usa el storefront) y lo inyecta en el iframe de previa.

       Se hace asi, y no calculando los colores en JavaScript, para que la
       previa no pueda desviarse nunca de lo que se publica.
       ===================================================================== */
    function hexValido(valor) {
        return /^#?([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(String(valor || '').trim());
    }

    function initDesignPreview(form) {
        var frame = document.querySelector('[data-design-preview]');
        var state = document.querySelector('[data-preview-state]');
        if (!frame) { return; }

        var tokensUrl = (CFG.designTokensUrl || (CFG.base || '') + '/panel/diseno/tokens');
        var timer = null;

        function marcar(texto) {
            if (state) { state.textContent = texto; }
        }

        function recoger() {
            var datos = {};
            ['color_primary', 'color_secondary', 'color_accent', 'color_bg', 'color_surface',
             'color_text', 'color_border', 'color_scheme', 'radius_scale', 'font',
             'header_style', 'theme_tokens'].forEach(function (nombre) {
                var campo = form.querySelector('[name="' + nombre + '"]');
                if (campo) { datos[nombre] = campo.value; }
            });

            // Los campos de color avanzados vacios significan "usa el tema".
            ['color_bg', 'color_surface', 'color_text', 'color_border'].forEach(function (nombre) {
                if (datos[nombre] && !hexValido(datos[nombre])) { datos[nombre] = ''; }
            });

            return datos;
        }

        function actualizar() {
            pendiente = false;
            var datos = recoger();
            var query = Object.keys(datos).filter(function (k) { return datos[k] !== ''; })
                .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(datos[k]); })
                .join('&');

            marcar('Previsualizando...');

            fetch(tokensUrl + (query ? '?' + query : ''), {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || typeof data.css !== 'string' || !frame.contentWindow) { return; }
                    frame.contentWindow.postMessage({
                        type: 'tienda-tokens',
                        css: data.css,
                        scheme: data.scheme || 'light'
                    }, window.location.origin);
                    marcar('Sin guardar');
                })
                .catch(function () { marcar('Vista previa no disponible'); });
        }

        function programar() {
            if (timer) { window.clearTimeout(timer); }
            timer = window.setTimeout(actualizar, 250);
        }

        form.addEventListener('input', programar);
        form.addEventListener('change', programar);

        // Sincroniza el selector de color con su campo de texto (y al reves).
        form.querySelectorAll('input[type=color][data-color-for]').forEach(function (color) {
            var texto = form.querySelector('[data-color-text="' + color.getAttribute('data-color-for') + '"]');
            if (!texto) { return; }
            color.addEventListener('input', function () { texto.value = color.value; });
            texto.addEventListener('input', function () {
                if (hexValido(texto.value)) { color.value = texto.value.trim(); }
            });
        });

        // Los botones de preset rellenan el formulario (ademas de poder
        // aplicarse en el servidor al enviarlo).
        form.querySelectorAll('[data-preset]').forEach(function (boton) {
            boton.addEventListener('click', function (evento) {
                if (!window.confirm('Se guardara la tienda con el preset "' +
                        boton.querySelector('strong').textContent.trim() + '". ¿Continuar?')) {
                    evento.preventDefault();
                    return;
                }
                var valores = {
                    color_primary: boton.getAttribute('data-primary'),
                    color_secondary: boton.getAttribute('data-secondary'),
                    color_accent: boton.getAttribute('data-accent'),
                    color_scheme: boton.getAttribute('data-scheme'),
                    radius_scale: boton.getAttribute('data-radius'),
                    font: boton.getAttribute('data-font')
                };
                Object.keys(valores).forEach(function (nombre) {
                    var campo = form.querySelector('[name="' + nombre + '"]');
                    if (!campo || !valores[nombre]) { return; }
                    if (campo.type === 'color') {
                        if (hexValido(valores[nombre])) { campo.value = valores[nombre]; }
                    } else {
                        campo.value = valores[nombre];
                    }
                    var texto = form.querySelector('[data-color-text="' + nombre + '"]');
                    if (texto) { texto.value = valores[nombre]; }
                });
            });
        });

        frame.addEventListener('load', function () { actualizar(); });
    }

    /* =====================================================================
       PEDIDOS
       =====================================================================
       1) Editor de lineas: buscador de productos (catalogo + propios) que va
          anadiendo filas y recalcula los totales del pedido.
       2) Envio a idirecto: las casillas de la tabla se asocian al formulario
          de envio con el atributo `form` de HTML5, asi que la tabla puede
          quedar fuera del formulario (cada linea tiene su boton de borrar sin
          anidar formularios). Aqui solo se suman las lineas seleccionadas.
       ===================================================================== */

    /** Numero a partir de un texto con coma o punto decimal. */
    function num(value) {
        var s = String(value === null || value === undefined ? '' : value).trim().replace(/\s|€/g, '');
        if (s === '') { return 0; }
        if (s.indexOf(',') > -1) { s = s.replace(/\./g, '').replace(',', '.'); }
        var n = parseFloat(s);
        return isNaN(n) ? 0 : n;
    }

    function eur(n) {
        return n.toFixed(2).replace('.', ',') + ' €';
    }

    function initLineEditors() {
        document.querySelectorAll('[data-line-editor]').forEach(function (editor) {
            var tbody = editor.querySelector('[data-lines]');
            var template = editor.querySelector('[data-line-template]');
            var search = editor.querySelector('[data-product-search]');
            var results = editor.querySelector('[data-search-results]');
            var url = editor.getAttribute('data-search-url');
            var shipping = document.querySelector('[name="shipping"]');
            if (!tbody || !template) { return; }

            /** Renumera los `lines[i]` de todas las filas y recalcula totales. */
            function renumber() {
                Array.prototype.forEach.call(tbody.querySelectorAll('[data-line]'), function (row, i) {
                    Array.prototype.forEach.call(row.querySelectorAll('input[name]'), function (input) {
                        input.name = input.name.replace(/^lines\[\d+\]/, 'lines[' + i + ']');
                    });
                });
                recalc();
            }

            function recalc() {
                var subtotal = 0;
                var impuestos = 0;

                Array.prototype.forEach.call(tbody.querySelectorAll('[data-line]'), function (row) {
                    var qty = num(row.querySelector('.line-qty') ? row.querySelector('.line-qty').value : 1);
                    var price = num(row.querySelector('.line-price') ? row.querySelector('.line-price').value : 0);
                    var tax = num(row.querySelector('.line-tax') ? row.querySelector('.line-tax').value : 0);
                    var base = qty * price;

                    subtotal += base;
                    impuestos += base * tax / 100;

                    var celda = row.querySelector('[data-line-total]');
                    if (celda) { celda.textContent = eur(base); }
                });

                var set = function (selector, value) {
                    var el = document.querySelector(selector);
                    if (el) { el.textContent = eur(value); }
                };
                var envio = shipping ? num(shipping.value) : 0;

                set('[data-total-subtotal]', subtotal);
                set('[data-total-tax]', impuestos);
                set('[data-total-grand]', subtotal + impuestos + envio);
            }

            function addRow(data) {
                var fragment = template.content.cloneNode(true);
                var row = fragment.querySelector('[data-line]');

                var set = function (selector, value, attribute) {
                    var el = row.querySelector(selector);
                    if (!el) { return; }
                    if (attribute) { el.setAttribute(attribute, value); } else { el.value = value; }
                };

                set('.line-name', data.name || '');
                set('.line-qty', data.qty || 1);
                set('.line-price', data.price !== undefined ? String(data.price).replace('.', ',') : '0,00');
                set('.line-tax', data.tax_rate !== undefined ? String(data.tax_rate).replace('.', ',') : '21,00');
                set('input[name$="[source]"]', data.source || 'catalog');
                set('input[name$="[product_id]"]', data.id || 0);
                set('input[name$="[sku]"]', data.sku || '');

                var sku = row.querySelector('[data-sku]');
                if (sku) { sku.textContent = data.sku || ''; }

                var propio = row.querySelector('[data-own]');
                if (propio) { propio.hidden = !data.own; }

                tbody.appendChild(fragment);
                renumber();
            }

            function ocultarResultados() {
                if (results) { results.hidden = true; results.innerHTML = ''; }
            }

            function pintarResultados(lista) {
                if (!results) { return; }
                results.innerHTML = '';
                if (!lista.length) {
                    results.hidden = false;
                    results.innerHTML = '<div class="search-result muted">Sin resultados.</div>';
                    return;
                }
                lista.forEach(function (item) {
                    var boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'search-result';
                    var precio = item.price ? ' · ' + eur(num(item.price)) : '';
                    boton.innerHTML = '<strong></strong><small></small>';
                    boton.querySelector('strong').textContent = item.name;
                    boton.querySelector('small').textContent =
                        (item.sku ? item.sku + ' · ' : '') +
                        (item.own ? 'producto propio (no se envia a idirecto)' : 'catalogo del mayorista') +
                        precio;
                    boton.addEventListener('click', function () {
                        addRow(item);
                        ocultarResultados();
                        if (search) { search.value = ''; search.focus(); }
                    });
                    results.appendChild(boton);
                });
                results.hidden = false;
            }

            if (search) {
                var timer = null;
                search.addEventListener('input', function () {
                    var q = search.value.trim();
                    if (timer) { window.clearTimeout(timer); }
                    if (q.length < 2) { ocultarResultados(); return; }
                    timer = window.setTimeout(function () {
                        fetch(url + '?q=' + encodeURIComponent(q), {
                            credentials: 'same-origin',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (data) { pintarResultados(data && data.results ? data.results : []); })
                            .catch(function () { ocultarResultados(); });
                    }, 250);
                });

                document.addEventListener('click', function (evento) {
                    if (!editor.contains(evento.target)) { ocultarResultados(); }
                });
            }

            editor.addEventListener('input', recalc);
            editor.addEventListener('click', function (evento) {
                var quitar = evento.target.closest('[data-remove-line]');
                if (quitar) {
                    var fila = quitar.closest('[data-line]');
                    if (fila) { fila.remove(); renumber(); }
                    return;
                }
                if (evento.target.closest('[data-add-line]')) {
                    addRow({ name: '', source: 'catalog', id: 0, qty: 1, price: 0, tax_rate: 21 });
                }
            });

            // Una linea en blanco de partida si el editor esta vacio.
            if (!tbody.querySelector('[data-line]')) {
                addRow({ name: '', source: 'catalog', id: 0, qty: 1, price: 0, tax_rate: 21 });
            }

            recalc();
        });
    }

    /** Totales del pedido que se enviara a idirecto segun lo seleccionado. */
    function initOrderSend() {
        var form = document.querySelector('[data-send-form]');
        if (!form) { return; }

        var checks = document.querySelectorAll('input[data-send-check]');
        var todos = document.querySelector('[data-send-all]');
        var subtotalEl = document.querySelector('[data-send-subtotal]');
        var taxEl = document.querySelector('[data-send-tax]');
        var totalEl = document.querySelector('[data-send-total]');
        var countEl = document.querySelector('[data-send-count]');
        var boton = document.querySelector('[data-send-button]');

        function recalc() {
            var subtotal = 0;
            var impuestos = 0;
            var total = 0;
            var n = 0;

            Array.prototype.forEach.call(checks, function (check) {
                if (!check.checked) { return; }
                var row = check.closest('[data-send-row]');
                if (!row) { return; }
                subtotal += num(row.getAttribute('data-base'));
                impuestos += num(row.getAttribute('data-iva'));
                total += num(row.getAttribute('data-total'));
                n++;
            });

            if (subtotalEl) { subtotalEl.textContent = eur(subtotal); }
            if (taxEl) { taxEl.textContent = eur(impuestos); }
            if (totalEl) { totalEl.textContent = eur(total); }
            if (countEl) { countEl.textContent = String(n); }
            if (boton) { boton.disabled = n === 0; }
        }

        Array.prototype.forEach.call(checks, function (check) {
            check.addEventListener('change', recalc);
        });

        if (todos) {
            todos.addEventListener('change', function () {
                Array.prototype.forEach.call(checks, function (check) { check.checked = todos.checked; });
                recalc();
            });
        }

        recalc();
    }

    /** El bloque de direccion de envio solo se muestra si no va a la tienda. */
    function initShipToggle() {
        var check = document.querySelector('[data-ship-to-store]');
        if (!check) { return; }
        var campos = document.querySelector('[data-ship-fields]');
        if (!campos) { return; }

        var sync = function () { campos.hidden = check.checked; };
        check.addEventListener('change', sync);
        sync();
    }

    /* =====================================================================
       EDITOR DEL MENU: arbol con drag & drop, formulario de nodo y vista previa.

       El orden se guarda por AJAX al soltar; el resto de acciones son
       formularios normales, para que funcionen tambien sin JavaScript.
       ===================================================================== */

    function initMenuEditor() {
        var editor = document.querySelector('[data-menu-editor]');
        if (!editor) { return; }

        var base = editor.getAttribute('data-menu-base') || (CFG.base + '/panel/menu');
        var dialog = document.querySelector('[data-menu-dialog]');
        var form = document.querySelector('[data-menu-form]');
        var status = document.querySelector('[data-menu-status]');
        var dragged = null;

        var aviso = function (texto, esError) {
            if (!status) { return; }
            status.textContent = texto;
            status.style.color = esError ? 'var(--danger)' : '';
        };

        /* Datos del nodo: se leen del propio DOM (nombre, id y padre). */
        var datosNodo = function (li) {
            var lista = li.parentNode;
            var etiqueta = li.querySelector('.mn-node-label strong') || li.querySelector('.mn-node-label span');
            var padreLi = lista.parentNode && lista.parentNode.classList.contains('mn-node') ? lista.parentNode : null;
            return {
                id: li.getAttribute('data-id'),
                label: etiqueta ? etiqueta.textContent.trim() : '',
                parent: padreLi ? padreLi.getAttribute('data-id') : '',
                nivel: parseInt(lista.getAttribute('data-menu-level'), 10) || 1
            };
        };

        var abrirFormulario = function (nodo) {
            if (!dialog || !form) { return; }
            form.reset();
            form.action = base + '/nodo';
            form.querySelector('[name="id"]').value = nodo.id || '';
            form.querySelector('[name="parent_id"]').value = nodo.parent || '';
            var titulo = form.querySelector('[data-menu-dialog-title]');
            if (titulo) {
                titulo.textContent = nodo.id
                    ? 'Editar: ' + nodo.label
                    : (nodo.parent ? 'Nuevo nodo (nivel ' + (nodo.nivel + 1) + ')' : 'Nueva categoria');
            }
            var campoLabel = form.querySelector('[data-menu-field="label"]');
            if (nodo.id && campoLabel) { campoLabel.value = nodo.label; }
            if (dialog.showModal) { dialog.showModal(); } else { dialog.setAttribute('open', 'open'); }
            if (campoLabel) { campoLabel.focus(); }
        };

        document.addEventListener('click', function (e) {
            var editar = e.target.closest('[data-menu-edit]');
            if (editar) {
                e.preventDefault();
                var li = editar.closest('.mn-node');
                if (li) { abrirFormulario(datosNodo(li)); }
                return;
            }
            var nuevo = e.target.closest('[data-menu-new]');
            if (nuevo) {
                e.preventDefault();
                abrirFormulario({
                    id: '',
                    parent: nuevo.getAttribute('data-menu-parent') || '',
                    nivel: nuevo.getAttribute('data-menu-parent') ? 2 : 1,
                    label: ''
                });
                return;
            }
            if (e.target.closest('[data-menu-close]')) {
                e.preventDefault();
                if (dialog && dialog.close) { dialog.close(); }
                else if (dialog) { dialog.removeAttribute('open'); }
            }
        });

        /* Guardado del orden: una sola peticion con la lista nueva. */
        var guardarOrden = function (destino) {
            var ids = [].map.call(destino.children, function (li) { return parseInt(li.getAttribute('data-id'), 10); });
            var padreLi = destino.parentNode && destino.parentNode.classList.contains('mn-node') ? destino.parentNode : null;
            var cuerpo = {
                parent: padreLi ? parseInt(padreLi.getAttribute('data-id'), 10) : null,
                order: ids,
                _token: CFG.csrf || ''
            };

            aviso('Guardando orden...');
            fetch(base + '/orden', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'fetch',
                    'X-CSRF-Token': CFG.csrf || ''
                },
                body: JSON.stringify(cuerpo)
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    aviso(data && data.message ? data.message : 'Orden guardado.', !(data && data.ok));
                })
                .catch(function () {
                    aviso('No se ha podido guardar el orden: recarga la pagina.', true);
                });
        };

        editor.addEventListener('dragstart', function (e) {
            var li = e.target.closest('.mn-node');
            if (!li) { return; }
            dragged = li;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', li.getAttribute('data-id') || '');
            li.classList.add('is-dragging');
        });

        editor.addEventListener('dragend', function () {
            if (dragged) { dragged.classList.remove('is-dragging'); }
            dragged = null;
            editor.querySelectorAll('.is-drop').forEach(function (el) { el.classList.remove('is-drop'); });
        });

        editor.addEventListener('dragover', function (e) {
            if (!dragged) { return; }
            var sobre = e.target.closest('.mn-node');
            if (!sobre) { return; }

            if (sobre === dragged) { return; }

            // Mismo padre: reordenar (antes o despues segun la mitad del nodo).
            if (sobre.parentNode === dragged.parentNode) {
                e.preventDefault();
                var caja = sobre.getBoundingClientRect();
                var despues = (e.clientY - caja.top) > (caja.height / 2);
                var referencia = despues ? sobre.nextSibling : sobre;
                if (referencia !== dragged && referencia !== dragged.nextSibling) {
                    dragged.parentNode.insertBefore(dragged, referencia);
                    aviso('Suelta para guardar el orden nuevo.');
                }
                return;
            }

            // Otro padre: se permite soltarlo DENTRO (cambia de nivel).
            if (sobre.contains(dragged)) { return; }
            e.preventDefault();
            sobre.classList.add('is-drop');
        });

        editor.addEventListener('dragleave', function (e) {
            var sobre = e.target.closest('.mn-node');
            if (sobre) { sobre.classList.remove('is-drop'); }
        });

        editor.addEventListener('drop', function (e) {
            if (!dragged) { return; }
            var sobre = e.target.closest('.mn-node');
            if (sobre) { sobre.classList.remove('is-drop'); }

            if (sobre && sobre !== dragged && sobre.parentNode !== dragged.parentNode && !sobre.contains(dragged)) {
                e.preventDefault();
                var lista = sobre.querySelector(':scope > ul[data-menu-level]');
                if (lista) {
                    lista.appendChild(dragged);
                }
                guardarOrden(dragged.parentNode);
                return;
            }

            if (sobre && sobre.parentNode === dragged.parentNode) {
                e.preventDefault();
                guardarOrden(dragged.parentNode);
            }
        });
    }

    /** La vista previa cambia de estilo sin recargar el panel. */
    function initMenuPreview() {
        var botones = document.querySelectorAll('[data-menu-preview-style]');
        if (!botones.length) { return; }

        botones.forEach(function (boton) {
            boton.addEventListener('click', function () {
                var estilo = boton.getAttribute('data-menu-preview-style');
                document.querySelectorAll('[data-menu-frame]').forEach(function (frame) {
                    var url = new URL(frame.getAttribute('src'), window.location.origin);
                    url.searchParams.set('estilo', estilo);
                    frame.setAttribute('src', url.toString());
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.uploader').forEach(initUploader);

        var designForm = document.querySelector('[data-design-form]');
        if (designForm) {
            initDesignPreview(designForm);
        }

        initLineEditors();
        initOrderSend();
        initShipToggle();
        initMenuEditor();
        initMenuPreview();
    });
})();
