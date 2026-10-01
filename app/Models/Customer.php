<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Model;

/**
 * Cliente final de una tienda (el que compra en su web).
 *
 * No confundir con `StoreUser` (usuario del panel del tendero) ni con la cuenta
 * del mayorista (`tiendas`). El cliente vive en nuestras tablas y pertenece a una
 * tienda: el mismo email puede ser cliente de dos tiendas y son dos cuentas.
 *
 * `is_guest = 1` mientras nadie ha puesto contrasena: es el cliente que se crea
 * al comprar como invitado. Si luego se registra con el mismo email se "reclama"
 * la cuenta (se le pone contrasena) y conserva sus pedidos.
 */
final class Customer extends Model
{
    protected static string $table = 'mt_customers';

    /** Cliente de esa tienda con ese email (null si no existe). */
    public static function findByEmail(int $storeId, string $email): ?array
    {
        return Database::first(
            'SELECT * FROM mt_customers WHERE store_id = :store AND email = :email LIMIT 1',
            ['store' => $storeId, 'email' => mb_strtolower(trim($email))]
        );
    }

    public static function findForStore(int $id, int $storeId): ?array
    {
        return Database::first(
            'SELECT * FROM mt_customers WHERE id = :id AND store_id = :store LIMIT 1',
            ['id' => $id, 'store' => $storeId]
        );
    }

    /**
     * Crea un cliente (registrado o invitado).
     *
     * @param array{email:string,name?:string,tax_id?:string,phone?:string,mobile?:string,password_hash?:?string,is_guest?:bool} $data
     */
    public static function createForStore(int $storeId, array $data): int
    {
        return self::create([
            'store_id'      => $storeId,
            'email'         => mb_strtolower(trim((string) $data['email'])),
            'password_hash' => $data['password_hash'] ?? null,
            'name'          => mb_substr(trim((string) ($data['name'] ?? '')), 0, 150),
            'tax_id'        => self::orNull($data['tax_id'] ?? null, 30),
            'phone'         => self::orNull($data['phone'] ?? null, 40),
            'mobile'        => self::orNull($data['mobile'] ?? null, 40),
            'is_guest'      => !empty($data['is_guest']) ? 1 : 0,
            'active'        => 1,
        ]);
    }

    /**
     * Cliente para comprar: devuelve el existente (lo completa con los datos que
     * falten) o lo crea. Un invitado que ya existia sigue siendo invitado.
     *
     * @param array{email:string,name?:string,tax_id?:string,phone?:string,mobile?:string} $data
     */
    public static function findOrCreateGuest(int $storeId, array $data): array
    {
        $email = mb_strtolower(trim((string) $data['email']));
        $customer = self::findByEmail($storeId, $email);

        if ($customer === null) {
            $id = self::createForStore($storeId, $data + ['is_guest' => true]);
            $customer = self::find($id);
        } else {
            // Completa los datos que el cliente no tuviera guardados.
            $updates = [];
            foreach (['name' => 150, 'tax_id' => 30, 'phone' => 40, 'mobile' => 40] as $field => $len) {
                $value = trim((string) ($data[$field] ?? ''));
                if ($value !== '' && trim((string) ($customer[$field] ?? '')) === '') {
                    $updates[$field] = mb_substr($value, 0, $len);
                }
            }
            if ($updates !== []) {
                self::updateById((int) $customer['id'], $updates);
                $customer = self::find((int) $customer['id']);
            }
        }

        return $customer;
    }

    /** Pedidos del cliente (para su panel). */
    public static function orders(int $customerId, int $storeId): array
    {
        return Database::select(
            'SELECT id, code, status, payment_method, payment_status, total, created_at
             FROM mt_orders
             WHERE customer_id = :customer AND store_id = :store AND status <> 6
             ORDER BY id DESC',
            ['customer' => $customerId, 'store' => $storeId]
        );
    }

    /** Clientes de la tienda con el numero de pedidos y lo que han comprado. */
    public static function forStore(int $storeId, string $q = ''): array
    {
        $params = ['store' => $storeId];
        $where = 'c.store_id = :store';
        if (trim($q) !== '') {
            // Tres parametros distintos: con EMULATE_PREPARES = false un nombre
            // no puede repetirse en la misma consulta.
            $where .= ' AND (c.name LIKE :q1 OR c.email LIKE :q2 OR c.phone LIKE :q3)';
            $params['q1'] = '%' . trim($q) . '%';
            $params['q2'] = '%' . trim($q) . '%';
            $params['q3'] = '%' . trim($q) . '%';
        }

        return Database::select(
            'SELECT c.*,
                    COUNT(o.id) AS orders_count,
                    COALESCE(SUM(o.total), 0) AS orders_total
             FROM mt_customers c
             LEFT JOIN mt_orders o ON o.customer_id = c.id AND o.status <> 6
             WHERE ' . $where . '
             GROUP BY c.id
             ORDER BY c.id DESC',
            $params
        );
    }

    private static function orNull(mixed $value, int $maxLength): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
