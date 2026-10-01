<?php

declare(strict_types=1);

/**
 * Front controller unico del proyecto.
 *
 *  - Storefront de la tienda (resuelto por hostname): / , /catalogo, /producto/{id}
 *  - Panel de la tienda:                            /panel/*
 */

// Servidor embebido de PHP: solo se sirven como estaticos los ficheros de
// /public (assets y subidas). Cualquier otra ruta pasa por el front controller,
// de modo que .env, .git, .agents, app/, config/... nunca se entregan.
if (PHP_SAPI === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $publicDir = realpath(__DIR__ . '/public');
    $requested = realpath(__DIR__ . $reqPath);

    if ($reqPath !== '/'
        && $publicDir !== false
        && $requested !== false
        && is_file($requested)
        && str_starts_with($requested, $publicDir . DIRECTORY_SEPARATOR)
    ) {
        return false;
    }
}

define('TIENDA_BASE', __DIR__);
require TIENDA_BASE . '/app/bootstrap.php';

use Tienda\Controllers\Admin\AuthController;
use Tienda\Controllers\Admin\BannerController;
use Tienda\Controllers\Admin\CustomerController as CustomerAdminController;
use Tienda\Controllers\Admin\DashboardController;
use Tienda\Controllers\Admin\DesignController;
use Tienda\Controllers\Admin\DomainController;
use Tienda\Controllers\Admin\MediaController;
use Tienda\Controllers\Admin\NoticeController;
use Tienda\Controllers\Admin\OrderController;
use Tienda\Controllers\Admin\ProductController;
use Tienda\Controllers\Admin\SettingsController;
use Tienda\Controllers\CartController;
use Tienda\Controllers\CheckoutController;
use Tienda\Controllers\CustomerController;
use Tienda\Controllers\RegistrationController;
use Tienda\Controllers\StorefrontController;
use Tienda\Core\Router;
use Tienda\Core\TenantResolver;

$router = new Router();

// -----------------------------------------------------------------------------
// STOREFRONT (tienda publica)
// -----------------------------------------------------------------------------
$router->get('/',                      [StorefrontController::class, 'home']);
$router->get('/catalogo',              [StorefrontController::class, 'catalog']);
// URL SEO tipo idirecto: /producto/{nombre-slug}/{id}
$router->get('/producto/{slug}/{id}',  [StorefrontController::class, 'product']);
// Compatibilidad: /producto/{id} redirige 301 a la URL canonica con slug
$router->get('/producto/{id}',         [StorefrontController::class, 'productLegacy']);
$router->get('/contacto',              [StorefrontController::class, 'contact']);
$router->get('/pagina/{slug}',         [StorefrontController::class, 'page']);

// -----------------------------------------------------------------------------
// COMPRA DEL CLIENTE (carrito, su cuenta y cierre del pedido)
// -----------------------------------------------------------------------------
$router->get('/carrito',                [CartController::class, 'index']);
$router->post('/carrito/anadir',        [CartController::class, 'add']);
$router->post('/carrito/actualizar',    [CartController::class, 'update']);
$router->post('/carrito/quitar',        [CartController::class, 'remove']);
$router->post('/carrito/vaciar',        [CartController::class, 'clear']);

$router->get('/checkout',               [CheckoutController::class, 'start']);
$router->post('/checkout',              [CheckoutController::class, 'place']);
$router->get('/checkout/gracias/{code}', [CheckoutController::class, 'thanks']);

$router->get('/cuenta/login',           [CustomerController::class, 'showLogin']);
$router->post('/cuenta/login',          [CustomerController::class, 'login']);
$router->get('/cuenta/registro',        [CustomerController::class, 'showRegister']);
$router->post('/cuenta/registro',       [CustomerController::class, 'register']);
$router->post('/cuenta/salir',          [CustomerController::class, 'logout']);

$router->get('/cuenta',                 [CustomerController::class, 'index']);
$router->get('/cuenta/pedidos',         [CustomerController::class, 'orders']);
$router->get('/cuenta/pedidos/{code}',  [CustomerController::class, 'order']);
// OJO: 'nueva' tiene que ir ANTES de '{id}' (el router resuelve en orden).
$router->get('/cuenta/direcciones',     [CustomerController::class, 'addresses']);
$router->get('/cuenta/direcciones/nueva', [CustomerController::class, 'addressForm']);
$router->get('/cuenta/direcciones/{id}', [CustomerController::class, 'addressForm']);
$router->post('/cuenta/direcciones',    [CustomerController::class, 'addressSave']);
$router->post('/cuenta/direcciones/{id}/borrar', [CustomerController::class, 'addressDelete']);
$router->get('/cuenta/perfil',          [CustomerController::class, 'profile']);
$router->post('/cuenta/perfil',         [CustomerController::class, 'profileSave']);

// -----------------------------------------------------------------------------
// REGISTRO DE UNA TIENDA NUEVA
// Solo se puede registrar quien tiene una cuenta activa en el mayorista: se
// comprueban sus credenciales de idirecto contra la tabla `tiendas`.
// -----------------------------------------------------------------------------
$router->get('/registro',              [RegistrationController::class, 'show']);
$router->post('/registro',             [RegistrationController::class, 'store']);

