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
ini_set('error_log', TIENDA_BASE . '/storage/logs/php-error.log');

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
 * Convierte una ruta relativa en URL absoluta (para <link rel="canonical">).
 * Usa el esquema y el host reales de la peticion, tambien detras de un
 * proxy/CDN (X-Forwarded-Proto / X-Forwarded-Host).
 */
function absolute_url(string $path): string
{
    return \Tienda\Core\Server::scheme() . '://' . \Tienda\Core\Server::host() . $path;
}
