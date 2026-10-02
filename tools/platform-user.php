<?php

declare(strict_types=1);

/**
 * Crea (o actualiza) un usuario de PLATAFORMA del panel.
 *
 * El rol vive en `mt_store_users.role` ('owner' por defecto). Un usuario con
 * `role = 'platform'` entra en el panel de plataforma y puede administrar el
 * menu de todas las tiendas; el resto solo ve la suya.
 *
 *   php tools/platform-user.php                     -> lista los usuarios
 *   php tools/platform-user.php <email> <clave>     -> crea/actualiza el usuario
 *   php tools/platform-user.php <email> <clave> <store_id>
 *
 * Escribe SOLO en las tablas propias del proyecto (`mt_store_users`).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\Database;

if (!Database::tableExists('mt_store_users')) {
    fwrite(STDERR, "Falta la tabla mt_store_users (migracion 001).\n");
    exit(1);
}

$email = trim((string) ($argv[1] ?? ''));
$password = (string) ($argv[2] ?? '');
$storeId = (int) ($argv[3] ?? 0);

if ($email === '' || $password === '') {
    echo "Usuarios del panel:\n";
    foreach (Database::select('SELECT id, store_id, name, email, role, active FROM mt_store_users ORDER BY id') as $user) {
        printf(
            "  #%d  tienda=%-3s  %-28s  rol=%-9s  activo=%s\n",
            (int) $user['id'],
            (string) $user['store_id'],
            (string) $user['email'],
            (string) $user['role'],
            (int) $user['active'] === 1 ? 'si' : 'no'
        );
    }
    echo "\nUso: php tools/platform-user.php <email> <clave> [store_id]\n";
    exit(0);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "El email no es valido.\n");
    exit(1);
}
if (strlen($password) < 8) {
    fwrite(STDERR, "La clave tiene que tener al menos 8 caracteres.\n");
    exit(1);
}

// Tienda a la que queda ligado el usuario (la columna es NOT NULL). Da igual
// cual sea: el rol es lo que le da acceso a todas.
if ($storeId <= 0) {
    $storeId = (int) Database::scalar('SELECT id FROM mt_stores ORDER BY id ASC LIMIT 1');
}
if ($storeId <= 0) {
    fwrite(STDERR, "No hay ninguna tienda en mt_stores: crea una antes.\n");
    exit(1);
}

$existing = Database::first('SELECT id FROM mt_store_users WHERE email = :email LIMIT 1', ['email' => strtolower($email)]);
$data = [
    'store_id'      => $storeId,
    'name'          => 'Administrador de plataforma',
    'email'         => strtolower($email),
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'role'          => 'platform',
    'active'        => 1,
];

if ($existing !== null) {
    Database::update('mt_store_users', (int) $existing['id'], $data);
    echo "Usuario actualizado como plataforma: {$email} (id " . (int) $existing['id'] . ").\n";
} else {
    $id = Database::insert('mt_store_users', $data + ['created_at' => date('Y-m-d H:i:s')]);
    echo "Usuario de plataforma creado: {$email} (id {$id}).\n";
}

echo "Entra en /panel/login con ese email y clave; el menu de plataforma esta en «Menu plataforma».\n";
