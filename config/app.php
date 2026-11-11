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
    // IPs/CIDR de reverse proxies/CDN cuyo X-Forwarded-For es fiable.
    'trusted_proxies' => array_values(array_filter(
        array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', ''))),
        static fn (string $value): bool => $value !== ''
    )),
];
