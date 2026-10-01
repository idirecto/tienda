<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Banner publicitario de una tienda.
 */
final class Banner extends Model
{
    protected static string $table = 'mt_banners';

    public static function forStore(int $storeId): array
    {
        return self::where(['store_id' => $storeId], 'sort ASC, id ASC');
    }

    /** Banners visibles ahora mismo, para el storefront. */
    public static function visibleForStore(int $storeId, string $position = 'hero'): array
    {
        return \Tienda\Core\Database::select(
            "SELECT * FROM mt_banners
             WHERE store_id = :store_id AND active = 1 AND position = :position
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY sort ASC, id ASC",
            ['store_id' => $storeId, 'position' => $position]
        );
    }
}
