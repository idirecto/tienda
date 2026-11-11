/**
 * Habilitacion progresiva del boton de alta.
 *
 * El servidor vuelve a validar todo. Este script solo evita que el boton se
 * pueda pulsar mientras falten campos, las contrasenas no coincidan o no se
 * haya marcado la aceptacion expresa.
 */
(function () {
    'use strict';

    function init() {
        var form = document.querySelector('[data-registration-form]');
        if (!form) {
            return;
        }

        var button = form.querySelector('[data-registration-submit]');
        var password = form.querySelector('input[name="password"]');
        var passwordConfirm = form.querySelector('input[name="password_confirm"]');
        var acceptance = form.querySelector('input[name="contract_accepted"]');
        if (!button || !password || !passwordConfirm) {
            return;
        }

        var ready = form.getAttribute('data-ready') === '1';
        var contractAvailable = form.getAttribute('data-contract-available') === '1';

        function update() {
            var valid = ready && contractAvailable && form.checkValidity();
            if (acceptance !== null && !acceptance.checked) {
                valid = false;
            }
            if (password.value !== passwordConfirm.value) {
                valid = false;
            }
            button.disabled = !valid;
            button.setAttribute('aria-disabled', valid ? 'false' : 'true');
        }

        form.addEventListener('input', update);
        form.addEventListener('change', update);
        update();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
