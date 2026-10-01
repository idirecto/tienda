<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Plan/nivel de cliente con cuota de productos propios.
 * own_products_quota: -1 = ilimitado, 0 = no permitido, N = limite.
 */
final class Plan extends Model
{
    protected static string $table = 'mt_plans';

    public static function active(): array
    {
        return self::where(['active' => 1], 'sort ASC, id ASC');
    }
}
