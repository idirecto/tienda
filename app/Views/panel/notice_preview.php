<?php
/**
 * Documento aislado para previsualizar un aviso con el CSS real de la tienda.
 *
 * @var array $notice
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */
use Tienda\Core\Appearance;

$scheme = Appearance::scheme($tenant);
$themeCss = Appearance::css($tenant);
$n = $notice;
?>
<!doctype html>
<html lang="es" data-color-scheme="<?= e($scheme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Previsualizacion del aviso</title>
    <style><?= $themeCss ?></style>
    <link rel="stylesheet" href="<?= e(asset('assets/css/shop.css')) ?>">
    <style>
        html, body { margin: 0; min-height: 100%; background: var(--c-bg); }
        .notice-preview-canvas { padding: 1rem 0; }
    </style>
</head>
<body class="shop theme-<?= e($tenant->theme()) ?>">
    <div class="notice-preview-canvas">
        <div class="container">
            <?php include TIENDA_BASE . '/app/Views/themes/idirecto/_notice.php'; ?>
        </div>
    </div>
</body>
</html>
