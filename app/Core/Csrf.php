<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Proteccion CSRF basada en token de sesion.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION[self::KEY];
    }

    /** Campo oculto listo para incrustar en formularios. */
    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    public static function validate(?string $token): bool
    {
        $stored = $_SESSION[self::KEY] ?? null;
        return is_string($token) && is_string($stored) && hash_equals($stored, $token);
    }
}
