<?php
/**
 * Portada del storefront (tema I-Directo).
 *
 * @var array $banners
 * @var array $central
 * @var array $ownProducts
 * @var bool $catalogReady
 * @var string $base
 * @var \Tienda\Core\Tenant $tenant
 */
?>
<?php if (!empty($banners)): ?>
    <section class="hero">
        <div class="container">
            <div class="hero-track">
                <?php foreach ($banners as $b): ?>
                    <article class="hero-slide"<?= $b['image_url'] ? ' style="background-image:url(' . e($b['image_url']) . ')"' : '' ?>>
                        <div class="hero-copy">
                            <?php if ($b['title']): ?><h2><?= e($b['title']) ?></h2><?php endif; ?>
                            <?php if ($b['subtitle']): ?><p><?= e($b['subtitle']) ?></p><?php endif; ?>
                            <?php if ($b['link']): ?>
                                <a class="btn btn-primary" href="<?= e($b['link']) ?>">Ver mas</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="hero hero-placeholder">
        <div class="container hero-copy">
            <h2><?= e($tenant->tagline() ?: 'Bienvenido a ' . $tenant->name()) ?></h2>
            <p>Esta es tu tienda. Personaliza el banner, los colores y los textos desde el panel.</p>
            <a class="btn btn-primary" href="<?= e($base) ?>/catalogo">Ver catalogo</a>
        </div>
    </section>
<?php endif; ?>

<section class="container section">
    <div class="feature-strip">
        <div class="feature"><strong>Envio 24/48 h</strong><span>Gestionado por la tienda</span></div>
        <div class="feature"><strong>Pago seguro</strong><span>Pasarelas de la tienda</span></div>
        <div class="feature"><strong>Devoluciones</strong><span>30 dias de garantia</span></div>
        <div class="feature"><strong>Soporte</strong><span>Atencion personalizada</span></div>
    </div>
</section>

<?php if (!empty($central)): ?>
    <section class="container section">
        <div class="section-head">
            <h3>Destacados</h3>
            <a class="link" href="<?= e($base) ?>/catalogo">Ver todo &rarr;</a>
        </div>
        <div class="grid grid-products">
            <?php foreach ($central as $p): ?>
                <?php include TIENDA_BASE . '/app/Views/themes/idirecto/_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!empty($ownProducts)): ?>
    <section class="container section">
        <div class="section-head">
            <h3>Nuestros productos</h3>
        </div>
        <div class="grid grid-products">
            <?php foreach ($ownProducts as $p): ?>
                <?php include TIENDA_BASE . '/app/Views/themes/idirecto/_card_own.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php if (!$catalogReady): ?>
    <section class="container section">
        <div class="card-empty">
            <h3>Tu tienda esta lista</h3>
            <p>El catalogo central no esta disponible o esta desactivado. Puedes anadir tus
               propios productos desde el panel y publicar tu tienda.</p>
            <a class="btn btn-primary" href="<?= e($base) ?>/panel/productos">Anadir productos</a>
        </div>
    </section>
<?php endif; ?>
