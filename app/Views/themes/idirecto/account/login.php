<?php
/**
 * Entrada del cliente de la tienda.
 *
 * @var string $email @var string $returnTo @var string $base
 */
use Tienda\Core\Csrf;
?>
<section class="container section section-narrow">
    <div class="page-head">
        <h1>Entrar en tu cuenta</h1>
        <p class="muted">Para ver tus pedidos y no volver a escribir tus direcciones.</p>
    </div>

    <form class="form-card account-form" method="post" action="<?= e($base) ?>/cuenta/login">
        <?= Csrf::field() ?>
        <input type="hidden" name="volver" value="<?= e($returnTo) ?>">

        <label>Email
            <input type="email" name="email" required maxlength="180" value="<?= e($email) ?>" autofocus autocomplete="email">
        </label>
        <label>Contrasena
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Entrar</button>
    </form>

    <p class="account-alt">
        ¿Todavia no tienes cuenta?
        <a href="<?= e($base) ?>/cuenta/registro<?= $returnTo !== '' ? '?volver=/' . e($returnTo) : '' ?>">Creala en un minuto</a>
    </p>
    <p class="account-alt muted">
        ¿Has comprado como invitado? Crea la cuenta con el <strong>mismo email</strong> y conservaras tus pedidos.
    </p>
</section>
