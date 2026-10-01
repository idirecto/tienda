<?php
/** Tarjeta de producto del catalogo central. @var array $p @var string $base */
$img = $p['image_url'] ?? null;
$price = $p['price_final'] ?? null;
$url = product_url($p);
?>
<article class="product-card">
    <a class="product-media" href="<?= e($url) ?>">
        <span class="product-ph" aria-hidden="true"><?= e(mb_substr((string) $p['nombre'], 0, 2)) ?></span>
        <?php if ($img): ?>
            <img src="<?= e($img) ?>" alt="<?= e($p['nombre']) ?>" loading="lazy" onerror="this.remove()">
        <?php endif; ?>
    </a>
    <div class="product-body">
        <?php if (!empty($p['marca_nombre'])): ?>
            <span class="product-brand"><?= e($p['marca_nombre']) ?></span>
        <?php endif; ?>
        <a class="product-name" href="<?= e($url) ?>"><?= e($p['nombre']) ?></a>
        <?php if ($tenant->showPrices() && $price !== null): ?>
            <span class="product-price"><?= e(euros($price)) ?></span>
        <?php elseif ($tenant->showPrices()): ?>
            <span class="product-price product-price-na">Consultar</span>
        <?php endif; ?>
    </div>
</article>
