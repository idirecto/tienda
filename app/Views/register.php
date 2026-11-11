<?php
/**
 * Registro de una tienda nueva.
 *
 * El formulario se prepara completo, pero el envio solo puede habilitarse
 * cuando existe una version contractual vigente autorizada y el flujo de
 * verificacion esta habilitado.
 *
 * @var bool $open
 * @var bool $ready
 * @var array|null $contract
 * @var string $slug
 * @var string $base
 */
use Tienda\Core\Csrf;
use Tienda\Core\Subdomain;

$baseDomain = Subdomain::baseDomain();
$effectiveDate = $contract !== null ? strtotime((string) ($contract['effective_date'] ?? '')) : false;
$effectiveLabel = $effectiveDate !== false ? date('d/m/Y', $effectiveDate) : '';
?>
<div class="auth-head">
    <h1>Crea tu tienda</h1>
    <p class="muted">Para clientes de idirecto: entra con tu cuenta del mayorista.</p>
</div>

<?php if (!$open): ?>
    <div class="alert alert-error">
        El registro de tiendas esta cerrado en este momento. Si ya tienes tienda, entra en el panel;
        si no, contacta con idirecto.
    </div>
    <p class="auth-hint"><a href="<?= e($base) ?>/panel/login">Entrar en el panel</a></p>
<?php else: ?>
    <?php if (!$ready): ?>
        <div class="alert alert-warning">
            <?php if (!empty($guard_closed)): ?>
                El alta está preparada, pero permanece cerrada temporalmente hasta la confirmación
                del responsable. No se creará ninguna cuenta.
            <?php else: ?>
                El alta todavía no está habilitada. Falta publicar una versión contractual vigente
                autorizada o completar la verificación por correo. No se creará ninguna cuenta.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/registro" class="auth-form"
          data-registration-form data-ready="<?= $ready ? '1' : '0' ?>"
          data-contract-available="<?= $contract !== null ? '1' : '0' ?>">
        <?= Csrf::field() ?>

        <div class="auth-sep"></div>
        <h2 class="auth-section-title">Cuenta de idirecto</h2>

        <label>Correo corporativo (el de tu cuenta en idirecto)
            <input type="email" name="email" required autofocus maxlength="60"
                   value="<?= e($_POST['email'] ?? '') ?>" placeholder="tu@empresa.com"
                   autocomplete="email">
        </label>
        <p class="muted small">
            Este correo debe coincidir con el de la cuenta del cliente en IDIRECTO. No se admite un correo distinto.
        </p>
        <label>Contrasena de idirecto
            <input type="password" name="account_password" required autocomplete="current-password">
        </label>
        <p class="muted small">
            Solo se comprueba que la cuenta es tuya: no guardamos esta contrasena.
        </p>

        <div class="auth-sep"></div>
        <h2 class="auth-section-title">Persona que realiza el alta</h2>

        <div class="auth-grid">
            <label>Nombre
                <input type="text" name="representative_first_name" required maxlength="80"
                       value="<?= e($_POST['representative_first_name'] ?? '') ?>"
                       autocomplete="given-name">
            </label>
            <label>Apellidos
                <input type="text" name="representative_last_name" required maxlength="120"
                       value="<?= e($_POST['representative_last_name'] ?? '') ?>"
                       autocomplete="family-name">
            </label>
        </div>

        <div class="auth-grid">
            <label>Cargo en la empresa
                <input type="text" name="representative_role" required maxlength="120"
                       value="<?= e($_POST['representative_role'] ?? '') ?>"
                       autocomplete="organization-title">
            </label>
            <label>Teléfono
                <input type="tel" name="representative_phone" required maxlength="40"
                       value="<?= e($_POST['representative_phone'] ?? '') ?>"
                       autocomplete="tel">
            </label>
        </div>

        <div class="auth-sep"></div>
        <h2 class="auth-section-title">Datos de la tienda</h2>

        <label>Direccion de tu tienda (opcional)
            <input type="text" name="slug" minlength="3" maxlength="40"
                   pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?"
                   value="<?= e($slug) ?>" placeholder="mi-tienda" autocomplete="off" spellcheck="false">
        </label>
        <p class="muted small">
            Sera <code><?= e(Subdomain::scheme()) ?>://&lt;direccion&gt;.<?= e($baseDomain) ?></code>. Si lo dejas vacio,
            la generamos a partir del nombre de tu cuenta. Solo letras, numeros y guiones.
        </p>

        <label>Contrasena para este panel
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
        </label>
        <label>Repite la contrasena del panel
            <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
        </label>

        <?php if ($contract !== null): ?>
            <div class="auth-contract">
                <p>
                    <a href="<?= e($base) ?>/registro/contrato" target="_blank" rel="noopener">
                        Consultar y descargar el Contrato de acceso y uso de la web
                    </a>
                </p>
                <p class="muted small">
                    Versión <?= e((string) ($contract['version_label'] ?? '')) ?><?= $effectiveLabel !== '' ? ' · vigente desde ' . e($effectiveLabel) : '' ?>.
                </p>
            </div>

            <input type="hidden" name="contract_version_id" value="<?= (int) $contract['id'] ?>">

            <label class="auth-check">
                <input type="checkbox" name="contract_accepted" value="1" required>
                <span>Declaro que estoy autorizado/a para activar y utilizar la cuenta web en nombre del cliente de IDIRECTO asociado a este acceso. He leído y acepto el <a href="<?= e($base) ?>/registro/contrato" target="_blank" rel="noopener">Contrato de acceso y uso de la web</a> en nombre de dicho cliente. La aceptación quedará registrada electrónicamente y se enviará una confirmación al correo indicado</span>
            </label>
        <?php else: ?>
            <div class="alert alert-warning">
                El contrato todavía no está disponible. La aceptación permanecerá oculta y el alta no podrá enviarse
                hasta que exista una versión vigente autorizada.
            </div>
        <?php endif; ?>

        <button class="btn btn-primary btn-block" type="submit" data-registration-submit disabled>
            Crear Cuenta y aceptar contrato
        </button>
        <noscript>
            <p class="muted small">Activa JavaScript para poder habilitar el envío con las validaciones del formulario.</p>
        </noscript>
    </form>

    <p class="auth-hint">
        ¿Ya tienes tienda? <a href="<?= e($base) ?>/panel/login">Entra en el panel</a>
    </p>
<?php endif; ?>
