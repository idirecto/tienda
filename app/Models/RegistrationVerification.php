<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Persistencia de codigos de verificacion. Solo contiene hashes.
 */
final class RegistrationVerification extends Model
{
    protected static string $table = 'mt_registration_verifications';

    public static function latestForRegistration(int $registrationId): ?array
    {
        if ($registrationId <= 0) {
            return null;
        }

        return Database::first(
            'SELECT *
               FROM mt_registration_verifications
              WHERE registration_id = :registration_id
              ORDER BY id DESC
              LIMIT 1',
            ['registration_id' => $registrationId]
        );
    }

    /** Codigo que aun no ha sido consumido ni sustituido, aunque pueda estar caducado. */
    public static function activeForRegistration(int $registrationId): ?array
    {
        if ($registrationId <= 0) {
            return null;
        }

        return Database::first(
            'SELECT *
               FROM mt_registration_verifications
              WHERE registration_id = :registration_id
                AND consumed_at IS NULL
                AND superseded_at IS NULL
              ORDER BY id DESC
              LIMIT 1',
            ['registration_id' => $registrationId]
        );
    }

    public static function createForRegistration(
        int $registrationId,
        string $codeHash,
        string $expiresAt,
        int $maxAttempts,
        string $sentAt
    ): int {
        return self::create([
            'registration_id' => $registrationId,
            'code_hash'       => $codeHash,
            'expires_at'      => $expiresAt,
            'attempts'        => 0,
            'max_attempts'    => $maxAttempts,
            'sent_at'         => $sentAt,
        ]);
    }

    public static function supersedeActive(int $registrationId, string $supersededAt): int
    {
        if ($registrationId <= 0) {
            return 0;
        }

        return Database::execute(
            'UPDATE mt_registration_verifications
                SET superseded_at = :superseded_at
              WHERE registration_id = :registration_id
                AND consumed_at IS NULL
                AND superseded_at IS NULL',
            ['superseded_at' => $supersededAt, 'registration_id' => $registrationId]
        );
    }

    public static function recordFailedAttempt(int $verificationId): void
    {
        if ($verificationId <= 0) {
            return;
        }

        Database::execute(
            'UPDATE mt_registration_verifications
                SET attempts = attempts + 1
              WHERE id = :id',
            ['id' => $verificationId]
        );
    }

    public static function consume(int $verificationId, string $consumedAt): void
    {
        if ($verificationId <= 0) {
            return;
        }

        Database::execute(
            'UPDATE mt_registration_verifications
                SET consumed_at = :consumed_at
              WHERE id = :id
                AND consumed_at IS NULL
                AND superseded_at IS NULL',
            ['consumed_at' => $consumedAt, 'id' => $verificationId]
        );
    }

    public static function sendsSince(int $registrationId, string $since): int
    {
        if ($registrationId <= 0) {
            return 0;
        }

        return (int) (Database::scalar(
            'SELECT COUNT(*)
               FROM mt_registration_verifications
              WHERE registration_id = :registration_id
                AND sent_at >= :since',
            ['registration_id' => $registrationId, 'since' => $since]
        ) ?? 0);
    }
}
