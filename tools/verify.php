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
use Tienda\Core\Appearance;
use Tienda\Core\Idirecto\Account;
use Tienda\Core\Idirecto\OrderGateway;
use Tienda\Core\Idirecto\Pricing;
use Tienda\Core\Media\ImageOptimizer;
use Tienda\Core\Media\MediaRules;
use Tienda\Core\Server;
use Tienda\Core\Specs;
use Tienda\Core\Storage\LocalStorage;
use Tienda\Core\Storage\S3Storage;
use Tienda\Core\Storage\StorageKey;
use Tienda\Core\Storage\StorageManager;
use Tienda\Core\Tenant;
use Tienda\Core\TenantResolver;
use Tienda\Models\Catalog;
use Tienda\Models\Order;
use Tienda\Models\OrderItem;
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
    'mt_content_blocks', 'mt_settings', 'mt_migrations', 'mt_orders', 'mt_order_items'];

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

    // Productos por pagina de los listados (CATALOG_PER_PAGE) y destacados de
    // portada (CATALOG_HOME_FEATURED): van por separado a proposito.
    $listado = Catalog::paginate(1);
    check(
        $listado['per_page'] === (int) config('catalog.per_page')
            && $listado['per_page'] > 4
            && count($listado['items']) === min($listado['per_page'], $listado['total']),
        'productos por pagina en los listados (' . $listado['per_page'] . ')'
    );
    $destacados = (int) config('catalog.home_featured', 12);
    check(
        $destacados >= 4 && $destacados <= 24 && count(Catalog::featured($destacados)) <= $destacados,
        'destacados de la portada (' . $destacados . ')'
    );

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

echo "\n== Sistema de diseno (design tokens) ==\n";
// Utilidades de color
check(Appearance::hex('#0AF') === '#00aaff' && Appearance::hex('#abc') === '#aabbcc', 'normaliza colores hexadecimales');
check(Appearance::hex('rojo') === null && Appearance::hex('#12345') === null, 'rechaza colores invalidos');
check(Appearance::mix('#000000', '#ffffff', 0.5) === '#808080', 'mezcla de colores');
check(Appearance::contrast('#ffffff') === '#0b0e13' && Appearance::contrast('#0a0e14') === '#ffffff', 'contraste de texto automatico');

// Tokens de una tienda de prueba
$tokenTenant = new Tenant([
    'name'             => 'Tienda tokens',
    'color_primary'    => '#0b5fff',
    'color_secondary'  => '#0f172a',
    'color_accent'     => '#14b8a6',
    'color_scheme'     => 'auto',
    'radius_scale'     => 'rounded',
    'font'             => 'grotesk',
    'theme_tokens'     => '{"light": {"bg": "#101010"}, "raw": {"--c-container-max": "1400px"}}',
]);
$tokens = Appearance::tokens($tokenTenant, 'light');
check(
    ($tokens['primary'] ?? '') === '#0b5fff'
        && ($tokens['accent'] ?? '') === '#14b8a6'
        && ($tokens['bg'] ?? '') === '#101010'
        && ($tokens['container-max'] ?? '') === '1400px',
    'tokens resueltos (marca + theme_tokens)'
);
check(($tokens['radius-md'] ?? '') === '16px', 'escala de radios de la tienda (' . ($tokens['radius-md'] ?? '?') . ')');
check(($tokens['primary-contrast'] ?? '') !== '' && ($tokens['font-head'] ?? '') !== '', 'tokens derivados (contraste y tipografia)');
$darkTokens = Appearance::tokens($tokenTenant, 'dark');
check(($darkTokens['bg'] ?? '') !== ($tokens['bg'] ?? ''), 'paleta oscura distinta de la clara');

