<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Direccion de envio o facturacion de un cliente de la tienda.
 *
 * Guarda los mismos datos que el mayorista espera en `pedidos_addr` (nombre,
 * nif_cif, direccion, cp, poblacion, provincia, pais, telefono y celular) mas el
 * detalle del portal/escalera y los ids de `paises`/`provincias`/`poblaciones`,
 * que son opcionales: un cliente de fuera puede escribir su provincia a mano.
 *
 * No se borran de verdad: `active = 0`, para no romper pedidos antiguos.
 */
final class CustomerAddress extends Model
{
    protected static string $table = 'mt_customer_addresses';

    /** Libreta de direcciones del cliente (las activas). */
    public static function forCustomer(int $customerId, int $storeId): array
    {
        return Database::select(
            'SELECT * FROM mt_customer_addresses
             WHERE customer_id = :customer AND store_id = :store AND active = 1
             ORDER BY is_default_ship DESC, id DESC',
            ['customer' => $customerId, 'store' => $storeId]
        );
    }

    /** Direccion del cliente (comprueba que sea suya y de esa tienda). */
    public static function findForCustomer(int $id, int $customerId, int $storeId): ?array
    {
        return Database::first(
            'SELECT * FROM mt_customer_addresses
             WHERE id = :id AND customer_id = :customer AND store_id = :store AND active = 1
             LIMIT 1',
            ['id' => $id, 'customer' => $customerId, 'store' => $storeId]
        );
    }

    /** Direccion por defecto (la de envio; si no hay, la ultima que uso). */
    public static function defaultForCustomer(int $customerId, int $storeId): ?array
    {
        $addresses = self::forCustomer($customerId, $storeId);

        return $addresses[0] ?? null;
    }

    /**
     * Crea o actualiza una direccion del cliente.
     *
     * @param array<string,mixed> $data
     * @return int id de la direccion
     */
    public static function save(int $customerId, int $storeId, array $data, int $id = 0): int
    {
        $fields = [
            'label'       => self::orNull($data['label'] ?? null, 60),
            'name'        => mb_substr(trim((string) ($data['name'] ?? '')), 0, 150),
            'tax_id'      => self::orNull($data['tax_id'] ?? null, 30),
            'address'     => mb_substr(trim((string) ($data['address'] ?? '')), 0, 250),
            'detail'      => self::orNull($data['detail'] ?? null, 200),
            'postal_code' => self::orNull($data['postal_code'] ?? null, 20),
            'city'        => self::orNull($data['city'] ?? null, 120),
            'province'    => self::orNull($data['province'] ?? null, 120),
            'country'     => mb_substr(trim((string) ($data['country'] ?? '')) ?: 'Espana', 0, 60),
            'id_pais'     => self::intOrNull($data['id_pais'] ?? null),
            'id_provincia' => self::intOrNull($data['id_provincia'] ?? null),
            'id_poblacion' => self::intOrNull($data['id_poblacion'] ?? null),
            'phone'       => self::orNull($data['phone'] ?? null, 40),
            'mobile'      => self::orNull($data['mobile'] ?? null, 40),
        ];

        // Solo una direccion por defecto de cada tipo.
        $fields['is_default_ship'] = !empty($data['is_default_ship']) ? 1 : 0;
        $fields['is_default_bill'] = !empty($data['is_default_bill']) ? 1 : 0;

        if ($id > 0) {
            self::updateById($id, $fields);
        } else {
            $id = self::create($fields + [
                'store_id'    => $storeId,
                'customer_id' => $customerId,
                'active'      => 1,
            ]);
        }

        if ($fields['is_default_ship'] === 1) {
            self::clearDefault($id, $customerId, 'is_default_ship');
        }
        if ($fields['is_default_bill'] === 1) {
            self::clearDefault($id, $customerId, 'is_default_bill');
        }

        return $id;
    }

    /** Borra (desactiva) una direccion del cliente. */
    public static function remove(int $id, int $customerId, int $storeId): bool
    {
        if (self::findForCustomer($id, $customerId, $storeId) === null) {
            return false;
        }

        self::updateById($id, ['active' => 0, 'is_default_ship' => 0, 'is_default_bill' => 0]);

        return true;
    }

    public static function countForCustomer(int $customerId, int $storeId): int
    {
        return self::countWhere(['customer_id' => $customerId, 'store_id' => $storeId, 'active' => 1]);
    }

    private static function clearDefault(int $keepId, int $customerId, string $column): void
    {
        Database::execute(
            "UPDATE mt_customer_addresses SET $column = 0 WHERE customer_id = :customer AND id <> :keep",
            ['customer' => $customerId, 'keep' => $keepId]
        );
    }

    private static function orNull(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }

    private static function intOrNull(mixed $value): ?int
    {
        $int = (int) $value;

        return $int > 0 ? $int : null;
    }
}
