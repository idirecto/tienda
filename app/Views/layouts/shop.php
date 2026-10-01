<?php
/**
 * Layout del storefront.
 *
 * @var string $content
 * @var \Tienda\Core\Tenant $tenant
 * @var string $pageTitle
 * @var string $base
 */
$brand = $tenant->colorPrimary();
$brand2 = $tenant->colorSecondary();
$fontMap = [
    'system' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
    'serif'  => "Georgia, 'Times New Roman', serif",
    'mono'   => "'SFMono-Regular', Consolas, 'Liberation Mono', monospace",
];
$font = $fontMap[$tenant->font()] ?? $fontMap['system'];

// Menu de categorias del catalogo (categorias -> subcategorias con stock).
// Va cacheado en fichero, asi que es barato en cada pagina del storefront.
$catalogMenu = \Tienda\Models\Catalog::menuTree();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $tenant->name()) ?></title>
    <meta name="description" content="<?= e($pageDescription ?? $tenant->metaDescription()) ?>">
    <?php if (!empty($canonical)): ?>
        <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">
    <style>
        :root {
            --brand: <?= e($brand) ?>;
            --brand-dark: <?= e($brand2) ?>;
            --font: <?= $font ?>;
        }
    </style>
</head>
<body class="shop theme-<?= e($tenant->theme()) ?> header-<?= e($tenant->headerStyle()) ?>">

<header class="shop-header">
    <div class="topbar">
        <div class="container topbar-inner">
            <span class="topbar-item">Envio rapido en 24/48 h</span>
            <span class="topbar-item">Atencion personalizada</span>
            <span class="topbar-item topbar-right">
                <a href="<?= e($base) ?>/contacto">Contacto</a>
            </span>
        </div>
    </div>

    <div class="container header-main">
        <a class="brand" href="<?= e($base) ?>/">
            <?php if ($tenant->logoUrl()): ?>
                <img src="<?= e($tenant->logoUrl()) ?>" alt="<?= e($tenant->name()) ?>">
            <?php else: ?>
                <span class="brand-mark"><?= e(mb_substr($tenant->name(), 0, 1)) ?></span>
                <span class="brand-text"><?= e($tenant->name()) ?></span>
            <?php endif; ?>
        </a>

        <form class="search" action="<?= e($base) ?>/catalogo" method="get">
            <input type="search" name="q" placeholder="Buscar productos..." value="<?= e($_GET['q'] ?? '') ?>">
            <button type="submit" aria-label="Buscar">Buscar</button>
        </form>

        <div class="header-actions">
            <?php if ($tenant->phone()): ?>
                <a class="phone" href="tel:<?= e($tenant->phone()) ?>"><?= e($tenant->phone()) ?></a>
            <?php endif; ?>
            <a class="btn-panel" href="<?= e($base) ?>/panel">Mi panel</a>
        </div>
    </div>

    <nav class="mainnav">
        <div class="container nav-inner">
            <a href="<?= e($base) ?>/">Inicio</a>
            <?php if ($catalogMenu !== []): ?>
                <span class="nav-catalog">
                    <a href="<?= e($base) ?>/catalogo">Catalogo</a>
                    <button type="button" class="nav-catalog-toggle" id="catalog-menu-trigger"
                            aria-expanded="false" aria-controls="catalog-menu"
                            aria-label="Abrir menu de categorias">
                        <span class="nav-catalog-caret" aria-hidden="true"></span>
                    </button>
                </span>
            <?php else: ?>
                <a href="<?= e($base) ?>/catalogo">Catalogo</a>
            <?php endif; ?>
            <?php foreach (($blocks ?? []) as $b): ?>
                <a href="<?= e($base) ?>/pagina/<?= e($b['slug']) ?>"><?= e($b['title']) ?></a>
            <?php endforeach; ?>
            <a href="<?= e($base) ?>/contacto">Contacto</a>
        </div>
    </nav>
</header>

