<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Config;
use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Version aprobada de un documento contractual.
 *
 * Solo se considera vigente una fila activa con version y fecha de vigencia
 * informadas, y cuyo PDF privado exista y coincida con el hash registrado.
 */
final class ContractVersion extends Model
{
    protected static string $table = 'mt_contract_versions';

    /** Version vigente y utilizable, o null si no hay ninguna autorizada. */
    public static function current(string $documentKey = 'access_web'): ?array
    {
        if (!Database::tableExists(self::$table)) {
            return null;
        }

        $row = Database::first(
            'SELECT *
               FROM mt_contract_versions
              WHERE document_key = :document_key
                AND active = 1
                AND version_label <> \'\'
                AND template_path <> \'\'
                AND template_sha256 <> \'\'
                AND approved_at IS NOT NULL
                AND approved_by <> \'\'
                AND effective_date IS NOT NULL
                AND effective_date <= CURDATE()
              ORDER BY effective_date DESC, id DESC
              LIMIT 1',
            ['document_key' => $documentKey]
        );

        if ($row === null || self::templateAbsolutePath($row) === null) {
            return null;
        }

        return $row;
    }

    /** Comprueba que el fichero privado existe, no escapa del directorio y conserva su hash. */
    public static function templateAbsolutePath(array $version): ?string
    {
        $relative = str_replace('\\', '/', trim((string) ($version['template_path'] ?? '')));
        $expectedHash = strtolower(trim((string) ($version['template_sha256'] ?? '')));
        $base = realpath((string) Config::get('registration.contract.template_dir', ''));
        if ($relative === '' || $base === false || !is_dir($base) || strlen($expectedHash) !== 64) {
            return null;
        }

        $candidate = realpath($base . '/' . ltrim($relative, '/'));
        $prefix = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($candidate === false
            || !str_starts_with($candidate, $prefix)
            || !is_file($candidate)
            || !is_readable($candidate)) {
            return null;
        }

        $actualHash = hash_file('sha256', $candidate);
        if (!is_string($actualHash) || !hash_equals($expectedHash, strtolower($actualHash))) {
            return null;
        }

        return $candidate;
    }

    public static function schemaReady(): bool
    {
        return Database::tableExists(self::$table);
    }
}
