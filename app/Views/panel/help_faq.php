<?php
/**
 * Preguntas frecuentes agrupadas por temas.
 *
 * @var array<string, array<string, mixed>> $categories
 * @var array<string, mixed> $category
 * @var array<int, array<string, mixed>> $groups
 * @var string $base
 */
use Tienda\Core\Help;

$activeCategory = 'preguntas-frecuentes';
$query = '';
$hideSideSearch = true;
$total = Help::faqCount();
?>
<div class="help-layout">
    <?php require __DIR__ . '/_help_side.php'; ?>

    <div class="help-main">
        <nav class="help-breadcrumb" aria-label="Migas de pan">
            <a href="<?= e(Help::homeUrl()) ?>">Ayuda</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?= e((string) $category['title']) ?></span>
        </nav>

        <header class="help-page-head help-page-head--stacked">
            <span class="help-category-icon" aria-hidden="true"><?= icon_svg('headset') ?></span>
            <div>
                <h2><?= e((string) $category['title']) ?></h2>
                <p><?= e((string) $category['description']) ?> Hay <?= (int) $total ?> preguntas agrupadas por tema.</p>
            </div>
        </header>

        <?php require __DIR__ . '/_help_search.php'; ?>

        <div class="help-faq-groups">
            <?php foreach ($groups as $group): ?>
                <section class="help-faq-group" aria-labelledby="faq-group-<?= e((string) Help::fold((string) $group['title'])) ?>">
                    <h3 id="faq-group-<?= e((string) Help::fold((string) $group['title'])) ?>"><?= e((string) $group['title']) ?></h3>
                    <?php foreach ((array) $group['items'] as $item): ?>
                        <details class="help-faq-item" id="<?= e((string) $item['anchor']) ?>">
                            <summary><?= e((string) $item['question']) ?></summary>
                            <div class="help-faq-answer">
                                <?= str_replace('{{base}}', e($base), (string) $item['answer']) ?>
                                <?php if (!empty($item['article'])): ?>
                                    <?php $faqArticle = Help::find((string) $item['article']); ?>
                                    <?php if ($faqArticle !== null): ?>
                                        <p class="help-faq-link">
                                            <a href="<?= e(Help::articleUrl((string) $faqArticle['path'])) ?>">
                                                Ver la guía completa: <?= e((string) $faqArticle['title']) ?> →
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </div>
</div>
