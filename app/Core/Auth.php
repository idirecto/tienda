<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Models\StoreUser;

/**
 * Autenticacion de usuarios del panel de tienda.
 */
final class Auth
{
    private const SESSION_KEY = 'auth_user';

    /** Caché por peticion de la validación real contra usuario y tienda. */
    private static ?bool $activeSession = null;

    public static function attempt(string $email, string $password): bool
    {
        self::$activeSession = null;
        $user = StoreUser::findForLogin($email);

        if (!$user) {
            return false;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        // Rehash si el algoritmo por defecto ha cambiado.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            StoreUser::updateById((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        Session::regenerate();
        Session::set(self::SESSION_KEY, [
            'id'       => (int) $user['id'],
            'store_id' => (int) $user['store_id'],
            'name'     => (string) $user['name'],
            'email'    => (string) $user['email'],
            'role'     => (string) ($user['role'] ?? 'owner'),
        ]);

        StoreUser::updateById((int) $user['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        self::$activeSession = true;

        return true;
    }

    /**
     * Comprueba que la sesión guardada sigue siendo válida ahora mismo.
     *
     * No basta con que exista una cookie: el usuario debe seguir activo y su
     * tienda debe estar en estado activo. Si deja de cumplirse, se cierra la
     * sesión y se devuelve false.
     */
    public static function validateSession(): bool
    {
        if (self::$activeSession !== null) {
            return self::$activeSession;
        }

        $user = self::user();
        if ($user === null || (int) ($user['id'] ?? 0) <= 0 || (int) ($user['store_id'] ?? 0) <= 0) {
            return self::$activeSession = false;
        }

        $valid = StoreUser::isActiveSession((int) $user['id'], (int) $user['store_id']);
        if (!$valid) {
            self::logout();
        }

        return self::$activeSession = $valid;
    }

    /** Simula el inicio de una peticion nueva en pruebas de integracion. */
    public static function resetSessionValidationCache(): void
    {
        self::$activeSession = null;
    }

    public static function check(): bool
    {
        $user = self::user();

        return $user !== null && !empty($user['id']);
    }

    public static function user(): ?array
    {
        $user = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($user) || !is_numeric($user['id'] ?? null)) {
            return null;
        }

        $user['id'] = (int) $user['id'];
        $user['store_id'] = is_numeric($user['store_id'] ?? null) ? (int) $user['store_id'] : 0;
        foreach (['name', 'email', 'role'] as $field) {
            if (isset($user[$field]) && !is_scalar($user[$field])) {
                unset($user[$field]);
            }
        }

        return $user;
    }

    public static function storeId(): ?int
    {
        $id = self::user()['store_id'] ?? null;
        return is_numeric($id) ? (int) $id : null;
    }

    /** Rol del usuario de la sesion (owner por defecto). */
    public static function role(): string
    {
        $role = self::user()['role'] ?? '';
        $role = is_scalar($role) ? (string) $role : '';

        return $role !== '' ? $role : 'owner';
    }

    /**
     * ¿Es un usuario de la plataforma (mayorista)?
     *
     * El rol vive en `mt_store_users.role` y solo hay dos niveles: `platform`
     * (puede administrar el menu de todas las tiendas) y el resto (`owner`),
     * que solo puede tocar lo suyo.
     */
    public static function isPlatform(): bool
    {
        return self::check() && self::role() === 'platform';
    }

    /** Roles admitidos en el panel. */
    public static function roles(): array
    {
        return [
            'owner'    => 'Dueno de la tienda',
            'staff'    => 'Empleado de la tienda',
            'platform' => 'Administrador de la plataforma',
        ];
    }

    public static function logout(): void
    {
        Session::forget(self::SESSION_KEY);
        Session::regenerate();
        self::$activeSession = null;
    }
}
