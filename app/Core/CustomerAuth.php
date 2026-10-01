<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Models\Customer;

/**
 * Sesion del CLIENTE de la tienda (el que compra en el storefront).
 *
 * Va aparte de `Auth` (usuarios del panel) y vive en un hueco de la sesion por
 * tienda, para que nadie pueda entrar al panel por estar comprando y para que el
 * mismo navegador pueda tener sesion abierta en dos tiendas distintas.
 *
 * La contrasena del cliente es propia (bcrypt con `password_hash`): no tiene
 * nada que ver con la cuenta del mayorista ni con el usuario del panel.
 */
final class CustomerAuth
{
    private const SESSION_KEY = 'shop_customers';

    private const GUEST = 'shop_guest';

    private const ATTEMPTS = 5;

    private const WINDOW = 900;

    /** Comprueba email y contrasena. false = credenciales invalidas o invitado. */
    public static function attempt(int $storeId, string $email, string $password): bool
    {
        $customer = Customer::findByEmail($storeId, $email);

        if ($customer === null || (int) $customer['active'] !== 1) {
            return false;
        }
        // Un cliente invitado no tiene contrasena: tiene que registrarse.
        if (empty($customer['password_hash'])) {
            return false;
        }
        if (!password_verify($password, (string) $customer['password_hash'])) {
            return false;
        }

        if (password_needs_rehash((string) $customer['password_hash'], PASSWORD_DEFAULT)) {
            Customer::updateById((int) $customer['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        self::login($storeId, $customer);

        return true;
    }

    /** Deja la sesion abierta para ese cliente. */
    public static function login(int $storeId, array $customer): void
    {
        Session::regenerate();
        $_SESSION[self::SESSION_KEY][$storeId] = [
            'id'       => (int) $customer['id'],
            'name'     => (string) ($customer['name'] ?? ''),
            'email'    => (string) ($customer['email'] ?? ''),
            'is_guest' => (int) ($customer['is_guest'] ?? 0),
        ];

        Customer::updateById((int) $customer['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
    }

    public static function check(int $storeId): bool
    {
        return !empty($_SESSION[self::SESSION_KEY][$storeId]['id']);
    }

    public static function id(int $storeId): ?int
    {
        $id = $_SESSION[self::SESSION_KEY][$storeId]['id'] ?? null;

        return $id === null ? null : (int) $id;
    }

    /** Fila del cliente que tiene la sesion abierta (se lee de la base). */
    public static function customer(int $storeId): ?array
    {
        $id = self::id($storeId);

        return $id === null ? null : Customer::findForStore($id, $storeId);
    }

    public static function logout(int $storeId): void
    {
        unset($_SESSION[self::SESSION_KEY][$storeId]);
        Session::regenerate();
    }

    // =====================================================================
    // INTENTOS DE ENTRADA
    // =====================================================================

    /**
     * Limite de intentos por sesion.
     *
     * El formulario de entrada esta abierto en la web, asi que sin limite
     * serviria para probar contrasenas de clientes a la fuerza. Es por sesion
     * (no por IP): suficiente para el uso normal y sin almacenamiento compartido.
     */
    public static function blocked(int $storeId, int $maxAttempts = self::ATTEMPTS, int $window = self::WINDOW): bool
    {
        $intentos = self::attempts($storeId);

        return ($intentos['until'] ?? 0) >= time() && (int) ($intentos['count'] ?? 0) >= $maxAttempts;
    }

    public static function noteAttempt(int $storeId, int $window = self::WINDOW): void
    {
        $intentos = self::attempts($storeId);
        $intentos['count'] = (int) ($intentos['count'] ?? 0) + 1;
        $intentos['until'] = time() + $window;
        $_SESSION[self::GUEST][$storeId] = $intentos;
    }

    public static function clearAttempts(int $storeId): void
    {
        unset($_SESSION[self::GUEST][$storeId]);
    }

    private static function attempts(int $storeId): array
    {
        $intentos = $_SESSION[self::GUEST][$storeId] ?? [];

        return is_array($intentos) ? $intentos : [];
    }
}
