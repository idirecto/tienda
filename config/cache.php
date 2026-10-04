<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Cache de datos de la aplicacion (no es cache de pagina).
 *
 * Sustituye a las dos caches en fichero que tenian Catalog y Menu por su
 * cuenta, y anade lo que faltaba: driver intercambiable, bloqueo para que una
 * clave caducada no la recalculen cien peticiones a la vez y limpieza de los
 * ficheros que ya no se usan.
 *
 *   driver = "auto" -> APCu si el servidor la tiene, si no fichero.
 *   driver = "apcu" -> fuerza APCu (si falta, cae a fichero).
 *   driver = "file" -> siempre fichero (util en local y para depurar).
 *
 * APCu es memoria compartida del propio servidor: no necesita instalar Redis ni
 * ningun servicio. El dia que haga falta repartir carga entre varios servidores
 * o invalidar por etiquetas, el mismo contrato admite un driver Redis sin tocar
 * los modelos.
 */
return [
    'driver' => (string) Env::get('CACHE_DRIVER', 'auto'),

    // Prefijo de las claves cuando el driver es APCu (evita chocar con otras
    // aplicaciones que compartan el mismo pool de APCu).
    'prefix' => (string) Env::get('CACHE_PREFIX', 'tienda:'),

    // Carpeta del driver de fichero (la que ya usa el proyecto).
    'path' => rtrim((string) Env::get('CACHE_PATH', TIENDA_BASE . '/storage/cache'), '/'),

    // Milisegundos que una peticion espera a que otra termine de calcular una
    // clave antes de calcularla por su cuenta (evita la estampida).
    'lock_wait' => Env::int('CACHE_LOCK_WAIT', 5000),

    // Cada cuanto (segundos) se permite una pasada de limpieza.
    'gc_interval' => Env::int('CACHE_GC_INTERVAL', 300),

    // Antiguedad (segundos) a partir de la cual un fichero de cache se borra.
    // 2 dias: ningun TTL declarado llega tan lejos (el mayor es 1 dia), asi que
    // la limpieza nunca puede borrar una entrada todavia viva.
    'gc_max_age' => Env::int('CACHE_GC_MAX_AGE', 172800),

    // Tope de ficheros de cache. Si se supera, la limpieza borra los mas
    // antiguos hasta bajar de este numero: la clave de listado lleva el WHERE y
    // el numero de pagina, asi que sin tope creceria sin fin.
    'gc_max_files' => Env::int('CACHE_GC_MAX_FILES', 2000),
];
