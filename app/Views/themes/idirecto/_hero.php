<?php
/**
 * Slider principal de la portada (ancho completo).
 *
 * Es el componente de mas peso visual de la tienda: imagen a sangre, titular
 * contundente, subtitulo y CTA. Funciona como carrusel accesible:
 *
 *   - `hidden` en las diapositivas no activas, asi que no reciben foco ni se
 *     anuncian dos veces;
 *   - flechas y puntos son botones reales, con etiquetas en castellano;
 *   - el JS pausa la rotacion al pasar el raton o al enfocar, y la desactiva
 *     del todo si el usuario pide menos movimiento (`prefers-reduced-motion`).
 *
 * Si la tienda no ha subido banners se pinta un "hero" de marca con los
 * colores del tema, nunca un hueco vacio.
 *
 * @var array $banners
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */
$banners = array_values(array_filter($banners ?? [], static fn (array $b): bool => true));
$total = count($banners);

$gradient = 'linear-gradient(115deg, var(--c-secondary), var(--c-primary))';
?>
<section class="hero" aria-label="Destacados de la tienda">
    <?php if ($total === 0): ?>
        <div class="container">
            <div class="hero-placeholder" style="background-image: <?= e($gradient) ?>">
                <div class="hero-copy">
                    <p class="hero-eyebrow"><?= e($tenant->name()) ?></p>
                    <h1 class="hero-title"><?= e($tenant->tagline() ?: 'Tecnologia al mejor precio') ?></h1>
                    <p class="hero-subtitle">Personaliza este banner, los colores y los textos desde el panel de la tienda.</p>
                    <div class="hero-actions">
                        <a class="btn btn-primary btn-lg" href="<?= e($base) ?>/catalogo">
                            Ver catalogo <?= icon_svg('arrow-r') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="hero-slider<?= $total > 1 ? ' is-carousel' : '' ?>" data-slider
             role="group" aria-roledescription="carrusel" aria-label="Banners destacados">

            <div class="hero-stage" data-slider-stage>
                <?php foreach ($banners as $i => $b): ?>
                    <?php
                    $active = $i === 0;
                    $link = trim((string) ($b['link'] ?? ''));
                    $image = trim((string) ($b['image_url'] ?? ''));
                    ?>
                    <article class="hero-slide<?= $active ? ' is-active' : '' ?>"
                             data-slider-slide
                             id="hero-slide-<?= $i ?>"
                             role="group" aria-roledescription="diapositiva"
                             aria-label="<?= (int) ($i + 1) ?> de <?= $total ?>"
                             <?= $active ? '' : 'hidden' ?>>
                        <?php if ($image !== ''): ?>
                            <img class="hero-media" src="<?= e($image) ?>"
                                 alt="" width="1600" height="600"
                                 <?= $active ? 'fetchpriority="high" decoding="async"' : 'loading="lazy" decoding="async"' ?>>
                        <?php endif; ?>
                        <div class="hero-overlay" aria-hidden="true"></div>
                        <div class="hero-copy">
                            <?php if (!empty($b['title'])): ?>
                                <h2 class="hero-title"><?= e($b['title']) ?></h2>
                            <?php endif; ?>
                            <?php if (!empty($b['subtitle'])): ?>
                                <p class="hero-subtitle"><?= e($b['subtitle']) ?></p>
                            <?php endif; ?>
                            <?php if ($link !== ''): ?>
                                <div class="hero-actions">
                                    <a class="btn btn-primary btn-lg" href="<?= e($link) ?>">
                                        Ver mas <?= icon_svg('arrow-r') ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($total > 1): ?>
                <div class="hero-controls">
                    <button type="button" class="hero-arrow hero-prev" data-slider-prev aria-label="Banner anterior">
                        <?= icon_svg('chevron-l') ?>
                    </button>

                    <div class="hero-dots" role="tablist" aria-label="Seleccionar banner">
                        <?php for ($i = 0; $i < $total; $i++): ?>
                            <button type="button" class="hero-dot<?= $i === 0 ? ' is-active' : '' ?>"
                                    data-slider-dot="<?= $i ?>" role="tab"
                                    aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                                    aria-controls="hero-slide-<?= $i ?>">
                                <span class="sr-only">Banner <?= $i + 1 ?></span>
                            </button>
                        <?php endfor; ?>
                    </div>

                    <button type="button" class="hero-arrow hero-next" data-slider-next aria-label="Banner siguiente">
                        <?= icon_svg('chevron-r') ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>
