<?php
/**
 * Articulo del Centro de Ayuda.
 *
 * @var array<string, array<string, mixed>> $categories
 * @var array<string, mixed> $article
 * @var array<int, array<string, mixed>> $related
 * @var array<string, mixed>|null $category
 * @var string $base
 */
use Tienda\Core\Help;

$activeCategory = (string) $article['category'];
$bodyHtml = str_replace('{{base}}', e($base), (string) $article['body']);
$panelPath = ltrim((string) $article['panel_path'], '/');
$panelLabel = (string) ($article['panel_label'] ?: 'Ir a esta pantalla');
?>
<div class="help-layout">
    <?php require __DIR__ . '/_help_side.php'; ?>

    <div class="help-main">
        <nav class="help-breadcrumb" aria-label="Migas de pan">
            <a href="<?= e(Help::homeUrl()) ?>">Ayuda</a>
            <span aria-hidden="true">/</span>
            <a href="<?= e(Help::categoryUrl((string) $article['category'])) ?>"><?= e((string) $article['category_title']) ?></a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e((string) $article['title']) ?></span>
        </nav>

        <article class="help-article">
            <header class="help-article-head">
                <span class="help-eyebrow"><?= e((string) $article['category_title']) ?></span>
                <h2><?= e((string) $article['title']) ?></h2>
                <p class="help-article-summary"><?= e((string) $article['summary']) ?></p>
            </header>

            <?php if ((string) $article['location'] !== ''): ?>
                <aside class="help-locate" aria-label="Dónde está esta pantalla">
                    <span class="help-locate-icon" aria-hidden="true"><?= icon_svg('pin') ?></span>
                    <div>
                        <strong>Dónde lo encuentras</strong>
                        <p><?= e((string) $article['location']) ?></p>
                    </div>
                </aside>
            <?php endif; ?>

            <div class="help-prose">
                <?= $bodyHtml ?>
            </div>

            <?php if ($panelPath !== ''): ?>
                <div class="help-action-box">
                    <div>
                        <strong>¿Listo para hacerlo?</strong>
                        <p>Este enlace abre la pantalla real del panel. No se guarda ningún cambio automáticamente.</p>
                    </div>
                    <a class="btn btn-primary" href="<?= e($base . '/' . $panelPath) ?>">
                        <?= e($panelLabel) ?> <?= icon_svg('arrow-r') ?>
                    </a>
                </div>
            <?php endif; ?>
        </article>

        <?php if ($related !== []): ?>
            <section class="help-related" aria-labelledby="help-related-title">
                <div class="help-section-head">
                    <div>
                        <span class="help-eyebrow">Continúa</span>
                        <h2 id="help-related-title">Artículos relacionados</h2>
                    </div>
                </div>
                <div class="help-related-grid">
                    <?php foreach ($related as $item): ?>
                        <a class="help-related-card" href="<?= e(Help::articleUrl((string) $item['path'])) ?>">
                            <span class="help-related-cat"><?= e((string) $item['category_title']) ?></span>
                            <strong><?= e((string) $item['title']) ?></strong>
                            <span><?= e((string) $item['summary']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="help-back-row">
            <a class="btn btn-ghost" href="<?= e(Help::categoryUrl((string) $article['category'])) ?>">
                <?= icon_svg('chevron-l') ?> Volver a <?= e((string) $article['category_title']) ?>
            </a>
        </div>
    </div>
</div>
