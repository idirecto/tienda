<?php

declare(strict_types=1);

namespace Tienda\Core\Media;

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
        $maxBytes = MediaRules::maxBytes($folder);
        if ($maxBytes > 0 && $bytes > $maxBytes) {
            throw new ValidationException(
                'La imagen pesa ' . MediaRules::formatoBytes($bytes) . ' y el maximo para "'
                . $folder . '" es ' . MediaRules::formatoBytes($maxBytes) . '.'
            );
        }

        $preparado = ImageOptimizer::optimize([
            'tmp_name' => $path,
            'name'     => $originalName,
            'size'     => $bytes,
        ], $folder);

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

    /**
     * true cuando la peticion no ha llegado entera porque supera
     * `post_max_size`: en ese caso PHP vacia $_FILES y $_POST sin avisar, y sin
     * esto el usuario solo veria "no se ha recibido ningun fichero".
     */
    public static function excedePostMaxSize(): bool
    {
        $enviado = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limite = self::bytesDeIni((string) ini_get('post_max_size'));

        return $enviado > 0 && $limite > 0 && $enviado > $limite;
    }

    /** Limite de subida que impone PHP (`upload_max_filesize`), en bytes. */
    public static function limitePhp(): int
    {
        return self::bytesDeIni((string) ini_get('upload_max_filesize'));
    }

    /** Mensaje claro cuando el fichero no ha llegado por los limites de PHP. */
    public static function mensajeLimitePhp(): string
    {
        return 'La imagen supera el limite de subida del servidor ('
            . ini_get('upload_max_filesize') . '). Pide que suban upload_max_filesize/post_max_size '
            . 'en la configuracion de PHP o sube una imagen mas ligera.';
    }

    /** Convierte "12M", "1G", "512K" a bytes. */
    private static function bytesDeIni(string $valor): int
    {
        $valor = trim($valor);
        if ($valor === '') {
            return 0;
        }

        $numero = (int) $valor;
        return match (strtolower(substr($valor, -1))) {
            'g'     => $numero * 1024 * 1024 * 1024,
            'm'     => $numero * 1024 * 1024,
            'k'     => $numero * 1024,
            default => $numero,
        };
    }

    private static function assertValidUpload(array $file): void
    {
        $error = $file['error'] ?? UPLOAD_ERR_OK;

        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(self::mensajeLimitePhp());
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error en la subida (codigo ' . $error . ').');
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }
    }
}