$css = Appearance::css($tokenTenant);
check(
    str_contains($css, ':root{')
        && str_contains($css, ':root[data-color-scheme="dark"]')
        && str_contains($css, 'prefers-color-scheme:dark')
        && str_contains($css, '--c-primary:#0b5fff'),
    'CSS de tokens con modo claro, oscuro y automatico'
);
check(Appearance::scheme($tokenTenant) === 'auto' && Appearance::themeColor($tokenTenant) !== '', 'esquema y color de barra del navegador');
check(count(Appearance::presets()) >= 3 && Appearance::preset('caseking') !== null && Appearance::preset('no-existe') === null, 'presets de identidad (' . count(Appearance::presets()) . ')');

// Los tokens libres se imprimen dentro de un <style>: no pueden cerrar la etiqueta.
$evilTenant = new Tenant(['theme_tokens' => '{"raw": {"--c-x": "</style><script>alert(1)</script>"}, "bg": "#123456"}']);
$evilTokens = Appearance::tokens($evilTenant, 'light');
check(
    !str_contains(Appearance::css($evilTenant), '<script')
        && !isset($evilTokens['x'])
        && ($evilTokens['bg'] ?? '') === '#123456',
    'los tokens libres no pueden inyectar HTML'
);

// Columnas de diseno en mt_stores (migracion 002)
$designColumns = ['color_accent', 'color_bg', 'color_surface', 'color_text', 'color_border', 'color_scheme', 'radius_scale', 'theme_tokens', 'custom_css'];
$missingDesign = [];
foreach ($designColumns as $column) {
    try {
        Database::scalar("SELECT `$column` FROM mt_stores LIMIT 1");
    } catch (\Throwable $e) {
        $missingDesign[] = $column;
    }
}
check($missingDesign === [], 'columnas de diseno en mt_stores' . ($missingDesign ? ' (faltan: ' . implode(', ', $missingDesign) . ')' : ''));

echo "\n== Filtros avanzados (facets) ==\n";
$selection = Catalog::selectionFromQuery([
    'f'    => ['socket' => ['am5', 'inventado'], 'marca' => ['7']],
    'pmin' => '50',
    'pmax' => '400,50',
]);
check(
    ($selection['terms']['socket'] ?? []) === ['am5'] && ($selection['terms']['marca'] ?? []) === ['7'],
    'la seleccion de filtros se sanea contra la configuracion'
);
check($selection['price_min'] === 50.0 && $selection['price_max'] === 400.5, 'rango de precio parseado (coma decimal)');
check(count($selection['flat']) === 3, 'chips de filtros activos (' . count($selection['flat']) . ')');
check(
    Catalog::selectionFromQuery(['pmin' => '900', 'pmax' => '100'])['price_min'] === 100.0,
    'rango de precio invertido se ordena solo'
);

$sortsConPrecio = Catalog::sorts(true);
$sortsSinPrecio = Catalog::sorts(false);
check(
    isset($sortsConPrecio['precio-asc']) && !isset($sortsSinPrecio['precio-asc']) && isset($sortsSinPrecio['relevancia']),
    'orden por precio solo cuando el listado esta acotado'
);
check(Catalog::sortKey('inventado') === 'relevancia' && Catalog::sortKey('precio-asc', false) === 'relevancia', 'orden invalido cae a relevancia');

