<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Evidencia inmutable de la aceptacion electronica.
 *
 * No se actualiza después de crearse: cualquier corrección debe generar un
 * evento nuevo y conservar el anterior.
 */
final class ContractAcceptance extends Model
{
    protected static string $table = 'mt_contract_acceptances';

    public static function findByEventUuid(string $eventUuid): ?array
    {
        $eventUuid = trim($eventUuid);
        if ($eventUuid === '') {
            return null;
        }

        return self::findBy('event_uuid', $eventUuid);
    }

    public static function latestForStore(int $storeId): ?array
    {
        if ($storeId <= 0) {
            return null;
        }

        return Database::first(
            'SELECT *
               FROM mt_contract_acceptances
              WHERE store_id = :store_id
              ORDER BY id DESC
              LIMIT 1',
            ['store_id' => $storeId]
        );
    }

    public static function latestForRegistration(int $registrationId): ?array
    {
        if ($registrationId <= 0) {
            return null;
        }

        return Database::first(
            'SELECT *
               FROM mt_contract_acceptances
              WHERE registration_id = :registration_id
              ORDER BY id DESC
              LIMIT 1',
            ['registration_id' => $registrationId]
        );
    }

    /**
     * Inserta una evidencia ya validada por la capa de alta.
     *
     * No actualiza filas existentes. La ausencia de métodos update/delete en
     * este modelo es deliberada.
     */
    public static function record(array $data): int
    {
        $required = [
            'event_uuid', 'registration_id', 'store_id', 'client_account_id',
            'contract_version_id', 'template_sha256', 'source_template_sha256',
            'pdf_path', 'pdf_sha256',
            'representative_first_name', 'representative_last_name',
            'representative_role', 'representative_email',
            'company_legal_name', 'company_tax_id', 'company_address',
            'acceptance_text_sha256', 'accepted_at_utc', 'timezone',
            'ip_address', 'user_agent',
        ];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
                throw new \InvalidArgumentException('Falta un dato obligatorio de la aceptación.');
            }
        }
        foreach (['template_sha256', 'source_template_sha256', 'pdf_sha256', 'acceptance_text_sha256'] as $hashField) {
            if (!preg_match('/^[a-f0-9]{64}$/i', (string) $data[$hashField])) {
                throw new \InvalidArgumentException('Hash no valido en la aceptación.');
            }
        }
        foreach (['registration_id', 'store_id', 'client_account_id', 'contract_version_id'] as $idField) {
            if ((int) $data[$idField] <= 0) {
                throw new \InvalidArgumentException('Identificador no valido en la aceptación.');
            }
        }
        if (!in_array((string) $data['timezone'], timezone_identifiers_list(), true)) {
            throw new \InvalidArgumentException('Zona horaria no valida.');
        }
        if (filter_var((string) $data['ip_address'], FILTER_VALIDATE_IP) === false) {
            throw new \InvalidArgumentException('IP no valida en la aceptación.');
        }
        if (mb_strlen((string) $data['user_agent']) > 512) {
            throw new \InvalidArgumentException('User-agent demasiado largo en la aceptación.');
        }

        $record = [
            'event_uuid'              => (string) $data['event_uuid'],
            'registration_id'         => (int) $data['registration_id'],
            'store_id'                => (int) $data['store_id'],
            'client_account_id'       => (int) $data['client_account_id'],
            'contract_version_id'     => (int) $data['contract_version_id'],
            'template_sha256'         => strtolower((string) $data['template_sha256']),
            'source_template_sha256'  => strtolower((string) $data['source_template_sha256']),
            'pdf_path'                => (string) $data['pdf_path'],
            'pdf_sha256'              => strtolower((string) $data['pdf_sha256']),
            'representative_first_name' => (string) $data['representative_first_name'],
            'representative_last_name'  => (string) $data['representative_last_name'],
            'representative_role'       => (string) $data['representative_role'],
            'representative_email'      => (string) $data['representative_email'],
            'company_legal_name'        => (string) $data['company_legal_name'],
            'company_tax_id'            => (string) $data['company_tax_id'],
            'company_address'           => (string) $data['company_address'],
            'acceptance_text_sha256'    => strtolower((string) $data['acceptance_text_sha256']),
            'accepted_at_utc'           => (string) $data['accepted_at_utc'],
            'timezone'                  => (string) $data['timezone'],
            'ip_address'                => (string) $data['ip_address'],
            'user_agent'                => mb_substr((string) $data['user_agent'], 0, 512),
        ];

        return Database::insert(self::$table, $record);
    }
}
