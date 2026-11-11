<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
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

    /**
     * Usuario que puede iniciar sesion ahora mismo.
     *
     * Exige usuario activo y tienda activa. Una tienda pendiente o suspendida
     * nunca devuelve un candidato de login, aunque su usuario siga activo.
     */
    public static function findForLogin(string $email): ?array
    {
        return Database::first(
            'SELECT u.*, s.status AS store_status
               FROM mt_store_users u
               INNER JOIN mt_stores s ON s.id = u.store_id
              WHERE u.email = :email
                AND u.active = 1
                AND s.status = 1
              LIMIT 1',
            ['email' => strtolower(trim($email))]
        );
    }

    /** Valida la sesion actual contra el estado real de usuario y tienda. */
    public static function isActiveSession(int $userId, int $storeId): bool
    {
        if ($userId <= 0 || $storeId <= 0) {
            return false;
        }

        return (int) (Database::scalar(
            'SELECT COUNT(*)
               FROM mt_store_users u
               INNER JOIN mt_stores s ON s.id = u.store_id
              WHERE u.id = :user_id
                AND u.store_id = :store_id
                AND u.active = 1
                AND s.status = 1',
            ['user_id' => $userId, 'store_id' => $storeId]
        ) ?? 0) > 0;
    }
}
