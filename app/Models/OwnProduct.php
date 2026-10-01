<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Producto propio de la tienda (ajeno al catalogo central).
 * Su numero esta limitado por la cuota del plan.
 */
final class OwnProduct extends Model
{
    protected static string $table = 'mt_own_products';

    public static function forStore(int $storeId): array
    {
        return self::where(['store_id' => $storeId], 'id DESC');
    }

    public static function publishedForStore(int $storeId): array
    {
        return \Tienda\Core\Database::select(
            "SELECT * FROM mt_own_products
             WHERE store_id = :store_id AND status = 1
             ORDER BY id DESC",
            ['store_id' => $storeId]
        );
    }

    public static function countForStore(int $storeId): int
    {
        return self::countWhere(['store_id' => $storeId]);
    }

    public static function findForStore(int $id, int $storeId): ?array
    {
        return \Tienda\Core\Database::first(
            'SELECT * FROM mt_own_products WHERE id = :id AND store_id = :store_id LIMIT 1',
            ['id' => $id, 'store_id' => $storeId]
        );
    }
}
