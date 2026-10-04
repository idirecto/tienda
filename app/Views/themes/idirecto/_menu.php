<?php
/**
 * Navegacion del storefront: **Menú Compacto** y **Menú Catálogo**.
 *
 * Los dos pintan EXACTAMENTE el mismo arbol (`$menuTree`, de `Models\Menu`):
 * categorias de primer nivel, grupos de segundo nivel y destinos de tercer
 * nivel. Lo unico que cambia es la presentacion, y la elige la tienda con
 * `mt_stores.menu_style`:
 *
 *   compacto -> barra horizontal bajo la cabecera; al pasar el raton o pulsar
 *               una categoria se abre su megamenu (sin la columna lateral).
 *   catalogo -> boton «Todas las categorias»; al pulsarlo aparece un panel con
 *               fondo oscuro, la columna de categorias a la izquierda y los
 *               grupos y destinos a la derecha.
 *
 * Por debajo de 1024 px los DOS se convierten en el mismo menu lateral
 * (drawer) con navegacion por niveles: categorias -> grupos y destinos, con
 * boton «Volver» y boton de cerrar. Se apoya en el mismo DOM, asi que no hay
 * dos copias del arbol en la pagina.
 *
 * @var array $menuTree  Arbol de Menu::forStore()
 * @var array $menuQuick Accesos rapidos de Menu::quickLinks()
 * @var string $menuStyle `compacto` | `catalogo`
 * @var string $base
 */

$cats = (array) ($menuTree['categories'] ?? []);
if ($cats === []) {
    return; // Sin arbol no hay menu: no se pinta nada.
}

