<?php
/**
 * Portada del storefront (tema I-Directo).
 *
 * Estructura de conversion, de arriba a abajo:
 *   1. Slider principal a ancho completo (banners de la tienda).
 *   2. Franja de garantias (envio, pago, devoluciones, soporte).
 *   3. Accesos rapidos a las categorias principales (grid tipo Caseking).
 *   4. Destacados del catalogo central.
 *   5. Productos propios de la tienda.
 *
 * @var array $banners
 * @var array $central
 * @var array $ownProducts
 * @var array $quickLinks
 * @var bool $catalogReady
 * @var string $base
 * @var \Tienda\Core\Tenant $tenant
 */
$cardFile = TIENDA_BASE . '/app/Views/themes/idirecto/_card.php';
$ownCardFile = TIENDA_BASE . '/app/Views/themes/idirecto/_card_own.php';

$services = [
    ['icon' => 'truck',   'title' => 'Envio 24/48 h',  'text' => 'Gestionado por la tienda'],
    ['icon' => 'shield',  'title' => 'Pago seguro',    'text' => 'Pasarelas verificadas'],
    ['icon' => 'refresh', 'title' => '30 dias',        'text' => 'Devoluciones sin complicaciones'],
    ['icon' => 'headset', 'title' => 'Soporte tecnico', 'text' => 'Atencion personalizada'],
];
?>

<?php include TIENDA_BASE . '/app/Views/themes/idirecto/_hero.php'; ?>

<section class="section section-services" aria-label="Ventajas de comprar en la tienda">
    <div class="container">
        <ul class="service-strip">
            <?php foreach ($services as $service): ?>
                <li class="service">
                    <span class="service-icon" aria-hidden="true"><?= icon_svg($service['icon']) ?></span>
                    <span class="service-body">
                        <strong><?= e($service['title']) ?></strong>
                        <small><?= e($service['text']) ?></small>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php if (!empty($quickLinks)): ?>
    <section class="section section-cats" aria-labelledby="titulo-categorias">
        <div class="container">
            <div class="section-head">
                <div>
                    <p class="section-eyebrow">Compra por categoria</p>
                    <h2 id="titulo-categorias" class="section-title">Accesos rapidos</h2>
                </div>
                <a class="link" href="<?= e($base) ?>/catalogo">
                    Ver todo el catalogo <?= icon_svg('arrow-r') ?>
                </a>
            </div>

            <ul class="cat-grid">
                <?php foreach ($quickLinks as $link): ?>
                    <li>
                        <a class="cat-card" href="<?= e($link['url']) ?>">
                            <span class="cat-icon" aria-hidden="true"><?= icon_svg($link['icon']) ?></span>
                            <span class="cat-body">
                                <strong class="cat-name"><?= e($link['label']) ?></strong>
                                <small class="cat-sub"><?= e($link['subtitle']) ?></small>
                            </span>
                            <span class="cat-meta">
                                <span class="cat-count"><?= number_format($link['total'], 0, ',', '.') ?> productos</span>
                                <?= icon_svg('arrow-r', 'cat-arrow') ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($central)): ?>
    <section class="section" aria-labelledby="titulo-destacados">
        <div class="container">
            <div class="section-head">
                <div>
                    <p class="section-eyebrow">Seleccion de la tienda</p>
                    <h2 id="titulo-destacados" class="section-title">Destacados</h2>
                </div>
                <a class="link" href="<?= e($base) ?>/catalogo">
                    Ver todo <?= icon_svg('arrow-r') ?>
                </a>
            </div>

            <div class="grid grid-products">
                <?php foreach ($central as $i => $p): ?>
                    <?php
                    $p['_eager'] = $i < 4;
                    $p['_lcp'] = $i === 0;
                    include $cardFile;
                    ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($ownProducts)): ?>
    <section class="section" aria-labelledby="titulo-propios">
        <div class="container">
            <div class="section-head">
                <div>
                    <p class="section-eyebrow">Exclusivo de esta tienda</p>
                    <h2 id="titulo-propios" class="section-title">Nuestros productos</h2>
                </div>
            </div>

            <div class="grid grid-products">
                <?php foreach ($ownProducts as $i => $p): ?>
                    <?php
                    $p['_eager'] = $i < 4;
                    $p['_lcp'] = $i === 0;
                    include $ownCardFile;
                    ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (!$catalogReady): ?>
    <section class="section">
        <div class="container">
            <div class="card-empty">
                <span class="empty-icon" aria-hidden="true"><?= icon_svg('wrench') ?></span>
                <h2>Tu tienda esta lista</h2>
                <p>El catalogo central no esta disponible o esta desactivado. Puedes anadir tus
                   propios productos desde el panel y publicar tu tienda.</p>
                <a class="btn btn-primary" href="<?= e($base) ?>/panel/productos">Anadir productos</a>
            </div>
        </div>
    </section>
<?php endif; ?>
