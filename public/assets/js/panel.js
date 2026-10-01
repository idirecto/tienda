/**
 * Panel: subida de imagenes por AJAX y utilidades de UI.
 * Requiere window.TIENDA = { base, csrf, uploadUrl } (definido en el layout).
 */
(function () {
    'use strict';

    var CFG = window.TIENDA || {};

    /**
     * Sube un fichero al endpoint de media y rellena los campos indicados.
     */
    function uploadFile(file, opts) {
        var body = new FormData();
        body.append('file', file);
        body.append('folder', opts.folder || 'general');
        body.append('_token', CFG.csrf || '');

        var status = opts.statusEl ? document.querySelector(opts.statusEl) : null;
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
            if (status) { status.textContent = 'Imagen subida correctamente.'; }
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

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.uploader').forEach(initUploader);
    });
})();
