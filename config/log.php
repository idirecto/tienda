<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Registro de actividad de la aplicacion.
 *
 * Los logs se guardan en FICHEROS, un fichero por dia y por tienda:
 *
 *     storage/logs/<canal>/<AAAA-MM-DD>/<id-tienda>_<slug>.log
 *     storage/logs/compras/2026-10-08/1_idirecto-demo.log
 *     storage/logs/sistema/2026-10-08/_plataforma.log
 *
 * Cada linea es un JSON (una linea = un evento), asi que se puede leer con
 * `tail`, `grep` o `jq` sin abrir el panel, y sobrevive a una caida de la base
 * de datos (que es justo cuando mas falta hace el log).
 *
 * El panel de la tienda (`/panel/logs`) y `tools/logs.php` leen esos ficheros;
 * no hay tabla en la base de datos a proposito: el log nunca debe depender de
 * la base de datos que intenta diagnosticar.
 *
 * Nada de esto es cache de pagina: aqui solo se anota QUE ha pasado.
 */
return [
    // Interruptor general. Con false no se escribe nada (util para medir coste).
    'enabled' => Env::bool('LOG_ENABLED', true),

    // Nivel minimo que se guarda: debug|info|notice|warning|error|critical.
    // En produccion se recomienda `info`; para investigar un fallo, `debug`.
    'level' => (string) Env::get('LOG_LEVEL', 'info'),

    // Carpeta raiz de los logs (por defecto, la que ya usa el proyecto). Un
    // LOG_PATH vacio cae al valor por defecto: no puede quedar en la raiz.
    'path' => rtrim((string) (Env::get('LOG_PATH') ?: TIENDA_BASE . '/storage/logs'), '/\\'),

    // Dias que se conservan los ficheros. La limpieza la lanza el panel al
    // abrirse (`/panel/logs`), `tools/logs.php --gc` y, de vez en cuando, una
    // peticion normal (probabilidad baja, con marca de tiempo en `.gc`).
    'retention_days' => max(1, Env::int('LOG_RETENTION_DAYS', 30)),

    // Segundos minimos entre dos limpiezas automaticas.
    'gc_interval' => Env::int('LOG_GC_INTERVAL', 86400),

    // Tope de bytes que se leen de un fichero al consultarlo desde el panel o
    // la consola (se lee el final, que es lo interesante). Evita que un fichero
    // enorme tumbe el visor.
    'read_max_bytes' => Env::int('LOG_READ_MAX_BYTES', 5242880),

    // Tope de entradas que se reunen en una consulta (proteccion de memoria).
    'read_max_entries' => Env::int('LOG_READ_MAX_ENTRIES', 20000),

    // Canales conocidos: se usan para etiquetar y para el filtro del panel. Un
    // canal que no este en la lista se sigue guardando igual (sanitizado).
    'channels' => [
        'compras'  => 'Compras y carrito',
        'pedidos'  => 'Pedidos y envio a idirecto',
        'acceso'   => 'Entradas, registros y cuentas',
        'seguridad' => 'Seguridad (CSRF, intentos, permisos)',
        'panel'    => 'Panel de la tienda',
        'catalogo' => 'Catalogo',
        'sistema'  => 'Sistema (errores y avisos)',
    ],

    // Etiquetas de los niveles (mismo orden que PSR-3, de menos a mas grave).
    'levels' => [
        'debug'    => 'Depuracion',
        'info'     => 'Informacion',
        'notice'   => 'Aviso',
        'warning'  => 'Advertencia',
        'error'    => 'Error',
        'critical' => 'Critico',
    ],
];
