<?php
/**
 * Mis pedidos.
 *
 * @var array $customer @var array $orders @var string $base
 */
use Tienda\Models\Order;
?>
<section class="container section">
    <div class="page-head">
        <h1>Mis pedidos</h1>
        <p class="muted">Puedes consultar aqui el estado y el detalle de tus compras.</p>
    </div>

    <?php if ($orders === []): ?>
        <div class="panel-box">
            <p class="muted">Todavia no has hecho ningun pedido.</p>
            <a class="btn btn-primary" href="<?= e($base) ?>/catalogo">Ver el catalogo</a>
        </div>
    <?php else: ?>
        <table class="shop-table">
            <thead>
                <tr>
                    <th>Pedido</th><th>Fecha</th><th>Estado</th><th>Pago</th><th class="ta-r">Total</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <tr>
                    <td><strong><?= e($order['code']) ?></strong></td>
                    <td><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
                    <td><span class="pill pill-info"><?= e(Order::statusLabel((int) $order['status'])) ?></span></td>
                    <td>
                        <?= e(Order::paymentLabel((string) $order['payment_method'])) ?><br>
                        <small class="muted"><?= e(Order::paymentStatusLabel((int) $order['payment_status'])) ?></small>
                    </td>
                    <td class="ta-r"><strong><?= e(euros((float) $order['total'])) ?></strong></td>
                    <td class="ta-r"><a class="link" href="<?= e($base) ?>/cuenta/pedidos/<?= e($order['code']) ?>">Ver <?= icon_svg('arrow-r') ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
