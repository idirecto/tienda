<?php
/**
 * Confirmacion final del alta.
 *
 * @var array $registration
 * @var array|null $store
 * @var string $store_url
 * @var string $base
 */
?>
<div class="auth-head">
    <h1>Tienda activada</h1>
    <p class="muted">Hemos verificado tu correo y ya puedes entrar en el panel.</p>
</div>

<div class="alert alert-success">
    La tienda<?= $store !== null ? ' <strong>' . e((string) $store['name']) . '</strong>' : '' ?>
    y su usuario estan activos.
</div>

<?php if ($store_url !== ''): ?>
    <p>Tu direccion publica es <a href="<?= e($store_url) ?>"><?= e($store_url) ?></a>.</p>
<?php endif; ?>

<p class="muted small">
    Conserva el contrato que se adjunto al correo de confirmacion. La aceptacion quedo registrada
    con su version, fecha y evidencia tecnica.
</p>

<p>
    <a class="btn btn-primary btn-block" href="<?= e($base) ?>/panel/login">Entrar en el panel</a>
</p>
<p class="auth-hint"><a href="<?= e($base) ?>/registro">Volver al formulario de alta</a></p>
