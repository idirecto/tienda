<?php
/**
 * Portada y resultados del Centro de Ayuda.
 *
 * @var array<string, array<string, mixed>> $categories
 * @var string $query
 * @var bool $queryTooShort
 * @var array<int, array<string, mixed>> $results
 * @var bool $didSearch
 * @var bool $isPlatformUser
 */
use Tienda\Core\Help;

$quickAccess = [];
foreach ([
    ['primeros-pasos/crear-tienda', 'bolt', 'Crear mi tienda', 'De la cuenta de idirecto a la primera configuración.'],
    ['pedidos/guia-pedidos', 'cart', 'Gestionar pedidos', 'Estados, cobro, detalle y envío al mayorista.'],
    ['diseno/banners', 'star', 'Configurar banners', 'Imagen, enlace, fechas y vista previa.'],
    ['envios/zonas-tarifas', 'truck', 'Configurar envíos', 'Zonas, costes, plazos y envío gratis.'],
] as [$path, $icon, $title, $description]) {
    $article = Help::find($path, $isPlatformUser);
    if ($article !== null) {
        $quickAccess[] = compact('path', 'icon', 'title', 'description') + ['article' => $article];
    }
}
?>
<section class="help-hero">
    <div class="help-hero-copy">
        <span class="help-eyebrow">Centro de Ayuda</span>
        <h2>¿Qué necesitas resolver?</h2>
        <p>Guías sencillas para configurar tu tienda, preparar pedidos y encontrar la pantalla que buscas.</p>
    </div>
    <?php require __DIR__ . '/_help_search.php'; ?>
</section>

<?php if ($didSearch): ?>
    <section class="help-results" aria-labelledby="help-results-title">
        <div class="help-section-head">
            <div>
                <span class="help-eyebrow">Buscador</span>
                <h2 id="help-results-title">Resultados para «<?= e($query) ?>»</h2>
            </div>
            <span class="help-result-count" role="status" aria-live="polite">
                <?= count($results) ?> <?= count($results) === 1 ? 'resultado' : 'resultados' ?>
            </span>
        </div>

        <?php if ($queryTooShort): ?>
            <div class="help-empty">
                <h3>Escribe al menos dos letras</h3>
                <p>Así podemos buscar coincidencias en títulos, resúmenes y contenido.</p>
            </div>
        <?php elseif ($results === []): ?>
            <div class="help-empty">
                <h3>No hemos encontrado nada con esa búsqueda</h3>
                <p>Prueba con otra palabra o entra en una de estas categorías:</p>
                <div class="help-chip-row">
                    <?php foreach ($categories as $slug => $category): ?>
                        <a class="help-chip" href="<?= e(Help::categoryUrl((string) $slug)) ?>">
                            <?= e((string) $category['title']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <ol class="help-result-list">
                <?php foreach ($results as $result): ?>
                    <li>
                        <a class="help-result-card" href="<?= e((string) $result['url']) ?>">
                            <span class="help-result-kind"><?= $result['type'] === 'faq' ? 'Pregunta frecuente' : 'Artículo' ?></span>
                            <strong><?= e((string) $result['title']) ?></strong>
                            <span class="help-result-summary"><?= e((string) $result['summary']) ?></span>
                            <span class="help-result-category"><?= e((string) $result['category_title']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if (!$didSearch): ?>
    <section class="help-section" aria-labelledby="help-quick-title">
        <div class="help-section-head">
            <div>
                <span class="help-eyebrow">Accesos rápidos</span>
                <h2 id="help-quick-title">Lo más habitual</h2>
            </div>
        </div>
        <div class="help-quick-grid">
            <?php foreach ($quickAccess as $quick): ?>
                <a class="help-quick-card" href="<?= e(Help::articleUrl((string) $quick['path'])) ?>">
                    <span class="help-quick-icon" aria-hidden="true"><?= icon_svg((string) $quick['icon']) ?></span>
                    <strong><?= e((string) $quick['title']) ?></strong>
                    <span><?= e((string) $quick['description']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="help-section" aria-labelledby="help-categories-title">
        <div class="help-section-head">
            <div>
                <span class="help-eyebrow">Temas</span>
                <h2 id="help-categories-title">Explora por categoría</h2>
            </div>
            <span class="help-muted"><?= count($categories) ?> categorías</span>
        </div>
        <div class="help-category-grid">
            <?php foreach ($categories as $slug => $category): ?>
                <a class="help-category-card" href="<?= e(Help::categoryUrl((string) $slug)) ?>">
                    <span class="help-category-icon" aria-hidden="true"><?= icon_svg((string) ($category['icon'] ?? 'grid')) ?></span>
                    <span class="help-category-copy">
                        <strong><?= e((string) $category['title']) ?></strong>
                        <span><?= e((string) $category['description']) ?></span>
                    </span>
                    <span class="help-category-count"><?= (int) $category['article_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="help-footnote">
    <strong>Una ayuda honesta</strong>
    <p>
        Estas guías explican lo que el panel permite hacer hoy. Cuando una función no existe
        o está pendiente, se indica en el artículo y en «Solución de problemas».
    </p>
</section>
