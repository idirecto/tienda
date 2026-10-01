<?php

declare(strict_types=1);

namespace Tienda\Core\Media;

use Tienda\Core\Config;
use Tienda\Core\Storage\StorageKey;
use Tienda\Core\StorageException;
use Tienda\Core\ValidationException;

/**
 * Deja las imagenes subidas en el formato mas ligero posible sin que se note:
 *
 *   - JPG, PNG, AVIF, BMP y WebP  ->  WebP (calidad configurable, por defecto 82)
 *   - GIF animado                 ->  se queda en GIF (a WebP perderia el movimiento)
 *   - GIF estatico                ->  WebP
 *   - SVG                         ->  se queda tal cual (es vectorial y ya pesa poco)
 *
 * Ademas:
 *   - Corrige la orientacion (EXIF `Orientation`): las fotos de movil no salen
 *     tumbadas aunque al reencodear se pierda el EXIF.
 *   - Limita el tamano maximo (sin ampliar lo que ya es pequeno).
 *   - Los metadatos EXIF/XMP no pasan al fichero final: menos peso y menos
 *     datos personales (GPS, numero de serie de la camara...).
 *
 * Solo usa GD (extension que ya trae el proyecto): no hay dependencias nuevas.
 */
final class ImageOptimizer
{
    /** Formatos que GD sabe leer y por tanto podemos reconvertir. */
    private const CONVERTIBLES = [
        'image/jpeg' => true,
        'image/png'  => true,
        'image/webp' => true,
        'image/gif'  => true,
        'image/avif' => true,
        'image/bmp'  => true,
    ];

    /**
     * @param array $file Entrada de $_FILES (tmp_name, size, name...)
     *
     * @return array{path:string,temporary:bool,mime:string,ext:string,bytes:int,
     *               width:?int,height:?int,optimized:bool,original_mime:string,original_bytes:int}
     */
    public static function optimize(array $file): array
    {
        $path = (string) ($file['tmp_name'] ?? '');
        if (!is_file($path)) {
            throw new ValidationException('No se ha recibido un fichero valido.');
        }

        $mime = self::mime($path);
        $bytes = (int) (@filesize($path) ?: 0);

        // Formatos de entrada que el proyecto admite (config/storage.php).
        $permitidos = (array) Config::get('storage.mime', []);
        if (!isset($permitidos[$mime])) {
            throw new ValidationException('Formato no permitido: ' . $mime);
        }

        $original = [
            'path'           => $path,
            'temporary'      => false,
            'mime'           => $mime,
            'ext'            => StorageKey::extensionParaMime($mime),
            'bytes'          => $bytes,
            'width'          => null,
            'height'         => null,
            'optimized'      => false,
            'original_mime'  => $mime,
            'original_bytes' => $bytes,
        ];

        // Interruptor de emergencia (IMAGE_OPTIMIZE=false): se guarda tal cual.
        if (!(bool) Config::get('storage.image.enabled', true)) {
            return array_merge($original, self::medidas($path));
        }

        // SVG: es vectorial, no lo toca GD y no gana nada convirtiendolo.
        if (!isset(self::CONVERTIBLES[$mime])) {
            return array_merge($original, self::medidas($path));
        }

        // GIF animado: a WebP se quedaria en el primer fotograma.
        if ($mime === 'image/gif'
            && (bool) Config::get('storage.image.keep_animated_gif', true)
            && self::gifAnimado($path)
        ) {
            return array_merge($original, self::medidas($path));
        }

        $imagen = self::leer($path);
        $imagen = self::orientar($imagen, $path);
        $imagen = self::limitar($imagen);

        $ancho = imagesx($imagen);
        $alto  = imagesy($imagen);
        self::prepararTransparencia($imagen);

        $calidad = max(1, min(100, (int) Config::get('storage.image.quality', 82)));
        $tmp = tempnam(sys_get_temp_dir(), 'tienda_webp_');
        if ($tmp === false) {
            imagedestroy($imagen);
            throw new StorageException('No se ha podido preparar la imagen optimizada.');
        }

        $ok = imagewebp($imagen, $tmp, $calidad);
        imagedestroy($imagen);
        if ($ok === false) {
            @unlink($tmp);
            throw new StorageException('No se ha podido convertir la imagen a WebP.');
        }

        $nuevo = (int) (@filesize($tmp) ?: 0);

        // Si el original ya estaba en un formato que los navegadores sirven
        // igual de bien y pesa lo mismo o menos (tipico en PNG de graficos
        // planos: logos, iconos), se queda el original: mas calidad y menos peso.
        // Los JPG/AVIF/BMP siempre se pasan a WebP porque casi siempre ganan.
        if (in_array($mime, ['image/webp', 'image/png', 'image/gif'], true)
            && $bytes > 0
            && $bytes <= $nuevo
        ) {
            @unlink($tmp);

            return array_merge($original, ['width' => $ancho, 'height' => $alto]);
        }

        return [
            'path'           => $tmp,
            'temporary'      => true,
            'mime'           => 'image/webp',
            'ext'            => 'webp',
            'bytes'          => $nuevo,
            'width'          => $ancho,
            'height'         => $alto,
            'optimized'      => true,
            'original_mime'  => $mime,
            'original_bytes' => $bytes,
        ];
    }

