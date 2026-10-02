<?php
/**
 * Catalogo: filtros avanzados, orden, listado y paginacion.
 *
 * Los filtros son enlaces (no un formulario con checkbox) para que funcionen
 * sin JavaScript y cada combinacion tenga URL propia, compartible e indexable.
 * El unico formulario es el de busqueda/categoria/precio, que reenvia los
 * filtros activos en campos ocultos.
 *
 * En movil los filtros pasan a un panel lateral; el boton que lo abre lo
 * activa el JS (sin JS, la columna se ve normalmente y no hay boton muerto).
 *
 * @var array $result @var array $menu @var string $q @var int|null $category
 * @var string|null $categoryName @var array|null $subcategory @var array $subcategories
 * @var string $title @var array $ownProducts @var bool $catalogReady @var string $base
 * @var array $facets @var array $activeFilters @var array $sorts @var string $sort
 */

use Tienda\Core\CatalogUrl;

$total = (int) ($result['total'] ?? 0);
$page = (int) ($result['page'] ?? 1);
$pages = (int) ($result['pages'] ?? 0);

// ---------------------------------------------------------------------------
// Constructores de URL: conservan siempre lo que el usuario ya ha elegido.
// ---------------------------------------------------------------------------
$baseParams = static function () use ($q, $category, $subcategory, $sort, $selection): array {
    $params = [];
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($category !== null && $category > 0) {
        $params['cat'] = $category;
    }
    if ($subcategory !== null) {
        $params['subcat'] = (int) $subcategory['id'];
    }
    if ($sort !== 'relevancia') {
        $params['orden'] = $sort;
    }
    // Facets y precio activos: cualquier enlace que se genere a partir de aqui
    // los conserva, de modo que quitar un filtro no borre los demas.
    if ($selection['terms'] !== []) {
        $params['f'] = $selection['terms'];
    }
    if ($selection['price_min'] !== null && $selection['price_min'] > 0) {
        $params['pmin'] = (string) (int) round($selection['price_min']);
    }
    if ($selection['price_max'] !== null && $selection['price_max'] > 0) {
        $params['pmax'] = (string) (int) round($selection['price_max']);
    }
    return $params;
};

/**
 * URL con los cambios indicados (null elimina la clave).
 *
 * Se construye siempre con las rutas SEO del catalogo (`CatalogUrl::fromQuery`)
 * para que los enlaces internos no pasen por la redireccion 301: el 301 es para
 * las URLs antiguas que ya esten enlazadas o indexadas, no para navegar.
 */
