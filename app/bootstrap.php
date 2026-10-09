<?php

declare(strict_types=1);

/**
 * Arranque de la aplicacion: autoload, entorno, rutas y sesion.
 * No incluye logica de negocio: solo deja el entorno listo.
 */

if (!defined('TIENDA_BASE')) {
    define('TIENDA_BASE', dirname(__DIR__));
}

// -----------------------------------------------------------------------------
// Autoload PSR-4: Tienda\  ->  app/
// -----------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix = 'Tienda\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = TIENDA_BASE . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// SDK de AWS opcional (para STORAGE_DRIVER=s3)
(static function (): void {
    $candidates = array_filter([
        getenv('AWS_SDK_AUTOLOAD') ?: null,
        TIENDA_BASE . '/vendor/autoload.php',
    ]);
    foreach ($candidates as $autoload) {
        if ($autoload && is_file($autoload)) {
            require_once $autoload;
            return;
        }
    }
})();

// -----------------------------------------------------------------------------
// Entorno (.env) y configuracion
// -----------------------------------------------------------------------------
\Tienda\Core\Env::load(TIENDA_BASE . '/.env');
\Tienda\Core\Config::init(TIENDA_BASE . '/config');

date_default_timezone_set((string) config('app.timezone', 'Europe/Madrid'));
setlocale(LC_ALL, 'es_ES.UTF-8', 'es_ES', 'esp');

$debug = config('app.debug', false);
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

// Log propio solo si el usuario del servidor puede escribir en storage/logs.
// Si no (permisos mal puestos, .env ilegible...), se deja el log por defecto de
// PHP para que el error acabe en el log del servidor en vez de perderse.
$logDir = TIENDA_BASE . '/storage/logs';
$logFile = $logDir . '/php-error.log';
if (is_dir($logDir) && (is_writable($logFile) || is_writable($logDir))) {
    ini_set('error_log', $logFile);
}

// -----------------------------------------------------------------------------
// Sesion (cookies endurecidas). No se inicia en CLI (migraciones, scripts).
// -----------------------------------------------------------------------------
if (PHP_SAPI !== 'cli') {
    \Tienda\Core\Session::start();
}

/**
 * Acceso rapido a configuracion con notacion de punto: config('db.host').
 */
function config(string $key, mixed $default = null): mixed
{
    return \Tienda\Core\Config::get($key, $default);
}

/** Escapa texto para HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formatea un importe en euros. */
function euros(mixed $value): string
{
    return number_format((float) $value, 2, ',', '.') . ' €';
}

/**
 * URL publica de un asset (respetando el subdirectorio base).
 * Se anyade la fecha del fichero como version para que el navegador no
 * muestre una copia antigua del CSS o del JS tras un cambio.
 *
 * El prefijo lo calcula `Server`: vale igual si la raiz del sitio es la del
 * proyecto (Apache y nginx, -> /public/assets/...) o si es public/ (-> /assets/...).
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $url = \Tienda\Core\Server::publicPath() . '/' . $path;

    $file = TIENDA_BASE . '/public/' . $path;
    if (is_file($file)) {
        $url .= '?v=' . filemtime($file);
    }

    return $url;
}

/**
 * URL canonica (SEO) de un producto:  /producto/{slug}/{id}
 * Igual que idirecto: el nombre del producto va en la URL.
 *
 * @param array $product Fila de producto (debe incluir `id`; `slug` es opcional)
 */
function product_url(array $product): string
{
    $id = (int) ($product['id'] ?? 0);
    $slug = (string) ($product['slug'] ?? '');

    if ($slug === '' && !empty($product['nombre'])) {
        $slug = \Tienda\Core\Str::slugify((string) $product['nombre']);
    }
    if ($slug === '') {
        $slug = 'producto';
    }

    return \Tienda\Core\View::basePath() . '/producto/' . $slug . '/' . $id;
}

/**
 * URL de un destino del menu.
 *
 * Un destino del menu puede ser una ruta propia (`/propios`, `/pagina/x`) o un
 * enlace libre que ya venga con esquema (`https://...`). Solo se antepone la
 * base de la tienda cuando la ruta es relativa.
 */
function menu_href(string $base, string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return $base . '/';
    }
    if (preg_match('#^(https?:)?//#i', $path) === 1) {
        return $path;
    }

    return $base . '/' . ltrim($path, '/');
}

/**
 * Convierte una ruta relativa en URL absoluta (para <link rel="canonical">).
 * Usa el esquema y el host reales de la peticion, tambien detras de un
 * proxy/CDN (X-Forwarded-Proto / X-Forwarded-Host).
 */
function absolute_url(string $path): string
{
    return \Tienda\Core\Server::scheme() . '://' . \Tienda\Core\Server::host() . $path;
}

