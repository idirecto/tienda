<?php

declare(strict_types=1);

use Tienda\Core\Env;

return [
    'name'     => Env::get('APP_NAME', 'Tienda'),
    'env'      => Env::get('APP_ENV', 'local'),
    'debug'    => Env::bool('APP_DEBUG', false),
    'url'      => rtrim((string) Env::get('APP_URL', ''), '/'),
    'timezone' => Env::get('APP_TIMEZONE', 'Europe/Madrid'),
    'locale'   => Env::get('APP_LOCALE', 'es'),
    'key'      => Env::get('APP_KEY', ''),
];
