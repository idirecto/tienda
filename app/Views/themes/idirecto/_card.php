<?php
/**
 * Tarjeta de producto del catalogo central.
 *
 * Estilo minimalista tipo Scan/Caseking: la foto manda, debajo la marca, el
 * nombre y los datos tecnicos que de verdad se comparan (socket, memoria,
 * capacidad...), y al final precio y CTA. La etiqueta de disponibilidad es
 * dinamica: "En stock", "Ultimas N ud." o "Sin stock".
 *
 * @var array $p Fila decorada por Catalog::decorate()
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */
$url = product_url($p);
$price = $p['price_final'] ?? null;
$band = (string) ($p['stock_band'] ?? ($p['in_stock'] ?? false ? 'in' : 'out'));
$label = (string) ($p['stock_label'] ?? '');
$specs = (array) ($p['specs'] ?? []);
$initial = mb_strtoupper(mb_substr((string) ($p['nombre'] ?? ''), 0, 2));
?>
<article class="product-card" data-stock="<?= e($band) ?>">
    <div class="product-media">
        <span class="product-ph" aria-hidden="true"><?= e($initial) ?></span>
        <?php if (!empty($p['image_url'])): ?>
            <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['nombre']) ?>"
                 loading="lazy" decoding="async" width="400" height="400"
                 onerror="this.remove()">
        <?php endif; ?>

        <div class="product-flags">
            <?php if ($label !== ''): ?>
                <span class="badge badge-stock badge-<?= e($band) ?>"><?= e($label) ?></span>
            <?php endif; ?>
            <?php if (!empty($p['is_new']) && $band !== 'out'): ?>
                <span class="badge badge-new">Novedad</span>
            <?php endif; ?>
            <?php if (!empty($p['on_offer'])): ?>
                <span class="badge badge-offer"><?= icon_svg('bolt') ?>Oferta</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="product-body">
        <div class="product-head">
            <?php if (!empty($p['marca_nombre'])): ?>
                <span class="product-brand"><?= e($p['marca_nombre']) ?></span>
            <?php endif; ?>
            <h3 class="product-name">
                <a href="<?= e($url) ?>"><?= e($p['nombre']) ?></a>
            </h3>
        </div>

        <?php if ($specs !== []): ?>
            <ul class="product-specs">
                <?php foreach (array_slice($specs, 0, 3) as $spec): ?>
                    <li>
                        <span class="spec-k"><?= e($spec['k']) ?></span>
                        <span class="spec-v"><?= e($spec['v']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
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
            <a class="btn btn-ghost product-cta" href="<?= e($url) ?>">
                Ver ficha <?= icon_svg('arrow-r') ?>
            </a>
            <?php if ($tenant->allowOrders() && $band !== 'out' && $price !== null): ?>
                <form class="product-add" method="post" action="<?= e($base) ?>/carrito/anadir">
                    <?= \Tienda\Core\Csrf::field() ?>
                    <input type="hidden" name="source" value="catalog">
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
