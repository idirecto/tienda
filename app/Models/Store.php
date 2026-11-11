<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Model;

/**
 * Tienda (tenant). Entidad principal del proyecto.
 */
final class Store extends Model
{
    protected static string $table = 'mt_stores';

    public const STATUS_PENDING = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_SUSPENDED = 2;

    /** SELECT base con los datos del plan incorporados. */
    private static function baseSelect(): string
    {
        return 'SELECT s.*,
                       p.code                AS plan_code,
                       p.name                AS plan_name,
                       p.own_products_quota  AS own_products_quota,
                       p.max_banners         AS max_banners,
                       p.max_notices         AS max_notices,
                       p.allow_custom_domain AS allow_custom_domain
                FROM mt_stores s
                LEFT JOIN mt_plans p ON p.id = s.id_plan';
    }

    public static function findBySlugWithPlan(string $slug): ?array
    {
        return \Tienda\Core\Database::first(
            self::baseSelect() . ' WHERE s.slug = :slug LIMIT 1',
            ['slug' => strtolower(trim($slug))]
        );
    }

    public static function findWithPlan(int $id): ?array
    {
        return \Tienda\Core\Database::first(
            self::baseSelect() . ' WHERE s.id = :id LIMIT 1',
            ['id' => $id]
        );
    }

    /** Tienda activa por slug. No devuelve pendientes ni suspendidas. */
    public static function findActiveBySlugWithPlan(string $slug): ?array
    {
        return \Tienda\Core\Database::first(
            self::baseSelect() . ' WHERE s.slug = :slug AND s.status = :status LIMIT 1',
            ['slug' => strtolower(trim($slug)), 'status' => self::STATUS_ACTIVE]
        );
    }

    /** Tienda activa por id. No devuelve pendientes ni suspendidas. */
    public static function findActiveWithPlan(int $id): ?array
    {
        return \Tienda\Core\Database::first(
            self::baseSelect() . ' WHERE s.id = :id AND s.status = :status LIMIT 1',
            ['id' => $id, 'status' => self::STATUS_ACTIVE]
        );
    }

    /** Un estado solo es de acceso publico cuando es el estado activo. */
    public static function statusAllowsPublicAccess(mixed $status): bool
    {
        return is_numeric($status) && (int) $status === self::STATUS_ACTIVE;
    }

    public static function firstActiveWithPlan(): ?array
    {
        return \Tienda\Core\Database::first(
            self::baseSelect() . ' WHERE s.status = 1 ORDER BY s.id ASC LIMIT 1'
        );
    }

    public static function allWithPlan(): array
    {
        return \Tienda\Core\Database::select(self::baseSelect() . ' ORDER BY s.name ASC');
    }
}
