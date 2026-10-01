<?php
/**
 * Mi cuenta: resumen del cliente (ultimos pedidos y direcciones).
 *
 * @var array $customer @var array $orders @var array $addresses
 * @var int $totalOrders @var int $cartUnits @var string $base
 */
use Tienda\Core\Csrf;

$esInvitado = (int) ($customer['is_guest'] ?? 0) === 1;
?>
<section class="container section">
    <div class="page-head">
        <h1>Mi cuenta</h1>
        <p class="muted">Hola, <?= e($customer['name']) ?> · <?= e($customer['email']) ?></p>
    </div>

    <?php if ($esInvitado): ?>
        <div class="alert alert-error">
            Has comprado como invitado. <a href="<?= e($base) ?>/cuenta/perfil">Pon una contrasena</a>
            para poder consultar tus pedidos cuando quieras.
        </div>
    <?php endif; ?>

    <div class="account-grid">
        <div class="panel-box">
            <h2>Mis pedidos (<?= (int) $totalOrders ?>)</h2>
            <?php if ($orders === []): ?>
                <p class="muted">Todavia no has hecho ningun pedido.</p>
            <?php else: ?>
                <table class="shop-table">
                    <thead><tr><th>Pedido</th><th>Fecha</th><th>Estado</th><th class="ta-r">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><a href="<?= e($base) ?>/cuenta/pedidos/<?= e($order['code']) ?>"><?= e($order['code']) ?></a></td>
                            <td><?= e(date('d/m/Y', strtotime((string) $order['created_at']))) ?></td>
                            <td>
                                <span class="pill pill-info"><?= e(\Tienda\Models\Order::statusLabel((int) $order['status'])) ?></span>
                                <?php if ((int) $order['payment_status'] !== 1): ?>
                                    <br><small class="muted"><?= e(\Tienda\Models\Order::paymentLabel((string) $order['payment_method'])) ?> pendiente</small>
                                <?php endif; ?>
                            </td>
                            <td class="ta-r"><?= e(euros((float) $order['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><a class="link" href="<?= e($base) ?>/cuenta/pedidos">Ver todos <?= icon_svg('arrow-r') ?></a></p>
            <?php endif; ?>
        </div>

        <div class="panel-box">
            <h2>Mis direcciones (<?= count($addresses) ?>)</h2>
            <?php if ($addresses === []): ?>
                <p class="muted">No tienes direcciones guardadas.</p>
            <?php else: ?>
                <ul class="address-list">
                    <?php foreach ($addresses as $address): ?>
                        <li>
                            <strong><?= e($address['name']) ?></strong><?= !empty($address['label']) ? ' · ' . e($address['label']) : '' ?>
                            <?= (int) $address['is_default_ship'] === 1 ? ' <span class="pill pill-info">Envio por defecto</span>' : '' ?>
                            <br><small><?= e($address['address']) ?><?= $address['detail'] ? ', ' . e($address['detail']) : '' ?></small>
                            <br><small><?= e($address['postal_code']) ?> <?= e($address['city']) ?>, <?= e($address['province']) ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <a class="btn btn-ghost btn-block" href="<?= e($base) ?>/cuenta/direcciones">Gestionar direcciones</a>
        </div>
    </div>

    <div class="account-links">
        <a class="link" href="<?= e($base) ?>/cuenta/perfil">Mis datos</a>
        <a class="link" href="<?= e($base) ?>/carrito">Mi carrito<?= $cartUnits > 0 ? ' (' . (int) $cartUnits . ')' : '' ?></a>
        <form method="post" action="<?= e($base) ?>/cuenta/salir">
            <?= Csrf::field() ?>
            <button class="link link-danger" type="submit">Cerrar sesion</button>
        </form>
    </div>
</section>
