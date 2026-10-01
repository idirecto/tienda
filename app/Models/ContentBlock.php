<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Bloque de contenido editable de la tienda (sobre nosotros, envios, etc.).
 */
final class ContentBlock extends Model
{
    protected static string $table = 'mt_content_blocks';

    public static function forStore(int $storeId): array
    {
        return self::where(['store_id' => $storeId], 'sort ASC, id ASC');
    }

    public static function findForStoreBySlug(int $storeId, string $slug): ?array
    {
        return \Tienda\Core\Database::first(
            'SELECT * FROM mt_content_blocks WHERE store_id = :store_id AND slug = :slug LIMIT 1',
            ['store_id' => $storeId, 'slug' => $slug]
        );
    }
}
