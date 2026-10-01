<?php
/**
 * Registro del cliente de la tienda.
 *
 * @var string $email @var string $name @var string $returnTo @var string $base
 */
use Tienda\Core\Csrf;
?>
<section class="container section section-narrow">
    <div class="page-head">
        <h1>Crea tu cuenta</h1>
        <p class="muted">Guardamos tus direcciones para la proxima compra y puedes seguir tus pedidos.</p>
    </div>

    <form class="form-card account-form" method="post" action="<?= e($base) ?>/cuenta/registro">
        <?= Csrf::field() ?>
        <input type="hidden" name="volver" value="<?= e($returnTo) ?>">

        <label>Nombre y apellidos
            <input type="text" name="name" required maxlength="150" value="<?= e($name) ?>" autofocus autocomplete="name">
        </label>
        <label>Email
            <input type="email" name="email" required maxlength="180" value="<?= e($email) ?>" autocomplete="email">
        </label>
        <label>Telefono (opcional)
            <input type="text" name="phone" maxlength="40" autocomplete="tel">
        </label>
        <label>Contrasena
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
        </label>
        <label>Repite la contrasena
            <input type="password" name="password_confirm" minlength="8" required autocomplete="new-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Crear mi cuenta</button>
    </form>

    <p class="account-alt">
        ¿Ya tienes cuenta?
        <a href="<?= e($base) ?>/cuenta/login<?= $returnTo !== '' ? '?volver=/' . e($returnTo) : '' ?>">Entra aqui</a>
    </p>
</section>
