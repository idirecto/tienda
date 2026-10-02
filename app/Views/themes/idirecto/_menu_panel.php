<?php
/**
 * Bloque promocional del panel de una categoria del menu (banner, destacados
 * y marcas). Se carga al abrir el panel, para que la primera carga de la
 * pagina no pague las consultas de las 14 categorias.
 *
 * @var array|null $banner   Banner de la tienda con posicion `menu`
 * @var array $products      Productos destacados de la categoria (decorados)
 * @var array $brands        Marcas con mas stock de la categoria
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */

$hasBanner = is_array($banner) && !empty($banner['image_url']);
$hasProducts = $products !== [];
$hasBrands = $brands !== [];

if (!$hasBanner && !$hasProducts && !$hasBrands) {
    return; // Nada que mostrar: el hueco queda vacio.
}
?>
<div class="mn-promo-inner">
    <?php if ($hasBanner): ?>
        <?php $bannerLink = trim((string) ($banner['link'] ?? '')); ?>
        <div class="mn-promo-banner">
            <a href="<?= e($bannerLink !== '' ? $bannerLink : $base . '/catalogo') ?>">
                <img src="<?= e((string) $banner['image_url']) ?>"
                     alt="<?= e((string) ($banner['title'] ?? '')) ?>"
                     loading="lazy" decoding="async">
                <?php if (!empty($banner['title']) || !empty($banner['subtitle'])): ?>
                    <span class="mn-promo-caption">
                        <?php if (!empty($banner['title'])): ?><strong><?= e((string) $banner['title']) ?></strong><?php endif; ?>
                        <?php if (!empty($banner['subtitle'])): ?><em><?= e((string) $banner['subtitle']) ?></em><?php endif; ?>
                    </span>
                <?php endif; ?>
            </a>
        </div>
    <?php endif; ?>

    <?php if ($hasBrands): ?>
        <div class="mn-promo-block">
            <h4 class="mn-promo-title">Marcas destacadas</h4>
            <ul class="mn-promo-brands">
                <?php foreach ($brands as $brand): ?>
                    <li>
                        <a href="<?= e($base) ?>/marca/<?= (int) $brand['value'] ?>">
                            <?= e((string) $brand['label']) ?>
                            <span class="mn-promo-count"><?= (int) $brand['count'] ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($hasProducts): ?>
        <div class="mn-promo-block">
            <h4 class="mn-promo-title">Destacados</h4>
            <div class="mn-promo-products">
                <?php foreach ($products as $p): ?>
                    <?php include __DIR__ . '/_card.php'; ?>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
