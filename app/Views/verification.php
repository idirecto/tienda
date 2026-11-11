<?php
/**
 * Pantalla de verificacion del alta.
 *
 * @var array $registration
 * @var string $uuid
 * @var string $masked_email
 * @var int|null $attempts_left
 * @var bool $can_verify
 * @var string $expires_label
 * @var string $base
 */
use Tienda\Core\Csrf;
?>
<div class="auth-head">
    <h1>Confirma tu correo</h1>
    <p class="muted">
        Hemos enviado un codigo a <strong><?= e($masked_email) ?></strong>.
        Introduce el codigo para activar la tienda.
    </p>
</div>

<?php if (!$can_verify): ?>
    <div class="alert alert-warning">
        Este alta ya no esta pendiente de verificacion. Si necesitas ayuda, contacta con el soporte.
    </div>
    <p class="auth-hint"><a href="<?= e($base) ?>/registro">Volver al formulario de alta</a></p>
<?php else: ?>
    <?php if ($attempts_left !== null): ?>
        <p class="muted small">
            Intentos disponibles con el codigo actual: <strong><?= (int) $attempts_left ?></strong>.
        </p>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/registro/verificar/<?= rawurlencode($uuid) ?>" class="auth-form">
        <?= Csrf::field() ?>
        <label>Codigo recibido por correo
            <input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code"
                   pattern="[0-9]{6,12}" minlength="6" maxlength="12" autofocus
                   placeholder="12345678">
        </label>
        <button class="btn btn-primary btn-block" type="submit">Confirmar y activar la tienda</button>
    </form>

    <form method="post" action="<?= e($base) ?>/registro/reenviar/<?= rawurlencode($uuid) ?>" class="auth-form">
        <?= Csrf::field() ?>
        <button class="btn btn-block" type="submit">Enviar un codigo nuevo</button>
    </form>

    <?php if ($expires_label !== ''): ?>
        <p class="muted small">El alta caduca el <?= e($expires_label) ?>.</p>
    <?php endif; ?>
    <p class="muted small">
        Al enviar un codigo nuevo, el anterior deja de funcionar. El contrato aceptado sigue adjunto
        al correo de confirmacion.
    </p>
    <p class="auth-hint"><a href="<?= e($base) ?>/registro">Volver al formulario de alta</a></p>
<?php endif; ?>
