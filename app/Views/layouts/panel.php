<?php
/**
 * Layout del panel de la tienda.
 *
 * @var string $content
 * @var \Tienda\Core\Tenant $tenant
 * @var array|null $auth_user
 * @var string $pageTitle
 * @var string $base
 */
use Tienda\Core\Auth;
use Tienda\Core\Session;

$current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$success = Session::pullFlash('success');
$error = Session::pullFlash('error');
$warning = Session::pullFlash('warning');

$nav = [
    ['panel',          'Panel',            'Resumen de tu tienda'],
    ['panel/pedidos',  'Pedidos',          'Pedidos de tus clientes'],
    ['panel/clientes', 'Clientes',         'Quien te compra y sus direcciones'],
    ['panel/menu',     'Menu',             'Menu Compacto o Menu Catalogo'],
    ['panel/diseno',   'Diseno',           'Plantilla, colores y textos'],
    ['panel/banners',  'Banners',          'Imagenes destacadas'],
    ['panel/avisos',   'Avisos',           'Anuncios para tus clientes'],
    ['panel/productos','Productos propios','Tu catalogo adicional'],
    ['panel/dominios', 'Dominios',         'Tu direccion web'],
    ['panel/ajustes',  'Ajustes',          'Datos y usuarios'],
    ['panel/logs',     'Logs',             'Actividad, compras y errores por dia'],
];

// El menu de la plataforma solo lo ve el rol `platform`.
if (Auth::isPlatform()) {
    $nav[] = ['panel/plataforma/menu', 'Menu plataforma', 'Arbol compartido y que ve cada tienda'];
}

$isActive = static function (string $path) use ($current, $base): bool {
    $full = $base . '/' . $path;
    if ($path === 'panel') {
        return rtrim($current, '/') === rtrim($full, '/');
    }
    return str_starts_with($current, $full);
};
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Panel') ?> · <?= e($tenant->name()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel.css')) ?>">
    <style>:root{--brand: <?= e($tenant->colorPrimary()) ?>;}</style>
</head>
<body class="panel">
<div class="shell">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div>
                <strong><?= e($tenant->name()) ?></strong>
                <small><?= e($tenant->planName()) ?></small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <?php foreach ($nav as [$path, $label, $hint]): ?>
                <a href="<?= e($base . '/' . $path) ?>" class="<?= $isActive($path) ? 'active' : '' ?>" title="<?= e($hint) ?>">
                    <span class="nav-label"><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <a class="sidebar-store" href="<?= e($base) ?>/" target="_blank" rel="noopener">Ver mi tienda &rarr;</a>
        </div>
    </aside>

    <div class="main">
        <header class="topbar">
            <h1><?= e($pageTitle ?? 'Panel') ?></h1>
            <div class="topbar-right">
                <span class="user"><?= e($auth_user['name'] ?? '') ?></span>
                <a class="btn btn-ghost" href="<?= e($base) ?>/panel/logout">Salir</a>
            </div>
        </header>

        <div class="content">
            <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
            <?php if ($warning): ?><div class="alert alert-warning"><?= e($warning) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
            <?= $content ?>
        </div>
    </div>
</div>
<script>
    window.TIENDA = {
        base: <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>,
        csrf: <?= json_encode(\Tienda\Core\Csrf::token()) ?>,
        uploadUrl: <?= json_encode($base . '/panel/media/subir', JSON_UNESCAPED_SLASHES) ?>,
        designTokensUrl: <?= json_encode($base . '/panel/diseno/tokens', JSON_UNESCAPED_SLASHES) ?>
    };
</script>
<script src="<?= e(asset('assets/js/panel.js')) ?>" defer></script>
</body>
</html>
