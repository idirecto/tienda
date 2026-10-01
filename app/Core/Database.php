<?php

declare(strict_types=1);

namespace Tienda\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Capa de acceso a datos sobre PDO.
 *
 * La conexion es CONFIGURABLE via .env (DB_HOST, DB_PORT, DB_NAME, DB_USER,
 * DB_PASS, DB_CHARSET) y se resuelve en config/database.php.
 *
 * Se usa consultas preparadas en todos los metodos para evitar inyeccion SQL.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) Config::get('database.host', 'localhost');
        $port = (int) Config::get('database.port', 3306);
        $name = (string) Config::get('database.name', '');
        $user = (string) Config::get('database.user', '');
        $pass = (string) Config::get('database.pass', '');
        $charset = (string) Config::get('database.charset', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException(
                'No se pudo conectar a la base de datos. Revisa la configuracion (.env). '
                . $e->getMessage(),
                0,
                $e
            );
        }

        return self::$pdo;
    }

    /** Devuelve todas las filas de una consulta. */
    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Devuelve la primera fila o null. */
    public static function first(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Devuelve un valor escalar de la primera fila/columna. */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $value = $stmt->fetchColumn();
        return $value === false ? null : $value;
    }

    /** Ejecuta una sentencia y devuelve filas afectadas. */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Inserta y devuelve el id generado. */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $fields = implode(', ', array_map(static fn ($c) => "`$c`", $columns));
        $placeholders = implode(', ', array_map(static fn ($c) => ":$c", $columns));

        $sql = "INSERT INTO `$table` ($fields) VALUES ($placeholders)";
        self::execute($sql, $data);

        return (int) self::pdo()->lastInsertId();
    }

    /** Actualiza por id y devuelve filas afectadas. */
    public static function update(string $table, int $id, array $data, string $pk = 'id'): int
    {
        if ($data === []) {
            return 0;
        }
        $sets = implode(', ', array_map(static fn ($c) => "`$c` = :$c", array_keys($data)));
        $sql = "UPDATE `$table` SET $sets WHERE `$pk` = :__pk";
        $data['__pk'] = $id;
        return self::execute($sql, $data);
    }

    public static function delete(string $table, int $id, string $pk = 'id'): int
    {
        return self::execute("DELETE FROM `$table` WHERE `$pk` = :__id", ['__id' => $id]);
    }

    /** Ejecuta un callable dentro de una transaccion. */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Comprueba si una tabla existe (util para avisar si falta migrar). */
    public static function tableExists(string $table): bool
    {
        $value = self::scalar(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = :t',
            ['t' => $table]
        );
        return (int) $value > 0;
    }
}
