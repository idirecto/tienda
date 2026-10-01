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

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.uploader').forEach(initUploader);

        var designForm = document.querySelector('[data-design-form]');
        if (designForm) {
            initDesignPreview(designForm);
        }
    });
})();
