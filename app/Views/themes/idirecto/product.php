<?php
/**
 * Ficha de producto con la misma presentacion que idirecto.
 *
 *   1) Galeria: carrusel horizontal navegado con miniaturas (sin flechas,
 *      como la web real) + visor ampliado al pulsar la imagen.
 *   2) Informacion: titulo, marca, especificaciones clave y descripcion corta.
 *   3) Compra: precio, stock, cantidad y formas de pago.
 *   Bloque inferior: De un vistazo, Descripcion, Especificaciones y Opiniones.
 *
 * @var array $product @var string $base
 */
$images      = $product['images'] ?? [];
$price       = $product['price_final'] ?? null;
$inStock     = (bool) ($product['in_stock'] ?? false);
$stock       = (int) ($product['stock_total'] ?? 0);
$stockBand   = (string) ($product['stock_band'] ?? ($inStock ? 'in' : 'out'));
$bullets     = $product['bullets'] ?? [];
$reviews     = $product['reviews'] ?? [];
$specSummary = $product['spec_summary'] ?? [];
$specGroups  = $product['spec_groups'] ?? [];
$highlights  = $product['highlights'] ?? [];
$url         = product_url($product);

/** Permite solo HTML basico de las descripciones (datos del mayorista). */
$safe = static fn (string $html): string => strip_tags(
    $html,
    '<p><br><b><strong><i><em><u><ul><ol><li><table><thead><tbody><tr><td><th><h3><h4><h5><span><div>'
);

/** Iconos en linea (sustituyen a los glyphicon de idirecto). */
$icon = static function (string $name): string {
    $paths = [
        'disk'    => '<rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="13" width="18" height="7" rx="2"/><circle cx="7" cy="7.5" r="1"/><circle cx="7" cy="16.5" r="1"/>',
        'weight'  => '<path d="M12 4v4"/><path d="M4 20h16"/><path d="M8 20l4-8 4 8"/>',
        'screen'  => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M9 20h6"/>',
        'chip'    => '<rect x="7" y="7" width="10" height="10" rx="2"/><path d="M4 10h3M4 14h3M17 10h3M17 14h3M10 4v3M14 4v3M10 17v3M14 17v3"/>',
        'window'  => '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M3 9h18"/>',
        'color'   => '<circle cx="12" cy="12" r="8"/><circle cx="9.5" cy="10" r=".6"/><circle cx="14.5" cy="10" r=".6"/><circle cx="12" cy="14.5" r=".6"/>',
        'battery' => '<rect x="2" y="8" width="17" height="8" rx="2"/><path d="M21 11v2"/>',
        'shield'  => '<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3Z"/>',
        'lines'   => '<path d="M4 6h16M4 12h16M4 18h11"/>',
        'list'    => '<circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/><path d="M8 6h13M8 12h13M8 18h13"/>',
        'star'    => '<path d="M12 4l2.4 5 5.6.8-4 3.9 1 5.5-5-2.9-5 2.9 1-5.5-4-3.9 5.6-.8L12 4Z"/>',
        'dot'     => '<circle cx="12" cy="12" r="8"/>',
    ];
    $path = $paths[$name] ?? $paths['dot'];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

/** Elige el icono segun la etiqueta del atributo. */
$glanceIcon = static function (string $label) use ($icon): string {
    $l = mb_strtolower($label);
    foreach ([
        ['almacen', 'ssd', 'disco', 'capacidad de almacenamiento'],
        ['peso', 'dimensiones'],
        ['pantalla', 'diagonal'],
        ['memoria', 'ram'],
        ['procesador', 'cpu', 'nucleos', 'núcleos'],
        ['sistema operativo', 'plataforma'],
        ['color'],
        ['bateria', 'batería'],
        ['garantia', 'garantía'],
    ] as $i => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($l, $needle)) {
                return $icon(['disk', 'weight', 'screen', 'chip', 'chip', 'window', 'color', 'battery', 'shield'][$i]);
            }
        }
    }
    return $icon('dot');
};