    /** Mime real del fichero (por contenido, nunca por la extension). */
    public static function mime(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($path) ?: '');

        return $mime !== '' ? strtolower($mime) : 'application/octet-stream';
    }

    /** Lee el fichero con GD. Lanza un error claro si no es una imagen valida. */
    private static function leer(string $path): \GdImage
    {
        $datos = @file_get_contents($path);
        if ($datos === false || $datos === '') {
            throw new ValidationException('No se ha podido leer la imagen subida.');
        }

        $imagen = @imagecreatefromstring($datos);
        if (!$imagen instanceof \GdImage) {
            throw new ValidationException(
                'La imagen no se ha podido procesar. Prueba a subirla en JPG, PNG o WebP.'
            );
        }

        return $imagen;
    }

    /** Aplica la orientacion EXIF para que la foto no salga tumbada. */
    private static function orientar(\GdImage $imagen, string $path): \GdImage
    {
        $orientacion = 1;
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (is_array($exif) && isset($exif['Orientation'])) {
                $orientacion = (int) $exif['Orientation'];
            }
        }

        if ($orientacion <= 1 || $orientacion > 8) {
            return $imagen;
        }

        $fondo = imagecolorallocatealpha($imagen, 0, 0, 0, 127);

        return match ($orientacion) {
            2 => self::voltear($imagen, IMG_FLIP_HORIZONTAL),
            3 => self::girar($imagen, 180, $fondo),
            4 => self::voltear($imagen, IMG_FLIP_VERTICAL),
            5 => self::voltear(self::girar($imagen, -90, $fondo), IMG_FLIP_HORIZONTAL),
            6 => self::girar($imagen, -90, $fondo),
            7 => self::voltear(self::girar($imagen, 90, $fondo), IMG_FLIP_HORIZONTAL),
            8 => self::girar($imagen, 90, $fondo),
            default => $imagen,
        };
    }

    private static function girar(\GdImage $imagen, float $grados, int $fondo): \GdImage
    {
        $girada = imagerotate($imagen, $grados, $fondo);
        if (!$girada instanceof \GdImage) {
            return $imagen;
        }
        imagedestroy($imagen);

        return $girada;
    }

    private static function voltear(\GdImage $imagen, int $modo): \GdImage
    {
        imageflip($imagen, $modo);

        return $imagen;
    }

    /** Reduce la imagen si pasa del maximo configurado. Nunca amplia. */
    private static function limitar(\GdImage $imagen): \GdImage
    {
        $maxAncho = (int) Config::get('storage.image.max_width', 2560);
        $maxAlto  = (int) Config::get('storage.image.max_height', 2560);

        $ancho = imagesx($imagen);
        $alto  = imagesy($imagen);
        if ($ancho <= 0 || $alto <= 0) {
            return $imagen;
        }

        $factor = 1.0;
        if ($maxAncho > 0 && $ancho > $maxAncho) {
            $factor = min($factor, $maxAncho / $ancho);
        }
        if ($maxAlto > 0 && $alto > $maxAlto) {
            $factor = min($factor, $maxAlto / $alto);
        }
        if ($factor >= 1.0) {
            return $imagen;
        }

        $nuevoAncho = max(1, (int) round($ancho * $factor));
        $nuevoAlto  = max(1, (int) round($alto * $factor));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        if (!$destino instanceof \GdImage) {
            return $imagen;
        }

        imagealphablending($destino, false);
        imagesavealpha($destino, true);
        imagefilledrectangle(
            $destino,
            0,
            0,
            $nuevoAncho - 1,
            $nuevoAlto - 1,
            imagecolorallocatealpha($destino, 0, 0, 0, 127)
        );
        imagecopyresampled($destino, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($imagen);

        return $destino;
    }

    /** Prepara la imagen para que `imagewebp` conserve la transparencia. */
    private static function prepararTransparencia(\GdImage $imagen): void
    {
        if (!imageistruecolor($imagen)) {
            imagepalettetotruecolor($imagen);
        }
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
    }

    private static function medidas(string $path): array
    {
        $dim = @getimagesize($path);

        return [
            'width'  => $dim ? (int) $dim[0] : null,
            'height' => $dim ? (int) $dim[1] : null,
        ];
    }

    /**
     * Un GIF animado tiene mas de un bloque "Graphic Control Extension".
     * Es la comprobacion barata y fiable para no destrozar GIFs con movimiento.
     */
    private static function gifAnimado(string $path): bool
    {
        $datos = @file_get_contents($path);
        if ($datos === false) {
            return false;
        }

        return substr_count($datos, "\x00\x21\xF9\x04") > 1;
    }
}