// -----------------------------------------------------------------------------
// PANEL DE LA TIENDA
// -----------------------------------------------------------------------------
$router->get('/panel/login',           [AuthController::class, 'showLogin']);
$router->post('/panel/login',          [AuthController::class, 'login']);
$router->get('/panel/logout',          [AuthController::class, 'logout']);

$router->get('/panel',                 [DashboardController::class, 'index']);

$router->get('/panel/diseno',          [DesignController::class, 'index']);
$router->post('/panel/diseno',         [DesignController::class, 'save']);
// Vista previa de la identidad visual (CSS de tokens + pagina de muestra).
$router->get('/panel/diseno/tokens',   [DesignController::class, 'tokens']);
$router->get('/panel/diseno/previa',   [DesignController::class, 'preview']);

$router->get('/panel/banners',         [BannerController::class, 'index']);
$router->post('/panel/banners',        [BannerController::class, 'store']);
$router->post('/panel/banners/{id}/borrar', [BannerController::class, 'destroy']);

$router->get('/panel/avisos',          [NoticeController::class, 'index']);
$router->post('/panel/avisos',         [NoticeController::class, 'store']);
$router->post('/panel/avisos/{id}/borrar', [NoticeController::class, 'destroy']);

$router->get('/panel/productos',       [ProductController::class, 'index']);
$router->get('/panel/productos/nuevo', [ProductController::class, 'create']);
$router->post('/panel/productos',      [ProductController::class, 'store']);
$router->get('/panel/productos/{id}/editar', [ProductController::class, 'edit']);
$router->post('/panel/productos/{id}', [ProductController::class, 'update']);
$router->post('/panel/productos/{id}/borrar', [ProductController::class, 'destroy']);

// Pedidos de la tienda. Ojo con el orden: 'nuevo' y 'buscar' son rutas fijas y
// tienen que ir ANTES de '/panel/pedidos/{id}'.
$router->get('/panel/pedidos',             [OrderController::class, 'index']);
$router->get('/panel/pedidos/nuevo',       [OrderController::class, 'create']);
$router->get('/panel/pedidos/buscar',      [OrderController::class, 'search']);
$router->post('/panel/pedidos',            [OrderController::class, 'store']);
$router->get('/panel/pedidos/{id}',        [OrderController::class, 'show']);
$router->post('/panel/pedidos/{id}/lineas', [OrderController::class, 'addLines']);
$router->post('/panel/pedidos/{id}/lineas/{line}/borrar', [OrderController::class, 'destroyLine']);
$router->post('/panel/pedidos/{id}/estado', [OrderController::class, 'status']);
// Cobro del pedido: pendiente/pagado (lo confirma el tendero al recibirlo).
$router->post('/panel/pedidos/{id}/cobro', [OrderController::class, 'payment']);
// Envio de las lineas elegidas al mayorista (tablas pedidos/pedidos_det).
$router->post('/panel/pedidos/{id}/idirecto', [OrderController::class, 'send']);
$router->post('/panel/pedidos/{id}/borrar', [OrderController::class, 'destroy']);
$router->post('/panel/pedidos/{id}/restaurar', [OrderController::class, 'restore']);

// Clientes de la tienda (quien compra en su web).
$router->get('/panel/clientes',            [CustomerAdminController::class, 'index']);
$router->get('/panel/clientes/{id}',       [CustomerAdminController::class, 'show']);

$router->get('/panel/dominios',        [DomainController::class, 'index']);
$router->post('/panel/dominios',       [DomainController::class, 'store']);
$router->post('/panel/dominios/{id}/verificar', [DomainController::class, 'verify']);
$router->post('/panel/dominios/{id}/borrar', [DomainController::class, 'destroy']);

$router->get('/panel/ajustes',         [SettingsController::class, 'index']);
$router->post('/panel/ajustes',        [SettingsController::class, 'save']);

// Subida de imagenes (usada por banners, productos, logo...)
$router->post('/panel/media/subir',    [MediaController::class, 'upload']);
$router->post('/panel/media/{id}/borrar', [MediaController::class, 'destroy']);

// -----------------------------------------------------------------------------
// Resolucion de tenant + despacho
// -----------------------------------------------------------------------------
try {
    \Tienda\Core\View::setBasePath($router->basePath());
    $tenant = TenantResolver::resolve();
    $router->dispatch($tenant);
} catch (\Throwable $e) {
    http_response_code(500);
    if (config('app.debug', false)) {
        echo '<h1>Error</h1><pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<h1>Error interno</h1>';
        // Pista util en el log: el sintoma tipico de "Error interno" sin mas
        // datos es que el usuario del servidor web no pueda leer .env (entonces
        // ni hay credenciales ni se aplica APP_DEBUG).
        $envFile = TIENDA_BASE . '/.env';
        $pista = (is_file($envFile) && !is_readable($envFile))
            ? ' [.env NO es legible por el usuario del servidor web: revisa permisos, '
              . 'grupo www-data o ACL; ver deploy/setup-local-domain.sh]'
            : '';
        error_log('[tienda] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . $pista);
    }
}
