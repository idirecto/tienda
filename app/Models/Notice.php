<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Aviso/anuncio de una tienda.
 */
final class Notice extends Model
{
    protected static string $table = 'mt_notices';

    public static function forStore(int $storeId): array
    {
        return self::where(['store_id' => $storeId], 'id DESC');
    }

    public static function visibleForStore(int $storeId): array
    {
        return \Tienda\Core\Database::select(
            "SELECT * FROM mt_notices
             WHERE store_id = :store_id AND visible = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY id DESC",
            ['store_id' => $storeId]
        );
    }
}
