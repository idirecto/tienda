<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Usuario del panel de una tienda.
 */
final class StoreUser extends Model
{
    protected static string $table = 'mt_store_users';

    public static function forStore(int $storeId): array
    {
        return self::where(['store_id' => $storeId], 'name ASC');
    }
}
