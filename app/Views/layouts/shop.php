<?php
/**
 * Layout del storefront.
 *
 * Todo el color, la tipografia y las formas salen de variables CSS que genera
 * `Appearance` a partir de la identidad de la tienda: aqui no hay ni un color
 * escrito a mano.
 *
 * @var string $content
 * @var \Tienda\Core\Tenant $tenant
 * @var string $pageTitle
 * @var string $pageDescription
 * @var string $base
 * @var string|null $canonical
 * @var array $blocks
 */
use Tienda\Core\Appearance;
use Tienda\Core\Cart;
use Tienda\Core\CustomerAuth;
use Tienda\Core\Session;
use Tienda\Models\Catalog;

$scheme = Appearance::scheme($tenant);
$themeCss = Appearance::css($tenant);
$brandName = $tenant->logoUrl();

// Avisos de la ultima accion (carrito, cuenta, pedido).
$flashSuccess = Session::pullFlash('success');
$flashError = Session::pullFlash('error');

// Carrito y sesion del cliente, para la cabecera.
$cartUnits = Cart::count($tenant->id());
$shopCustomer = CustomerAuth::customer($tenant->id());

// Menu de categorias del catalogo (categorias -> subcategorias con stock).
// Va cacheado en fichero, asi que es barato en cada pagina del storefront.
$catalogMenu = isset($catalogMenu) ? $catalogMenu : Catalog::menuTree();

// Precarga de la conexion con el CDN de imagenes del catalogo: ahorra el
// saludo TLS en cuanto aparece la primera tarjeta.
$imageHost = (string) parse_url((string) config('catalog.image_url', ''), PHP_URL_HOST);

$headerStyle = preg_replace('/[^a-z0-9_\-]/i', '', $tenant->headerStyle()) ?: 'classic';
?>
<!doctype html>
<html lang="es" data-color-scheme="<?= e($scheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? $tenant->name()) ?></title>
    <meta name="description" content="<?= e($pageDescription ?? $tenant->metaDescription()) ?>">
    <meta name="theme-color" content="<?= e(Appearance::themeColor($tenant)) ?>">
    <?php if (!empty($canonical)): ?>
        <link rel="canonical" href="<?= e($canonical) ?>">
    <?php endif; ?>
    <?php if ($tenant->get('favicon_url')): ?>
        <link rel="icon" href="<?= e((string) $tenant->get('favicon_url')) ?>">
    <?php endif; ?>

    <?php if ($imageHost): ?>
        <link rel="preconnect" href="https://<?= e($imageHost) ?>" crossorigin>
    <?php endif; ?>

    <?php /* Preferencia de modo guardada por el usuario: se aplica antes de pintar para que no haya parpadeo. */ ?>
    <script>
        (function () {
            try {
                var saved = window.localStorage.getItem('tienda.color-scheme');
                if (saved === 'dark' || saved === 'light') {
                    document.documentElement.setAttribute('data-color-scheme', saved);
                }
            } catch (e) { /* Modo privado: se usa el esquema de la tienda. */ }
        })();
    </script>

    <?php /* Identidad de la tienda (design tokens) */ ?>
    <style><?= $themeCss ?></style>
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">
    <?php if ($tenant->customCss() !== ''): ?>
        <style><?= $tenant->customCss() ?></style>
    <?php endif; ?>
</head>
<body class="shop theme-<?= e($tenant->theme()) ?> header-<?= e($headerStyle) ?>">

<a class="skip-link" href="#contenido">Saltar al contenido principal</a>

<header class="shop-header">
    <div class="topbar">
        <div class="container topbar-inner">
            <span class="topbar-item">Envio rapido en 24/48 h</span>
            <span class="topbar-item topbar-hide-sm">Atencion personalizada</span>
            <span class="topbar-item topbar-right">
                <a href="<?= e($base) ?>/contacto">Contacto</a>
            </span>
        </div>
    </div>

    <div class="container header-main">
        <a class="brand" href="<?= e($base) ?>/" aria-label="<?= e($tenant->name()) ?> - Inicio">
            <?php if ($brandName): ?>
                <img src="<?= e($brandName) ?>" alt="<?= e($tenant->name()) ?>" width="160" height="44" decoding="async">
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true"><?= e(mb_substr($tenant->name(), 0, 1)) ?></span>
                <span class="brand-text"><?= e($tenant->name()) ?></span>
            <?php endif; ?>
        </a>

        <form class="search" action="<?= e($base) ?>/catalogo" method="get" role="search">
            <label class="sr-only" for="search-q">Buscar productos</label>
            <input type="search" id="search-q" name="q" placeholder="Buscar productos, marcas, referencia..."
                   value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
            <button type="submit" aria-label="Buscar">
                <?= icon_svg('search') ?>
                <span class="search-label">Buscar</span>
            </button>
        </form>

        <div class="header-actions">
            <?php if ($tenant->phone()): ?>
                <a class="phone" href="tel:<?= e($tenant->phone()) ?>">
                    <?= icon_svg('phone') ?>
                    <span><?= e($tenant->phone()) ?></span>
                </a>
            <?php endif; ?>

            <button type="button" class="icon-btn scheme-toggle" data-scheme-toggle
                    aria-label="Cambiar entre modo claro y oscuro" aria-pressed="false">
                <?= icon_svg('sun', 'icon-sun') ?>
                <?= icon_svg('moon', 'icon-moon') ?>
            </button>

            <a class="icon-btn shop-account" href="<?= e($base) ?>/cuenta"
               aria-label="<?= $shopCustomer ? 'Mi cuenta' : 'Entrar en mi cuenta' ?>">
                <?= icon_svg('user') ?>
                <span class="shop-account-label"><?= $shopCustomer ? e(mb_substr((string) $shopCustomer['name'], 0, 12)) : 'Entrar' ?></span>
            </a>

            <a class="icon-btn shop-cart" href="<?= e($base) ?>/carrito" aria-label="Carrito de la compra">
                <?= icon_svg('cart') ?>
                <?php if ($cartUnits > 0): ?>
                    <span class="shop-cart-count"><?= (int) $cartUnits ?></span>
                <?php endif; ?>
            </a>

            <a class="btn-panel" href="<?= e($base) ?>/panel">Mi panel</a>
        </div>
    </div>

    <nav class="mainnav" aria-label="Navegacion principal">
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
                <div class="notice notice-<?= e($n['type']) ?>" role="status"><?= e($n['message']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<main class="shop-main" id="contenido" tabindex="-1">
    <?php if ($flashSuccess || $flashError): ?>
        <div class="container shop-flash">
            <?php if ($flashSuccess): ?><div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div><?php endif; ?>
            <?php if ($flashError): ?><div class="alert alert-error" role="alert"><?= e($flashError) ?></div><?php endif; ?>
        </div>
    <?php endif; ?>
    <?= $content ?>
</main>

<footer class="shop-footer">
    <div class="container footer-grid">
        <div>
            <h4><?= e($tenant->name()) ?></h4>
            <p><?= e($tenant->tagline()) ?></p>
        </div>
        <nav aria-label="Enlaces de la tienda">
            <h4>Tienda</h4>
            <a href="<?= e($base) ?>/catalogo">Catalogo</a>
            <a href="<?= e($base) ?>/contacto">Contacto</a>
            <?php foreach (array_slice($blocks ?? [], 0, 3) as $b): ?>
                <a href="<?= e($base) ?>/pagina/<?= e($b['slug']) ?>"><?= e($b['title']) ?></a>
            <?php endforeach; ?>
        </nav>
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

<script src="<?= e(asset('assets/js/shop.js')) ?>" defer></script>
</body>
</html>
