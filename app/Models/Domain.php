<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Dominio propio o subdominio de una tienda, con su estado de verificacion DNS.
 */
final class Domain extends Model
{
    protected static string $table = 'mt_domains';

    public static function forStore(int $storeId): array
    {
        return self::where(['store_id' => $storeId], 'is_primary DESC, id ASC');
    }

    /** Dominio verificado que corresponde a un hostname. */
    public static function findVerified(string $host): ?array
    {
        $host = \Tienda\Core\TenantResolver::normalizeHost($host);
        if ($host === '') {
            return null;
        }
        return \Tienda\Core\Database::first(
            'SELECT * FROM mt_domains WHERE domain = :domain AND status = 1 LIMIT 1',
            ['domain' => $host]
        );
    }

    public static function findForStore(int $id, int $storeId): ?array
    {
        return \Tienda\Core\Database::first(
            'SELECT * FROM mt_domains WHERE id = :id AND store_id = :store_id LIMIT 1',
            ['id' => $id, 'store_id' => $storeId]
        );
    }
}
