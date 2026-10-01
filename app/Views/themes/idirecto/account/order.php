<?php
/**
 * Detalle de un pedido del cliente.
 *
 * @var array $customer @var array $order @var array $store @var string $base
 */
use Tienda\Models\Order;

$items = (array) ($order['items'] ?? []);
?>
<section class="container section">
    <div class="page-head">
        <h1>Pedido <?= e($order['code']) ?></h1>
        <p class="muted">
            <?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?> ·
            <span class="pill pill-info"><?= e(Order::statusLabel((int) $order['status'])) ?></span> ·
            <?= e(Order::paymentLabel((string) $order['payment_method'])) ?>
            (<?= e(Order::paymentStatusLabel((int) $order['payment_status'])) ?>)
        </p>
    </div>

    <div class="thanks-grid">
        <div class="panel-box">
            <h2>Productos</h2>
            <table class="shop-table">
                <thead><tr><th>Producto</th><th class="ta-c">Cant.</th><th class="ta-r">Precio</th><th class="ta-r">Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $item): ?>
                    <?php $bruto = (float) $item['price_customer'] * (1 + (float) $item['tax_rate'] / 100); ?>
                    <tr>
                        <td><?= e($item['name']) ?><?php if (!empty($item['sku'])): ?><br><small class="muted"><?= e($item['sku']) ?></small><?php endif; ?></td>
                        <td class="ta-c"><?= (int) $item['qty'] ?></td>
                        <td class="ta-r"><?= e(euros($bruto)) ?></td>
                        <td class="ta-r"><strong><?= e(euros($bruto * (int) $item['qty'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <dl class="totals">
                <div><dt>Productos</dt><dd><?= e(euros((float) $order['subtotal'] + (float) $order['tax_total'])) ?></dd></div>
                <div><dt>Gastos de envio</dt><dd><?= (float) $order['shipping'] > 0 ? e(euros($order['shipping'])) : 'Gratis' ?></dd></div>
                <div class="totals-grand"><dt>Total</dt><dd><?= e(euros($order['total'])) ?></dd></div>
            </dl>
        </div>

        <div class="panel-box">
            <h2>Envio</h2>
            <p>
                <?= e($order['ship_name'] ?? '') ?><br>
                <?= e($order['ship_address'] ?? '') ?><?= $order['ship_detail'] ? ', ' . e($order['ship_detail']) : '' ?><br>
                <?= e($order['ship_postal_code'] ?? '') ?> <?= e($order['ship_city'] ?? '') ?><br>
                <?= e($order['ship_province'] ?? '') ?>
            </p>

            <?php if (!empty($order['bill_address']) && $order['bill_address'] !== $order['ship_address']): ?>
                <h2>Facturacion</h2>
                <p>
                    <?= e($order['bill_name'] ?? '') ?><br>
                    <?= e($order['bill_address']) ?><br>
                    <?= e($order['bill_postal_code'] ?? '') ?> <?= e($order['bill_city'] ?? '') ?><br>
                    <?php if (!empty($order['bill_tax_id'])): ?><small class="muted"><?= e($order['bill_tax_id']) ?></small><?php endif; ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($order['customer_note'])): ?>
                <h2>Tu comentario</h2>
                <p class="muted"><?= nl2br(e((string) $order['customer_note'])) ?></p>
            <?php endif; ?>

            <a class="btn btn-ghost btn-block" href="<?= e($base) ?>/cuenta/pedidos">Volver a mis pedidos</a>
        </div>
    </div>
</section>
