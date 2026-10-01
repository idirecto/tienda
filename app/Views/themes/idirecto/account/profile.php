<?php
/**
 * Mis datos: nombre, contacto y contrasena.
 *
 * @var array $customer @var string $base
 */
use Tienda\Core\Csrf;

$esInvitado = (int) ($customer['is_guest'] ?? 0) === 1;
?>
<section class="container section section-narrow">
    <div class="page-head">
        <h1>Mis datos</h1>
        <p class="muted"><?= e($customer['email']) ?></p>
    </div>

    <form class="form-card" method="post" action="<?= e($base) ?>/cuenta/perfil">
        <?= Csrf::field() ?>

        <label>Nombre y apellidos
            <input type="text" name="name" required maxlength="150" value="<?= e($customer['name']) ?>">
        </label>
        <label>DNI / NIF
            <input type="text" name="tax_id" maxlength="30" value="<?= e((string) ($customer['tax_id'] ?? '')) ?>">
        </label>
        <label>Telefono
            <input type="text" name="phone" maxlength="40" value="<?= e((string) ($customer['phone'] ?? '')) ?>">
        </label>
        <label>Movil
            <input type="text" name="mobile" maxlength="40" value="<?= e((string) ($customer['mobile'] ?? '')) ?>">
        </label>

        <h2><?= $esInvitado ? 'Pon una contrasena' : 'Cambiar contrasena' ?></h2>
        <p class="muted small">
            <?= $esInvitado
                ? 'Compraste como invitado: con una contrasena podras entrar cuando quieras y ver tus pedidos.'
                : 'Dejalo en blanco si no quieres cambiarla.' ?>
        </p>
        <label>Nueva contrasena
            <input type="password" name="password" minlength="8" autocomplete="new-password">
        </label>
        <label>Repite la contrasena
            <input type="password" name="password_confirm" minlength="8" autocomplete="new-password">
        </label>

        <button class="btn btn-primary" type="submit">Guardar</button>
        <a class="link" href="<?= e($base) ?>/cuenta">Volver a mi cuenta</a>
    </form>
</section>
