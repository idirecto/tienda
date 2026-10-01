<?php

declare(strict_types=1);

namespace Tienda\Core\Storage;

use Tienda\Core\Config;
use Tienda\Core\StorageException;
use Tienda\Core\ValidationException;

/**
 * Almacenamiento en Amazon S3 (bucket del mayorista).
 * Requiere aws/aws-sdk-php (ver composer.json / AWS_SDK_AUTOLOAD en .env).
 */
final class S3Storage implements StorageInterface
{
    private ?object $client = null;

    public function driver(): string
    {
        return 's3';
    }

    private function client(): object
    {
        if ($this->client !== null) {
            return $this->client;
        }

        if (!class_exists(\Aws\S3\S3Client::class)) {
            throw new StorageException(
                'El SDK de AWS no esta disponible. Ejecuta "composer require aws/aws-sdk-php" '
                . 'o define AWS_SDK_AUTOLOAD apuntando a un vendor existente.'
            );
        }

        $this->client = new \Aws\S3\S3Client([
            'region'      => (string) Config::get('storage.s3.region', 'eu-west-1'),
            'version'     => '2006-03-01',
            'credentials' => [
                'key'    => (string) Config::get('storage.s3.key', ''),
                'secret' => (string) Config::get('storage.s3.secret', ''),
            ],
            'use_path_style_endpoint' => true,
            'suppress_php_deprecation_warning' => true,
        ]);

        return $this->client;
    }

    public function put(array $file, int $storeId, string $folder): array
    {
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new ValidationException('Error en la subida (codigo ' . $file['error'] . ').');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($file['tmp_name']) ?: '');

        $allowed = (array) Config::get('storage.mime', []);
        if (!isset($allowed[$mime])) {
            throw new ValidationException('Formato no permitido: ' . $mime);
        }

        $maxBytes = (int) Config::get('storage.max_bytes', 5242880);
        if ((int) $file['size'] > $maxBytes) {
            throw new ValidationException('El fichero supera el tamano maximo permitido.');
        }

        $ext = $allowed[$mime];
        $key = $this->buildKey($storeId, $folder, $ext);
        $bucket = (string) Config::get('storage.s3.bucket', '');

        if ($bucket === '') {
            throw new StorageException('S3_BUCKET no esta configurado.');
        }

        try {
            $this->client()->putObject([
                'Bucket'      => $bucket,
                'Key'         => $key,
                'Body'        => fopen($file['tmp_name'], 'r'),
                'ACL'         => 'public-read',
                'ContentType' => $mime,
            ]);
        } catch (\Throwable $e) {
            throw new StorageException('Error al subir a S3: ' . $e->getMessage(), 0, $e);
        }

        $dim = @getimagesize($file['tmp_name']);

        return [
            'key'    => $key,
            'url'    => $this->publicUrl($key),
            'mime'   => $mime,
            'bytes'  => (int) $file['size'],
            'width'  => $dim ? (int) $dim[0] : null,
            'height' => $dim ? (int) $dim[1] : null,
        ];
    }

    public function delete(string $key): bool
    {
        $key = ltrim($key, '/');
        if ($key === '') {
            return false;
        }
        try {
            $this->client()->deleteObject([
                'Bucket' => (string) Config::get('storage.s3.bucket', ''),
                'Key'    => $key,
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /** tenants/{id}/{folder}/{uuid}.{ext} */
    public function buildKey(int $storeId, string $folder, string $ext): string
    {
        $folder = preg_replace('/[^a-z0-9_\-]/i', '', $folder) ?? '';
        $folder = $folder !== '' ? strtolower($folder) : 'otros';
        $scope = $storeId > 0 ? (string) $storeId : 'global';
        $prefix = trim((string) Config::get('storage.s3.prefix', 'tenants/'), '/');

        return $prefix . '/' . $scope . '/' . $folder . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    }

    private function publicUrl(string $key): string
    {
        $base = (string) Config::get('storage.s3.public_url', '');
        if ($base !== '') {
            return $base . '/' . ltrim($key, '/');
        }
        return (string) $this->client()->getObjectUrl((string) Config::get('storage.s3.bucket', ''), $key);
    }
}
