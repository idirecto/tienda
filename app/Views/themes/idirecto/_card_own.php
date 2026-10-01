<?php
/** Tarjeta de producto propio. @var array $p @var string $base */
$price = $p['sale_price'] ?? $p['price'] ?? null;
?>
<article class="product-card product-card-own">
    <span class="product-tag">Propio</span>
    <a class="product-media" href="<?= e($base) ?>/catalogo">
        <?php if (!empty($p['image_url'])): ?>
            <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
        <?php else: ?>
            <span class="product-ph"><?= e(mb_substr((string) $p['name'], 0, 2)) ?></span>
        <?php endif; ?>
    </a>
    <div class="product-body">
        <?php if (!empty($p['category'])): ?>
            <span class="product-brand"><?= e($p['category']) ?></span>
        <?php endif; ?>
        <span class="product-name"><?= e($p['name']) ?></span>
        <?php if ($tenant->showPrices() && $price !== null): ?>
            <span class="product-price"><?= e(euros($price)) ?></span>
        <?php endif; ?>
    </div>
</article>
