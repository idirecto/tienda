<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Configuracion clave/valor por tienda (textos libres, opciones del tema...).
 */
final class Setting extends Model
{
    protected static string $table = 'mt_settings';

    public static function get(int $storeId, string $key, mixed $default = null): mixed
    {
        $row = Database::first(
            'SELECT value FROM mt_settings WHERE store_id = :store_id AND `key` = :key LIMIT 1',
            ['store_id' => $storeId, 'key' => $key]
        );
        return $row === null ? $default : $row['value'];
    }

    public static function set(int $storeId, string $key, ?string $value): void
    {
        $exists = Database::scalar(
            'SELECT COUNT(*) FROM mt_settings WHERE store_id = :store_id AND `key` = :key',
            ['store_id' => $storeId, 'key' => $key]
        );
        if ((int) $exists > 0) {
            Database::execute(
                'UPDATE mt_settings SET value = :value WHERE store_id = :store_id AND `key` = :key',
                ['value' => $value, 'store_id' => $storeId, 'key' => $key]
            );
            return;
        }
        Database::insert('mt_settings', ['store_id' => $storeId, 'key' => $key, 'value' => $value]);
    }

    /** Devuelve todas las claves de una tienda como array asociativo. */
    public static function allForStore(int $storeId): array
    {
        $rows = Database::select(
            'SELECT `key`, value FROM mt_settings WHERE store_id = :store_id',
            ['store_id' => $storeId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }
        return $out;
    }
}
