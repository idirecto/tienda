<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Almacenamiento de ficheros/imagenes (configurable).
 *
 *   driver = "s3"    -> bucket del mayorista (requiere aws/aws-sdk-php)
 *   driver = "local" -> public/uploads (desarrollo)
 *
 * Las claves son las mismas en los dos drivers (ver StorageKey):
 *   {prefix}/{folder_prefix}_{tipo}/{id}_{tipo}_{fecha}-{aleatorio}.{ext}
 *   tenants/tienda_banners/7_banners_20261001-174530-9f3c1a2b.webp
 */
return [
    'driver' => Env::get('STORAGE_DRIVER', 'local'),

    // Prefijo comun (dentro del bucket y dentro de public/uploads): separa lo
    // de la plataforma de cualquier otra cosa que haya en el mismo bucket.
    'prefix' => trim((string) Env::get('STORAGE_PREFIX', Env::get('S3_PREFIX', 'tenants/')), '/'),

    // Nombre de la carpeta de cada tipo de imagen: {folder_prefix}_{tipo}.
    // Con "tienda" (por defecto): tienda_banners, tienda_productos, tienda_logo.
    'folder_prefix' => Env::get('STORAGE_FOLDER_PREFIX', 'tienda'),

    's3' => [
        'region'      => Env::get('S3_REGION', 'eu-west-1'),
        'bucket'      => Env::get('S3_BUCKET', ''),
        'key'         => Env::get('S3_KEY', ''),
        'secret'      => Env::get('S3_SECRET', ''),
        'public_url'  => rtrim((string) Env::get('S3_PUBLIC_URL', ''), '/'),
    ],

    'local' => [
        'path'    => TIENDA_BASE . '/public/uploads',
        'url'     => '/public/uploads',
    ],

    // Limites de subida
    'max_bytes' => (int) Env::get('STORAGE_MAX_BYTES', 5 * 1024 * 1024),

    /**
     * Tratamiento de las imagenes al subirlas (Tienda\Core\Media\ImageOptimizer):
     * JPG/PNG/AVIF/BMP/WebP -> WebP comprimido; SVG y GIF animado se respetan.
     */
    'image' => [
        'enabled'           => Env::bool('IMAGE_OPTIMIZE', true),
        'quality'           => (int) Env::get('IMAGE_QUALITY', 82),      // WebP: 1-100
        'max_width'         => (int) Env::get('IMAGE_MAX_WIDTH', 2560),  // px (no amplia)
        'max_height'        => (int) Env::get('IMAGE_MAX_HEIGHT', 2560),
        'keep_animated_gif' => Env::bool('IMAGE_KEEP_ANIMATED_GIF', true),
    ],

    /**
     * Ajustes por tipo de imagen (el tipo es la carpeta logica que manda el
     * panel: banners, productos, logo, general...). Lo que no se indique usa
     * los valores generales de arriba. Ver Tienda\Core\Media\MediaRules.
     *
     * Los banners son fotos grandes de portada: se admiten hasta 8 MB y se
     * recomprimen con algo mas de calidad para que no se noten los degradados.
     */
    'types' => [
        'banners' => [
            'max_bytes' => (int) Env::get('STORAGE_MAX_BYTES_BANNERS', 8 * 1024 * 1024),
            'quality'   => (int) Env::get('IMAGE_QUALITY_BANNERS', 86),
        ],
    ],

    // Formatos de entrada aceptados (mime real detectado por contenido).
    'mime' => [
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'image/gif'       => 'gif',
        'image/svg+xml'   => 'svg',
        'image/avif'      => 'avif',
        'image/bmp'       => 'bmp',
    ],
];
