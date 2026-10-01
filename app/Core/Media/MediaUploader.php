<?php

declare(strict_types=1);

namespace Tienda\Core\Media;

use Tienda\Core\Config;
use Tienda\Core\Storage\StorageManager;
use Tienda\Core\ValidationException;

/**
 * Punto unico de entrada para guardar una imagen de una tienda:
 *
 *   1. Valida la subida (fichero recibido, tamano maximo).
 *   2. La optimiza con {@see ImageOptimizer} (WebP, calidad, tamano, EXIF...).
 *   3. La guarda con el driver configurado (S3 o local) usando la clave de
 *      {@see \Tienda\Core\Storage\StorageKey}:
 *      `tenants/tienda_banners/7_banners_20261001-174530-9f3c1a2b.webp`.
 *
 * Devuelve los metadatos que se guardan en `mt_media`, mas `optimized`,
 * `original_mime` y `original_bytes` para poder informar del ahorro.
 */
final class MediaUploader
{
    /**
     * Subida HTTP normal (entrada de $_FILES).
     *
     * @return array{key:string,url:string,mime:string,bytes:int,width:?int,height:?int,
     *               optimized:bool,original_mime:string,original_bytes:int}
     */
    public static function upload(array $file, int $storeId, string $folder): array
    {
        self::assertValidUpload($file);

        return self::storePath(
            (string) $file['tmp_name'],
            (string) ($file['name'] ?? ''),
            $storeId,
            $folder
        );
    }

    /**
     * Fichero que ya esta en disco: importaciones, tareas de mantenimiento y
     * los tests. Todo pasa por el mismo camino (validacion + optimizacion).
     */
    public static function storePath(string $path, string $originalName, int $storeId, string $folder): array
    {
        if (!is_file($path)) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }

        $bytes = (int) (@filesize($path) ?: 0);
        $maxBytes = self::maxBytes();
        if ($maxBytes > 0 && $bytes > $maxBytes) {
            throw new ValidationException(
                'El fichero supera el tamano maximo permitido (' . self::formatoBytes($maxBytes) . ').'
            );
        }

        $preparado = ImageOptimizer::optimize([
            'tmp_name' => $path,
            'name'     => $originalName,
            'size'     => $bytes,
        ]);

        try {
            $guardado = StorageManager::driver()->put(
                (string) $preparado['path'],
                (string) $preparado['mime'],
                $storeId,
                $folder
            );
        } finally {
            if (!empty($preparado['temporary'])) {
                @unlink((string) $preparado['path']);
            }
        }

        return array_merge($guardado, [
            'optimized'      => (bool) ($preparado['optimized'] ?? false),
            'original_mime'  => (string) ($preparado['original_mime'] ?? ''),
            'original_bytes' => (int) ($preparado['original_bytes'] ?? 0),
        ]);
    }

    /** Limite de subida en bytes (0 = sin limite). */
    public static function maxBytes(): int
    {
        return (int) Config::get('storage.max_bytes', 5 * 1024 * 1024);
    }

    /** Tamano legible para los mensajes de error. */
    public static function formatoBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' bytes';
    }

    private static function assertValidUpload(array $file): void
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error en la subida (codigo ' . $file['error'] . ').');
        }
    }
}
