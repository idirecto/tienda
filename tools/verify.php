<?php

declare(strict_types=1);

/**
 * Comprobacion automatica del proyecto.
 *
 *   php tools/verify.php
 *
 * Verifica configuracion, esquema, resolver de tenants, cuotas de plan,
 * almacenamiento y DNS (sin red).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\Database;
use Tienda\Core\Dns;
use Tienda\Core\Media\ImageOptimizer;
use Tienda\Core\Media\MediaRules;
use Tienda\Core\Server;
use Tienda\Core\Storage\LocalStorage;
use Tienda\Core\Storage\S3Storage;
use Tienda\Core\Storage\StorageKey;
use Tienda\Core\Storage\StorageManager;
use Tienda\Core\Tenant;
use Tienda\Core\TenantResolver;
use Tienda\Models\Catalog;
use Tienda\Models\Plan;
use Tienda\Models\Store;
use Tienda\Models\Theme;

$fail = 0;
$ok = 0;

function check(bool $cond, string $msg): void
{
    global $fail, $ok;
    if ($cond) { $ok++; echo "  OK    $msg\n"; }
    else { $fail++; echo "  FALLO $msg\n"; }
}

echo "==============================================================\n";
echo " TIENDA - verificacion\n";
echo "==============================================================\n";

echo "\n== Configuracion ==\n";
echo '  Driver de almacenamiento: ' . config('storage.driver') . "\n";
echo '  Base de datos: ' . config('database.name') . ' @ ' . config('database.host') . "\n";
foreach (TenantResolver::getBaseDomains() as $d) { echo "  Dominio base: $d\n"; }

echo "\n== Conexion y esquema ==\n";
try {
    Database::scalar('SELECT 1');
    check(true, 'conexion a la base de datos');
} catch (\Throwable $e) {
    check(false, 'conexion a la base de datos: ' . $e->getMessage());
}

$tables = ['mt_plans', 'mt_themes', 'mt_stores', 'mt_store_users', 'mt_media',
    'mt_banners', 'mt_notices', 'mt_own_products', 'mt_domains', 'mt_dns_log',
    'mt_content_blocks', 'mt_settings', 'mt_migrations'];

$missing = [];
try {
    foreach ($tables as $t) {
        if (!Database::tableExists($t)) { $missing[] = $t; }
    }
    check($missing === [], 'tablas del proyecto creadas' . ($missing ? ' (faltan: ' . implode(', ', $missing) . ')' : ''));
} catch (\Throwable $e) {
    check(false, 'comprobacion de tablas: ' . $e->getMessage());
}

echo "\n== Datos base ==\n";
try {
    check(count(Plan::active()) >= 3, 'planes activos (' . count(Plan::active()) . ')');
    check(count(Theme::active()) >= 3, 'plantillas activas (' . count(Theme::active()) . ')');
    $stores = Store::allWithPlan();
    check(count($stores) >= 1, 'tiendas registradas (' . count($stores) . ')');
} catch (\Throwable $e) {
    check(false, 'datos base: ' . $e->getMessage());
}

echo "\n== Resolucion de tenant (unitario) ==\n";
check(TenantResolver::normalizeHost('MiTienda.COM:443') === 'mitienda.com', 'normaliza host');
check(TenantResolver::extractSubdomain('kartutx.idirecto.es', ['idirecto.es']) === 'kartutx', 'extrae subdominio');
check(TenantResolver::extractSubdomain('idirecto.es', ['idirecto.es']) === null, 'dominio raiz no es tenant');
check(TenantResolver::extractSubdomain('mitienda.com', ['idirecto.es']) === null, 'dominio externo no es subdominio');
check(TenantResolver::extractSubdomain('www.idirecto.es', ['idirecto.es']) === null, 'www excluido');

echo "\n== Cuotas de plan ==\n";
$basico = new Tenant(['own_products_quota' => 0]);
$medio = new Tenant(['own_products_quota' => 50]);
$premium = new Tenant(['own_products_quota' => -1]);
check($basico->canAddOwnProduct(0) === false, 'basico: 0 productos propios');
check($medio->canAddOwnProduct(49) === true, 'medio: permite hasta 50');
check($medio->canAddOwnProduct(50) === false, 'medio: bloquea en 50');
check($premium->quotaIsUnlimited() === true, 'premium: ilimitado');
check($premium->canAddOwnProduct(9999) === true, 'premium: sin limite');

echo "\n== Almacenamiento ==\n";
check(StorageManager::driver() instanceof LocalStorage || StorageManager::driver() instanceof S3Storage, 'driver de almacenamiento operativo (' . StorageManager::driver()->driver() . ')');

// Clave unica para S3 y local: {prefijo}/{tienda}_{tipo}/{id}_{tipo}_{fecha}-{aleatorio}.{ext}
$key = StorageKey::build(7, 'banners', 'webp');
echo '  Clave: ' . $key . "\n";
check(
    (bool) preg_match('#^tenants/tienda_banners/7_banners_\d{8}-\d{6}-[0-9a-f]{8}\.webp$#', $key),
    'clave con carpeta tienda_tipo y el id en el nombre'
);
$keyBad = StorageKey::build(7, '../../etc', 'png');
check(
    !str_contains($keyBad, '..')
        && (bool) preg_match('#^tenants/tienda_[a-z0-9_\-]+/[0-9]+_[a-z0-9_\-]+_\d{8}-\d{6}-[0-9a-f]{8}\.png$#', $keyBad),
    'clave saneada (sin path traversal): ' . $keyBad
);
$keyS3 = (new S3Storage())->buildKey(12, 'productos', 'webp');
check(
    (bool) preg_match('#^tenants/tienda_productos/12_productos_\d{8}-\d{6}-[0-9a-f]{8}\.webp$#', $keyS3),
    'el driver S3 usa el mismo esquema: ' . $keyS3
);

// Optimizacion de imagenes: JPG grande -> WebP mas ligero y con el tamano limitado.
if (function_exists('imagecreatetruecolor')) {
    $jpg = sys_get_temp_dir() . '/tienda_verify_' . getmypid() . '.jpg';
    $imagen = imagecreatetruecolor(3000, 1200);
    for ($x = 0; $x < 3000; $x++) {
        imageline($imagen, $x, 0, $x, 1199, imagecolorallocate($imagen, (int) ($x / 3000 * 255), 120, 255 - (int) ($x / 3000 * 255)));
    }
    imagejpeg($imagen, $jpg, 95);
    imagedestroy($imagen);

    $opt = ImageOptimizer::optimize(['tmp_name' => $jpg, 'size' => (int) filesize($jpg)]);
    check(
        $opt['mime'] === 'image/webp' && $opt['bytes'] < $opt['original_bytes'] && (int) $opt['width'] <= 2560,
        'JPG -> WebP mas ligero y limitado (' . ImageOptimizer::mime($jpg) . ' ' . $opt['original_bytes']
            . ' B -> webp ' . $opt['bytes'] . ' B, ' . (int) $opt['width'] . 'px)'
    );
    if (!empty($opt['temporary'])) {
        @unlink((string) $opt['path']);
    }
    @unlink($jpg);

    // SVG: se respeta tal cual (es vectorial, convertirlo no aporta nada).
    $svg = sys_get_temp_dir() . '/tienda_verify_' . getmypid() . '.svg';
    file_put_contents($svg, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 4"><rect width="4" height="4"/></svg>');
    $optSvg = ImageOptimizer::optimize(['tmp_name' => $svg, 'size' => (int) filesize($svg)]);
    check(
        $optSvg['mime'] === 'image/svg+xml' && $optSvg['optimized'] === false && $optSvg['path'] === $svg,
        'SVG sin reconvertir'
    );
    @unlink($svg);
}

// Limites por tipo: los banners admiten mas peso (8 MB) y se recomprimen con
// mejor calidad que el resto, sin tocar el limite general.
check(
    MediaRules::maxBytes('banners') >= 8 * 1024 * 1024
        && MediaRules::maxBytes('banners') > MediaRules::maxBytes('productos')
        && MediaRules::quality('banners') >= MediaRules::quality('productos'),
    'limites por tipo: banners ' . MediaRules::formatoBytes(MediaRules::maxBytes('banners'))
        . ' calidad ' . MediaRules::quality('banners')
        . ' - productos ' . MediaRules::formatoBytes(MediaRules::maxBytes('productos'))
        . ' calidad ' . MediaRules::quality('productos')
);

echo "\n== Servidor web (Apache / nginx) ==\n";
echo '  Detectado: ' . Server::label() . ' · /public -> ' . Server::publicPath() . "\n";
$softwareOk = match (PHP_SAPI) {
    'cli'        => Server::is(Server::CLI),
    'cli-server' => Server::is(Server::CLI_SERVER),
    default      => Server::isNginx() || Server::isApache() || Server::software() !== Server::UNKNOWN,
};
check($softwareOk, 'deteccion del servidor web (' . Server::label() . ')');
check(str_starts_with(Server::publicPath(), '/'), 'ruta publica de /public (' . Server::publicPath() . ')');

// Esquema real detras de un proxy/CDN: se simulan las cabeceras con el
// entorno limpio (sin HTTPS ni REQUEST_SCHEME) y se restaura al terminar.
$previo = [
    'HTTPS'                   => $_SERVER['HTTPS'] ?? null,
    'REQUEST_SCHEME'          => $_SERVER['REQUEST_SCHEME'] ?? null,
    'HTTP_X_FORWARDED_SSL'    => $_SERVER['HTTP_X_FORWARDED_SSL'] ?? null,
    'HTTP_X_FORWARDED_PROTO'  => $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null,
];
unset($_SERVER['HTTPS'], $_SERVER['REQUEST_SCHEME'], $_SERVER['HTTP_X_FORWARDED_SSL']);
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
$secureDetectado = Server::isSecure();
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'http';
$insecureDetectado = Server::isSecure();
foreach ($previo as $clave => $valor) {
    if ($valor === null) {
        unset($_SERVER[$clave]);
    } else {
        $_SERVER[$clave] = $valor;
    }
}
check($secureDetectado === true && $insecureDetectado === false, 'esquema real segun X-Forwarded-Proto (https/http)');

echo "\n== DNS (comprobacion logica) ==\n";
$fake = static function (string $type, string $host): array {
    return match (true) {
        $type === 'A' && $host === 'tienda.com' => ['203.0.113.9'],
        $type === 'CNAME' && $host === 'tienda.com' => ['stores.idirecto.es'],
        default => [],
    };
};
$good = Dns::evaluate('tienda.com', '203.0.113.9', 'stores.idirecto.es', Dns::METHOD_A, $fake);
check($good['status'] === Dns::VERIFIED, 'A correcto => verificado');
$bad = Dns::evaluate('tienda.com', '198.51.100.1', 'stores.idirecto.es', Dns::METHOD_A, $fake);
check($bad['status'] === Dns::ERROR, 'A incorrecto => error');
$none = Dns::evaluate('nada.com', '203.0.113.9', 'stores.idirecto.es', Dns::METHOD_A, static fn () => []);
check($none['status'] === Dns::ERROR, 'sin registros => error');
$ins = Dns::instructions('tienda.com');
check(isset($ins['records'][0]['type']) && $ins['records'][0]['type'] === 'A', 'instrucciones DNS generadas');

echo "\n== Catalogo central ==\n";
// El catalogo necesita TODAS estas tablas del mayorista; si falta alguna se
// trata como no disponible (listados vacios) en lugar de romper la web.
$tablasCatalogo = ['productos', 'stock', 'almacenes', 'precios', 'marcas', 'categorias', 'subcategorias'];
$presentes = count(array_filter($tablasCatalogo, static fn (string $t): bool => Database::tableExists($t)));
echo '  Tablas del catalogo: ' . $presentes . '/' . count($tablasCatalogo) . "\n";
check(Catalog::isAvailable() === ($presentes === count($tablasCatalogo)), 'deteccion del catalogo central (' . $presentes . '/' . count($tablasCatalogo) . ' tablas)');

if (Catalog::isAvailable()) {
    $page = Catalog::paginate(1, 4);
    check(count($page['items']) <= 4, 'paginacion de catalogo (' . $page['total'] . ' productos)');

    // Menu de categorias -> subcategorias (mismo arbol que usa el storefront).
    $menu = Catalog::menuTree();
    check($menu !== [] && isset($menu[0]['subcategories'][0]['id']), 'menu de categorias (' . count($menu) . ' categorias)');

    if ($menu !== []) {
        $catId = (int) $menu[0]['id'];
        $subId = (int) $menu[0]['subcategories'][0]['id'];
        $filtrado = Catalog::paginate(1, 4, null, $catId, $subId);
        $soloSubcategoria = true;
        foreach ($filtrado['items'] as $item) {
            if ((int) $item['id_subcategoria'] !== $subId) {
                $soloSubcategoria = false;
                break;
            }
        }
        check($filtrado['total'] > 0 && $soloSubcategoria, 'filtro por subcategoria (' . $filtrado['total'] . ' productos)');
    }
}

echo "\n==============================================================\n";
if ($fail === 0) {
    echo " RESULTADO: TODO OK ($ok comprobaciones)\n";
    exit(0);
}
echo " RESULTADO: $fail fallo(s) de " . ($ok + $fail) . " comprobaciones\n";
exit(1);
