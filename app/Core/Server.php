<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Deteccion del servidor web (Apache, nginx, servidor embebido o CLI) y de la
 * infraestructura que hay delante (proxy/CDN con TLS).
 *
 * La aplicacion funciona igual en Apache (con `.htaccess`) y en nginx (con su
 * `server` block): el enrutado al front controller y el bloqueo de rutas
 * internas son responsabilidad del servidor. Lo unico que cambia de uno a otro
 * es de donde salen el esquema, el host y la ruta publica de `/public`, y eso
 * se centraliza aqui para no repartir `$_SERVER[...]` por todo el codigo.
 *
 * Notas:
 *  - En Apache el esquema llega en `HTTPS`.
 *  - Con nginx + PHP-FPM llega en `HTTPS` y/o `REQUEST_SCHEME` (nginx los pasa
 *    en `fastcgi_params`).
 *  - Si hay un proxy/CDN terminando el TLS, el esquema real es el de
 *    `X-Forwarded-Proto`.
 */
final class Server
{
    public const NGINX = 'nginx';
    public const APACHE = 'apache';
    public const CLI_SERVER = 'cli-server';
    public const CLI = 'cli';
    public const UNKNOWN = 'unknown';

    /** Servidor web que atiende la peticion. */
    public static function software(): string
    {
        $software = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));

        if (str_contains($software, 'nginx')) {
            return self::NGINX;
        }
        if (str_contains($software, 'apache')) {
            return self::APACHE;
        }

        return match (PHP_SAPI) {
            'cli-server' => self::CLI_SERVER,
            'cli'        => self::CLI,
            default      => self::UNKNOWN,
        };
    }

    public static function is(string $name): bool
    {
        return self::software() === strtolower($name);
    }

    public static function isNginx(): bool
    {
        return self::is(self::NGINX);
    }

    public static function isApache(): bool
    {
        return self::is(self::APACHE);
    }

    public static function isCliServer(): bool
    {
        return self::is(self::CLI_SERVER);
    }

    /** Nombre legible del servidor, para el panel y los mensajes de diagnostico. */
    public static function label(): string
    {
        return match (self::software()) {
            self::NGINX      => 'nginx + PHP-FPM',
            self::APACHE     => 'Apache',
            self::CLI_SERVER => 'servidor embebido de PHP',
            self::CLI        => 'linea de comandos',
            default          => 'servidor desconocido',
        };
    }

    /**
     * true si la peticion llego por HTTPS, ya sea directa o a traves de un
     * proxy/CDN que termina el TLS delante del servidor web.
     */
    public static function isSecure(): bool
    {
        // Directo (Apache, nginx).
        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && $https !== '0' && strtolower((string) $https) !== 'off') {
            return true;
        }

        // nginx tambien pasa REQUEST_SCHEME ($scheme).
        $scheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }

        // Detras de proxy/CDN.
        if (strtolower(self::firstForwarded((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))) === 'https') {
            return true;
        }
        $ssl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($ssl === 'on' || $ssl === '1') {
            return true;
        }

        return false;
    }

    /** Esquema real de la peticion: https si hay TLS (directo o en el proxy). */
    public static function scheme(): string
    {
        return self::isSecure() ? 'https' : 'http';
    }

    /** Host de la peticion, respetando `X-Forwarded-Host` si viene de un proxy. */
    public static function host(): string
    {
        $forwarded = self::firstForwarded((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
        $host = $forwarded !== '' ? $forwarded : (string) ($_SERVER['HTTP_HOST'] ?? '');

        // Nunca devolver saltos de linea ni espacios (inyeccion en cabeceras).
        return trim((string) preg_replace('/[^A-Za-z0-9\.\-:\[\]]/', '', $host));
    }

    /** Raiz de documentos del servidor, sin barra final. Vacio si no consta. */
    public static function documentRoot(): string
    {
        $root = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($root === '') {
            return '';
        }
        $real = realpath($root);

        return $real === false ? '' : str_replace('\\', '/', $real);
    }

    /**
     * Prefijo de URL con el que se sirve el directorio `public/`.
     *
     * Con la raiz del proyecto como DocumentRoot (lo normal en este proyecto,
     * tanto en Apache como en nginx) devuelve `<basePath>/public`. Si algun dia
     * se apunta la raiz directamente a `public/`, devuelve solo `<basePath>`.
     */
    public static function publicPath(): string
    {
        $base = View::basePath();
        $docRoot = self::documentRoot();
        $public = realpath(TIENDA_BASE . '/public');
        $public = $public === false ? '' : str_replace('\\', '/', $public);

        if ($docRoot !== '' && $public !== '' && $docRoot === $public) {
            return $base;
        }

        return $base . '/public';
    }

    private static function firstForwarded(string $value): string
    {
        return trim(explode(',', $value)[0]);
    }
}
