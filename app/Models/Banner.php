<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Banner publicitario de una tienda.
 *
 * El estado editorial se guarda en `status`; `active` se mantiene sincronizado
 * como espejo para que el codigo anterior pueda seguir leyendo la tabla si se
 * revierte un despliegue. Los registros antiguos con `status = NULL` conservan
 * el comportamiento historico basado en `active` y las fechas.
 */
final class Banner extends Model
{
    protected static string $table = 'mt_banners';

    /** Posiciones que el storefront pinta realmente. */
    public static function positionOptions(): array
    {
        return [
            'hero' => 'Portada (hero)',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function forStore(int $storeId): array
    {
        $rows = self::where(['store_id' => $storeId], 'sort ASC, id ASC');

        return array_map(static fn (array $row): array => self::decorate($row), $rows);
    }

    /** Un banner de ESTA tienda. La condicion de aislamiento va en la consulta. */
    public static function findForStore(int $id, int $storeId): ?array
    {
        $row = Database::first(
            'SELECT * FROM mt_banners WHERE id = :id AND store_id = :store_id LIMIT 1',
            ['id' => $id, 'store_id' => $storeId]
        );

        return $row === null ? null : self::decorate($row);
    }

    /**
     * Banners visibles ahora mismo, para el storefront.
     *
     * `status = NULL` es el caso de los registros anteriores a la migracion 011:
     * se conserva exactamente el criterio antiguo (`active` + fechas).
     */
    public static function visibleForStore(int $storeId, string $position = 'hero'): array
    {
        return Database::select(
            "SELECT * FROM mt_banners
             WHERE store_id = :store_id AND active = 1 AND position = :position
               AND (status IS NULL OR status IN ('active', 'scheduled'))
               AND (status IS NULL OR status <> 'scheduled' OR starts_at IS NOT NULL)
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort ASC, id ASC",
            ['store_id' => $storeId, 'position' => $position]
        );
    }

    /** Opciones que puede guardar el editor. */
    public static function statusOptions(): array
    {
        return [
            'draft'     => 'Borrador',
            'scheduled' => 'Programado',
            'active'    => 'Activo',
            'inactive'  => 'Inactivo',
        ];
    }

    /** Etiquetas visibles, incluido el estado calculado «caducado». */
    public static function statusLabel(string $status): string
    {
        return [
            'draft'     => 'Borrador',
            'scheduled' => 'Programado',
            'active'    => 'Activo',
            'inactive'  => 'Inactivo',
            'expired'   => 'Caducado',
        ][$status] ?? 'Inactivo';
    }

    /**
     * Estado efectivo de un banner. No modifica la fila.
     *
     * Las fechas mandan siempre sobre la etiqueta guardada: un banner «activo»
     * cuya fecha de fin ya paso se muestra como caducado, y uno «programado»
     * cuya fecha de inicio ya llego se muestra como activo.
     */
    public static function effectiveStatus(array $banner, ?int $now = null): string
    {
        $now ??= time();
        $stored = strtolower(trim((string) ($banner['status'] ?? '')));
        $known = self::statusOptions();

        if ($stored !== '' && !isset($known[$stored])) {
            return 'inactive';
        }

        if ($stored === 'draft' || $stored === 'inactive') {
            return $stored;
        }

        if ($stored === '') {
            // Registro legado: se deduce con los campos que ya existian.
            if ((int) ($banner['active'] ?? 0) !== 1) {
                return 'inactive';
            }
        }

        $starts = self::timestamp($banner['starts_at'] ?? null);
        $ends = self::timestamp($banner['ends_at'] ?? null);

        if ($stored === 'scheduled' && $starts === null) {
            // Dato incoherente o manipulado: no se presenta como activo.
            return 'inactive';
        }
        if ($ends !== null && $ends < $now) {
            return 'expired';
        }
        if ($starts !== null && $starts > $now) {
            return 'scheduled';
        }

        return 'active';
    }

    /** Nombre que se muestra en el panel (nunca en la tienda). */
    public static function displayName(array $banner): string
    {
        $name = trim((string) ($banner['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $title = trim((string) ($banner['title'] ?? ''));

        return $title !== '' ? $title : 'Banner #' . (int) ($banner['id'] ?? 0);
    }

    /** Opacidad del velo del hero, saneada al rango que admite la interfaz. */
    public static function overlayOpacity(array $banner): int
    {
        $raw = $banner['overlay_opacity'] ?? null;
        $value = $raw === null || $raw === '' ? 35 : (int) $raw;

        return max(0, min(70, $value));
    }

    /**
     * Enlace seguro para el HTML publico.
     *
     * La validacion del editor solo admite rutas internas y http/https, pero
     * una fila antigua pudo guardarse antes de esa validacion. Se filtra
     * tambien al pintar para que un enlace manipulado no llegue al href.
     */
    public static function safePublicLink(mixed $value): string
    {
        $link = trim((string) ($value ?? ''));
        if ($link === '') {
            return '';
        }

        if (str_starts_with($link, '/')) {
            return str_starts_with($link, '//') ? '' : $link;
        }

        $parts = parse_url($link);
        if (!is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)
            || empty($parts['host'])
            || filter_var($link, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $link;
    }

    /** Posiciones conocidas y si tienen un render publico en esta version. */
    public static function positionLabel(string $position): string
    {
        return [
            'hero'   => 'Portada (hero)',
            'middle' => 'Intermedio (no se muestra)',
            'footer' => 'Pie (no se muestra)',
            'menu'   => 'Menu (fuera de esta fase)',
        ][$position] ?? ($position !== '' ? $position . ' (no se muestra)' : 'Sin posicion');
    }

    public static function isRenderedPosition(string $position): bool
    {
        return array_key_exists($position, self::positionOptions());
    }

    /** Actualiza solo la fila de la tienda indicada. */
    public static function updateForStore(int $id, int $storeId, array $data): int
    {
        $allowed = [
            'name', 'title', 'subtitle', 'alt_text', 'link', 'media_id',
            'image_url', 'position', 'sort', 'active', 'status', 'starts_at',
            'ends_at', 'overlay_opacity',
        ];
        $fields = [];
        $params = ['id' => $id, 'store_id' => $storeId];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $fields[] = '`' . $key . '` = :' . $key;
            $params[$key] = $data[$key];
        }

        if ($fields === []) {
            return 0;
        }

        return Database::execute(
            'UPDATE mt_banners SET ' . implode(', ', $fields)
            . ' WHERE id = :id AND store_id = :store_id',
            $params
        );
    }

    /** Borra solo la fila; los ficheros de media no se tocan. */
    public static function deleteForStore(int $id, int $storeId): int
    {
        return Database::execute(
            'DELETE FROM mt_banners WHERE id = :id AND store_id = :store_id',
            ['id' => $id, 'store_id' => $storeId]
        );
    }

    /** Anade el estado efectivo para las vistas del panel. */
    private static function decorate(array $row): array
    {
        $row['effective_status'] = self::effectiveStatus($row);

        return $row;
    }

    private static function timestamp(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $ts = strtotime($value);

        return $ts === false ? null : $ts;
    }
}