<?php if ($catalogMenu !== []): ?>
    <div class="catmenu" id="catalog-menu" hidden>
        <div class="catmenu-backdrop" data-catmenu-close></div>
        <div class="catmenu-panel" role="dialog" aria-modal="true" aria-label="Categorias del catalogo">
            <div class="catmenu-head">
                <button type="button" class="catmenu-back" data-catmenu-back aria-label="Volver a categorias">&larr;</button>
                <span class="catmenu-title">Categorias</span>
                <button type="button" class="catmenu-close" data-catmenu-close aria-label="Cerrar menu">&times;</button>
            </div>

            <div class="catmenu-body">
                <nav class="catmenu-side" aria-label="Categorias del catalogo">
                    <a class="catmenu-all" href="<?= e($base) ?>/catalogo">Ver todo el catalogo</a>
                    <ul class="catmenu-cats" role="tablist" aria-orientation="vertical">
                        <?php foreach ($catalogMenu as $i => $cat): ?>
                            <li class="catmenu-cat<?= $i === 0 ? ' is-active' : '' ?>" role="presentation">
                                <button type="button" class="catmenu-cat-btn" data-catmenu-cat="<?= $i ?>"
                                        id="catmenu-tab-<?= (int) $cat['id'] ?>" role="tab"
                                        aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                                        aria-controls="catmenu-pane-<?= (int) $cat['id'] ?>">
                                    <span class="catmenu-cat-name"><?= e($cat['name']) ?></span>
                                    <i class="catmenu-arrow" aria-hidden="true"></i>
                                </button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <div class="catmenu-content">
                    <?php foreach ($catalogMenu as $i => $cat): ?>
                        <section class="catmenu-pane<?= $i === 0 ? ' is-active' : '' ?>"
                                 id="catmenu-pane-<?= (int) $cat['id'] ?>" data-catmenu-pane="<?= $i ?>"
                                 role="tabpanel" aria-labelledby="catmenu-tab-<?= (int) $cat['id'] ?>"
                                 tabindex="0">
                            <div class="catmenu-pane-head">
                                <h3 class="catmenu-pane-title">
                                    <?= e($cat['name']) ?>
                                    <small><?= (int) $cat['total'] ?> productos</small>
                                </h3>
                                <a class="catmenu-pane-all" href="<?= e($base) ?>/catalogo?cat=<?= (int) $cat['id'] ?>">
                                    Ver todo &rarr;
                                </a>
                            </div>
                            <ul class="catmenu-links">
                                <?php foreach ($cat['subcategories'] as $sub): ?>
                                    <li>
                                        <a href="<?= e($base) ?>/catalogo?cat=<?= (int) $cat['id'] ?>&amp;subcat=<?= (int) $sub['id'] ?>">
                                            <?= e($sub['name']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($notices)): ?>
    <div class="notices">
        <div class="container">
            <?php foreach ($notices as $n): ?>
                <div class="notice notice-<?= e($n['type']) ?>"><?= e($n['message']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<main class="shop-main">
    <?= $content ?>
</main>

<footer class="shop-footer">
    <div class="container footer-grid">
        <div>
            <h4><?= e($tenant->name()) ?></h4>
            <p><?= e($tenant->tagline()) ?></p>
        </div>
        <div>
            <h4>Tienda</h4>
            <a href="<?= e($base) ?>/catalogo">Catalogo</a>
            <a href="<?= e($base) ?>/contacto">Contacto</a>
            <?php foreach (array_slice($blocks ?? [], 0, 3) as $b): ?>
                <a href="<?= e($base) ?>/pagina/<?= e($b['slug']) ?>"><?= e($b['title']) ?></a>
            <?php endforeach; ?>
        </div>
        <div>
            <h4>Contacto</h4>
            <?php if ($tenant->address()): ?><p><?= e($tenant->address()) ?></p><?php endif; ?>
            <?php if ($tenant->postalCode() || $tenant->city()): ?>
                <p><?= e(trim($tenant->postalCode() . ' ' . $tenant->city())) ?></p>
            <?php endif; ?>
            <?php if ($tenant->email()): ?><p><a href="mailto:<?= e($tenant->email()) ?>"><?= e($tenant->email()) ?></a></p><?php endif; ?>
            <?php if ($tenant->phone()): ?><p><?= e($tenant->phone()) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <span>&copy; <?= date('Y') ?> <?= e($tenant->legalName() ?: $tenant->name()) ?></span>
            <span>Tienda gestionada con la plataforma de <?= e($app_name ?? 'idirecto') ?></span>
        </div>
    </div>
</footer>

<script src="<?= e(asset('assets/js/shop.js')) ?>"></script>
</body>
</html>
