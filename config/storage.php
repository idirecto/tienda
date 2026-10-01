<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Almacenamiento de ficheros/imagenes (configurable).
 *
 *   driver = "s3"    -> bucket del mayorista (requiere aws/aws-sdk-php)
 *   driver = "local" -> public/uploads (desarrollo)
 */
return [
    'driver' => Env::get('STORAGE_DRIVER', 'local'),

    's3' => [
        'region'      => Env::get('S3_REGION', 'eu-west-1'),
        'bucket'      => Env::get('S3_BUCKET', ''),
        'key'         => Env::get('S3_KEY', ''),
        'secret'      => Env::get('S3_SECRET', ''),
        'prefix'      => Env::get('S3_PREFIX', 'tenants/'),
        'public_url'  => rtrim((string) Env::get('S3_PUBLIC_URL', ''), '/'),
    ],

    'local' => [
        'path'    => TIENDA_BASE . '/public/uploads',
        'url'     => '/public/uploads',
    ],

    // Limites de subida
    'max_bytes' => 5 * 1024 * 1024,
    'mime' => [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'image/svg+xml'   => 'svg',
    ],
];