if (Catalog::isAvailable()) {
    // Facets declarados para una categoria real (placas base: socket, memoria...).
    $facets = Catalog::facets(9, 102);
    $claves = array_column($facets, 'key');
    check(in_array('socket', $claves, true) && in_array('precio', $claves, true), 'facets del contexto: ' . implode(', ', $claves));

    $precio = Catalog::priceBounds();
    check($precio['max'] > $precio['min'] && $precio['max'] > 0, 'rango de precios del catalogo (' . $precio['min'] . ' - ' . $precio['max'] . ')');

    // Filtro real por socket: todos los resultados deben mencionarlo.
    $filtrado = Catalog::paginate(1, 6, null, 9, 102, ['terms' => ['socket' => ['am5']]]);
    $soloAm5 = $filtrado['total'] > 0;
    foreach ($filtrado['items'] as $item) {
        $texto = mb_strtoupper((string) $item['nombre']);
        $specs = (string) Database::scalar(
            'SELECT caracteristicas FROM productos_ext WHERE id_producto = :id',
            ['id' => (int) $item['id']]
        );
        if (!str_contains($texto, 'AM5') && !str_contains(mb_strtoupper($specs), 'AM5')) {
            $soloAm5 = false;
            break;
        }
    }
    check($soloAm5, 'filtro por socket AM5 (' . $filtrado['total'] . ' productos)');

    // Filtro por precio.
    $porPrecio = Catalog::paginate(1, 6, null, 9, 102, [], 50.0, 200.0);
    $enRango = $porPrecio['total'] > 0;
    foreach ($porPrecio['items'] as $item) {
        $valor = $item['price_final'];
        if ($valor === null || $valor < 50 || $valor > 200) {
            $enRango = false;
            break;
        }
    }
    check($enRango, 'filtro por rango de precio (' . $porPrecio['total'] . ' productos)');

    // Las tarjetas traen especificaciones clave y etiqueta de stock.
    $conSpecs = 0;
    foreach ($filtrado['items'] as $item) {
        if (($item['specs'] ?? []) !== []) {
            $conSpecs++;
        }
    }
    check(
        $conSpecs > 0 && isset($filtrado['items'][0]['stock_band']) && isset($filtrado['items'][0]['stock_label']),
        'tarjetas con especificaciones (' . $conSpecs . '/' . count($filtrado['items']) . ') y etiqueta de stock'
    );

    // Accesos rapidos de la portada: resueltos contra el catalogo real.
    $accesos = Catalog::quickCategories('');
    check(
        count($accesos) >= 3 && $accesos[0]['total'] > 0 && str_contains($accesos[0]['url'], '/catalogo?'),
        'accesos rapidos de portada (' . count($accesos) . ')'
    );
}

echo "\n== Especificaciones para tarjeta ==\n";
$chips = Specs::quickHighlights('', 3, 'MSI GeForce RTX 5080 VENTUS 3X OC 16 GB GDDR7 PCI-E 5.0');
$etiquetas = array_column($chips, 'k');
check(
    in_array('Grafica', $etiquetas, true) && in_array('Capacidad', $etiquetas, true),
    'chips extraidos del nombre: ' . json_encode($chips, JSON_UNESCAPED_UNICODE)
);
$chipsPares = Specs::quickHighlights('Socket de procesador: Socket AM5, tipos de memoria compatibles: DDR5-SDRAM', 2, '');
check(
    ($chipsPares[0]['v'] ?? '') === 'AM5' && str_contains((string) ($chipsPares[1]['v'] ?? ''), 'DDR5'),
    'chips desde caracteristicas y sin prefijos redundantes: ' . json_encode($chipsPares, JSON_UNESCAPED_UNICODE)
);

echo "\n== Pedidos de la tienda ==\n";
// Enlace con el mayorista (migracion 003): cuenta y tarifa de cada tienda.
$storeColumns = ['id_tienda_idirecto', 'id_margen'];
$missingStore = [];
foreach ($storeColumns as $column) {
    try {
        Database::scalar("SELECT `$column` FROM mt_stores LIMIT 1");
    } catch (\Throwable $e) {
        $missingStore[] = $column;
    }
}
check($missingStore === [], 'cuenta del mayorista en mt_stores' . ($missingStore ? ' (faltan: ' . implode(', ', $missingStore) . ')' : ''));

check(
    count(Order::statuses()) === 7 && Order::statusLabel(Order::STATUS_INVOICED) === 'Facturado',
    'estados de pedido (' . implode(', ', array_values(Order::statuses())) . ')'
);
$grupos = Order::statusGroups();
check(
    isset($grupos['todos'], $grupos['activos'], $grupos['facturados'], $grupos['borrados'])
        && $grupos['todos']['statuses'] === null
        && in_array(Order::STATUS_INVOICED, $grupos['facturados']['statuses'], true),
    'pestanas del listado: ' . implode(', ', array_column($grupos, 'label'))
);
check(
    Order::statusesForGroup('facturados') === [Order::STATUS_INVOICED]
        && Order::statusesForGroup((string) Order::STATUS_DELETED) === [Order::STATUS_DELETED]
        && Order::statusesForGroup('inventado') === null
        && Order::statusesForGroup('todos') === null,
    'filtro por pestana y por estado concreto'
);
check(Order::normalizeStatus(99) === Order::STATUS_ACTIVE, 'estado invalido cae a activo');

