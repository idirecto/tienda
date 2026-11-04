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

    public static function attempt(string $email, string $password): bool
    {
        $user = StoreUser::findBy('email', strtolower(trim($email)));

        if (!$user || (int) $user['active'] !== 1) {
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

        return true;
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
    }
}
