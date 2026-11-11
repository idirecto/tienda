<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\Config;
use Tienda\Core\Favicon;
use Tienda\Core\PanelUrl;
use Tienda\Core\PlatformLanding;
use Tienda\Core\PlatformLandingException;
use Tienda\Core\Tenant;
use Tienda\Core\TenantResolver;
use Tienda\Core\TenantUnavailableException;
use Tienda\Models\Store;

$fail = 0;
$ok = 0;
$check = static function (bool $condition, string $label) use (&$fail, &$ok): void {
    if ($condition) {
        $ok++;
        echo "  OK    {$label}\n";
    } else {
        $fail++;
        echo "  FALLO {$label}\n";
    }
};

$base = TenantResolver::getBaseDomains()[0] ?? 'local.tienda';
$store = Store::firstActiveWithPlan();
$previousOverride = (bool) Config::get('tenant.allow_query_override', false);
Config::set('tenant.allow_query_override', false);
$_GET = [];

$resolve = static function (string $host, string $path) use ($base): array {
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['REQUEST_URI'] = $path;
    TenantResolver::resetCache();

    try {
        return ['tenant' => TenantResolver::resolve($path), 'error' => null];
    } catch (Throwable $e) {
        return ['tenant' => null, 'error' => $e];
    }
};

echo "== Portada de plataforma ==\n";
$landing = $resolve($base, '/');
$check($landing['error'] instanceof PlatformLandingException, 'el dominio principal no resuelve ninguna tienda');
$check(PlatformLanding::isApexHost($base) && PlatformLanding::isApexHost('www.' . $base), 'apex y www se reconocen como plataforma');
$check(!PlatformLanding::isApexHost(($store['slug'] ?? 'tienda') . '.' . $base), 'un subdominio de tienda no es el apex');

$html = PlatformLanding::html();
$check(str_contains($html, 'IDIRECTO'), 'la portada muestra IDIRECTO');
$check(str_contains($html, 'Servicio para las tiendas de IDIRECTO'), 'la portada aclara que es un servicio para tiendas de IDIRECTO');
$check(str_contains($html, 'no la tienda online del mayorista'), 'la portada distingue el servicio del catálogo mayorista');
$check(str_contains($html, 'Crear mi tienda') && str_contains($html, '/registro'), 'la portada enlaza al registro');
$check(str_contains($html, '976 910 100') && str_contains($html, 'ventas@i-directo.com'), 'la portada usa los datos de contacto de idirecto');
$check(str_contains($html, 'idirecto-logo-head.png') && str_contains($html, 'idirecto-favicon.ico'), 'la portada usa logo y favicon de idirecto');
$check(!str_contains($html, 'XGRABU') && !str_contains($html, 'ServiTecni') && !str_contains($html, 'Informatica San Jose'), 'la portada no muestra nombres de tiendas');

$_SERVER['HTTP_HOST'] = $base;
$_SERVER['REQUEST_URI'] = '/';
$_GET = [];
$check(PlatformLanding::isLandingRequest(), 'la portada del apex no inicia sesion/cookie');
Config::set('tenant.allow_query_override', true);
$_GET['tienda'] = (string) ($store['slug'] ?? 'xgrabu');
$check(!PlatformLanding::isLandingRequest(), '?tienda=slug en desarrollo conserva la sesion de la tienda');
Config::set('tenant.allow_query_override', false);
$_GET = [];

echo "\n== Rutas de plataforma ==\n";
$registro = $resolve($base, '/registro');
$check($registro['error'] === null && $registro['tenant'] instanceof Tenant && $registro['tenant']->isPlatform(), '/registro funciona en el dominio principal');
$login = $resolve($base, '/panel/login');
$check($login['error'] === null && $login['tenant'] instanceof Tenant && $login['tenant']->isPlatform(), '/panel/login funciona en el dominio principal');
$catalogo = $resolve($base, '/catalogo');
$check($catalogo['error'] instanceof TenantUnavailableException, '/catalogo no muestra una tienda en el dominio principal');
$inexistente = $resolve('no-existe.local.tienda', '/');
$check($inexistente['error'] instanceof PlatformLandingException, 'un subdominio inexistente muestra la portada, no otra tienda');
$desconocido = $resolve('host-desconocido.example', '/');
$check($desconocido['error'] instanceof PlatformLandingException, 'un host desconocido no cae a la primera tienda activa');

if ($store !== null) {
    echo "\n== Tiendas de subdominio ==\n";
    $storeResult = $resolve((string) $store['slug'] . '.' . $base, '/');
    $check(
        $storeResult['error'] === null
            && $storeResult['tenant'] instanceof Tenant
            && $storeResult['tenant']->id() === (int) $store['id'],
        'el subdominio de una tienda sigue resolviendo su tienda'
    );

    $panelUrl = PanelUrl::forUser(['store_id' => (int) $store['id']]);
    $check(
        str_contains($panelUrl, (string) $store['slug'] . '.') && str_ends_with($panelUrl, '/panel'),
        'el login del apex lleva al panel del subdominio de la tienda'
    );
}

echo "\n== Favicon de plataforma ==\n";
$platformFavicon = Favicon::linkTags(Tenant::platform());
$check(str_contains($platformFavicon, 'idirecto-favicon.ico'), 'la plataforma usa el favicon de IDIRECTO');
$check(!str_contains($platformFavicon, 'favicon-valduran'), 'la plataforma no usa el favicon de Valduran');

Config::set('tenant.allow_query_override', $previousOverride);
unset($_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI']);

echo "\nResultado: {$ok} OK, {$fail} fallos\n";
exit($fail === 0 ? 0 : 1);
