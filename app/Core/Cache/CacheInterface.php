<?php

declare(strict_types=1);

namespace Tienda\Core\Cache;

/**
 * Contrato minimo de un almacen de cache.
 *
 * Es deliberadamente pequeno (tipo PSR-16, pero sin traer la dependencia) para
 * que cualquier driver nuevo -fichero, APCu, Redis o el que venga- encaje sin
 * tocar a quien lo usa.
 */
interface CacheInterface
{
    /**
     * Devuelve el valor guardado o `$default` si no existe o ya ha caducado.
     */
    public function get(string $key, mixed $default = null): mixed;

    /** Guarda un valor durante `$ttl` segundos. */
    public function set(string $key, mixed $value, int $ttl): void;

    /** Borra una clave. Devuelve true si existia. */
    public function delete(string $key): bool;

    /** Vacia todo el almacen. */
    public function flush(): void;

    /**
     * Borra todas las claves que encajen con un patron tipo glob (`*`).
     * Devuelve cuantas se borraron.
     */
    public function forgetPattern(string $pattern): int;

    /** Nombre del driver (`file`, `apcu`...), para logs y comprobaciones. */
    public function name(): string;
}
