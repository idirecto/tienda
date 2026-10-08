<?php
/** Login del panel. @var string $base */
use Tienda\Core\Csrf;
?>
<div class="auth-head">
    <h1>Accede a tu tienda</h1>
    <p class="muted">Gestiona tu catalogo, diseno y pedidos.</p>
</div>

<form method="post" action="<?= e($base) ?>/panel/login" class="auth-form">
    <?= Csrf::field() ?>
    <label>Email
        <input type="email" name="email" required autofocus autocomplete="username">
    </label>
    <label>Contrasena
        <input type="password" name="password" required autocomplete="current-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Entrar</button>
</form>

<p class="auth-hint">
    ¿Todavia no tienes tienda? <a href="<?= e($base) ?>/registro">Registra la tuya con tu cuenta de idirecto</a>
</p>
