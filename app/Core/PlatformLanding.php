<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Portada publica de la plataforma.
 *
 * Se sirve en el dominio principal (apex/www) y en hosts sin tienda resuelta.
 * Es una pagina estatica: no lee cookies, sesiones, tiendas, banners, menus ni
 * ningun dato de la base de datos. La unica identidad visible es IDIRECTO.
 */
final class PlatformLanding
{
    /** Host del dominio principal o su alias www. */
    public static function isApexHost(string $host): bool
    {
        $host = TenantResolver::normalizeHost($host);
        if ($host === '') {
            return false;
        }

        foreach (TenantResolver::getBaseDomains() as $base) {
            $base = TenantResolver::normalizeHost($base);
            if ($base !== '' && ($host === $base || $host === 'www.' . $base)) {
                return true;
            }
        }

        return false;
    }

    /** Ruta que muestra la portada de plataforma. */
    public static function isLandingPath(string $path): bool
    {
        $path = self::normalizePath($path);

        return $path === '/' || $path === '/index.php';
    }

    /**
     * ¿La peticion actual es la portada del dominio principal?
     *
     * Se usa antes de iniciar sesion para no crear ninguna cookie en la
     * portada estatica. En hosts desconocidos no se fuerza esta deteccion:
     * resolverlos puede requerir comprobar un dominio propio.
     */
    public static function isLandingRequest(): bool
    {
        if (!self::isLandingPath((string) ($_SERVER['REQUEST_URI'] ?? '/'))) {
            return false;
        }

        // En desarrollo, ?tienda=slug sigue abriendo una tienda concreta y
        // necesita sesión (carrito/cuenta).
        if ((bool) Config::get('tenant.allow_query_override', false)
            && isset($_GET['tienda'])
            && trim((string) $_GET['tienda']) !== '') {
            return false;
        }

        $host = TenantResolver::normalizeHost($_SERVER['HTTP_HOST'] ?? '');

        return $host === '' || self::isApexHost($host);
    }

    /**
     * Rutas que siguen funcionando en el dominio principal con un contexto de
     * plataforma (sin tienda): registro, acceso al panel y favicon.
     */
    public static function isPlatformRoute(string $path): bool
    {
        $path = self::normalizePath($path);
        if (self::isLandingPath($path)) {
            return true;
        }

        foreach ([
            '/registro',
            '/panel/login',
            '/panel/logout',
            '/favicon.ico',
            '/favicon.svg',
        ] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    /** HTML de la portada. Sin acceso a base de datos. */
    public static function html(): string
    {
        return View::render('platform/home', [
            'pageTitle'       => 'IDIRECTO | Plataforma web para sus tiendas',
            'pageDescription' => 'La plataforma web que IDIRECTO pone a disposición de sus tiendas para vender online: catálogo, pedidos, clientes y gestión en un solo panel.',
        ], 'platform');
    }

    /** HTML de la portada con las cabeceras HTTP. */
    public static function render(): string
    {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: public, max-age=300');
        header('Vary: Host');
        header('X-Content-Type-Options: nosniff');

        return self::html();
    }

    private static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }
}
