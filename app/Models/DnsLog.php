<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Historico de comprobaciones DNS.
 */
final class DnsLog extends Model
{
    protected static string $table = 'mt_dns_log';

    public static function forDomain(int $domainId): array
    {
        return \Tienda\Core\Database::select(
            'SELECT * FROM mt_dns_log WHERE domain_id = :id ORDER BY id DESC LIMIT 20',
            ['id' => $domainId]
        );
    }
}
