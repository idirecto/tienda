<?php

declare(strict_types=1);

namespace Tienda\Core\Storage;

use Tienda\Core\Config;
use Tienda\Core\ValidationException;
use Tienda\Core\StorageException;

/**
 * Almacenamiento local (desarrollo): public/uploads.
 * No debe usarse en produccion; sirve para trabajar sin S3 ni SDK de AWS.
 */
final class LocalStorage implements StorageInterface
{
    public function driver(): string
    {
        return 'local';
    }

    public function put(array $file, int $storeId, string $folder): array
    {
        $this->assertValidUpload($file);

        $mime = $this->detectMime($file['tmp_name']);
        $allowed = (array) Config::get('storage.mime', []);
        if (!isset($allowed[$mime])) {
            throw new ValidationException('Formato no permitido: ' . $mime);
        }

        $maxBytes = (int) Config::get('storage.max_bytes', 5242880);
        if ((int) $file['size'] > $maxBytes) {
            throw new ValidationException('El fichero supera el tamano maximo permitido.');
        }

        $ext = $allowed[$mime];
        $scope = $storeId > 0 ? (string) $storeId : 'global';
        $folder = $this->sanitizeSegment($folder);

        $relativeDir = '/' . $scope . '/' . $folder;
        $absoluteDir = rtrim((string) Config::get('storage.local.path'), '/') . $relativeDir;

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new StorageException('No se pudo crear el directorio de destino.');
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $absolute = $absoluteDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $absolute)) {
            throw new StorageException('No se pudo guardar el fichero subido.');
        }

        $dim = @getimagesize($absolute);

        return [
            'key'    => ltrim($scope . '/' . $folder . '/' . $filename, '/'),
            'url'    => rtrim((string) Config::get('storage.local.url', '/uploads'), '/') . $relativeDir . '/' . $filename,
            'mime'   => $mime,
            'bytes'  => (int) filesize($absolute),
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
        $path = rtrim((string) Config::get('storage.local.path'), '/') . '/' . $key;
        return is_file($path) ? @unlink($path) : false;
    }

    private function assertValidUpload(array $file): void
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error en la subida (codigo ' . $file['error'] . ').');
        }
    }

    private function detectMime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return (string) ($finfo->file($path) ?: '');
    }

    private function sanitizeSegment(string $segment): string
    {
        $segment = preg_replace('/[^a-z0-9_\-]/i', '', $segment) ?? '';
        return $segment !== '' ? strtolower($segment) : 'otros';
    }
}
