<?php

declare(strict_types=1);

namespace Tienda\Core\Media;

use Tienda\Core\Config;

/**
 * Limites y calidad de las imagenes subidas, con ajustes **por tipo**.
 *
 * El tipo es la carpeta logica que manda el panel: `banners`, `productos`,
 * `logo`, `general`... En `config/storage.php` (clave `storage.types`) se puede
 * afinar cualquiera de ellos; lo que no se indique usa los valores generales.
 *
 * Por defecto los **banners** tienen mas margen (8 MB) y algo mas de calidad
 * (86) porque son fotos grandes de portada donde el degradado se nota.
 */
final class MediaRules
{
    /** Tipo que se usa cuando el panel no manda ninguno. */
    public const TIPO_POR_DEFECTO = 'general';

    /** Peso maximo que se acepta subir para ese tipo (bytes). */
    public static function maxBytes(string $tipo): int
    {
        $propio = (int) Config::get(self::ruta($tipo, 'max_bytes'), 0);
        if ($propio > 0) {
            return $propio;
        }

        return (int) Config::get('storage.max_bytes', 5 * 1024 * 1024);
    }

    /** Calidad WebP (1-100) con la que se recomprime ese tipo. */
    public static function quality(string $tipo): int
    {
        $propio = (int) Config::get(self::ruta($tipo, 'quality'), 0);
        if ($propio <= 0) {
            $propio = (int) Config::get('storage.image.quality', 82);
        }

        return max(1, min(100, $propio));
    }

    /** Lado maximo en pixeles (nunca se amplia). */
    public static function maxWidth(string $tipo): int
    {
        $propio = (int) Config::get(self::ruta($tipo, 'max_width'), 0);

        return $propio > 0 ? $propio : (int) Config::get('storage.image.max_width', 2560);
    }

    public static function maxHeight(string $tipo): int
    {
        $propio = (int) Config::get(self::ruta($tipo, 'max_height'), 0);

        return $propio > 0 ? $propio : (int) Config::get('storage.image.max_height', 2560);
    }

    /** Ajustes efectivos de un tipo (para mostrarlos en el panel). */
    public static function resumen(string $tipo): array
    {
        return [
            'tipo'       => self::clave($tipo),
            'max_bytes'  => self::maxBytes($tipo),
            'max_humano' => self::formatoBytes(self::maxBytes($tipo)),
            'quality'    => self::quality($tipo),
            'max_width'  => self::maxWidth($tipo),
            'max_height' => self::maxHeight($tipo),
        ];
    }

    /** Peso legible: 8 MB, 5 MB, 512 KB. */
    public static function formatoBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            $mb = $bytes / 1048576;

            return ($mb === floor($mb) ? (string) (int) $mb : number_format($mb, 1, ',', '.')) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /** Ruta de config del ajuste propio del tipo. */
    private static function ruta(string $tipo, string $ajuste): string
    {
        return 'storage.types.' . self::clave($tipo) . '.' . $ajuste;
    }

    /** El tipo se usa como clave de configuracion: solo minusculas y _ -. */
    private static function clave(string $tipo): string
    {
        $tipo = strtolower(trim($tipo));
        $tipo = preg_replace('/[^a-z0-9_\-]/', '', $tipo) ?? '';

        return $tipo !== '' ? $tipo : self::TIPO_POR_DEFECTO;
    }
}