// Normalizacion de las lineas del formulario (precio con coma, cantidad minima)
$normalizadas = OrderItem::normalizeLines([
    ['source' => 'catalog', 'product_id' => '7', 'name' => 'Producto del catalogo', 'qty' => '2', 'price_customer' => '95,50', 'tax_rate' => '21'],
    ['source' => 'own', 'product_id' => '3', 'name' => 'Producto propio', 'qty' => '0', 'price_customer' => '10', 'tax_rate' => '21'],
    ['source' => 'catalog', 'product_id' => '0', 'name' => '  ', 'qty' => '1', 'price_customer' => '0', 'tax_rate' => '21'],
]);
check(
    count($normalizadas['lines']) === 2
        && $normalizadas['lines'][0]['price_customer'] === 95.5
        && $normalizadas['lines'][0]['qty'] === 2
        && $normalizadas['lines'][1]['source'] === 'own',
    'lineas del pedido normalizadas (2 de 3: la vacia se ignora)'
);
check(
    $normalizadas['lines'][1]['qty'] === 1 && count($normalizadas['errors']) === 1,
    'cantidad minima 1 con aviso: ' . ($normalizadas['errors'][0] ?? '')
);

// Resumen de lineas: enviadas, pendientes y propias (no enviables)
$resumen = OrderItem::summary([
    ['source' => 'catalog', 'product_id' => 5, 'qty' => 2, 'price_customer' => 10, 'sent_at' => null],
    ['source' => 'catalog', 'product_id' => 6, 'qty' => 1, 'price_customer' => 10, 'sent_at' => '2026-01-01 10:00:00'],
    ['source' => 'own', 'product_id' => 9, 'qty' => 1, 'price_customer' => 30, 'sent_at' => null],
]);
check(
    $resumen['lines'] === 3 && $resumen['lines_sendable'] === 1 && $resumen['lines_sent'] === 1
        && $resumen['lines_own'] === 1 && $resumen['amount_sendable'] === 20.0,
    'resumen de lineas del pedido (' . $resumen['lines_sendable'] . ' enviables, ' . $resumen['lines_sent'] . ' enviadas)'
);
check(
    OrderItem::isSendable(['source' => 'catalog', 'product_id' => 1, 'sent_at' => null])
        && !OrderItem::isSendable(['source' => 'own', 'product_id' => 1, 'sent_at' => null])
        && !OrderItem::isSendable(['source' => 'catalog', 'product_id' => 1, 'sent_at' => '2026-01-01 10:00:00']),
    'solo se envian lineas del catalogo pendientes'
);

if (Catalog::isAvailable()) {
    $busqueda = Catalog::search('ssd', 3);
    check(
        count($busqueda) > 0 && isset($busqueda[0]['nombre'], $busqueda[0]['id']),
        'busqueda de productos para el selector (' . count($busqueda) . ' resultados)'
    );
}

