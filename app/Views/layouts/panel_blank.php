<?php
/**
 * Layout sin menu (login).
 * @var string $content @var string $pageTitle @var string $base
 * @var \Tienda\Core\Tenant|null $tenant
 */
use Tienda\Core\Favicon;
use Tienda\Core\Session;
$success = Session::pullFlash('success');
$error = Session::pullFlash('error');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Acceso') ?> · <?= e($app_name ?? 'Tienda') ?></title>
    <?php /* Sin tienda resuelta se usa el favicon predeterminado de Valduran. */ ?>
    <?= Favicon::linkTags($tenant ?? null) ?>
    <link rel="stylesheet" href="<?= e(asset('assets/css/panel.css')) ?>">
</head>
<body class="panel panel-auth">
    <main class="auth-card">
        <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <?= $content ?>
    </main>
</body>
</html>
