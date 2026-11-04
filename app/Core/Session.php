<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Sesion con cookies endurecidas.
 */
final class Session
{
    private const NAME = 'tienda_session';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Esquema real de la peticion: vale igual en Apache, en nginx y detras
        // de un proxy/CDN que termina el TLS (lo resuelve `Server`).
        $secure = Server::isSecure() || (($_SERVER['SERVER_PORT'] ?? null) === '443');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name(self::NAME);
        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $flash = $_SESSION['_flash'] ?? [];
        if (!is_array($flash)) {
            $flash = [];
        }
        $flash[$key] = $value;
        $_SESSION['_flash'] = $flash;
    }

    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $flash = $_SESSION['_flash'] ?? [];
        if (!is_array($flash)) {
            unset($_SESSION['_flash']);
            return $default;
        }

        $value = array_key_exists($key, $flash) ? $flash[$key] : $default;
        unset($flash[$key]);
        $_SESSION['_flash'] = $flash;

        // En una sesion antigua puede haber objetos/arrays donde esperamos texto.
        return is_scalar($value) || $value === null ? $value : $default;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(self::NAME, '', time() - 42000, $p['path'], $p['domain'], (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }
}
