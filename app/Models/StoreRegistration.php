<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Alta tecnica de una tienda pendiente de verificar el correo.
 *
 * La tienda y el usuario existen antes de la verificacion, pero deben tener
 * respectivamente `mt_stores.status = 0` y `mt_store_users.active = 0`.
 */
final class StoreRegistration extends Model
{
    protected static string $table = 'mt_store_registrations';

    public const STATUS_PENDING = 'pending_verification';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    public static function schemaReady(): bool
    {
        return Database::tableExists(self::$table)
            && Database::tableExists('mt_contract_versions')
            && Database::tableExists('mt_contract_acceptances')
            && Database::tableExists('mt_registration_verifications');
    }

    public static function findByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            return null;
        }

        return self::findBy('public_uuid', $uuid);
    }

    public static function findByStore(int $storeId): ?array
    {
        if ($storeId <= 0) {
            return null;
        }

        return self::findBy('store_id', $storeId);
    }

    public static function findByAccount(int $accountId): ?array
    {
        if ($accountId <= 0) {
            return null;
        }

        return self::findBy('id_tienda_idirecto', $accountId);
    }

    public static function markEmailSent(int $registrationId, string $sentAtUtc): void
    {
        if ($registrationId <= 0) {
            return;
        }

        self::updateById($registrationId, [
            'status'        => self::STATUS_PENDING,
            'email_sent_at' => $sentAtUtc,
        ]);
    }

    public static function markVerified(int $registrationId, string $verifiedAtUtc): void
    {
        if ($registrationId <= 0) {
            return;
        }

        self::updateById($registrationId, [
            'status'            => self::STATUS_VERIFIED,
            'email_verified_at' => $verifiedAtUtc,
        ]);
    }

    public static function markExpired(int $registrationId): void
    {
        if ($registrationId <= 0) {
            return;
        }

        self::updateById($registrationId, ['status' => self::STATUS_EXPIRED]);
    }
}
