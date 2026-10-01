<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Utilidades de texto.
 */
final class Str
{
    /**
     * Convierte un texto en un slug apto para URL.
     * Mismo criterio que idirecto (ListingUrl::slugify): minusculas, sin acentos,
     * solo [a-z0-9] separado por guiones.
     *
     *   "FUSOR OKI 42931703"  ->  "fusor-oki-42931703"
     *   "LG BL20 teléfono"    ->  "lg-bl20-telefono"
     */
    public static function slugify(string $text, int $maxLength = 80): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');

        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false && $converted !== '') {
            $text = $converted;
        }

        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        $text = trim($text, '-');

        if ($maxLength > 0 && strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            // Preferir cortar en un limite de palabra para que la URL sea legible.
            $pos = strrpos($text, '-');
            if ($pos !== false && $pos > (int) ($maxLength * 0.6)) {
                $text = substr($text, 0, $pos);
            }
            $text = rtrim($text, '-');
        }

        return $text;
    }

    /** Recorta texto para meta descripciones. */
    public static function excerpt(string $text, int $length = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, $length - 1)) . '…';
    }
}
