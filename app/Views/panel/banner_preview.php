<?php
/**
 * Documento aislado para previsualizar un banner con el CSS real de la tienda.
 *
 * @var array $banner
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */
use Tienda\Core\Appearance;

$scheme = Appearance::scheme($tenant);
$themeCss = Appearance::css($tenant);
$banners = [$banner];
?>
<!doctype html>
<html lang="es" data-color-scheme="<?= e($scheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Previsualizacion del banner</title>
    <style><?= $themeCss ?></style>
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">
    <style>
        html, body { margin: 0; min-height: 100%; background: var(--c-bg); }
        .banner-preview-canvas { padding: 0 0 1rem; }
    </style>
</head>
<body class="shop theme-<?= e($tenant->theme()) ?>">
    <div class="banner-preview-canvas">
        <?php include TIENDA_BASE . '/app/Views/themes/idirecto/_hero.php'; ?>
    </div>
</body>
</html>
