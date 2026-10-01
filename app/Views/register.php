<?php
/**
 * Registro de una tienda nueva.
 *
 * Solo pueden registrarse los clientes del mayorista: se comprueba su email y su
 * contrasena de idirecto contra `tiendas` y la tienda nace enlazada a esa cuenta
 * (y con su tarifa), con un usuario propio para este panel.
 *
 * @var bool $open Registro abierto @var string $slug @var string $base
 */
use Tienda\Core\Csrf;

$baseDomain = (string) (config('tenant.base_domains', ['localhost'])[0] ?? 'localhost');
?>
<div class="auth-head">
    <span class="logo-dot"></span>
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
    <form method="post" action="<?= e($base) ?>/registro" class="auth-form">
        <?= Csrf::field() ?>

        <label>Email de tu cuenta en idirecto
            <input type="email" name="email" required autofocus maxlength="60"
                   value="<?= e($_POST['email'] ?? '') ?>" placeholder="tu@email.com">
        </label>
        <label>Contrasena de idirecto
            <input type="password" name="account_password" required autocomplete="current-password">
        </label>
        <p class="muted small">
            Solo se comprueba que la cuenta es tuya: no guardamos esta contrasena.
        </p>

        <div class="auth-sep"></div>

        <label>Direccion de tu tienda (opcional)
            <input type="text" name="slug" maxlength="40" value="<?= e($slug) ?>" placeholder="mi-tienda">
        </label>
        <p class="muted small">
            Sera <code>https://&lt;direccion&gt;.<?= e($baseDomain) ?></code>. Si lo dejas vacio,
            la generamos a partir del nombre de tu cuenta.
        </p>

        <label>Contrasena para este panel
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
        </label>
        <label>Repite la contrasena del panel
            <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
        </label>

        <button class="btn btn-primary btn-block" type="submit">Crear mi tienda</button>
    </form>

    <p class="auth-hint">
        ¿Ya tienes tienda? <a href="<?= e($base) ?>/panel/login">Entra en el panel</a>
    </p>
<?php endif; ?>
