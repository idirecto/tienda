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
        $token = $_SESSION[self::KEY] ?? null;
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::KEY] = $token;
        }

        return $token;
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
