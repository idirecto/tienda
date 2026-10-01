<?php
/**
 * Tarjeta de producto propio de la tienda.
 *
 * Comparte la misma rejilla y el mismo lenguaje visual que la tarjeta del
 * catalogo central, pero solo con los datos que la tienda rellena a mano.
 *
 * @var array $p Fila de mt_own_products
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */
$price = $p['sale_price'] ?? $p['price'] ?? null;
$stock = isset($p['stock']) ? (int) $p['stock'] : null;
$band = $stock === null ? 'in' : ($stock <= 0 ? 'out' : ($stock <= 5 ? 'low' : 'in'));
$label = match ($band) {
    'out'   => 'Sin stock',
    'low'   => 'Ultimas ' . $stock . ' ud.',
    default => 'En stock',
};
$initial = mb_strtoupper(mb_substr((string) ($p['name'] ?? ''), 0, 2));
?>
<article class="product-card product-card-own" data-stock="<?= e($band) ?>">
    <div class="product-media">
        <span class="product-ph" aria-hidden="true"><?= e($initial) ?></span>
        <?php if (!empty($p['image_url'])): ?>
            <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>"
                 loading="lazy" decoding="async" width="400" height="400" onerror="this.remove()">
        <?php endif; ?>

        <div class="product-flags">
            <span class="badge badge-own">Propio</span>
            <span class="badge badge-stock badge-<?= e($band) ?>">
                <i class="dot" aria-hidden="true"></i><?= e($label) ?>
            </span>
        </div>
    </div>

    <div class="product-body">
        <div class="product-head">
            <?php if (!empty($p['category'])): ?>
                <span class="product-brand"><?= e($p['category']) ?></span>
            <?php endif; ?>
            <h3 class="product-name">
                <a href="<?= e($base) ?>/catalogo"><?= e($p['name']) ?></a>
            </h3>
        </div>

        <?php if (!empty($p['description'])): ?>
            <p class="product-desc"><?= e(\Tienda\Core\Str::excerpt((string) $p['description'], 90)) ?></p>
        <?php endif; ?>

        <div class="product-foot">
            <div class="product-price-box">
                <?php if ($tenant->showPrices() && $price !== null): ?>
                    <span class="product-price"><?= e(euros($price)) ?></span>
                    <span class="product-vat">IVA incl.</span>
                <?php elseif ($tenant->showPrices()): ?>
                    <span class="product-price product-price-na">Consultar</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="product-actions">
            <a class="btn btn-ghost product-cta" href="<?= e($base) ?>/catalogo">
                Ver ficha <?= icon_svg('arrow-r') ?>
            </a>
            <?php if ($tenant->allowOrders() && $price !== null): ?>
                <form class="product-add" method="post" action="<?= e($base) ?>/carrito/anadir">
                    <?= \Tienda\Core\Csrf::field() ?>
                    <input type="hidden" name="source" value="own">
                    <input type="hidden" name="product_id" value="<?= (int) ($p['id'] ?? 0) ?>">
                    <input type="hidden" name="qty" value="1">
                    <button class="btn btn-primary" type="submit" aria-label="Anadir al carrito" title="Anadir al carrito">
                        <?= icon_svg('cart') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