$buildUrl = static function (array $changes = []) use ($baseParams, $base): string {
    $params = $baseParams();
    foreach ($changes as $key => $value) {
        if ($value === null || $value === '' || $value === []) {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    $path = CatalogUrl::fromQuery($params);
    if ($path !== '') {
        return $base . $path;
    }

    $query = http_build_query($params);
    return $base . '/catalogo' . ($query !== '' ? '?' . $query : '');
};

/** Alterna un termino de faceta (lo anade o lo quita) y mantiene el resto. */
$toggleTerm = static function (string $facetKey, string $value) use ($selection, $buildUrl): string {
    $terms = $selection['terms'];
    $current = $terms[$facetKey] ?? [];
    if (in_array($value, $current, true)) {
        $current = array_values(array_diff($current, [$value]));
    } else {
        $current[] = $value;
    }
    if ($current === []) {
        unset($terms[$facetKey]);
    } else {
        $terms[$facetKey] = $current;
    }
    return $buildUrl(['f' => $terms]);
};

/** Quita un filtro activo concreto. */
$removeFilter = static function (array $filter) use ($selection, $buildUrl): string {
    if (!empty($filter['price'])) {
        return $buildUrl(['pmin' => null, 'pmax' => null]);
    }
    $terms = $selection['terms'];
    $key = (string) $filter['key'];
    $values = array_values(array_diff($terms[$key] ?? [], [(string) $filter['value']]));
    if ($values === []) {
        unset($terms[$key]);
    } else {
        $terms[$key] = $values;
    }
    return $buildUrl(['f' => $terms]);
};

$hasFilters = $activeFilters !== [] || $category !== null;
$priceMin = $selection['price_min'];
$priceMax = $selection['price_max'];
?>
<section class="container section catalog-section" id="catalogo">
    <nav class="breadcrumb" aria-label="Migas de pan">
        <a href="<?= e($base) ?>/">Inicio</a>
        <span aria-hidden="true">&gt;</span>
        <?php if ($categoryName === null): ?>
            <span class="current" aria-current="page">Catalogo</span>
        <?php elseif ($subcategory === null): ?>
            <a href="<?= e($buildUrl(['cat' => null, 'subcat' => null, 'page' => null])) ?>">Catalogo</a>
            <span aria-hidden="true">&gt;</span>
            <span class="current" aria-current="page"><?= e($categoryName) ?></span>
        <?php else: ?>
            <a href="<?= e($buildUrl(['cat' => null, 'subcat' => null, 'page' => null])) ?>">Catalogo</a>
            <span aria-hidden="true">&gt;</span>
            <a href="<?= e($buildUrl(['subcat' => null, 'page' => null])) ?>"><?= e($categoryName) ?></a>
            <span aria-hidden="true">&gt;</span>
            <span class="current" aria-current="page"><?= e($subcategory['name']) ?></span>
        <?php endif; ?>
    </nav>

    <div class="catalog-head">
        <div>
            <h1 class="catalog-title"><?= e($title) ?></h1>
            <p class="catalog-count">
                <?= $total === 1 ? '1 producto' : number_format($total, 0, ',', '.') . ' productos' ?>
                <?php if ($q !== ''): ?>
                    para <strong>&laquo;<?= e($q) ?>&raquo;</strong>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="catalog-layout">
        <aside class="catalog-aside" id="catalogo-filtros" aria-label="Filtros del catalogo">
            <form class="filters" method="get" action="<?= e($base) ?>/catalogo" data-filters-form>
                <div class="filters-head">
                    <h2 class="filters-title"><?= icon_svg('filter') ?> Filtrar</h2>
                    <?php if ($hasFilters): ?>
                        <a class="filters-clear" href="<?= e($base) ?>/catalogo">Limpiar</a>
                    <?php endif; ?>
                    <button type="button" class="icon-btn filters-close" data-filters-close
                            aria-label="Cerrar filtros" hidden><?= icon_svg('close') ?></button>
                </div>

                <?php /* Filtros activos que no se tocan desde este formulario. */ ?>
                <?php if ($sort !== 'relevancia'): ?>
                    <input type="hidden" name="orden" value="<?= e($sort) ?>">
                <?php endif; ?>
                <?php foreach ($selection['terms'] as $facetKey => $values): ?>
                    <?php foreach ($values as $value): ?>
                        <input type="hidden" name="f[<?= e($facetKey) ?>][]" value="<?= e($value) ?>">
                    <?php endforeach; ?>
                <?php endforeach; ?>

                <label class="filters-field">
                    <span>Buscar</span>
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre, marca, referencia...">
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

                <input type="hidden" name="subcat" value="<?= $subcategory !== null ? (int) $subcategory['id'] : '' ?>">

                <div class="filters-price">
                    <span class="filters-legend">Precio (EUR)</span>
                    <div class="filters-price-inputs">
                        <label>
                            <span class="sr-only">Precio minimo</span>
                            <input type="number" name="pmin" min="0" step="1" inputmode="numeric"
                                   placeholder="Min." value="<?= $priceMin !== null ? e((string) (int) $priceMin) : '' ?>">
                        </label>
                        <span aria-hidden="true">-</span>
                        <label>
                            <span class="sr-only">Precio maximo</span>
                            <input type="number" name="pmax" min="0" step="1" inputmode="numeric"
                                   placeholder="Max." value="<?= $priceMax !== null ? e((string) (int) $priceMax) : '' ?>">
                        </label>
                    </div>
                </div>

                <button class="btn btn-primary btn-block" type="submit">Aplicar filtros</button>
            </form>

            <?php foreach ($facets as $facet): ?>
                <?php if ($facet['type'] === 'price') { continue; } ?>

                <nav class="facet" aria-labelledby="facet-<?= e($facet['key']) ?>">
                    <h3 class="facet-head" id="facet-<?= e($facet['key']) ?>">
                        <?= e($facet['label']) ?>
                        <?php if (!empty($facet['selected'])): ?>
                            <span class="facet-count"><?= count($facet['selected']) ?></span>
                        <?php endif; ?>
                    </h3>

                    <ul class="facet-list">
                        <?php foreach ($facet['options'] as $option): ?>
                            <?php
                            $isActive = !empty($option['selected']);
                            $count = isset($option['count']) ? ' <small>(' . number_format((int) $option['count'], 0, ',', '.') . ')</small>' : '';
                            ?>
                            <li>
                                <a class="facet-option<?= $isActive ? ' is-active' : '' ?>"
                                   href="<?= e($toggleTerm((string) $facet['key'], (string) $option['value'])) ?>"
                                   <?= $isActive ? 'aria-pressed="true"' : 'aria-pressed="false"' ?>>
                                    <span class="facet-box" aria-hidden="true"><?= icon_svg('check') ?></span>
                                    <span class="facet-label"><?= e($option['label']) ?><?= $count ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endforeach; ?>

            <?php if ($subcategories !== []): ?>
                <nav class="facet" aria-labelledby="facet-subcats">
                    <h3 class="facet-head" id="facet-subcats">Subcategorias</h3>
                    <ul class="facet-list facet-list--scroll">
                        <li>
                            <a class="facet-option<?= $subcategory === null ? ' is-active' : '' ?>"
                               href="<?= e($buildUrl(['subcat' => null, 'page' => null])) ?>">
                                <span class="facet-box" aria-hidden="true"><?= icon_svg('check') ?></span>
                                <span class="facet-label">Todas</span>
                            </a>
                        </li>
                        <?php foreach ($subcategories as $s): ?>
                            <?php $isActive = $subcategory !== null && (int) $subcategory['id'] === (int) $s['id']; ?>
                            <li>
                                <a class="facet-option<?= $isActive ? ' is-active' : '' ?>"
                                   href="<?= e($buildUrl(['subcat' => (int) $s['id'], 'page' => null])) ?>">
                                    <span class="facet-box" aria-hidden="true"><?= icon_svg('check') ?></span>
                                    <span class="facet-label"><?= e($s['name']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </aside>

        <div class="catalog-results">
            <div class="results-bar">
                <button type="button" class="btn btn-ghost filters-toggle" data-filters-toggle hidden>
                    <?= icon_svg('filter') ?> Filtros
                    <?php if ($activeFilters !== []): ?>
                        <span class="filters-badge"><?= count($activeFilters) ?></span>
                    <?php endif; ?>
                </button>

                <div class="results-bar-right">
                    <?php if ($sorts !== []): ?>
                        <label class="sort-select">
                            <span>Ordenar por</span>
                            <select data-sort-select>
                                <?php foreach ($sorts as $key => $label): ?>
                                    <option value="<?= e($buildUrl(['orden' => $key === 'relevancia' ? null : $key, 'page' => null])) ?>"
                                        <?= $sort === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($activeFilters !== []): ?>
                <div class="active-filters" aria-label="Filtros activos">
                    <?php foreach ($activeFilters as $filter): ?>
                        <a class="chip chip-remove" href="<?= e($removeFilter($filter)) ?>">
                            <?php if (!empty($filter['price'])): ?>
                                <?= e($filter['label']) ?>
                                <?= $priceMin !== null ? 'desde ' . e(euros($priceMin)) : '' ?>
                                <?= $priceMax !== null ? 'hasta ' . e(euros($priceMax)) : '' ?>
                            <?php else: ?>
                                <?= e($filter['label']) ?>
                            <?php endif; ?>
                            <?= icon_svg('close') ?>
                        </a>
                    <?php endforeach; ?>
                    <a class="chip chip-clear" href="<?= e($base) ?>/catalogo">Quitar todo</a>
                </div>
            <?php endif; ?>

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
                        <span class="empty-icon" aria-hidden="true"><?= icon_svg('search') ?></span>
                        <h2>Sin resultados</h2>
                        <p>No hemos encontrado productos con esos filtros. Prueba a quitar alguno.</p>
                        <a class="btn btn-primary" href="<?= e($base) ?>/catalogo">Ver todo el catalogo</a>
                    <?php else: ?>
                        <span class="empty-icon" aria-hidden="true"><?= icon_svg('wrench') ?></span>
                        <h2>Catalogo no disponible</h2>
                        <p>El catalogo central no esta accesible ahora mismo.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-products">
                    <?php foreach ($result['items'] as $p): ?>
                        <?php include TIENDA_BASE . '/app/Views/themes/idirecto/_card.php'; ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <nav class="pagination" aria-label="Paginacion">
                        <?php if ($page > 1): ?>
                            <a class="page-nav" href="<?= e($buildUrl(['page' => $page - 1])) ?>" rel="prev"
                               aria-label="Pagina anterior"><?= icon_svg('chevron-l') ?></a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $pages; $i++): ?>
                            <?php if ($i > 12 && $i < $pages - 1) { continue; } ?>
                            <a class="<?= $i === $page ? 'active' : '' ?>"
                               href="<?= e($buildUrl(['page' => $i])) ?>"
                               <?= $i === $page ? 'aria-current="page"' : '' ?>>
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pages): ?>
                            <a class="page-nav" href="<?= e($buildUrl(['page' => $page + 1])) ?>" rel="next"
                               aria-label="Pagina siguiente"><?= icon_svg('chevron-r') ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</section>
