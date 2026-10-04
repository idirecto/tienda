<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Core\Cache\ApcuCache;
use Tienda\Core\Cache\CacheInterface;
use Tienda\Core\Cache\FileCache;

/**
 * Fachada de cache de la aplicacion.
 *
 * Un unico punto de entrada para todos los datos que merece la pena guardar
 * (contadores, arboles, facetas...) con tres cosas que antes no habia:
 *
 *  1. **Driver intercambiable**: APCu si esta disponible, fichero si no. Quien
 *     cachea no sabe ni le importa donde acaba el dato.
 *  2. **Memo por peticion (L1)**: dentro de una misma peticion, pedir dos veces
 *     la misma clave no vuelve a leer del almacen.
 *  3. **Bloqueo por clave**: si una entrada caduca y llegan varias peticiones a
 *     la vez, solo una la recalcula; el resto espera el resultado. Antes, una
 *     clave cara caducada se recalculaba N veces en paralelo.
 *
 * Regla de oro de este proyecto: **cachear lo caro y estable, nunca el precio**.
 * El precio y el stock se resuelven siempre en vivo (ver `Catalog::featured()`).
 */
final class Cache
{
    private static ?CacheInterface $store = null;

    /** Valores ya leidos en esta peticion (L1). */
    private static array $memo = [];

    private static ?\stdClass $miss = null;

    /** Almacen configurado (se crea la primera vez que se usa). */
    public static function store(): CacheInterface
    {
        return self::$store ??= self::make();
    }

    /** Nombre del driver activo (`file`, `apcu`). */
    public static function driverName(): string
    {
        return self::store()->name();
    }

    /** La extension APCu esta cargada y activa en este proceso. */
    public static function apcuAvailable(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled() && function_exists('apcu_fetch');
    }

    /**
     * Valor guardado, o `$default` si no existe o ha caducado.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }

        $value = self::store()->get($key, self::miss());
        if ($value === self::miss()) {
            return $default;
        }

        self::$memo[$key] = $value;

        return $value;
    }

    /** Guarda un valor durante `$ttl` segundos. */
    public static function set(string $key, mixed $value, int $ttl): void
    {
        self::$memo[$key] = $value;
        self::store()->set($key, $value, $ttl);
    }

    /** Borra una clave (tambien de la memoria de esta peticion). */
    public static function delete(string $key): bool
    {
        unset(self::$memo[$key]);

        return self::store()->delete($key);
    }

    /** Borra todas las claves que encajen con un patron tipo glob (`*`). */
    public static function forgetPattern(string $pattern): int
    {
        self::$memo = [];

        return self::store()->forgetPattern($pattern);
    }

    /** Vacia toda la cache. */
    public static function flush(): void
    {
        self::$memo = [];
        self::store()->flush();
    }

    /**
     * Devuelve el valor cacheado y, si no esta, lo calcula y lo guarda.
     *
     * El calculo va protegido con un bloqueo por clave: si otra peticion ya lo
     * esta haciendo, esta espera a que termine en vez de repetir el trabajo.
     */
    public static function remember(string $key, int $ttl, callable $compute): mixed
    {
        $value = self::get($key, self::miss());
        if ($value !== self::miss()) {
            return $value;
        }

        return self::computeLocked($key, $ttl, $compute);
    }

    /** Limpieza de la cache (solo el driver de fichero la necesita). */
    public static function gc(bool $force = false): int
    {
        $store = self::store();

        return $store instanceof FileCache ? $store->gc($force) : 0;
    }

    // -------------------------------------------------------------------------
    // INTERNOS
    // -------------------------------------------------------------------------

    private static function make(): CacheInterface
    {
        $driver = strtolower(trim((string) Config::get('cache.driver', 'auto')));

        if ($driver !== 'file' && self::apcuAvailable()) {
            return new ApcuCache((string) Config::get('cache.prefix', 'tienda:'));
        }

        return new FileCache(
            (string) Config::get('cache.path', TIENDA_BASE . '/storage/cache'),
            (int) Config::get('cache.gc_max_age', 172800),
            (int) Config::get('cache.gc_max_files', 2000),
            (int) Config::get('cache.gc_interval', 300)
        );
    }

    /**
     * Calcula el valor bajo bloqueo.
     *
     * Se intenta el bloqueo sin esperar; si lo tiene otra peticion, se espera a
     * que deje el valor en la cache (con un tope de `cache.lock_wait` ms). Si se
     * agota el tope se calcula igualmente: es preferible una respuesta lenta a
     * una respuesta que no llega.
     */
    private static function computeLocked(string $key, int $ttl, callable $compute): mixed
    {
        $dir = (string) Config::get('cache.path', TIENDA_BASE . '/storage/cache');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $fp = @fopen($dir . '/.lock_' . substr(sha1($key), 0, 24), 'c');
        if ($fp === false) {
            return self::storeAndReturn($key, $ttl, $compute);
        }

        if (!@flock($fp, LOCK_EX | LOCK_NB)) {
            @fclose($fp);
            $value = self::waitForValue($key);
            if ($value !== self::miss()) {
                return $value;
            }

            return self::storeAndReturn($key, $ttl, $compute);
        }

        try {
            // Doble comprobacion: puede haber terminado otra peticion entre el
            // primer `get` y la adquisicion del bloqueo.
            $value = self::store()->get($key, self::miss());
            if ($value !== self::miss()) {
                self::$memo[$key] = $value;

                return $value;
            }

            return self::storeAndReturn($key, $ttl, $compute);
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }

    private static function waitForValue(string $key): mixed
    {
        $deadline = microtime(true) + max(0, (int) Config::get('cache.lock_wait', 5000)) / 1000;
        while (microtime(true) < $deadline) {
            usleep(50000);
            $value = self::store()->get($key, self::miss());
            if ($value !== self::miss()) {
                self::$memo[$key] = $value;

                return $value;
            }
        }

        return self::miss();
    }

    private static function storeAndReturn(string $key, int $ttl, callable $compute): mixed
    {
        $value = $compute();
        self::set($key, $value, $ttl);

        return $value;
    }

    private static function miss(): \stdClass
    {
        return self::$miss ??= new \stdClass();
    }
}