$avgRating = 0.0;
if ($reviews !== []) {
    $avgRating = round(array_sum(array_map(static fn ($r) => (int) $r['puntuacion'], $reviews)) / count($reviews), 1);
}
$initial = mb_strtoupper(mb_substr((string) $product['nombre'], 0, 2));
?>
<div class="ficha-breadcrumb">
    <div class="container">
        <a href="<?= e($base) ?>/">Inicio</a>
        <?php if (!empty($product['categoria'])): ?>
            &gt; <span><?= e($product['categoria']) ?></span>
        <?php endif; ?>
        <?php if (!empty($product['subcategoria'])): ?>
            &gt; <a href="<?= e($base) ?>/catalogo?subcat=<?= (int) $product['id_subcategoria'] ?>"><?= e($product['subcategoria']) ?></a>
        <?php endif; ?>
        &gt; <span class="current"><?= e($product['nombre']) ?></span>
    </div>
</div>

<div class="container">
    <article class="ficha" id="ficha" itemscope itemtype="https://schema.org/Product">
        <div class="ficha-grid">

            <!-- 1. GALERIA -->
            <div class="ficha-col ficha-gallery">
                <div class="gallery" id="gallery">
                    <div class="gallery-stage" id="gallery-stage">
                        <span class="gallery-empty product-ph product-ph-lg"><?= e($initial) ?></span>
                        <?php if ($images !== []): ?>
                            <div class="gallery-track" id="gallery-track">
                                <?php foreach ($images as $i => $im): ?>
                                    <div class="gallery-slide" data-index="<?= $i ?>">
                                        <img src="<?= e($im['large']) ?>"
                                             alt="<?= e($product['nombre']) ?><?= $i > 0 ? ' (' . ($i + 1) . ')' : '' ?>"
                                             data-zoom="<?= e($im['zoom']) ?>"
                                             itemprop="image"
                                             <?= $i === 0 ? '' : 'loading="lazy"' ?>>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($images) > 1): ?>
                        <ol class="gallery-thumbs" id="gallery-thumbs">
                            <?php foreach ($images as $i => $im): ?>
                                <li class="<?= $i === 0 ? 'is-active' : '' ?>" data-index="<?= $i ?>">
                                    <img src="<?= e($im['thumb']) ?>" alt="<?= e($product['nombre']) ?> (<?= $i + 1 ?>)" loading="lazy">
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. INFORMACION -->
            <div class="ficha-col ficha-info">
                <h1 itemprop="name"><?= e($product['nombre']) ?></h1>
                <div class="ficha-title-divider" aria-hidden="true"></div>

                <div class="ficha-meta">
                    <?php if (!empty($product['marca_nombre'])): ?>
                        <span class="ficha-brand"><?= e($product['marca_nombre']) ?></span>
                    <?php endif; ?>
                    <?php if ($reviews !== []): ?>
                        <span class="ficha-rating" title="<?= e((string) $avgRating) ?> de 5">
                            <?= str_repeat('★', (int) round($avgRating)) . str_repeat('☆', 5 - (int) round($avgRating)) ?>
                            <small><?= e((string) $avgRating) ?>/5 · <?= count($reviews) ?></small>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($product['part_number'])): ?>
                    <p class="ficha-ref">Referencia: <span itemprop="productID"><?= e($product['part_number']) ?></span></p>
                <?php endif; ?>

                <?php if ($specSummary !== []): ?>
                    <div class="spec-summary" id="spec-summary">
                        <div class="spec-summary-title">Especificaciones clave</div>
                        <div class="spec-summary-list">
                            <?php foreach ($specSummary as $i => $s): ?>
                                <div class="spec-summary-row<?= $i >= 6 ? ' spec-summary-row--hidden' : '' ?>">
                                    <span class="spec-summary-name"><?= e($s['k']) ?></span>
                                    <span class="spec-summary-value"><?= e($s['v']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($specSummary) > 6): ?>
                            <button type="button" class="spec-summary-toggle" id="spec-toggle" aria-expanded="false">
                                <span>Ver más</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($bullets !== []): ?>
                    <div class="ficha-shortdesc" id="ficha-shortdesc">
                        <ul class="ficha-shortdesc-list">
                            <?php foreach ($bullets as $i => $b): ?>
                                <li<?= $i >= 4 ? ' style="display:none"' : '' ?>><?= e($b) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($bullets) > 4): ?>
                            <button type="button" class="ficha-shortdesc-toggle" id="shortdesc-toggle" aria-expanded="false">Ver más</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 3. COMPRA -->
            <div class="ficha-col ficha-buy">
                <div class="ficha-price-box">
                    <?php if ($price !== null): ?>
                        <h5 class="ficha-price" itemprop="price" content="<?= e(number_format((float) $price, 2, '.', '')) ?>">
                            <?= e(euros($price)) ?> <span itemprop="priceCurrency" content="EUR">EUR</span>
                        </h5>
                        <p class="ficha-tax">IVA incluido</p>
                    <?php else: ?>
                        <p class="muted">Precio no disponible. Consulta con la tienda.</p>
                    <?php endif; ?>

                    <p class="stock stock-<?= e($stockBand) ?>">
                        <?php if ($stockBand === 'out'): ?>
                            Producto sin stock
                        <?php elseif ($stockBand === 'low'): ?>
                            Ultimas unidades disponibles (<?= $stock ?>)
                        <?php else: ?>
                            Stock disponible: <?= $stock ?>
                        <?php endif; ?>
                    </p>

                    <?php if ($inStock && $tenant->allowOrders()): ?>
                        <form class="ficha-buy" method="post" action="<?= e($base) ?>/carrito/anadir">
                            <?= \Tienda\Core\Csrf::field() ?>
                            <input type="hidden" name="source" value="catalog">
                            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                            <input type="hidden" name="return" value="/producto/<?= e($product['slug']) ?>/<?= (int) $product['id'] ?>">
                            <input type="hidden" name="stay" value="1">

                            <div class="ficha-qty">
                                <button type="button" class="qty-btn" data-step="-1" aria-label="Menos">−</button>
                                <input type="text" id="ficha-qty" name="qty" value="1" inputmode="numeric" aria-label="Cantidad">
                                <button type="button" class="qty-btn" data-step="1" aria-label="Más">+</button>
                            </div>
                            <button class="btn btn-primary btn-lg btn-block" type="submit">
                                <?= icon_svg('cart') ?> Añadir al carrito
                            </button>
                        </form>
                    <?php elseif (!$inStock): ?>
                        <button type="button" class="btn btn-ghost btn-lg btn-block" disabled>No disponible</button>
                    <?php endif; ?>

                    <div class="ficha-pagos">
                        <?php $formasPago = array_column(\Tienda\Core\Checkout::paymentMethods($tenant->toArray()), 'label'); ?>
                        <span class="pago-item">🔒 Compra protegida</span>
                        <?php if ($formasPago !== []): ?>
                            <span class="pago-item"><?= e(implode(' · ', $formasPago)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </article>

    <!-- ================= BLOQUE INFERIOR ================= -->
    <div class="ficha-detail">

        <?php if ($highlights !== []): ?>
            <div class="glance">
                <div class="glance-label">De un vistazo</div>
                <div class="glance-grid">
                    <?php foreach ($highlights as $h): ?>
                        <div class="glance-item">
                            <div class="glance-icon"><?= $glanceIcon((string) $h['label']) ?></div>
                            <div class="glance-value"><?= e($h['value']) ?></div>
                            <div class="glance-key"><?= e($h['label']) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- DESCRIPCION -->
        <div class="pb-card pb-card-desc">
            <div class="pb-card-head">
                <div class="pb-card-head-icon"><?= $icon('lines') ?></div>
                <h2>Descripción</h2>
            </div>

            <?php if (!empty($product['razones'])): ?>
                <div class="pb-card-text"><?= $safe((string) $product['razones']) ?></div>
            <?php endif; ?>

            <?php if ($bullets !== []): ?>
                <div class="pb-bullets">
                    <?php foreach ($bullets as $b): ?>
                        <div class="pb-bullet"><span class="pb-bullet-ico">✓</span><span><?= e($b) ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($product['caracteristicas'])): ?>
                <div class="pb-card-text"><?= e((string) $product['caracteristicas']) ?></div>
            <?php endif; ?>

            <?php if (!empty($product['brand_story'])): ?>
                <div class="pb-card-text">
                    <p class="pb-subtitle">De la marca <?= e((string) ($product['marca_nombre'] ?? '')) ?></p>
                    <?= $safe((string) $product['brand_story']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($product['contenido_enriquecido'])): ?>
                <div class="pb-card-text">
                    <p class="pb-subtitle">Más información del producto</p>
                    <?= $safe((string) $product['contenido_enriquecido']) ?>
                </div>
            <?php endif; ?>

            <div class="pb-meta-row">
                <strong>Marca:</strong> <?= e((string) ($product['marca_nombre'] ?? '-')) ?><br>
                <strong>Part number:</strong> <span itemprop="productID"><?= e((string) ($product['part_number'] ?? '-')) ?></span>
            </div>
        </div>

        <!-- ESPECIFICACIONES -->
        <div class="pb-specs-section">
            <div class="pb-card-head" style="margin: 8px 2px 16px;">
                <div class="pb-card-head-icon"><?= $icon('list') ?></div>
                <h2>Especificaciones</h2>
                <?php if ($specGroups !== []): ?>
                    <span class="badge"><?= count($specGroups) ?> grupos</span>
                <?php endif; ?>
            </div>

            <?php if ($specGroups !== []): ?>
                <div class="pb-specs-grid">
                    <?php foreach ($specGroups as $group): ?>
                        <div class="pb-spec-card<?= count($group['rows']) > 10 ? ' pb-spec-card--scroll' : '' ?>">
                            <div class="pb-spec-card-head"><span><?= e($group['name']) ?></span></div>
                            <div class="pb-spec-card-rows">
                                <?php foreach ($group['rows'] as $row): ?>
                                    <div class="pb-spec-row">
                                        <span class="pb-spec-k"><?= e($row['k']) ?></span>
                                        <span class="pb-spec-v"><?= e($row['v']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (!empty($product['especificaciones'])): ?>
                <div class="pb-card-fallback"><?= $safe((string) $product['especificaciones']) ?></div>
            <?php else: ?>
                <p class="muted">Este producto no tiene especificaciones detalladas.</p>
            <?php endif; ?>
        </div>

        <!-- OPINIONES -->
        <div class="pb-card" id="reviews">
            <div class="pb-card-head">
                <div class="pb-card-head-icon"><?= $icon('star') ?></div>
                <h2>Opiniones de clientes</h2>
                <?php if ($reviews !== []): ?>
                    <span class="badge"><?= e((string) $avgRating) ?> / 5</span>
                <?php endif; ?>
            </div>

            <?php if ($reviews === []): ?>
                <p class="muted">Todavía no hay opiniones de este producto.</p>
            <?php else: ?>
                <ul class="reviews">
                    <?php foreach ($reviews as $r): ?>
                        <li class="review">
                            <div class="review-head">
                                <strong><?= e((string) ($r['autor_nombre'] ?: 'Cliente')) ?></strong>
                                <span class="review-stars">
                                    <?= str_repeat('★', (int) $r['puntuacion']) . str_repeat('☆', 5 - (int) $r['puntuacion']) ?>
                                </span>
                            </div>
                            <?php if (!empty($r['titulo'])): ?>
                                <p class="review-title"><?= e((string) $r['titulo']) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($r['comentario'])): ?>
                                <p class="review-text"><?= e((string) $r['comentario']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($images !== []): ?>
    <!-- Visor ampliado: la imagen grande (tamano g) se carga solo al abrirlo -->
    <div class="lightbox" id="lightbox" hidden role="dialog" aria-modal="true" aria-label="Imagen ampliada">
        <button type="button" class="lightbox-close" id="lightbox-close" aria-label="Cerrar">&times;</button>
        <button type="button" class="lightbox-nav lightbox-prev" data-step="-1" aria-label="Anterior">&lsaquo;</button>
        <img id="lightbox-image" src="" alt="<?= e($product['nombre']) ?>">
        <button type="button" class="lightbox-nav lightbox-next" data-step="1" aria-label="Siguiente">&rsaquo;</button>
        <span class="lightbox-counter"><b id="lightbox-position">1</b>/<span id="lightbox-total"><?= count($images) ?></span></span>
    </div>
<?php endif; ?>
