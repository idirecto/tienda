<?php
/**
 * Aviso y preferencias de cookies.
 *
 * Se incluye UNA sola vez desde `layouts/shop.php`. El banner se renderiza ya
 * visible en servidor cuando no hay una decision valida, de modo que no depende
 * de quitar `hidden` con JavaScript. Los formularios permiten aceptar, rechazar
 * y guardar preferencias sin JS; el JS solo mejora el dialogo y bloquea terceros.
 *
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 * @var array{decided:bool,analytics:bool,marketing:bool,external:bool} $cookieState
 * @var bool $showCookieBanner
 */
use Tienda\Core\Csrf;

$consentState = $cookieState ?? \Tienda\Core\CookieConsent::emptyState();
$showBanner = $showCookieBanner ?? !$consentState['decided'];
$returnTo = (string) ($_SERVER['REQUEST_URI'] ?? ($base . '/'));
$consentUrl = $base . '/cookies/consent';
$policyUrl = $base . '/cookies';
?>
<div id="idc-cookie-banner" class="idc-banner" role="region"
     aria-labelledby="idc-banner-title" aria-describedby="idc-banner-text"
     <?= $showBanner ? '' : 'hidden' ?>>
    <div class="idc-banner__inner">
        <h2 id="idc-banner-title" class="idc-banner__title">Uso de cookies</h2>
        <p id="idc-banner-text" class="idc-banner__text">
            En <strong><?= e($tenant->brandName()) ?></strong> usamos cookies necesarias para que la tienda funcione.
            Si nos das permiso, tambien podemos usar cookies analiticas, de marketing o contenido externo.
            Puedes aceptarlas todas, rechazarlas o configurar tus preferencias. Tu decision se guarda durante
            6 meses y puedes cambiarla cuando quieras desde &laquo;Configurar cookies&raquo; al pie de la pagina.
            Mas informacion en la <a href="<?= e($policyUrl) ?>">politica de cookies</a>.
        </p>

        <form class="idc-banner__form" method="post" action="<?= e($consentUrl) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">
            <div class="idc-banner__actions">
                <button type="submit" name="decision" value="reject"
                        class="idc-btn idc-btn--choice" data-idc-action="reject">Rechazar todas</button>
                <a class="idc-btn idc-btn--ghost" href="<?= e($policyUrl) ?>#preferencias"
                   data-idc-action="configure" aria-haspopup="dialog">Configurar</a>
                <button type="submit" name="decision" value="accept"
                        class="idc-btn idc-btn--choice" data-idc-action="accept">Aceptar todas</button>
            </div>
        </form>
    </div>
</div>

<div id="idc-cookie-modal" class="idc-modal" hidden>
    <div class="idc-modal__backdrop" data-idc-close aria-hidden="true"></div>
    <div class="idc-modal__dialog" role="dialog" aria-modal="true"
         aria-labelledby="idc-modal-title" aria-describedby="idc-modal-intro" tabindex="-1">
        <form method="post" action="<?= e($consentUrl) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">

            <div class="idc-modal__header">
                <h2 id="idc-modal-title">Preferencias de cookies</h2>
                <button type="button" class="idc-modal__close" data-idc-close
                        aria-label="Cerrar preferencias de cookies">&times;</button>
            </div>

            <p id="idc-modal-intro" class="idc-modal__intro">
                Puedes activar o desactivar las cookies no necesarias. Las necesarias no se pueden desactivar
                porque son imprescindibles para la navegacion, el carrito y el proceso de compra.
            </p>

            <div class="idc-cats">
                <div class="idc-cat idc-cat--necessary">
                    <div>
                        <p class="idc-cat__title">Necesarias <span class="idc-badge">Siempre activas</span></p>
                        <p class="idc-cat__desc">
                            Permiten la navegacion, mantener la sesion, el carrito, el inicio de sesion,
                            la seguridad de los formularios y completar la compra.
                        </p>
                    </div>
                    <label class="idc-cat__control">
                        <input type="checkbox" checked disabled aria-label="Cookies necesarias, siempre activas">
                        <span>Activas</span>
                    </label>
                </div>

                <div class="idc-cat">
                    <div>
                        <p class="idc-cat__title">Analiticas</p>
                        <p class="idc-cat__desc" id="idc-cat-analytics-desc">
                            Nos ayudan a medir de forma agregada como se usa la web para mejorarla.
                            Solo se activan si las permites.
                        </p>
                    </div>
                    <label class="idc-cat__control" for="idc-consent-analytics">
                        <input type="checkbox" id="idc-consent-analytics" name="analytics" value="1"
                               aria-describedby="idc-cat-analytics-desc"
                               <?= $consentState['analytics'] ? 'checked' : '' ?>>
                        <span>Permitir</span>
                    </label>
                </div>

                <div class="idc-cat">
                    <div>
                        <p class="idc-cat__title">Marketing</p>
                        <p class="idc-cat__desc" id="idc-cat-marketing-desc">
                            Reservada para pixeles o campanas publicitarias. Actualmente no se carga
                            ningun servicio de marketing sin tu permiso.
                        </p>
                    </div>
                    <label class="idc-cat__control" for="idc-consent-marketing">
                        <input type="checkbox" id="idc-consent-marketing" name="marketing" value="1"
                               aria-describedby="idc-cat-marketing-desc"
                               <?= $consentState['marketing'] ? 'checked' : '' ?>>
                        <span>Permitir</span>
                    </label>
                </div>

                <div class="idc-cat">
                    <div>
                        <p class="idc-cat__title">Contenido externo</p>
                        <p class="idc-cat__desc" id="idc-cat-external-desc">
                            Permite cargar videos de YouTube y mapas de Google. Estos proveedores pueden
                            instalar cookies cuando se muestra el contenido. Si no lo permites, podras
                            cargar cada contenido de forma puntual desde su boton.
                        </p>
                    </div>
                    <label class="idc-cat__control" for="idc-consent-external">
                        <input type="checkbox" id="idc-consent-external" name="external" value="1"
                               aria-describedby="idc-cat-external-desc"
                               <?= $consentState['external'] ? 'checked' : '' ?>>
                        <span>Permitir</span>
                    </label>
                </div>
            </div>

            <p class="idc-modal__note">
                Puedes cambiar o retirar tu consentimiento en cualquier momento desde el enlace
                &laquo;Configurar cookies&raquo; del pie de pagina. Mas informacion en la
                <a href="<?= e($policyUrl) ?>">politica de cookies</a>.
            </p>

            <div class="idc-modal__actions">
                <button type="submit" name="decision" value="reject"
                        class="idc-btn idc-btn--choice" data-idc-action="reject">Rechazar todas</button>
                <button type="submit" name="decision" value="save"
                        class="idc-btn idc-btn--ghost" data-idc-action="save">Guardar seleccion</button>
                <button type="submit" name="decision" value="accept"
                        class="idc-btn idc-btn--choice" data-idc-action="accept">Aceptar todas</button>
            </div>
        </form>
    </div>
</div>

<p id="idc-live-status" class="sr-only" role="status" aria-live="polite"></p>
