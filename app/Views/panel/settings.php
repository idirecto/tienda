<?php
/**
 * Ajustes de la tienda y usuarios.
 * @var array $store @var array $users @var string $base
 */
use Tienda\Core\Csrf;
$v = static fn (string $k, string $d = '') => e($store[$k] ?? $d);
?>
<section class="two-col">
    <div class="card">
        <h2>Datos de la tienda</h2>
        <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
            <?= Csrf::field() ?>
            <div class="field-grid">
                <label>Nombre comercial<input type="text" name="name" value="<?= $v('name') ?>" required></label>
                <label>Razon social<input type="text" name="legal_name" value="<?= $v('legal_name') ?>"></label>
                <label>Email<input type="email" name="email" value="<?= $v('email') ?>"></label>
                <label>Telefono<input type="text" name="phone" value="<?= $v('phone') ?>"></label>
                <label>Codigo postal<input type="text" name="postal_code" value="<?= $v('postal_code') ?>"></label>
                <label>Ciudad<input type="text" name="city" value="<?= $v('city') ?>"></label>
                <label>Provincia<input type="text" name="province" value="<?= $v('province') ?>"></label>
                <label>IVA por defecto (%)<input type="text" name="tax_rate" value="<?= $v('tax_rate', '21') ?>"></label>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar datos</button>
            </div>
        </form>

        <div class="hint-box">
            <strong>Tu direccion web</strong>
            <p>Subdominio: <code><?= e($store['slug'] ?? '') ?></code></p>
            <p class="muted small">Plan actual: <strong><?= e($store['plan_name'] ?? 'Sin plan') ?></strong></p>
        </div>
    </div>

    <div class="card">
        <h2>Usuarios del panel</h2>
        <ul class="list">
            <?php foreach ($users as $u): ?>
                <li class="list-item">
                    <div class="avatar"><?= e(mb_substr((string) $u['name'], 0, 1)) ?></div>
                    <div class="list-body">
                        <strong><?= e($u['name']) ?></strong>
                        <small><?= e($u['email']) ?> · <?= e($u['role']) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <h3 class="subsection">Anadir usuario</h3>
        <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
            <?= Csrf::field() ?>
            <input type="hidden" name="form" value="user">
            <label>Nombre<input type="text" name="name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Contrasena<input type="password" name="password" minlength="8" required></label>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Crear usuario</button>
            </div>
        </form>
    </div>
</section>
