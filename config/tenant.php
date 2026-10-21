<?php

declare(strict_types=1);

use Tienda\Core\Env;
use Tienda\Core\TenantResolver;

/**
 * Configuracion multi-tenant.
 *
 * Los subdominios de tienda se derivan de `BASE_DOMAINS`:
 *   <slug>.ecom-era.com  ->  tienda con slug "slug"
 *
 * El host de `APP_URL` se anade automaticamente a la lista: asi, si el panel
 * esta en https://ecom-era.com y alguien olvida ponerlo en BASE_DOMAINS, los
 * subdominios siguen resolviendo en vez de caer a la tienda demo.
 */
$cleanDomain = static function (mixed $value): string {
    $value = trim((string) $value);
    $value = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $value) ?? '';
    $value = preg_split('~[/?#]~', $value, 2)[0] ?? '';
    $value = preg_replace('/^www\./', '', $value) ?? '';
    return TenantResolver::normalizeHost($value);
};

$baseDomains = array_values(array_filter(array_map(
    static fn (mixed $domain): string => $cleanDomain($domain),
    explode(',', (string) Env::get('BASE_DOMAINS', 'localhost'))
)));

$appHost = $cleanDomain(parse_url((string) Env::get('APP_URL', ''), PHP_URL_HOST) ?: '');
if ($appHost !== '' && !in_array($appHost, $baseDomains, true)) {
    $baseDomains[] = $appHost;
}

// Dominio canonico que se muestra en el panel y en las URLs publicas. Si esta
// vacio se usa el dominio base de la peticion actual (recomendado) y, en CLI,
// el primer dominio base configurado.
$publicDomain = $cleanDomain(Env::get(
    'TENANT_PUBLIC_DOMAIN',
    Env::get('PLATFORM_DOMAIN', '')
));

return [
    // Dominios de los que se derivan subdominios de tienda (separados por coma).
    'base_domains' => $baseDomains,

    // Dominio publico de los subdominios (opcional). En produccion:
    // TENANT_PUBLIC_DOMAIN=ecom-era.com. Vacio = se deduce de la peticion.
    'public_domain' => $publicDomain,

    // Tienda mostrada cuando no se puede resolver por hostname (demo/pruebas).
    'demo_store' => Env::get('DEMO_STORE', ''),

    // Permite forzar tienda por query string (?tienda=slug). Solo fuera de produccion.
    'allow_query_override' => Env::get('APP_ENV', 'local') !== 'production',

    // Datos de la plataforma para las instrucciones DNS.
    'platform_ip'    => Env::get('PLATFORM_IP', ''),
    'platform_cname' => Env::get('PLATFORM_CNAME', 'stores.idirecto.es'),
];
