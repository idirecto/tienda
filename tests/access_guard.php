<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\Auth;
use Tienda\Core\Config;
use Tienda\Core\Database;
use Tienda\Core\TenantResolver;
use Tienda\Core\TenantUnavailableException;
use Tienda\Models\Store;
use Tienda\Models\StoreUser;

if (strtolower((string) config('app.env', 'local')) === 'production') {
    fwrite(STDERR, "No se ejecutan pruebas de escritura en produccion.\n");
    exit(2);
}

$fail = 0;
$ok = 0;
$check = static function (bool $condition, string $label) use (&$fail, &$ok): void {
    if ($condition) {
        $ok++;
        echo "  OK    {$label}\n";
    } else {
        $fail++;
        echo "  FALLO {$label}\n";
    }
};

$pdo = Database::pdo();
$slug = 'guard-' . bin2hex(random_bytes(4));
$email = 'guard-' . bin2hex(random_bytes(4)) . '@example.test';
$password = 'clave-de-prueba-1234';
$planId = Database::scalar('SELECT id FROM mt_plans WHERE active = 1 ORDER BY sort ASC, id ASC LIMIT 1');
$previousOverride = (bool) Config::get('tenant.allow_query_override', false);

$pdo->beginTransaction();
try {
    $storeId = Database::insert('mt_stores', [
        'slug'       => $slug,
        'name'       => 'Prueba de bloqueo',
        'legal_name' => 'Prueba de Bloqueo S.L.',
        'email'      => $email,
        'id_plan'    => $planId !== null ? (int) $planId : null,
        'status'     => Store::STATUS_PENDING,
        'theme'      => 'idirecto',
    ]);
    $userId = Database::insert('mt_store_users', [
        'store_id'      => $storeId,
        'name'          => 'Usuario de prueba',
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role'          => 'owner',
        'active'        => 1,
    ]);

    echo "== Login del panel ==\n";
    $check(!Auth::attempt($email, $password), 'una tienda pendiente no permite iniciar sesion');
    $check(!Auth::check(), 'no queda sesion de panel tras el intento pendiente');

    Database::execute('UPDATE mt_stores SET status = :status WHERE id = :id', [
        'status' => Store::STATUS_ACTIVE,
        'id'     => $storeId,
    ]);
    $check(Auth::attempt($email, $password), 'una tienda activa permite iniciar sesion');
    $check(Auth::validateSession(), 'la sesion activa se valida contra la tienda');
    $check(Auth::storeId() === $storeId, 'la sesion apunta a la tienda de prueba');

    Database::execute('UPDATE mt_stores SET status = :status WHERE id = :id', [
        'status' => Store::STATUS_SUSPENDED,
        'id'     => $storeId,
    ]);
    Auth::resetSessionValidationCache();
    $check(!Auth::validateSession(), 'suspender la tienda invalida la sesion existente');
    $check(!Auth::check(), 'la sesion se cierra al detectar la suspension');
    $check(!Auth::attempt($email, $password), 'una tienda suspendida no permite volver a entrar');

    echo "\n== Resolucion publica ==\n";
    Config::set('tenant.allow_query_override', true);
    $_GET['tienda'] = $slug;

    TenantResolver::resetCache();
    $pendingRejected = false;
    Database::execute('UPDATE mt_stores SET status = :status WHERE id = :id', [
        'status' => Store::STATUS_PENDING,
        'id'     => $storeId,
    ]);
    try {
        TenantResolver::resolve();
    } catch (TenantUnavailableException) {
        $pendingRejected = true;
    }
    $check($pendingRejected, 'el storefront rechaza una tienda pendiente');

    TenantResolver::resetCache();
    $suspendedRejected = false;
    Database::execute('UPDATE mt_stores SET status = :status WHERE id = :id', [
        'status' => Store::STATUS_SUSPENDED,
        'id'     => $storeId,
    ]);
    try {
        TenantResolver::resolve();
    } catch (TenantUnavailableException) {
        $suspendedRejected = true;
    }
    $check($suspendedRejected, 'el storefront rechaza una tienda suspendida');

    TenantResolver::resetCache();
    Database::execute('UPDATE mt_stores SET status = :status WHERE id = :id', [
        'status' => Store::STATUS_ACTIVE,
        'id'     => $storeId,
    ]);
    $tenant = TenantResolver::resolve();
    $check($tenant->id() === $storeId, 'el storefront resuelve la tienda cuando esta activa');
} catch (Throwable $e) {
    $check(false, 'prueba de acceso: ' . $e->getMessage());
} finally {
    Auth::logout();
    TenantResolver::resetCache();
    unset($_GET['tienda']);
    Config::set('tenant.allow_query_override', $previousOverride);
    $pdo->rollBack();
}

$check(Store::findBySlugWithPlan($slug) === null, 'la prueba no deja tienda');
$check(StoreUser::findBy('email', $email) === null, 'la prueba no deja usuario');

echo "\nResultado: {$ok} OK, {$fail} fallos\n";
exit($fail === 0 ? 0 : 1);
