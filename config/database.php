<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Conexion a la base de datos central (configurable por entorno).
 */
return [
    'host'    => Env::get('DB_HOST', 'localhost'),
    'port'    => Env::int('DB_PORT', 3306),
    'name'    => Env::get('DB_NAME', 'idirecto_db'),
    'user'    => Env::get('DB_USER', 'root'),
    'pass'    => Env::get('DB_PASS', ''),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
];
