<?php
/**
 * Politica de cookies de la tienda.
 *
 * Los datos del responsable salen de `mt_stores` (a traves de Tenant) y el
 * NIF/CIF de `Account::forStore()` cuando existe. La tabla de cookies sale de
 * `config/cookies.php`. No hay datos legales fijos.
 *
 * @var array $policy
 * @var array{decided:bool,analytics:bool,marketing:bool,external:bool} $cookieState
 * @var string $base
 */
use Tienda\Core\Csrf;

$responsible = (array) ($policy['responsible'] ?? []);
$ownCookies = (array) ($policy['own_cookies'] ?? []);
$localStorage = (array) ($policy['local_storage'] ?? []);
$state = $cookieState ?? \Tienda\Core\CookieConsent::emptyState();
$returnTo = $base . '/cookies#preferencias';

$addressParts = array_filter([
    trim((string) ($responsible['address'] ?? '')),
    trim((string) ($responsible['locality'] ?? '')),
    trim((string) ($responsible['province'] ?? '')),
    trim((string) ($responsible['country'] ?? '')),
], static fn (string $value): bool => $value !== '');
?>
<section class="container section idc-policy">
    <div class="page-head">
        <h1>Politica de cookies</h1>
    </div>

    <?php if (!empty($policy['updated_at'])): ?>
        <p class="idc-policy-meta">Ultima actualizacion: <?= e($policy['updated_at']) ?></p>
    <?php endif; ?>

    <p>
        Esta politica explica que cookies y almacenamiento utiliza esta tienda, con que finalidad y como puedes
        aceptarlas, rechazarlas o cambiar tu decision en cualquier momento.
    </p>

    <h2>1. Responsable del tratamiento</h2>
    <p>
        El responsable es <strong><?= e((string) ($responsible['legal_name'] ?? '')) ?></strong>.
        <?php if (!empty($responsible['tax_id'])): ?>
            NIF/CIF: <strong><?= e((string) $responsible['tax_id']) ?></strong>.
        <?php endif; ?>
        <?php if ($addressParts !== []): ?>
            Domicilio: <?= e(implode(', ', $addressParts)) ?>.
        <?php endif; ?>
        <?php if (!empty($responsible['email'])): ?>
            Para consultas sobre cookies o privacidad puedes escribir a
            <a href="mailto:<?= e((string) $responsible['email']) ?>"><?= e((string) $responsible['email']) ?></a>.
        <?php endif; ?>
    </p>

    <h2>2. Que son las cookies</h2>
    <p>
        Las cookies son pequenos archivos que se descargan en tu dispositivo al visitar una pagina web. Permiten
        almacenar y recuperar informacion sobre la navegacion, como mantener la sesion, recordar tus preferencias o
        medir el uso del sitio. No danan el equipo.
    </p>

    <h2>3. Base legal y consentimiento</h2>
    <p>
        Aplicamos el Reglamento (UE) 2016/679 (RGPD), la Ley Organica 3/2018 (LOPDGDD), la Ley 34/2002 de Servicios
        de la Sociedad de la Informacion y de Comercio Electronico (LSSI) y la guia de la Agencia Espanola de
        Proteccion de Datos sobre el uso de cookies.
    </p>
    <p>
        Las cookies necesarias estan exentas de consentimiento porque permiten el funcionamiento basico del sitio.
        Las cookies analiticas, de marketing y el contenido externo solo se activan si las aceptas.
        <strong>No se cargan cookies no necesarias antes de que otorgues tu consentimiento</strong>. Rechazar es tan
        facil como aceptar, no hay casillas premarcadas y puedes retirar tu decision cuando quieras.
    </p>

    <h2>4. Categorias de cookies</h2>
    <ul>
        <li><strong>Necesarias:</strong> sesion, carrito, inicio de sesion, seguridad y proceso de compra. Siempre estan activas.</li>
        <li><strong>Analiticas:</strong> permiten medir de forma agregada como se utiliza la web. Solo se activan con consentimiento.</li>
        <li><strong>Marketing:</strong> reservada para pixeles o campanas publicitarias. Se activa solo con consentimiento.</li>
        <li><strong>Contenido externo:</strong> permite cargar videos de YouTube y mapas de Google, que pueden instalar cookies de terceros. Se activa con consentimiento o mediante una carga puntual solicitada por el visitante.</li>
    </ul>

    <h2>5. Cookies propias</h2>
    <div class="idc-table-wrap">
        <table class="idc-cookie-table">
            <caption>Cookies propias utilizadas por esta tienda</caption>
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Categoria</th>
                    <th scope="col">Finalidad</th>
                    <th scope="col">Duracion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ownCookies as $cookie): ?>
                    <tr>
                        <td><code><?= e((string) ($cookie['name'] ?? '')) ?></code></td>
                        <td><?= e((string) ($cookie['category'] ?? '')) ?></td>
                        <td><?= e((string) ($cookie['purpose'] ?? '')) ?></td>
                        <td><?= e((string) ($cookie['duration'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>6. Almacenamiento local</h2>
    <p>
        Ademas de cookies, el selector de modo claro/oscuro guarda una preferencia en el almacenamiento local del
        navegador. No se usa para identificar ni rastrear al visitante.
    </p>
    <div class="idc-table-wrap">
        <table class="idc-cookie-table">
            <caption>Almacenamiento funcional utilizado por esta tienda</caption>
            <thead>
                <tr>
                    <th scope="col">Nombre</th>
                    <th scope="col">Categoria</th>
                    <th scope="col">Finalidad</th>
                    <th scope="col">Duracion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($localStorage as $storage): ?>
                    <tr>
                        <td><code><?= e((string) ($storage['name'] ?? '')) ?></code></td>
                        <td><?= e((string) ($storage['category'] ?? '')) ?></td>
                        <td><?= e((string) ($storage['purpose'] ?? '')) ?></td>
                        <td><?= e((string) ($storage['duration'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2>7. Cookies de terceros</h2>
    <p>
        <strong>Actualmente no se cargan cookies de terceros.</strong> No hay activado ningun servicio de analitica,
        publicidad, YouTube ni Google Maps. Si en el futuro se activa alguno, solo se cargara con la categoria
        correspondiente y esta politica se actualizara con sus cookies y plazos.
    </p>

    <h2 id="preferencias">8. Como cambiar tus preferencias</h2>
    <p>
        Puedes aceptar todas las cookies, rechazarlas todas o elegir que categorias permites. Tambien puedes cambiar
        o retirar tu consentimiento en cualquier momento desde el enlace <strong>Configurar cookies</strong> del pie
        de pagina o desde este formulario:
    </p>

    <div class="idc-policy-actions">
        <a href="#preferencias" class="idc-btn idc-btn--ghost" data-idc-open aria-haspopup="dialog">Configurar mis preferencias</a>
    </div>

    <div class="idc-policy-preferences">
        <form method="post" action="<?= e($base) ?>/cookies/consent">
            <?= Csrf::field() ?>
            <input type="hidden" name="return" value="<?= e($returnTo) ?>">

            <div class="idc-cats">
                <div class="idc-cat idc-cat--necessary">
                    <div>
                        <p class="idc-cat__title">Necesarias <span class="idc-badge">Siempre activas</span></p>
                        <p class="idc-cat__desc">
                            Permiten la navegacion, la sesion, el carrito, el inicio de sesion y la seguridad de los
                            formularios.
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
                        <p class="idc-cat__desc" id="idc-page-cat-analytics-desc">
                            Nos ayudan a medir de forma agregada como se usa la web para mejorarla.
                        </p>
                    </div>
                    <label class="idc-cat__control" for="idc-page-consent-analytics">
                        <input type="checkbox" id="idc-page-consent-analytics" name="analytics" value="1"
                               aria-describedby="idc-page-cat-analytics-desc"
                               <?= $state['analytics'] ? 'checked' : '' ?>>
                        <span>Permitir</span>
                    </label>
                </div>

                <div class="idc-cat">
                    <div>
                        <p class="idc-cat__title">Marketing</p>
                        <p class="idc-cat__desc" id="idc-page-cat-marketing-desc">
                            Reservada para pixeles o campanas publicitarias. Actualmente no se carga ningun servicio.
                        </p>
                    </div>
                    <label class="idc-cat__control" for="idc-page-consent-marketing">
                        <input type="checkbox" id="idc-page-consent-marketing" name="marketing" value="1"
                               aria-describedby="idc-page-cat-marketing-desc"
                               <?= $state['marketing'] ? 'checked' : '' ?>>
                        <span>Permitir</span>
                    </label>
                </div>

                <div class="idc-cat">
                    <div>
                        <p class="idc-cat__title">Contenido externo</p>
                        <p class="idc-cat__desc" id="idc-page-cat-external-desc">
                            Permite cargar videos de YouTube y mapas de Google. Si no lo permites, podras cargar cada
                            contenido de forma puntual desde su boton.
                        </p>
                    </div>
                    <label class="idc-cat__control" for="idc-page-consent-external">
                        <input type="checkbox" id="idc-page-consent-external" name="external" value="1"
                               aria-describedby="idc-page-cat-external-desc"
                               <?= $state['external'] ? 'checked' : '' ?>>
                        <span>Permitir</span>
                    </label>
                </div>
            </div>

            <div class="idc-modal__actions">
                <button type="submit" name="decision" value="reject" class="idc-btn idc-btn--choice">Rechazar todas</button>
                <button type="submit" name="decision" value="save" class="idc-btn idc-btn--ghost">Guardar seleccion</button>
                <button type="submit" name="decision" value="accept" class="idc-btn idc-btn--choice">Aceptar todas</button>
            </div>

            <p class="idc-policy-note">
                La cookie de consentimiento se guarda durante 180 dias. Puedes borrarla o cambiarla en cualquier momento.
            </p>
        </form>
    </div>

    <h2>9. Como bloquear o eliminar cookies en el navegador</h2>
    <p>
        Puedes restringir, bloquear o borrar las cookies desde la configuracion de tu navegador. La forma de hacerlo
        varia segun el navegador; consulta su menu de ayuda. Si bloqueas las cookies necesarias, algunas funciones de
        la tienda, como el carrito o el inicio de sesion, pueden dejar de funcionar.
    </p>

    <h2>10. Actualizaciones</h2>
    <p>
        Podemos actualizar esta politica si cambian las cookies utilizadas o la normativa aplicable. La fecha de la
        ultima revision aparece al inicio de la pagina. Si cambia la version del consentimiento, volveremos a
        solicitarlo.
    </p>
</section>
