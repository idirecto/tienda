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

<section class="card">
    <h2>Cuenta en idirecto (mayorista)</h2>
    <p class="muted small">
        Para enviar los pedidos de tus clientes al mayorista, indica con que cuenta compras.
        El id de cuenta es el que aparece en idirecto (<code>tiendas.id</code>) y la tarifa es
        el margen con el que te factura (<code>precios.id_margen</code>). Si dejas la tarifa
        vacia se usa la de la propia cuenta.
    </p>

    <?php if (!$idirectoReady): ?>
        <div class="alert alert-error">
            No se encuentran las tablas de pedidos del mayorista, asi que el envio esta desactivado.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
        <?= Csrf::field() ?>
        <div class="field-grid">
            <label>Id de cuenta en idirecto
                <input type="number" name="id_tienda_idirecto" min="0" step="1"
                       value="<?= (int) ($store['id_tienda_idirecto'] ?? 0) ?>">
            </label>
            <label>Tarifa / margen (opcional)
                <input type="number" name="id_margen" min="0" step="1"
                       value="<?= (int) ($store['id_margen'] ?? 0) ?>">
            </label>
        </div>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Guardar cuenta</button>
        </div>
    </form>

    <?php if (!empty($account['configurada'])): ?>
        <div class="hint-box">
            <strong>Cuenta <?= (int) $account['id_tienda'] ?>: <?= e($account['razon_social'] ?: $account['nombre']) ?></strong>
            <p>
                NIF/CIF: <?= e($account['nif'] ?: 'sin datos') ?> ·
                Tarifa efectiva: <strong><?= (int) $account['id_margen'] ?></strong>
                <span class="muted small">(<?= e($account['margen_origen'] === 'tienda' ? 'puesta en la tienda' : ($account['margen_origen'] === 'cuenta' ? 'la de la cuenta' : 'por defecto')) ?>)</span>
            </p>
            <p class="muted small">
                Los pedidos se facturan a: <?= e($account['razon_social']) ?> ·
                <?= e($account['direccion'] ?: 'sin direccion') ?>
                <?= e(trim(($account['cp'] ?? '') . ' ' . ($account['poblacion'] ?? ''))) ?>
            </p>
        </div>
    <?php endif; ?>
</section>
