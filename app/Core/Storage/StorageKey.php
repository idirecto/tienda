<?php

declare(strict_types=1);

namespace Tienda\Core\Storage;

use Tienda\Core\Config;

/**
 * Construye la clave (ruta dentro del bucket / carpeta de uploads) de un
 * fichero subido. La usan los dos drivers, asi que S3 y local guardan igual y
 * las claves guardadas en `mt_media` siguen valiendo si se cambia de driver.
 *
 *   {prefijo}/{tienda}_{tipo}/{id}_{tipo}_{fecha}-{aleatorio}.{ext}
 *
 *   tenants/tienda_banners/7_banners_20261001-174530-9f3c1a2b.webp
 *
 *  - `prefijo` separa lo de la plataforma dentro del bucket (por defecto
 *    `tenants`): permite tener el bucket compartido con otras cosas.
 *  - La carpeta lleva el prefijo de la tienda y el tipo de contenido
 *    (`tienda_banners`, `tienda_productos`, `tienda_logo`...), asi se pueden
 *    listar o borrar por familias enteras.
 *  - El nombre empieza por el **id de la tienda**: cualquier fichero se puede
 *    identificar (y borrar) aunque se mueva de carpeta o se descargue.
 */
final class StorageKey
{
    /** Nombre de la carpeta cuando el tipo no es valido. */
    private const TIPO_POR_DEFECTO = 'otros';

    /** Extensiones que aceptamos escribir en disco. */
    private const EXTENSIONES = ['webp', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'avif'];

    /** Extension con la que se guarda cada mime (los formatos ya definitivos). */
    private const EXTENSION_POR_MIME = [
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
        'image/gif'     => 'gif',
        'image/png'     => 'png',
        'image/jpeg'    => 'jpg',
        'image/avif'    => 'avif',
    ];

    public static function build(int $storeId, string $folder, string $ext): string
    {
        $tipo = self::segmento($folder, self::TIPO_POR_DEFECTO);
        $prefijoCarpeta = self::segmento((string) Config::get('storage.folder_prefix', 'tienda'), 'tienda');
        $prefijo = trim((string) Config::get('storage.prefix', 'tenants'), '/');

        $carpeta = $prefijoCarpeta . '_' . $tipo;
        $nombre = self::id($storeId)
            . '_' . $tipo
            . '_' . date('Ymd-His')
            . '-' . bin2hex(random_bytes(4))
            . '.' . self::extension($ext);

        return ($prefijo !== '' ? $prefijo . '/' : '') . $carpeta . '/' . $nombre;
    }

    /**
     * Id de la tienda tal y como aparece en el nombre del fichero.
     * Las subidas sin tienda (0) se marcan con `0`.
     */
    public static function id(int $storeId): string
    {
        return $storeId > 0 ? (string) $storeId : '0';
    }

    /** Limpia la extension recibida y deja solo las que escribimos. */
    public static function extension(string $ext): string
    {
        $ext = strtolower(trim($ext));
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?? '';

        return in_array($ext, self::EXTENSIONES, true) ? $ext : 'webp';
    }

    /** Extension definitiva de un mime ya procesado. */
    public static function extensionParaMime(string $mime): string
    {
        return self::EXTENSION_POR_MIME[strtolower($mime)] ?? 'webp';
    }

    /** Carpeta fisica (sin prefijo) de un tipo: `tienda_banners`. */
    public static function folder(string $tipo): string
    {
        $prefijo = self::segmento((string) Config::get('storage.folder_prefix', 'tienda'), 'tienda');

        return $prefijo . '_' . self::segmento($tipo, self::TIPO_POR_DEFECTO);
    }

    /** Deja un segmento en minusculas y sin nada que pueda escaparse de la ruta. */
    private static function segmento(string $valor, string $defecto): string
    {
        $valor = strtolower(trim($valor));
        $valor = preg_replace('/[^a-z0-9_\-]/', '', $valor) ?? '';

        return $valor !== '' ? $valor : $defecto;
    }
}