/**
 * Icono SVG en linea del storefront.
 *
 * Los iconos van en linea (y no en un sprite o una fuente) porque son pocos,
 * heredan el color del texto (`currentColor`) y no anaden peticiones: es la
 * opcion mas ligera para una plantilla sin build.
 *
 * @param string $name  Nombre del icono
 * @param string $class Clase CSS opcional
 * @param int    $size  Tamano en px (0 = hereda del CSS)
 */
function icon_svg(string $name, string $class = '', int $size = 0): string
{
    static $icons = [
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/>',
        'phone'     => '<path d="M5 4h3l2 5-2 1a11 11 0 0 0 6 6l1-2 5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2Z"/>',
        'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'moon'      => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5Z"/>',
        'gpu'       => '<rect x="3" y="6" width="15" height="12" rx="2"/><path d="M18 10h2v4h-2M7 18v3M13 18v3"/><circle cx="10" cy="12" r="3"/>',
        'laptop'    => '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 19h20"/>',
        'chip'      => '<rect x="7" y="7" width="10" height="10" rx="2"/><path d="M10 4v3M14 4v3M10 17v3M14 17v3M4 10h3M4 14h3M17 10h3M17 14h3"/>',
        'keyboard'  => '<rect x="2" y="7" width="20" height="10" rx="2"/><path d="M6 11h.01M10 11h.01M14 11h.01M18 11h.01M8 14h8"/>',
        'gamepad'   => '<path d="M7 8h10a5 5 0 0 1 5 5v1a3 3 0 0 1-5.2 2L15 15H9l-1.8 1A3 3 0 0 1 2 14v-1a5 5 0 0 1 5-5Z"/><path d="M7 11v2M6 12h2M16 11h.01M18 13h.01"/>',
        'truck'     => '<path d="M3 6h11v9H3zM14 9h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
        'shield'    => '<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6l7-3Z"/><path d="M9 12l2 2 4-4"/>',
        'refresh'   => '<path d="M20 11a8 8 0 0 0-14-4L3 10M4 13a8 8 0 0 0 14 4l3-3"/><path d="M3 6v4h4M21 18v-4h-4"/>',
        'headset'   => '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="2" y="13" width="4" height="6" rx="1.5"/><rect x="18" y="13" width="4" height="6" rx="1.5"/><path d="M20 19a3 3 0 0 1-3 3h-3"/>',
        'filter'    => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'close'     => '<path d="M6 6l12 12M18 6L6 18"/>',
        'check'     => '<path d="M4 12.5l5 5L20 6.5"/>',
        'chevron-l' => '<path d="M14.5 5l-7 7 7 7"/>',
        'chevron-r' => '<path d="M9.5 5l7 7-7 7"/>',
        'chevron-d' => '<path d="M5 9.5l7 7 7-7"/>',
        'arrow-r'   => '<path d="M4 12h15M13 6l6 6-6 6"/>',
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'list'      => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'star'      => '<path d="M12 4l2.4 5 5.6.8-4 3.9 1 5.5-5-2.9-5 2.9 1-5.5-4-3.9 5.6-.8L12 4Z"/>',
        'bolt'      => '<path d="M13 2L4 14h6l-1 8 9-12h-6l1-8Z"/>',
        'cpu'       => '<rect x="7" y="7" width="10" height="10" rx="2"/><path d="M4 10h3M4 14h3M17 10h3M17 14h3M10 4v3M14 4v3M10 17v3M14 17v3"/>',
        'wrench'    => '<path d="M15 3a5 5 0 0 0-4.5 7.1L4 16.6 7.4 20l6.5-6.5A5 5 0 1 0 15 3Z"/>',
        'cart'      => '<circle cx="9" cy="20" r="1.6"/><circle cx="18" cy="20" r="1.6"/><path d="M2 3h3l2.6 11.6A2 2 0 0 0 9.6 16h8.8a2 2 0 0 0 2-1.6L22 7H6"/>',
        'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
        'package'   => '<path d="M12 3l8 4.4v9.2L12 21l-8-4.4V7.4L12 3Z"/><path d="M4 7.4l8 4.4 8-4.4M12 11.8V21"/>',
        'pin'       => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
    ];

    $path = $icons[$name] ?? $icons['grid'];
    $attrs = 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    if ($class !== '') {
        $attrs .= ' class="' . e($class) . '"';
    }
    if ($size > 0) {
        $attrs .= ' width="' . $size . '" height="' . $size . '"';
    }

    return '<svg ' . $attrs . '>' . $path . '</svg>';
}

// -----------------------------------------------------------------------------
// Registro de actividad de la aplicacion (storage/logs/<canal>/<dia>/<tienda>.log)
//
// Aqui solo se engancha la captura de errores FATALES de PHP: los que no se
// pueden convertir en excepcion (memoria agotada, error de compilacion...) y que,
// si no, se perderian. Las excepciones normales las registra el front controller
// (`index.php`), y los eventos de negocio (compras, accesos, pedidos...) los
// anotan los controladores con `Logger::info()` y compania.
// -----------------------------------------------------------------------------
\Tienda\Core\Logger::registerHandlers();
