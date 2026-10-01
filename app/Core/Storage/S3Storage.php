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

    public function put(string $localPath, string $mime, int $storeId, string $folder): array
    {
        if (!is_file($localPath)) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }

        $bucket = (string) Config::get('storage.s3.bucket', '');
        if ($bucket === '') {
            throw new StorageException('S3_BUCKET no esta configurado.');
        }

        $key = StorageKey::build($storeId, $folder, StorageKey::extensionParaMime($mime));
        $cuerpo = fopen($localPath, 'r');
        if ($cuerpo === false) {
            throw new StorageException('No se ha podido abrir el fichero para subirlo.');
        }

        try {
            $this->client()->putObject([
                'Bucket'      => $bucket,
                'Key'         => $key,
                'Body'        => $cuerpo,
                'ACL'         => 'public-read',
                'ContentType' => $mime,
                // Los ficheros llevan fecha y aleatorio en el nombre: nunca se
                // reescriben, asi que se pueden cachear para siempre.
                'CacheControl' => 'public, max-age=31536000, immutable',
                'Metadata'    => [
                    'tienda-id'    => StorageKey::id($storeId),
                    'tienda-tipo'  => $folder,
                ],
            ]);
        } catch (\Throwable $e) {
            throw new StorageException('Error al subir a S3: ' . $e->getMessage(), 0, $e);
        } finally {
            if (is_resource($cuerpo)) {
                fclose($cuerpo);
            }
        }

        $dim = @getimagesize($localPath);

        return [
            'key'    => $key,
            'url'    => $this->publicUrl($key),
            'mime'   => $mime,
            'bytes'  => (int) (@filesize($localPath) ?: 0),
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

    /**
     * Clave del objeto. Delega en StorageKey para que S3 y local compartan
     * exactamente el mismo esquema:
     *   tenants/tienda_banners/7_banners_20261001-174530-9f3c1a2b.webp
     */
    public function buildKey(int $storeId, string $folder, string $ext): string
    {
        return StorageKey::build($storeId, $folder, $ext);
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
