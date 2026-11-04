<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Configuracion de la aplicacion cargada desde config/*.php.
 * Acceso con notacion de punto: Config::get('db.host').
 */
final class Config
{
    private static array $items = [];
    private static bool $loaded = false;

    public static function init(string $dir): void
    {
        if (self::$loaded) {
            return;
        }
        foreach (glob(rtrim($dir, '/') . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            $value = require $file;
            if (is_array($value)) {
                self::$items[$key] = $value;
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $value = self::$items;
        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /**
     * Cambia un valor en memoria.
     *
     * No se usa para configuración normal (que vive en config/*.php y .env),
     * sino para las comprobaciones automatizadas que necesitan simular ambos
     * estados del modo de pruebas dentro de la misma ejecución.
     */
    public static function set(string $key, mixed $value): void
    {
        $parts = explode('.', $key);
        $target = &self::$items;
        foreach ($parts as $part) {
            if (!isset($target[$part]) || !is_array($target[$part])) {
                $target[$part] = [];
            }
            $target = &$target[$part];
        }
        $target = $value;
    }

    public static function all(): array
    {
        return self::$items;
    }
}
