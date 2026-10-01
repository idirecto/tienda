<?php

declare(strict_types=1);

namespace Tienda\Core\Storage;

use Tienda\Core\Config;
use Tienda\Core\ValidationException;
use Tienda\Core\StorageException;

/**
 * Almacenamiento local (desarrollo): public/uploads.
 * No debe usarse en produccion; sirve para trabajar sin S3 ni SDK de AWS.
 *
 * Usa las MISMAS claves que S3 (`tenants/tienda_banners/7_banners_....webp`),
 * asi cambiar de driver no invalida lo ya guardado en `mt_media`.
 */
final class LocalStorage implements StorageInterface
{
    public function driver(): string
    {
        return 'local';
    }

    public function put(string $localPath, string $mime, int $storeId, string $folder): array
    {
        if (!is_file($localPath)) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }

        $key = StorageKey::build($storeId, $folder, StorageKey::extensionParaMime($mime));
        $absolute = $this->absolutePath($key);

        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new StorageException('No se pudo crear el directorio de destino.');
        }

        if (!@copy($localPath, $absolute)) {
            throw new StorageException('No se pudo guardar el fichero subido.');
        }
        @chmod($absolute, 0644);

        $dim = @getimagesize($absolute);

        return [
            'key'    => $key,
            'url'    => rtrim((string) Config::get('storage.local.url', '/uploads'), '/') . '/' . $key,
            'mime'   => $mime,
            'bytes'  => (int) (@filesize($absolute) ?: 0),
            'width'  => $dim ? (int) $dim[0] : null,
            'height' => $dim ? (int) $dim[1] : null,
        ];
    }

    public function delete(string $key): bool
    {
        $key = ltrim($key, '/');
        if ($key === '' || str_contains($key, '..')) {
            return false;
        }

        return is_file($this->absolutePath($key)) ? @unlink($this->absolutePath($key)) : false;
    }

    /** Ruta absoluta de una clave, sin dejar que se escape de la carpeta base. */
    public function absolutePath(string $key): string
    {
        $base = rtrim((string) Config::get('storage.local.path'), '/');

        return $base . '/' . ltrim($key, '/');
    }
}
