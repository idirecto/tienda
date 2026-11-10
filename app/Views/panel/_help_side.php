<?php
/**
 * Navegacion lateral comun del Centro de Ayuda.
 *
 * @var array<string, array<string, mixed>> $categories
 * @var string|null $activeCategory
 */
use Tienda\Core\Help;

$activeCategory = $activeCategory ?? null;
?>
<aside class="help-side" aria-label="Categorías de ayuda">
    <a class="help-side-home<?= $activeCategory === null ? ' is-current' : '' ?>"
       href="<?= e(Help::homeUrl()) ?>"<?= $activeCategory === null ? ' aria-current="page"' : '' ?>>
        <span class="help-side-mark" aria-hidden="true">?</span>
        <span>
            <strong>Centro de Ayuda</strong>
            <small>Guías de uso del panel</small>
        </span>
    </a>

    <nav class="help-side-nav" aria-label="Categorías">
        <?php foreach ($categories as $catSlug => $cat): ?>
            <?php
            $catActive = $activeCategory === $catSlug;
            $catCount = (int) ($cat['article_count'] ?? 0);
            $catUnit = ($cat['type'] ?? 'articles') === 'faq'
                ? ($catCount === 1 ? 'pregunta' : 'preguntas')
                : ($catCount === 1 ? 'artículo' : 'artículos');
            ?>
            <a href="<?= e(Help::categoryUrl((string) $catSlug)) ?>"
               class="<?= $catActive ? 'is-active' : '' ?>"
               <?= $catActive ? 'aria-current="page"' : '' ?>>
                <span class="help-side-icon" aria-hidden="true"><?= icon_svg((string) ($cat['icon'] ?? 'grid')) ?></span>
                <span class="help-side-copy">
                    <strong><?= e((string) $cat['title']) ?></strong>
                    <small><?= (int) $catCount ?> <?= e($catUnit) ?></small>
                </span>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php if (empty($hideSideSearch)): ?>
        <form class="help-side-search" method="get" action="<?= e(Help::homeUrl()) ?>" role="search">
            <label class="sr-only" for="help-side-q">Buscar en la ayuda</label>
            <input id="help-side-q" type="search" name="q" placeholder="Buscar en la ayuda…" autocomplete="off">
            <button class="btn btn-ghost btn-sm" type="submit" aria-label="Buscar en la ayuda">
                <?= icon_svg('search') ?>
            </button>
        </form>
    <?php endif; ?>

    <div class="help-side-note">
        <strong>Consejo</strong>
        <p>Usa el buscador para encontrar una palabra concreta: «pedido», «banner», «envío» o «dominio».</p>
    </div>
</aside>
