<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Registro de un fichero/imagen almacenado (S3 o local).
 * En el sistema solo se guardan keys y URLs, nunca binarios.
 */
final class Media extends Model
{
    protected static string $table = 'mt_media';

    public static function forStore(int $storeId): array
    {
        return self::where(['store_id' => $storeId], 'id DESC');
    }

    public static function findForStore(int $id, int $storeId): ?array
    {
        return \Tienda\Core\Database::first(
            'SELECT * FROM mt_media WHERE id = :id AND store_id = :store_id LIMIT 1',
            ['id' => $id, 'store_id' => $storeId]
        );
    }

    /**
     * Registro de un fichero por su clave, acotado a la tienda.
     *
     * Se usa al sustituir o quitar una imagen "de un solo uso" (el favicon) para
     * poder borrar tambien su fila de `mt_media` y no dejar registros huerfanos.
     */
    public static function findByKeyForStore(string $key, int $storeId): ?array
    {
        return \Tienda\Core\Database::first(
            'SELECT * FROM mt_media WHERE file_key = :key AND store_id = :store_id LIMIT 1',
            ['key' => $key, 'store_id' => $storeId]
        );
    }
}
