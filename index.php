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
use Tienda\Controllers\Admin\LogController;
use Tienda\Controllers\Admin\MediaController;
use Tienda\Controllers\Admin\MenuController;
use Tienda\Controllers\Admin\PlatformMenuController;
use Tienda\Controllers\Admin\NoticeController;
use Tienda\Controllers\Admin\OrderController;
use Tienda\Controllers\Admin\ProductController;
use Tienda\Controllers\Admin\SettingsController;
use Tienda\Controllers\CartController;
use Tienda\Controllers\CheckoutController;
use Tienda\Controllers\CustomerController;
use Tienda\Controllers\RegistrationController;
use Tienda\Controllers\StorefrontController;
use Tienda\Core\Logger;
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
// NAVEGACION COMERCIAL (accesos rapidos del menu)
//
//   /marcas           directorio de marcas con stock
//   /marca/{id}       listado de una marca
//   /ofertas /novedades /destacados   listados por etiqueta
//   /propios          productos propios de la tienda (destino de menu)
//   /menu/panel/{id}  contenido del panel de una categoria (JSON)
// -----------------------------------------------------------------------------
$router->get('/marcas',                [StorefrontController::class, 'brands']);
$router->get('/marca/{id}',            [StorefrontController::class, 'brand']);
$router->get('/propios',               [StorefrontController::class, 'ownProducts']);
foreach (['ofertas', 'novedades', 'destacados'] as $tagRoute) {
    $router->get('/' . $tagRoute, [StorefrontController::class, 'tag']);
}
$router->get('/menu/panel/{id}',       [StorefrontController::class, 'menuPanel']);

// Buscador en vivo (JSON): resultados del catalogo visible de la tienda y de
// sus productos propios. La ruta fija va antes del catch-all SEO.
$router->get('/buscar/live',           [StorefrontController::class, 'searchLive']);

// -----------------------------------------------------------------------------
// COMPRA DEL CLIENTE (carrito, su cuenta y cierre del pedido)
// -----------------------------------------------------------------------------
$router->get('/carrito',                [CartController::class, 'index']);
// Estado del carrito para el mini-carrito lateral (JSON, solo lectura).
$router->get('/carrito/mini',           [CartController::class, 'mini']);
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

// Menu de navegacion: estilo (Compacto/Catalogo) y arbol de la tienda.
// Ojo con el orden: 'regenerar' es una ruta fija.
$router->get('/panel/menu',            [MenuController::class, 'index']);
$router->post('/panel/menu',           [MenuController::class, 'save']);
$router->post('/panel/menu/regenerar', [MenuController::class, 'seed']);
// Editor del arbol (borrador), vista previa y publicacion (con invalidacion de cache).
$router->get('/panel/menu/previa',             [MenuController::class, 'preview']);
$router->get('/panel/menu/previa/marco',       [MenuController::class, 'previewFrame']);
$router->post('/panel/menu/publicar',          [MenuController::class, 'publish']);
$router->post('/panel/menu/orden',             [MenuController::class, 'nodeOrder']);
$router->post('/panel/menu/nodo',              [MenuController::class, 'nodeCreate']);
$router->post('/panel/menu/nodo/{id}',         [MenuController::class, 'nodeUpdate']);
$router->post('/panel/menu/nodo/{id}/borrar',  [MenuController::class, 'nodeDelete']);
$router->post('/panel/menu/nodo/{id}/activar', [MenuController::class, 'nodeToggle']);
// Modo del menu (completo|elegido) y anulaciones por tienda (mostrar/ocultar y renombrar).
$router->post('/panel/menu/alcance',                [MenuController::class, 'saveScope']);
$router->post('/panel/menu/nodo/{id}/anular',       [MenuController::class, 'nodeOverride']);
$router->post('/panel/menu/nodo/{id}/anular/quitar', [MenuController::class, 'nodeOverrideClear']);

