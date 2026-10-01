<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Modelo simple sobre PDO. Cada modelo concreto define su tabla.
 */
abstract class Model
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';

    public static function table(): string
    {
        return static::$table;
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            'SELECT * FROM `' . static::$table . '` WHERE `' . static::$primaryKey . '` = :id LIMIT 1',
            ['id' => $id]
        );
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        return Database::first(
            'SELECT * FROM `' . static::$table . '` WHERE `' . $column . '` = :v LIMIT 1',
            ['v' => $value]
        );
    }

    /** Listado con condiciones de igualdad simples. */
    public static function where(array $conditions = [], string $orderBy = 'id DESC', ?int $limit = null): array
    {
        $sql = 'SELECT * FROM `' . static::$table . '`';
        $params = [];

        if ($conditions !== []) {
            $parts = [];
            foreach ($conditions as $column => $value) {
                $parts[] = "`$column` = :$column";
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $parts);
        }

        $sql .= ' ORDER BY ' . $orderBy;
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        return Database::select($sql, $params);
    }

    public static function create(array $data): int
    {
        return Database::insert(static::$table, $data);
    }

    public static function updateById(int $id, array $data): int
    {
        return Database::update(static::$table, $id, $data, static::$primaryKey);
    }

    public static function deleteById(int $id): int
    {
        return Database::delete(static::$table, $id, static::$primaryKey);
    }

    public static function countWhere(array $conditions = []): int
    {
        $sql = 'SELECT COUNT(*) FROM `' . static::$table . '`';
        $params = [];
        if ($conditions !== []) {
            $parts = [];
            foreach ($conditions as $column => $value) {
                $parts[] = "`$column` = :$column";
                $params[$column] = $value;
            }
            $sql .= ' WHERE ' . implode(' AND ', $parts);
        }
        return (int) Database::scalar($sql, $params);
    }
}
