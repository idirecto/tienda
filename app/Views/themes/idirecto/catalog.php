<?php
/**
 * Catalogo: menu de categorias y subcategorias, filtros y paginacion.
 *
 * Los filtros van en una columna lateral y las subcategorias de la categoria
 * activa se listan debajo (asi se puede navegar sin usar el megamenu de la
 * cabecera). En movil y tablet todo se apila.
 *
 * @var array $result @var array $menu @var string $q @var int|null $category
 * @var string|null $categoryName @var array|null $subcategory @var array $subcategories
 * @var string $title @var array $ownProducts @var bool $catalogReady @var string $base
 */
$query = static function (array $extra = []) use ($q, $category, $subcategory): string {
    $params = array_filter([
        'q'      => $q !== '' ? $q : null,
        'cat'    => $category,
        'subcat' => $subcategory['id'] ?? null,
    ], static fn ($v): bool => $v !== null && $v !== '');

    foreach ($extra as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }

    return '?' . http_build_query($params);
};
?>
<section class="container section">
    <div class="catalog-layout">
        <aside class="catalog-aside">
            <form class="filters filters-aside" method="get" action="<?= e($base) ?>/catalogo">
                <h2 class="filters-title">Filtrar</h2>
                <label class="filters-field">
                    <span>Buscar</span>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar...">
                </label>
                <label class="filters-field">
                    <span>Categoria</span>
                    <select name="cat">
                        <option value="">Todas las categorias</option>
                        <?php foreach ($menu as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"<?= $category === (int) $c['id'] ? ' selected' : '' ?>>
                                <?= e($c['name']) ?> (<?= (int) $c['total'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if ($subcategories !== []): ?>
                    <div class="filters-subcats">
                        <span class="filters-subcats-title">Subcategorias</span>
                        <ul class="filters-subcats-list">
                            <li<?= $subcategory === null ? ' class="is-active"' : '' ?>>
                                <a href="<?= e($query(['subcat' => null])) ?>">Todas</a>
                            </li>
                            <?php foreach ($subcategories as $s): ?>
                                <li<?= $subcategory !== null && (int) $subcategory['id'] === (int) $s['id'] ? ' class="is-active"' : '' ?>>
                                    <a href="<?= e($query(['subcat' => (int) $s['id']])) ?>"><?= e($s['name']) ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <button class="btn btn-primary" type="submit">Filtrar</button>
            </form>
        </aside>

        <div class="catalog-results">
            <nav class="breadcrumb">
                <a href="<?= e($base) ?>/">Inicio</a> &gt;
                <?php if ($categoryName === null): ?>
                    <span class="current">Catalogo</span>
                <?php elseif ($subcategory === null): ?>
                    <a href="<?= e($base) ?>/catalogo">Catalogo</a> &gt;
                    <span class="current"><?= e($categoryName) ?></span>
                <?php else: ?>
                    <a href="<?= e($base) ?>/catalogo">Catalogo</a> &gt;
                    <a href="<?= e($query(['subcat' => null])) ?>"><?= e($categoryName) ?></a> &gt;
                    <span class="current"><?= e($subcategory['name']) ?></span>
                <?php endif; ?>
            </nav>

            <div class="page-head">
                <h1><?= e($title) ?></h1>
            </div>

            <?php if (!empty($ownProducts)): ?>
                <h2 class="subsection">Productos de la tienda</h2>
                <div class="grid grid-products">
                    <?php foreach ($ownProducts as $p): ?>
                        <?php include TIENDA_BASE . '/app/Views/themes/idirecto/_card_own.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($result['items'])): ?>
                <div class="card-empty">
                    <?php if ($catalogReady): ?>
                        <h3>Sin resultados</h3>
                        <p>No hemos encontrado productos con esos filtros.</p>
                    <?php else: ?>
                        <h3>Catalogo no disponible</h3>
                        <p>El catalogo central no esta accesible ahora mismo.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <h2 class="subsection">
                    <?= (int) $result['total'] === 1 ? '1 producto' : (int) $result['total'] . ' productos' ?>
                </h2>
                <div class="grid grid-products">
                    <?php foreach ($result['items'] as $p): ?>
                        <?php include TIENDA_BASE . '/app/Views/themes/idirecto/_card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <?php if (($result['pages'] ?? 0) > 1): ?>
                    <nav class="pagination">
                        <?php for ($i = 1; $i <= (int) $result['pages']; $i++): ?>
                            <?php if ($i > 12 && $i < $result['pages'] - 1) { continue; } ?>
                            <a class="<?= $i === (int) $result['page'] ? 'active' : '' ?>"
                               href="<?= e($query(['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
