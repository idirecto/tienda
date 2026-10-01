<?php
/**
 * Carrito de la compra.
 *
 * @var array $items @var array $totals @var bool $freeFrom @var float $missingFree
 * @var bool $allowOrders @var int $maxQty @var string $base
 */
use Tienda\Core\Csrf;

$puedeSeguir = $allowOrders && $items !== [];
?>
<section class="container section">
    <div class="page-head">
        <h1>Tu carrito</h1>
        <?php if ($items !== []): ?>
            <p class="muted"><?= (int) $totals['units'] ?> unidad(es) en <?= (int) $totals['lines'] ?> producto(s).</p>
        <?php endif; ?>
    </div>

    <?php if ($items === []): ?>
        <div class="cart-empty">
            <p>Tu carrito esta vacio.</p>
            <a class="btn btn-primary" href="<?= e($base) ?>/catalogo">Ver el catalogo</a>
        </div>
    <?php else: ?>
        <div class="cart-grid">
            <div class="cart-lines">
                <form method="post" action="<?= e($base) ?>/carrito/actualizar">
                    <?= Csrf::field() ?>
                    <table class="shop-table cart-table">
                        <thead>
                            <tr>
                                <th colspan="2">Producto</th>
                                <th class="ta-c">Precio</th>
                                <th class="ta-c">Cantidad</th>
                                <th class="ta-r">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="cart-media">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"
                                             width="64" height="64" loading="lazy" onerror="this.remove()">
                                    <?php endif; ?>
                                </td>
                                <td class="cart-name">
                                    <?php if (!empty($item['url'])): ?>
                                        <a href="<?= e($item['url']) ?>"><?= e($item['name']) ?></a>
                                    <?php else: ?>
                                        <strong><?= e($item['name']) ?></strong>
                                    <?php endif; ?>
                                    <?php if (!empty($item['sku'])): ?><br><small class="muted"><?= e($item['sku']) ?></small><?php endif; ?>
                                    <?php if ((string) $item['source'] === 'own'): ?> <span class="pill pill-info">propio</span><?php endif; ?>
                                </td>
                                <td class="ta-c"><?= e(euros($item['price'])) ?><br><small class="muted">IVA incl.</small></td>
                                <td class="ta-c">
                                    <input class="cart-qty" type="number" name="qty[<?= e($item['key']) ?>]"
                                           value="<?= (int) $item['qty'] ?>" min="0" max="<?= (int) $maxQty ?>"
                                           inputmode="numeric" aria-label="Cantidad de <?= e($item['name']) ?>">
                                    <?php if (!empty($item['stock_total'])): ?>
                                        <br><small class="muted"><?= (int) $item['stock_total'] ?> disp.</small>
                                    <?php endif; ?>
                                </td>
                                <td class="ta-r"><strong><?= e(euros($item['line_total'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="cart-actions">
                        <button class="btn btn-ghost" type="submit">Actualizar cantidades</button>
                        <a class="link" href="<?= e($base) ?>/catalogo">Seguir comprando <?= icon_svg('arrow-r') ?></a>
                    </div>
                </form>

                <?php foreach ($items as $item): ?>
                    <form class="cart-remove" method="post" action="<?= e($base) ?>/carrito/quitar">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="key" value="<?= e($item['key']) ?>">
                        <button class="link link-danger" type="submit">Quitar <?= e(mb_substr($item['name'], 0, 40)) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>

            <aside class="cart-summary">
                <h2>Resumen</h2>
                <dl class="totals">
                    <div><dt>Productos</dt><dd><?= e(euros($totals['subtotal'])) ?></dd></div>
                    <div><dt>IVA incluido</dt><dd><?= e(euros($totals['tax_total'])) ?></dd></div>
                    <div><dt>Gastos de envio</dt><dd><?= $totals['shipping'] > 0 ? e(euros($totals['shipping'])) : 'Gratis' ?></dd></div>
                    <div class="totals-grand"><dt>Total</dt><dd><?= e(euros($totals['total'])) ?></dd></div>
                </dl>

                <?php if ($missingFree > 0): ?>
                    <p class="cart-hint">Te faltan <strong><?= e(euros($missingFree)) ?></strong> para el envio gratis.</p>
                <?php elseif ($freeFrom && $totals['shipping'] <= 0): ?>
                    <p class="cart-hint cart-hint-ok">Envio gratis incluido.</p>
                <?php endif; ?>

                <?php if ($puedeSeguir): ?>
                    <a class="btn btn-primary btn-lg btn-block" href="<?= e($base) ?>/checkout">Tramitar pedido</a>
                <?php else: ?>
                    <p class="muted">Esta tienda no esta aceptando pedidos ahora mismo.</p>
                <?php endif; ?>

                <form method="post" action="<?= e($base) ?>/carrito/vaciar">
                    <?= Csrf::field() ?>
                    <button class="link link-danger" type="submit">Vaciar el carrito</button>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</section>
