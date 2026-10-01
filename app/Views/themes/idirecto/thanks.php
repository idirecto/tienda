<?php
/**
 * Pedido registrado: resumen e instrucciones de pago.
 *
 * @var array $order @var array $store @var string $askedEmail @var string $base
 */
use Tienda\Models\Order;

$metodo = (string) ($order['payment_method'] ?? '');
$pendiente = (int) ($order['payment_status'] ?? 0) !== 1;
$items = (array) ($order['items'] ?? []);
?>
<section class="container section">
    <div class="page-head">
        <h1>Gracias, tu pedido esta registrado</h1>
        <p class="muted">
            Numero de pedido <strong><?= e($order['code']) ?></strong> ·
            <?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?>
        </p>
    </div>

    <div class="thanks-grid">
        <div class="panel-box">
            <h2>Resumen</h2>
            <ul class="checkout-lines">
                <?php foreach ($items as $item): ?>
                    <li>
                        <span class="checkout-line-name"><?= (int) $item['qty'] ?> x <?= e($item['name']) ?></span>
                        <span><?= e(euros((float) $item['qty'] * (float) $item['price_customer'] * (1 + (float) $item['tax_rate'] / 100))) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="totals">
                <div><dt>Productos</dt><dd><?= e(euros((float) $order['subtotal'] + (float) $order['tax_total'])) ?></dd></div>
                <div><dt>Gastos de envio</dt><dd><?= (float) $order['shipping'] > 0 ? e(euros($order['shipping'])) : 'Gratis' ?></dd></div>
                <div class="totals-grand"><dt>Total</dt><dd><?= e(euros($order['total'])) ?></dd></div>
            </dl>
            <p class="muted small">Los precios incluyen el IVA.</p>
        </div>

        <div class="panel-box">
            <h2>Como pagar</h2>
            <p><strong><?= e(Order::paymentLabel($metodo)) ?></strong> ·
                <?= e(Order::paymentStatusLabel((int) $order['payment_status'])) ?></p>

            <?php if ($pendiente && $metodo === Order::PAY_TRANSFER): ?>
                <?php if (!empty($store['bank_details'])): ?>
                    <div class="bank-box"><?= nl2br(e((string) $store['bank_details'])) ?></div>
                <?php else: ?>
                    <p class="muted">La tienda te enviara los datos de la transferencia por email.</p>
                <?php endif; ?>
            <?php elseif ($pendiente && $metodo === Order::PAY_COD): ?>
                <p class="muted">Pagas al repartidor cuando recibas el pedido.</p>
            <?php elseif ($pendiente && $metodo === Order::PAY_PICKUP): ?>
                <p class="muted">Te avisamos cuando puedas pasar a recogerlo. Direccion: <?= e($store['address'] ?? '') ?> <?= e($store['postal_code'] ?? '') ?> <?= e($store['city'] ?? '') ?>.</p>
            <?php endif; ?>

            <h2>Envio</h2>
            <p>
                <?= e($order['ship_name'] ?? '') ?><br>
                <?= e($order['ship_address'] ?? '') ?><?= $order['ship_detail'] ? ', ' . e($order['ship_detail']) : '' ?><br>
                <?= e($order['ship_postal_code'] ?? '') ?> <?= e($order['ship_city'] ?? '') ?><br>
                <?= e($order['ship_province'] ?? '') ?>
            </p>

            <?php if (!empty($order['customer_note'])): ?>
                <h2>Tu comentario</h2>
                <p class="muted"><?= nl2br(e((string) $order['customer_note'])) ?></p>
            <?php endif; ?>

            <div class="thanks-actions">
                <a class="btn btn-primary" href="<?= e($base) ?>/cuenta/pedidos">Ver mis pedidos</a>
                <a class="btn btn-ghost" href="<?= e($base) ?>/catalogo">Seguir comprando</a>
            </div>
        </div>
    </div>

    <?php if (($order['customer_id'] ?? null) === null): ?>
        <p class="muted">Guarda tu numero de pedido para consultarlo con la tienda.</p>
    <?php endif; ?>
</section>
