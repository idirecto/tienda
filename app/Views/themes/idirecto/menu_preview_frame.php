<?php
/**
 * Marco de la vista previa: SOLO el menu, con los estilos reales de la tienda.
 *
 * Se carga dentro de los iframes del panel. El ancho del iframe activa las
 * media queries de `shop.css`, asi que el marco de 390 px ensena el cajon de
 * movil de verdad, sin trucos.
 *
 * @var \Tienda\Core\Tenant $tenant
 * @var string $menuStyle
 * @var array $menuTree
 * @var array $menuQuick
 * @var array $styles
 * @var string $base
 */

use Tienda\Core\Appearance;
?>
<!doctype html>
<html lang="es" data-color-scheme="<?= e(Appearance::scheme($tenant)) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vista previa del menu</title>
    <style><?= Appearance::css($tenant) ?></style>
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">
    <style>
        /* En la previa no hay cabecera de tienda: el menu se pega arriba. */
        body { margin: 0; background: var(--c-bg); }
        .mn-preview-hint {
            padding: .5rem .75rem; font: 500 12px/1.4 system-ui, sans-serif;
            color: var(--c-muted); background: var(--c-surface-2);
            border-bottom: 1px solid var(--c-border);
        }
        /* La cabecera con el boton del menu lateral solo se ve en el marco
           estrecho (390 px), igual que en la tienda de verdad. */
        .mn-preview-header {
            display: none; align-items: center; gap: .6rem;
            padding: .55rem .8rem; border-bottom: 1px solid var(--c-border);
            background: var(--c-surface); font-weight: 700; color: var(--c-heading);
        }
        @media (max-width: 1023.98px) {
            .mn-preview-header { display: flex; }
            .mn-preview-header .nav-menu-btn { display: inline-flex; }
        }
    </style>
</head>
<body>
<p class="mn-preview-hint">
    Borrador sin publicar · estilo:
    <?= e((string) ($styles[$menuStyle] ?? $menuStyle)) ?>
</p>

<?php if ($menuTree['categories'] === []): ?>
    <div class="container section"><p class="muted">El borrador no tiene ninguna categoria activa.</p></div>
<?php else: ?>
    <header class="mn-preview-header">
        <button type="button" class="nav-menu-btn" data-mn-mobile-open
                aria-expanded="false" aria-controls="mn-panel"
                aria-label="Abrir menu de categorias">
            <?= icon_svg('list') ?>
            <span class="nav-menu-btn-text">Categorias</span>
        </button>
        <span><?= e($tenant->name()) ?></span>
    </header>
    <?php include __DIR__ . '/_menu.php'; ?>
<?php endif; ?>

<script src="<?= e(asset('assets/js/shop.js')) ?>" defer></script>
</body>
</html>
