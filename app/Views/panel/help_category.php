<?php
/**
 * Categoria del Centro de Ayuda.
 *
 * @var array<string, array<string, mixed>> $categories
 * @var array<string, mixed> $category
 * @var string $categorySlug
 * @var array<int, array<string, mixed>> $articles
 */
use Tienda\Core\Help;

$activeCategory = $categorySlug;
?>
<div class="help-layout">
    <?php require __DIR__ . '/_help_side.php'; ?>

    <div class="help-main">
        <nav class="help-breadcrumb" aria-label="Migas de pan">
            <a href="<?= e(Help::homeUrl()) ?>">Ayuda</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e((string) $category['title']) ?></span>
        </nav>

        <header class="help-page-head">
            <span class="help-category-icon" aria-hidden="true"><?= icon_svg((string) ($category['icon'] ?? 'grid')) ?></span>
            <div>
                <h2><?= e((string) $category['title']) ?></h2>
                <p><?= e((string) $category['description']) ?></p>
            </div>
        </header>

        <?php if ($articles === []): ?>
            <div class="help-empty">
                <h3>Todavía no hay artículos en esta categoría</h3>
                <p>Vuelve al inicio de la ayuda y elige otro tema.</p>
                <a class="btn btn-primary" href="<?= e(Help::homeUrl()) ?>">Volver a la ayuda</a>
            </div>
        <?php else: ?>
            <ol class="help-article-list">
                <?php foreach ($articles as $index => $article): ?>
                    <li>
                        <a class="help-article-card" href="<?= e(Help::articleUrl((string) $article['path'])) ?>">
                            <span class="help-article-number" aria-hidden="true"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="help-article-copy">
                                <strong><?= e((string) $article['title']) ?></strong>
                                <span><?= e((string) $article['summary']) ?></span>
                            </span>
                            <span class="help-article-arrow" aria-hidden="true"><?= icon_svg('chevron-r') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </div>
</div>
