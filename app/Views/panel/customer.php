<?php
/**
 * Ficha de un cliente de la tienda.
 *
 * @var array $customer @var array $addresses @var array $orders @var string $base
 */
use Tienda\Models\Order;
?>
<section class="card">
    <div class="card-head">
        <div>
            <h2><?= e($customer['name'] !== '' ? $customer['name'] : $customer['email']) ?></h2>
            <p class="muted small">
                <?= e($customer['email']) ?>
                <?php if (!empty($customer['tax_id'])): ?> · <?= e($customer['tax_id']) ?><?php endif; ?>
                <?php if (!empty($customer['phone']) || !empty($customer['mobile'])): ?>
                    · <?= e($customer['phone'] ?: $customer['mobile']) ?>
                <?php endif; ?>
                <?php if ((int) $customer['is_guest'] === 1): ?>
                    <span class="pill pill-warning">Compro como invitado</span>
                <?php endif; ?>
            </p>
        </div>
        <div class="card-head-right">
            <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/clientes">&larr; Clientes</a>
        </div>
    </div>

    <p class="muted small">
        Cliente desde el <?= e(date('d/m/Y', strtotime((string) $customer['created_at']))) ?>.
        <?php if (!empty($customer['last_login_at'])): ?>
            Ultima entrada: <?= e(date('d/m/Y H:i', strtotime((string) $customer['last_login_at']))) ?>.
        <?php endif; ?>
        <br>Sus datos y direcciones los edita el propio cliente desde su cuenta en la web.
    </p>
</section>

<section class="two-col">
    <div class="card">
        <h2>Pedidos</h2>
        <?php if ($orders === []): ?>
            <p class="muted">Todavia no ha hecho pedidos.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Pedido</th><th>Fecha</th><th>Estado</th><th>Pago</th><th class="ta-r">Total</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><a href="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>"><?= e($order['code']) ?></a></td>
                        <td><small><?= e(date('d/m/Y', strtotime((string) $order['created_at']))) ?></small></td>
                        <td><span class="pill pill-<?= e(Order::statusPill((int) $order['status'])) ?>"><?= e(Order::statusLabel((int) $order['status'])) ?></span></td>
                        <td><small><?= e(Order::paymentStatusLabel((int) $order['payment_status'])) ?></small></td>
                        <td class="ta-r"><?= e(euros((float) $order['total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Direcciones</h2>
        <?php if ($addresses === []): ?>
            <p class="muted">No tiene direcciones guardadas.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($addresses as $address): ?>
                    <li class="list-item">
                        <div class="list-body">
                            <strong><?= e($address['name']) ?><?= !empty($address['label']) ? ' · ' . e($address['label']) : '' ?></strong>
                            <small>
                                <?= e($address['address']) ?><?= !empty($address['detail']) ? ', ' . e($address['detail']) : '' ?><br>
                                <?= e(trim(($address['postal_code'] ?? '') . ' ' . ($address['city'] ?? ''))) ?>,
                                <?= e($address['province'] ?? '') ?> · <?= e($address['country']) ?>
                                <?php if (!empty($address['phone']) || !empty($address['mobile'])): ?>
                                    <br><?= e($address['phone'] ?: $address['mobile']) ?>
                                <?php endif; ?>
                            </small>
                            <?php if ((int) $address['is_default_ship'] === 1): ?><span class="pill pill-info">Envio por defecto</span><?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
