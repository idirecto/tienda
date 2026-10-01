<?php

declare(strict_types=1);

/**
 * Ejecutor de migraciones y semillas.
 *
 *   php database/migrate.php           -> aplica migraciones pendientes
 *   php database/migrate.php --seed    -> aplica migraciones + semillas
 *   php database/migrate.php --status  -> muestra el estado
 *
 * El esquema se aplica sobre la BD configurada en .env y SOLO crea tablas con
 * prefijo mt_ (no toca las tablas del mayorista).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\Database;

$seed   = in_array('--seed', $argv, true);
$status = in_array('--status', $argv, true);

/** Ejecuta un fichero .sql completo (multiples sentencias). */
function runSqlFile(string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException("Fichero SQL vacio o ilegible: $path");
    }
    Database::pdo()->exec($sql);
}

function ensureMigrationsTable(): void
{
    Database::pdo()->exec(
        'CREATE TABLE IF NOT EXISTS mt_migrations (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            filename VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_mt_migrations_filename (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function appliedMigrations(): array
{
    $rows = Database::select('SELECT filename FROM mt_migrations');
    return array_column($rows, 'filename');
}

echo "==============================================================\n";
echo " TIENDA - migraciones\n";
echo "==============================================================\n";
echo ' BD: ' . config('database.name') . ' @ ' . config('database.host') . "\n\n";

ensureMigrationsTable();

$migrationDir = TIENDA_BASE . '/database/migrations';
$files = glob($migrationDir . '/*.sql') ?: [];
sort($files);

$applied = appliedMigrations();

if ($status) {
    echo "Migraciones encontradas: " . count($files) . "\n";
    foreach ($files as $file) {
        $name = basename($file);
        echo (in_array($name, $applied, true) ? '  [aplicada] ' : '  [pendiente] ') . $name . "\n";
    }
    exit(0);
}

$pending = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "Aplicando: $name ... ";
    try {
        // Nota: no se usa transaccion porque en MySQL las sentencias DDL (CREATE
        // TABLE) provocan un commit implicito. Los ficheros son idempotentes.
        runSqlFile($file);
        Database::execute('INSERT INTO mt_migrations (filename) VALUES (:f)', ['f' => $name]);
        echo "OK\n";
        $pending++;
    } catch (Throwable $e) {
        echo "ERROR\n";
        fwrite(STDERR, '  -> ' . $e->getMessage() . "\n");
        exit(1);
    }
}

echo $pending === 0
    ? "No hay migraciones pendientes.\n"
    : "Aplicadas $pending migracion(es).\n";

// -----------------------------------------------------------------------------
// Semillas
// -----------------------------------------------------------------------------
if ($seed) {
    echo "\nAplicando semillas...\n";
    $seedFiles = glob(TIENDA_BASE . '/database/seeds/*.sql') ?: [];
    sort($seedFiles);
    foreach ($seedFiles as $file) {
        echo '  ' . basename($file) . ' ... ';
        try {
            runSqlFile($file);
            echo "OK\n";
        } catch (Throwable $e) {
            echo "ERROR\n";
            fwrite(STDERR, '  -> ' . $e->getMessage() . "\n");
            exit(1);
        }
    }
    echo "Semillas aplicadas.\n";
}

echo "\nListo.\n";