echo "\n== Envio a idirecto (solo lectura en esta comprobacion) ==\n";
echo '  Puente disponible: ' . (OrderGateway::isAvailable() ? 'si' : 'no') . "\n";
if (OrderGateway::isAvailable()) {
    $tiendaVerificada = Store::allWithPlan()[0] ?? [];
    $cuenta = Account::forStore($tiendaVerificada);
    check(
        isset($cuenta['id_margen']) && (int) $cuenta['id_margen'] > 0 && array_key_exists('configurada', $cuenta),
        'cuenta del mayorista resuelta (tarifa ' . ($cuenta['id_margen'] ?? '?') . ', origen ' . ($cuenta['margen_origen'] ?? '?') . ')'
    );

    $margen = (int) $cuenta['id_margen'];
    $productoId = (int) Database::scalar(
        'SELECT pr.id_producto
         FROM precios pr
         INNER JOIN stock s ON s.id = pr.id_stock
         INNER JOIN almacenes a ON a.id = s.id_almacen
         INNER JOIN productos p ON p.id = pr.id_producto
         WHERE pr.id_margen = :margen AND pr.precio > 0 AND s.stock > 0 AND s.activo = 1
           AND a.tipo <> 2 AND p.estado <> 4
         LIMIT 1',
        ['margen' => $margen]
    );
    $tarifa = $productoId > 0 ? Pricing::forProduct($productoId, $margen, 1) : null;
    check(
        $tarifa !== null && $tarifa['precio'] > 0 && $tarifa['coste'] > 0 && $tarifa['tarifa_conocida'],
        'tarifa del mayorista para un producto real'
            . ($tarifa ? ' (#' . $productoId . ': precio ' . $tarifa['precio'] . ', coste ' . $tarifa['coste'] . ')' : '')
    );

    // La vista previa no debe escribir NADA en las tablas del mayorista.
    $pedidosAntes = Database::tableExists('pedidos')
        ? [(int) Database::scalar('SELECT COUNT(*) FROM pedidos'), (int) Database::scalar('SELECT COALESCE(MAX(id), 0) FROM pedidos')]
        : null;

    $pedidoPrueba = ['id' => 0, 'code' => 'VERIFY-1', 'status' => Order::STATUS_ACTIVE, 'customer_phone' => '', 'ship_name' => '', 'ship_address' => ''];
    $lineasPrueba = [[
        'id' => 1, 'order_id' => 0, 'source' => 'catalog', 'product_id' => $productoId,
        'name' => 'Producto de prueba', 'qty' => 2, 'price_customer' => 1.0, 'tax_rate' => 21,
    ]];
    $previa = OrderGateway::preview($tiendaVerificada, $pedidoPrueba, $lineasPrueba);
    check(
        $previa['errors'] === [] && count($previa['lines']) === 1
            && $previa['totals']['subtotal'] > 0 && $previa['totals']['impuestos'] > 0
            && $previa['totals']['total'] >= $previa['totals']['subtotal'],
        'vista previa del pedido antes de enviar (subtotal ' . $previa['totals']['subtotal']
            . ', IVA ' . $previa['totals']['impuestos'] . ', total ' . $previa['totals']['total'] . ')'
    );

    $previaPropia = OrderGateway::preview($tiendaVerificada, $pedidoPrueba, [[
        'id' => 2, 'order_id' => 0, 'source' => 'own', 'product_id' => 9,
        'name' => 'Producto propio', 'qty' => 1, 'price_customer' => 1.0, 'tax_rate' => 21,
    ]]);
    check(
        $previaPropia['errors'] !== [] && str_contains(implode(' ', $previaPropia['errors']), 'no se puede enviar'),
        'un producto propio no se puede enviar al mayorista'
    );

    check(
        OrderGateway::reference(['slug' => 'mi-tienda'], ['code' => 'P26-00007']) === 'TIENDA-mi-tienda-P26-00007',
        'referencia del pedido en el mayorista'
    );

    if ($pedidosAntes !== null) {
        $pedidosDespues = [(int) Database::scalar('SELECT COUNT(*) FROM pedidos'), (int) Database::scalar('SELECT COALESCE(MAX(id), 0) FROM pedidos')];
        check($pedidosAntes === $pedidosDespues, 'la vista previa no escribe en las tablas del mayorista');
    }
}

echo "\n==============================================================\n";
if ($fail === 0) {
    echo " RESULTADO: TODO OK ($ok comprobaciones)\n";
    exit(0);
}
echo " RESULTADO: $fail fallo(s) de " . ($ok + $fail) . " comprobaciones\n";
exit(1);
