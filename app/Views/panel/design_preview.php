<?php
/**
 * Vista previa de la identidad visual (se carga dentro de un <iframe>).
 *
 * Pagina autonoma, sin layout del panel: incluye la hoja del storefront y el
 * mismo bloque de tokens que publicara la tienda, con una muestra de los
 * componentes reales (banner, tarjeta de producto, etiquetas, botones y
 * filtros). Asi el tendero ve de verdad lo que va a publicar.
 *
 * El panel la actualiza en caliente por `postMessage` cada vez que se toca un
 * color, sin recargar el iframe.
 *
 * @var string $tokensCss
 * @var string $scheme
 * @var \Tienda\Core\Tenant $tenant
 */
$demoSpecs = [
    ['k' => 'Socket', 'v' => 'AM5'],
    ['k' => 'Memoria', 'v' => 'DDR5 6000'],
    ['k' => 'Capacidad', 'v' => '32 GB'],
];
?>
<!doctype html>
<html lang="es" data-color-scheme="<?= e($scheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vista previa - <?= e($tenant->name()) ?></title>
    <style id="preview-tokens"><?= $tokensCss ?></style>
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">
    <style>html, body { margin: 0; }</style>
</head>
<body class="shop">
<div class="preview-canvas">

    <section class="hero">
        <div class="hero-slide is-active" style="min-height: 220px;">
            <div class="hero-overlay" aria-hidden="true"></div>
            <div class="hero-copy" style="padding: 1.5rem 1.25rem;">
                <p class="hero-eyebrow">Novedades</p>
                <h2 class="hero-title" style="font-size: 1.6rem;">RTX 50 Series ya disponible</h2>
                <p class="hero-subtitle" style="margin: .5rem 0 1rem; font-size: .9rem;">
                    Rendimiento de nueva generacion con GDDR7 y DLSS 4.
                </p>
                <div class="hero-actions">
                    <span class="btn btn-primary">Ver catalogo</span>
                </div>
            </div>
        </div>
    </section>

    <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 1.25rem;">
        <ul class="service-strip" style="margin: 0;">
            <li class="service">
                <span class="service-icon" aria-hidden="true"><?= icon_svg('truck') ?></span>
                <span class="service-body"><strong>Envio 24/48 h</strong><small>Stock real</small></span>
            </li>
            <li class="service">
                <span class="service-icon" aria-hidden="true"><?= icon_svg('shield') ?></span>
                <span class="service-body"><strong>Pago seguro</strong><small>Pasarelas verificadas</small></span>
            </li>
        </ul>

        <div class="grid grid-products" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));">
            <?php foreach ([
                ['name' => 'ASUS PRIME B850M-F WIFI Placa Base', 'brand' => 'ASUS', 'price' => '189,90 €', 'band' => 'in', 'label' => 'En stock'],
                ['name' => 'MSI GeForce RTX 5080 VENTUS 3X OC 16 GB', 'brand' => 'MSI', 'price' => '1.249,00 €', 'band' => 'low', 'label' => 'Ultimas 3 ud.'],
                ['name' => 'Corsair Vengeance DDR5 32 GB 6000 MT/s', 'brand' => 'CORSAIR', 'price' => '129,95 €', 'band' => 'out', 'label' => 'Sin stock'],
            ] as $demo): ?>
                <article class="product-card" data-stock="<?= e($demo['band']) ?>">
                    <div class="product-media">
                        <span class="product-ph" aria-hidden="true"><?= e(mb_substr($demo['brand'], 0, 2)) ?></span>
                        <div class="product-flags">
                            <span class="badge badge-stock badge-<?= e($demo['band']) ?>">
                                <i class="dot" aria-hidden="true"></i><?= e($demo['label']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="product-body">
                        <div class="product-head">
                            <span class="product-brand"><?= e($demo['brand']) ?></span>
                            <h3 class="product-name"><a href="#"><?= e($demo['name']) ?></a></h3>
                        </div>
                        <ul class="product-specs">
                            <?php foreach ($demoSpecs as $spec): ?>
                                <li>
                                    <span class="spec-k"><?= e($spec['k']) ?></span>
                                    <span class="spec-v"><?= e($spec['v']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="product-foot">
                            <div class="product-price-box">
                                <span class="product-price"><?= e($demo['price']) ?></span>
                                <span class="product-vat">IVA incl.</span>
                            </div>
                        </div>
                        <div class="product-actions">
                            <span class="btn btn-primary btn-block product-cta">Ver ficha</span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="filters">
            <div class="filters-head">
                <h2 class="filters-title"><?= icon_svg('filter') ?> Filtrar</h2>
                <span class="filters-clear">Limpiar</span>
            </div>
            <div class="facet">
                <h3 class="facet-head">Socket</h3>
                <ul class="facet-list">
                    <li><span class="facet-option is-active"><span class="facet-box"><?= icon_svg('check') ?></span><span class="facet-label">AM5</span></span></li>
                    <li><span class="facet-option"><span class="facet-box"><?= icon_svg('check') ?></span><span class="facet-label">LGA 1851</span></span></li>
                </ul>
            </div>
            <span class="btn btn-primary btn-block">Aplicar filtros</span>
        </div>

        <div class="active-filters" style="margin: 0;">
            <span class="chip chip-remove">AM5 <?= icon_svg('close') ?></span>
            <span class="chip chip-clear">Quitar todo</span>
        </div>
    </div>
</div>

<script>
    // El panel envia el CSS de tokens generado con lo que hay en el formulario.
    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data) {
            return;
        }
        if (event.data.type !== 'tienda-tokens') {
            return;
        }
        var style = document.getElementById('preview-tokens');
        if (style && typeof event.data.css === 'string') {
            style.textContent = event.data.css;
        }
        if (typeof event.data.scheme === 'string') {
            document.documentElement.setAttribute('data-color-scheme', event.data.scheme);
        }
    });
</script>
</body>
</html>
