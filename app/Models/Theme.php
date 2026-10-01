<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Plantilla/plantilla visual disponible para las tiendas.
 */
final class Theme extends Model
{
    protected static string $table = 'mt_themes';

    public static function active(): array
    {
        return self::where(['active' => 1], 'sort ASC, id ASC');
    }
}