// Menu de la PLATAFORMA: arbol compartido, visibilidad por tienda y por nivel.
$router->get('/panel/plataforma/menu',           [PlatformMenuController::class, 'index']);
$router->post('/panel/plataforma/menu/publicar', [PlatformMenuController::class, 'publishAll']);
$router->post('/panel/plataforma/menu/orden',    [PlatformMenuController::class, 'nodeOrder']);
$router->post('/panel/plataforma/menu/nodo',     [PlatformMenuController::class, 'nodeCreate']);
$router->post('/panel/plataforma/menu/nodo/{id}',              [PlatformMenuController::class, 'nodeUpdate']);
$router->post('/panel/plataforma/menu/nodo/{id}/borrar',       [PlatformMenuController::class, 'nodeDelete']);
$router->post('/panel/plataforma/menu/nodo/{id}/activar',      [PlatformMenuController::class, 'nodeToggle']);
$router->post('/panel/plataforma/menu/nodo/{id}/visibilidad',  [PlatformMenuController::class, 'visibility']);
$router->get('/panel/plataforma/menu/{store}/previa',          [PlatformMenuController::class, 'preview']);
$router->get('/panel/plataforma/menu/{store}/previa/marco',    [PlatformMenuController::class, 'previewFrame']);
$router->post('/panel/plataforma/menu/{store}/publicar',       [PlatformMenuController::class, 'publishStore']);
$router->post('/panel/plataforma/menu/{store}/compartir',      [PlatformMenuController::class, 'shareStore']);

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

// Logs: actividad por dia y por tienda. Cada tienda ve solo la suya; el rol
// `platform` puede ver todas. No hay tabla: se leen los ficheros de storage/logs.
$router->get('/panel/logs',            [LogController::class, 'index']);
$router->post('/panel/logs/limpiar',   [LogController::class, 'purge']);

// Subida de imagenes (usada por banners, productos, logo...)
$router->post('/panel/media/subir',    [MediaController::class, 'upload']);
$router->post('/panel/media/{id}/borrar', [MediaController::class, 'destroy']);

// -----------------------------------------------------------------------------
// Rutas SEO del catalogo (esquema de PuntoByZE). SIEMPRE la ultima de los GET:
//   /{categoria}
//   /{categoria}/{subcategoria}
//   /{categoria}/{subcategoria}/m/{marca}/orden/{orden}/page/{n}
// Cualquier otra ruta de un bloque cae aqui y responde 404 si el primer
// segmento no es una categoria real. Si se declara antes, se traga el resto de
// rutas GET (panel incluido): no moverla de sitio.
// -----------------------------------------------------------------------------
$router->get('/{ruta...}',             [StorefrontController::class, 'seoListado']);

// -----------------------------------------------------------------------------
// Resolucion de tenant + despacho
// -----------------------------------------------------------------------------
try {
    \Tienda\Core\View::setBasePath($router->basePath());
    $tenant = TenantResolver::resolve();
    // Contexto del log: a partir de aqui, todo lo que se registre sabe de que
    // tienda es (el panel puede afinarlo con la tienda del usuario).
    Logger::setStore($tenant->id(), $tenant->slug());
    $router->dispatch($tenant);
} catch (\Throwable $e) {
    http_response_code(500);

    // Pista util en el log: el sintoma tipico de "Error interno" sin mas datos
    // es que el usuario del servidor web no pueda leer .env (entonces ni hay
    // credenciales ni se aplica APP_DEBUG). Se registra siempre, en depuracion y
    // en produccion, para poder diagnosticar desde /panel/logs o por SSH.
    $envFile = TIENDA_BASE . '/.env';
    $pista = (is_file($envFile) && !is_readable($envFile))
        ? ' (.env NO es legible por el usuario del servidor web: revisa permisos, '
          . 'grupo www-data o ACL; ver deploy/setup-local-domain.sh)'
        : '';
    Logger::critical('sistema', 'Error no controlado: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file'      => $e->getFile() . ':' . $e->getLine(),
        'ruta'      => (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'),
        'pista'     => $pista,
    ]);

    if (config('app.debug', false)) {
        echo '<h1>Error</h1><pre>' . htmlspecialchars((string) $e . $pista, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo '<h1>Error interno</h1>';
    }
}
