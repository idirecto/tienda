<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Aviso/anuncio de una tienda.
 *
 * La tabla historica solo tenia tipo, mensaje, visibilidad y fechas. La
 * migracion 011 anade presentacion opcional; los valores NULL conservan el
 * aspecto anterior mediante los metodos de este modelo.
 */
final class Notice extends Model
{
    protected static string $table = 'mt_notices';

    /** @return array<int, array<string, mixed>> */
    public static function forStore(int $storeId): array
    {
        $rows = self::where(['store_id' => $storeId], 'id DESC');

        return array_map(static fn (array $row): array => self::decorate($row), $rows);
    }

    /** Un aviso de ESTA tienda. */
    public static function findForStore(int $id, int $storeId): ?array
    {
        $row = Database::first(
            'SELECT * FROM mt_notices WHERE id = :id AND store_id = :store_id LIMIT 1',
            ['id' => $id, 'store_id' => $storeId]
        );

        return $row === null ? null : self::decorate($row);
    }

    public static function visibleForStore(int $storeId): array
    {
        return Database::select(
            "SELECT * FROM mt_notices
             WHERE store_id = :store_id AND visible = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY id DESC",
            ['store_id' => $storeId]
        );
    }

    public static function typeOptions(): array
    {
        return [
            'info'    => 'Informacion',
            'promo'   => 'Promocion',
            'warning' => 'Aviso importante',
        ];
    }

    public static function variantOptions(): array
    {
        return [
            'soft'    => 'Suave',
            'solid'   => 'Solida',
            'outline' => 'Contorno',
        ];
    }

    public static function textSizeOptions(): array
    {
        return [
            'sm' => 'Pequeno',
            'md' => 'Normal',
            'lg' => 'Grande',
        ];
    }

    /**
     * Iconos que el editor puede elegir. Solo claves conocidas por icon_svg(),
     * de modo que una peticion manual no pueda inyectar SVG arbitrario.
     */
    public static function iconOptions(): array
    {
        return [
            'star'    => 'Estrella',
            'bolt'    => 'Rayo',
            'truck'   => 'Envio',
            'shield'  => 'Escudo',
            'refresh' => 'Devolucion',
            'check'   => 'Check',
            'package' => 'Paquete',
            'pin'     => 'Ubicacion',
            'headset' => 'Soporte',
            'cart'    => 'Carrito',
            'grid'    => 'Categorias',
            'wrench'  => 'Servicio',
            'filter'  => 'Filtro',
            'laptop'  => 'Portatil',
            'gpu'     => 'Grafica',
        ];
    }

    public static function effectiveStatus(array $notice, ?int $now = null): string
    {
        $now ??= time();

        if ((int) ($notice['visible'] ?? 0) !== 1) {
            return 'inactive';
        }

        $starts = self::timestamp($notice['starts_at'] ?? null);
        $ends = self::timestamp($notice['ends_at'] ?? null);

        if ($ends !== null && $ends < $now) {
            return 'expired';
        }
        if ($starts !== null && $starts > $now) {
            return 'scheduled';
        }

        return 'active';
    }

    public static function statusLabel(string $status): string
    {
        return [
            'scheduled' => 'Programado',
            'active'    => 'Activo',
            'inactive'  => 'Inactivo',
            'expired'   => 'Caducado',
        ][$status] ?? 'Inactivo';
    }

    /** Valores ya saneados y listos para la vista publica. */
    public static function presentation(array $notice): array
    {
        $types = self::typeOptions();
        $variants = self::variantOptions();
        $sizes = self::textSizeOptions();
        $icons = self::iconOptions();

        $type = strtolower(trim((string) ($notice['type'] ?? 'info')));
        $variant = strtolower(trim((string) ($notice['variant'] ?? '')));
        $size = strtolower(trim((string) ($notice['text_size'] ?? '')));
        $icon = strtolower(trim((string) ($notice['icon'] ?? '')));

        return [
            'type'      => isset($types[$type]) ? $type : 'info',
            'variant'   => isset($variants[$variant]) ? $variant : 'soft',
            'text_size' => isset($sizes[$size]) ? $size : 'md',
            'icon'      => isset($icons[$icon]) ? $icon : '',
            'badge'     => mb_substr(trim((string) ($notice['badge_text'] ?? '')), 0, 40),
            'image_url' => trim((string) ($notice['image_url'] ?? '')),
            'message'   => (string) ($notice['message'] ?? ''),
        ];
    }

    /** Actualiza solo la fila de la tienda indicada. */
    public static function updateForStore(int $id, int $storeId, array $data): int
    {
        $allowed = [
            'type', 'variant', 'text_size', 'message', 'badge_text', 'icon',
            'media_id', 'image_url', 'visible', 'starts_at', 'ends_at',
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
            'UPDATE mt_notices SET ' . implode(', ', $fields)
            . ' WHERE id = :id AND store_id = :store_id',
            $params
        );
    }

    /** Borra solo la fila; la imagen de media no se toca. */
    public static function deleteForStore(int $id, int $storeId): int
    {
        return Database::execute(
            'DELETE FROM mt_notices WHERE id = :id AND store_id = :store_id',
            ['id' => $id, 'store_id' => $storeId]
        );
    }

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
