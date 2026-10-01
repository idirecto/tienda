<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Configuracion multi-tenant.
 */
return [
    // Dominios de los que se derivan subdominios de tienda (separados por coma).
    'base_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) Env::get('BASE_DOMAINS', 'localhost'))
    ))),

    // Tienda mostrada cuando no se puede resolver por hostname (demo/pruebas).
    'demo_store' => Env::get('DEMO_STORE', ''),

    // Permite forzar tienda por query string (?tienda=slug). Solo fuera de produccion.
    'allow_query_override' => Env::get('APP_ENV', 'local') !== 'production',

    // Datos de la plataforma para las instrucciones DNS.
    'platform_ip'    => Env::get('PLATFORM_IP', ''),
    'platform_cname' => Env::get('PLATFORM_CNAME', 'stores.idirecto.es'),
];