$style = $menuStyle === 'compacto' ? 'compacto' : 'catalogo';
$compactMax = max(1, (int) \Tienda\Core\Config::get('menu.compact_max', 7));
$visible = $style === 'compacto' ? array_slice($cats, 0, $compactMax) : $cats;
$extra = $style === 'compacto' ? array_slice($cats, $compactMax) : [];
$isCatalogo = $style === 'catalogo';
?>
<div class="mn<?= $isCatalogo ? ' mn--catalogo' : ' mn--compacto' ?>" data-menu-nav data-menu-style="<?= e($style) ?>">

    <?php if (!$isCatalogo): ?>
        <?php /* ------------------------------------------------------------------
                 MENU COMPACTO: barra horizontal bajo la cabecera.
                 ------------------------------------------------------------------ */ ?>
        <nav class="mn-bar" aria-label="Categorias del catalogo">
            <div class="container mn-bar-inner">
                <ul class="mn-bar-list">
                    <?php foreach ($visible as $i => $cat): ?>
                        <li class="mn-bar-item">
                            <a class="mn-bar-link" href="<?= e(menu_href($base, (string) $cat['path'])) ?>"
                               data-mn-cat="<?= $i ?>" aria-current="false">
                                <?= e($cat['label']) ?>
                                <?php if (!empty($cat['badge'])): ?>
                                    <span class="mn-badge"><?= e($cat['badge']) ?></span>
                                <?php endif; ?>
                            </a>
                            <?php if (!empty($cat['groups'])): ?>
                                <?php /* Sin grupos (categoria propia que apunta a una pagina) el
                                         enlace navega directo y no hace falta el desplegable. */ ?>
                                <button type="button" class="mn-bar-toggle" data-mn-open="<?= $i ?>"
                                        aria-expanded="false" aria-controls="mn-panel"
                                        aria-label="Abrir <?= e($cat['label']) ?>"><?= icon_svg('chevron-d', 'mn-caret') ?></button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($extra !== []): ?>
                    <div class="mn-more" data-mn-more>
                        <button type="button" class="mn-more-btn" data-mn-more-btn
                                aria-expanded="false" aria-controls="mn-more-panel">Mas categorias<?= icon_svg('chevron-d', 'mn-caret') ?></button>
                        <div class="mn-more-panel" id="mn-more-panel" hidden>
                            <ul>
                                <?php foreach ($extra as $cat): ?>
                                    <li>
                                        <a href="<?= e(menu_href($base, (string) $cat['path'])) ?>"><?= e($cat['label']) ?></a>
                                        <?php if (!empty($cat['groups'])): ?>
                                            <span class="mn-more-groups">
                                                <?php
                                                $names = array_map(static fn (array $g): string => (string) $g['label'], $cat['groups']);
                                                echo e(implode(' · ', array_slice($names, 0, 6)));
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>

                <ul class="mn-quick">
                    <?php foreach ($menuQuick as $quick): ?>
                        <li>
                            <a href="<?= e(menu_href($base, (string) $quick['path'])) ?>" class="mn-quick-link">
                                <?= icon_svg((string) $quick['icon'], 'mn-quick-icon') ?>
                                <span><?= e($quick['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </nav>
    <?php else: ?>
        <?php /* ------------------------------------------------------------------
                 MENU CATALOGO: boton principal «Todas las categorias».
                 ------------------------------------------------------------------ */ ?>
        <div class="mn-trigger">
            <div class="container">
                <button type="button" class="mn-open" data-mn-open="0"
                        aria-expanded="false" aria-controls="mn-panel">
                    <?= icon_svg('grid', 'mn-open-icon') ?>
                    <span>Todas las categorias</span>
                </button>

                <ul class="mn-quick mn-quick--inline">
                    <?php foreach ($menuQuick as $quick): ?>
                        <li>
                            <a href="<?= e(menu_href($base, (string) $quick['path'])) ?>" class="mn-quick-link">
                                <?= icon_svg((string) $quick['icon'], 'mn-quick-icon') ?>
                                <span><?= e($quick['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php /* ----------------------------------------------------------------------
             PANEL COMPARTIDO por los dos menus y por el movil.
             Escritorio + compacto : se abre bajo la barra, sin columna lateral.
             Escritorio + catalogo : panel con fondo oscuro y columna lateral.
             Movil                 : menu lateral por niveles con boton Volver.
             ---------------------------------------------------------------------- */ ?>
    <div class="mn-panel" id="mn-panel" hidden
         data-mn-modal="<?= $isCatalogo ? 'true' : 'false' ?>">
        <div class="mn-backdrop" data-mn-close></div>

        <div class="mn-dialog<?= $isCatalogo ? ' mn-dialog--full' : ' mn-dialog--drop' ?>"
             role="<?= $isCatalogo ? 'dialog' : 'group' ?>"
             <?= $isCatalogo ? 'aria-modal="true"' : '' ?>
             aria-label="Categorias del catalogo">

            <div class="mn-head">
                <button type="button" class="mn-back" data-mn-back aria-label="Volver a categorias" hidden>
                    <?= icon_svg('chevron-l') ?>
                </button>
                <span class="mn-title" data-mn-title>Todas las categorias</span>
                <button type="button" class="mn-close" data-mn-close aria-label="Cerrar menu">
                    <?= icon_svg('close') ?>
                </button>
            </div>

            <div class="mn-body">
                <nav class="mn-side" aria-label="Categorias principales" data-mn-side
                     <?= $isCatalogo ? 'data-mn-view="cats"' : '' ?>>
                    <ul class="mn-cats" role="<?= $isCatalogo ? 'tablist' : 'list' ?>"
                        aria-orientation="<?= $isCatalogo ? 'vertical' : '' ?>">
                        <?php foreach ($cats as $i => $cat): ?>
                            <li role="presentation">
                                <button type="button" class="mn-cat" data-mn-cat-tab="<?= $i ?>"
                                        id="mn-tab-<?= $i ?>" aria-controls="mn-pane-<?= $i ?>"
                                        <?= $isCatalogo ? 'role="tab"' : '' ?>
                                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                                        aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>">
                                    <span class="mn-cat-label"><?= e($cat['label']) ?></span>
                                    <?php if ((int) $cat['total'] > 0): ?>
                                        <span class="mn-cat-count"><?= (int) $cat['total'] ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($cat['badge'])): ?>
                                        <span class="mn-badge"><?= e($cat['badge']) ?></span>
                                    <?php endif; ?>
                                    <span class="mn-arrow" aria-hidden="true">&#8250;</span>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="mn-side-foot">
                        <a class="mn-side-all" href="<?= e($base) ?>/catalogo">
                            <?= icon_svg('grid', 'mn-quick-icon') ?><span>Ver todo el catalogo</span>
                        </a>
                    </div>
                </nav>

                <div class="mn-content" data-mn-content>
                    <?php foreach ($cats as $i => $cat): ?>
                        <section class="mn-pane<?= $i === 0 ? ' is-active' : '' ?>"
                                 id="mn-pane-<?= $i ?>" data-mn-pane="<?= $i ?>"
                                 role="tabpanel" aria-labelledby="mn-tab-<?= $i ?>"
                                 tabindex="0" <?= $i === 0 ? '' : 'hidden' ?>>

                            <div class="mn-pane-head">
                                <h3 class="mn-pane-title">
                                    <?= e($cat['label']) ?>
                                    <?php if ((int) $cat['total'] > 0): ?>
                                        <small><?= (int) $cat['total'] ?> productos</small>
                                    <?php endif; ?>
                                </h3>
                                <a class="mn-pane-all" href="<?= e(menu_href($base, (string) $cat['path'])) ?>">
                                    Ver todo <?= e(mb_strtolower($cat['label'])) ?> &rarr;
                                </a>
                            </div>

                            <div class="mn-cols">
                                <?php foreach ($cat['groups'] as $group): ?>
                                    <section class="mn-col">
                                        <h4 class="mn-col-title">
                                            <a href="<?= e(menu_href($base, (string) $group['path'])) ?>"><?= e($group['label']) ?></a>
                                        </h4>
                                        <ul class="mn-links">
                                            <?php foreach ($group['links'] as $link): ?>
                                                <li>
                                                    <a href="<?= e(menu_href($base, (string) $link['path'])) ?>">
                                                        <?= e($link['label']) ?>
                                                        <?php if (!empty($link['badge'])): ?>
                                                            <span class="mn-badge"><?= e($link['badge']) ?></span>
                                                        <?php endif; ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </section>
                                <?php endforeach; ?>
                            </div>

                            <?php /* Banner, destacados y marcas: se cargan al abrir el panel
                                     para no castigar la primera carga de la pagina. Solo las
                                     categorias del catalogo tienen panel promocional; en las
                                     instantaneas publicadas antes de `panel_id` se usa el id
                                     del nodo, que para una categoria es el de la categoria. */ ?>
                            <?php $panelId = (int) ($cat['panel_id'] ?? $cat['id'] ?? 0); ?>
                            <?php if ($panelId > 0): ?>
                                <div class="mn-promo" data-menu-panel="<?= $panelId ?>"
                                     data-menu-panel-url="<?= e($base) ?>/menu/panel/<?= $panelId ?>"></div>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
