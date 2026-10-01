<?php

declare(strict_types=1);

namespace Tienda\Core\Storage;

use Tienda\Core\Config;
use Tienda\Core\StorageException;

/**
 * Selecciona el driver de almacenamiento segun configuracion (.env).
 * Si se pide S3 pero el SDK no esta disponible, avisa claramente.
 */
final class StorageManager
{
    private static ?StorageInterface $instance = null;

    public static function driver(): StorageInterface
    {
        if (self::$instance instanceof StorageInterface) {
            return self::$instance;
        }

        $driver = strtolower((string) Config::get('storage.driver', 'local'));

        self::$instance = match ($driver) {
            's3'    => new S3Storage(),
            'local' => new LocalStorage(),
            default => throw new StorageException("Driver de almacenamiento no soportado: {$driver}"),
        };

        return self::$instance;
    }
}
