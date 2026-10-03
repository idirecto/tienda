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








use Tienda\Core\Appearance;
use Tienda\Core\Auth;
use Tienda\Core\Cart;
use Tienda\Core\Checkout;
use Tienda\Core\Database;
use Tienda\Core\Dns;
use Tienda\Core\Idirecto\Account;
use Tienda\Core\Idirecto\OrderGateway;
use Tienda\Core\Idirecto\Pricing;
use Tienda\Core\Media\ImageOptimizer;
use Tienda\Core\Media\MediaRules;
use Tienda\Core\Registration;
use Tienda\Core\Shipping;
use Tienda\Core\Server;
use Tienda\Core\Specs;
use Tienda\Core\Storage\LocalStorage;
use Tienda\Core\Storage\S3Storage;
use Tienda\Core\Storage\StorageKey;
use Tienda\Core\Storage\StorageManager;
use Tienda\Core\Tenant;
use Tienda\Core\TenantResolver;
use Tienda\Core\CatalogUrl;
use Tienda\Models\Catalog;
use Tienda\Models\Menu;
use Tienda\Models\MenuAdmin;
use Tienda\Models\Customer;
use Tienda\Models\CustomerAddress;
use Tienda\Models\Order;
use Tienda\Models\OrderItem;
use Tienda\Models\Plan;
use Tienda\Models\Store;
use Tienda\Models\StoreUser;
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

    // Filtros estructurados del mayorista (filtros/subfiltros por subcategoria):
    // son los que usan idirecto y puntobyze en sus listados.
    $hayFiltros = Database::tableExists('filtros')
        && Database::tableExists('subfiltros')
        && Database::tableExists('rel_filtros_subcat')
        && Database::tableExists('rel_filtro_producto');
    $subFiltro = null;
    $gruposFiltro = [];
    if ($hayFiltros) {
        foreach (Database::select(
            'SELECT id_subcategoria FROM rel_filtros_subcat
              GROUP BY id_subcategoria ORDER BY COUNT(*) DESC LIMIT 12'
        ) as $row) {
            $candidato = (int) $row['id_subcategoria'];
            $grupos = Catalog::structuredFilters($candidato);
            if ($grupos !== [] && !empty($grupos[0]['options'])) {
                $subFiltro = $candidato;
                $gruposFiltro = $grupos;
                break;
            }
        }
    }
    check(!$hayFiltros || $subFiltro !== null, 'filtros del mayorista por subcategoria (' . count($gruposFiltro) . ' grupos)');

    if ($subFiltro !== null && $gruposFiltro !== []) {
        $grupo = $gruposFiltro[0];
        $opcion = $grupo['options'][0];
        $sinFiltro = Catalog::paginate(1, 4, null, null, $subFiltro);
        $conFiltro = Catalog::paginate(1, 4, null, null, $subFiltro, [$grupo['key'] => [$opcion['value']]]);
        check(
            $conFiltro['total'] > 0 && $conFiltro['total'] <= $sinFiltro['total'],
            'aplicar filtro estructurado (' . $conFiltro['total'] . ' de ' . $sinFiltro['total'] . ')'
        );

        $sel = Catalog::selectionFromQuery(['f' => [$grupo['key'] => [$opcion['value']]]]);
        check(
            ($sel['terms'][$grupo['key']] ?? []) === [(string) $opcion['value']] && $sel['flat'] !== [],
            'leer filtro estructurado de la URL'
        );

        $rutaFiltro = CatalogUrl::fromQuery(['subcat' => $subFiltro, 'f' => [$grupo['key'] => [$opcion['value']]]]);
        check(str_contains($rutaFiltro, '/f/' . $grupo['key'] . '-' . $opcion['value']), 'ruta SEO del filtro (' . $rutaFiltro . ')');
        $estadoFiltro = CatalogUrl::parse($rutaFiltro);
        check(
            is_array($estadoFiltro) && ($estadoFiltro['f'][$grupo['key']][0] ?? '') === (string) $opcion['value'],
            'parsear ruta SEO del filtro'
        );

        // Filtrado progresivo por marca: los contadores de los filtros se
        // recalculan en el contexto y cuadran con el listado.
        $marcaFacet = null;
        foreach (Catalog::facets(null, $subFiltro) as $facetCtx) {
            if (($facetCtx['type'] ?? '') === 'brand' && !empty($facetCtx['options'])) {
                $marcaFacet = $facetCtx;
                break;
            }
        }
        if ($marcaFacet !== null) {
            $marcaCtx = (string) $marcaFacet['options'][0]['value'];
            $gruposCtx = Catalog::structuredFilters($subFiltro, ['terms' => [Catalog::brandFacetKey() => [$marcaCtx]]]);
            $cuadra = $gruposCtx !== [];
            foreach ($gruposCtx as $grupoCtx) {
                $optCtx = $grupoCtx['options'][0];
                $totalCtx = Catalog::paginate(1, 1, null, null, $subFiltro, [
                    Catalog::brandFacetKey() => [$marcaCtx],
                    $grupoCtx['key']         => [$optCtx['value']],
                ])['total'];
                if ($totalCtx !== (int) $optCtx['count']) {
                    $cuadra = false;
                    break;
                }
            }
            check($cuadra, 'contadores de filtros con marca (filtrado progresivo)');
        }
    }

    // Los facetas de configuracion (marca, socket...) tienen que filtrar de
    // verdad: el WHERE los ignoraba por un envoltorio mal formado.
    $topMarca = Catalog::brandList(1);
    if ($topMarca !== []) {
        $marcaId = (string) $topMarca[0]['id'];
        $conMarca = Catalog::paginate(1, 4, null, null, null, [Catalog::brandFacetKey() => [$marcaId]]);
        check(
            $conMarca['total'] > 0 && $conMarca['total'] <= Catalog::paginate(1, 4)['total'],
            'aplicar faceta de marca (' . $conMarca['total'] . ' productos)'
        );
    }

    // La marca y la etiqueta no se pierden al construir enlaces (el fallo que
    // tenia el catalogo: /ofertas + marca acababa en el catalogo entero).
    $tagUrl = CatalogUrl::fromQuery(['etiqueta' => 'ofertas', 'f' => [Catalog::brandFacetKey() => ['43']], 'page' => 2]);
    check(
        str_starts_with($tagUrl, '/ofertas?') && str_contains($tagUrl, 'f%5Bmarca%5D') && str_contains($tagUrl, 'page=2'),
        'la etiqueta y la marca se conservan en los enlaces (' . $tagUrl . ')'
    );
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

    // Los filtros con `when` son por subcategoria: no deben "escaparse" a
    // cualquier subcategoria de su categoria (antes Socket salia tambien en
    // Tarjetas Graficas porque la categoria coincidia).
    $clavesTarjetas = array_column(Catalog::facets(9, 181), 'key');
    check(!in_array('socket', $clavesTarjetas, true), 'los filtros de configuracion no se escapan de su subcategoria');
    $clavesCategoria = array_column(Catalog::facets(9, null), 'key');
    check(
        !in_array('socket', $clavesCategoria, true)
            && in_array('marca', $clavesCategoria, true)
            && in_array('precio', $clavesCategoria, true),
        'sin subcategoria solo hay facetas globales (marca y precio)'
    );

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

echo "\n== Registro de tiendas (solo clientes del mayorista) ==\n";
// La contrasena de `tiendas` no es password_hash: idirecto usa esta firma.
check(
    Account::signature('clave-de-prueba') === hash('sha256', md5(sha1('clave-de-prueba'))),
    'firma de la contrasena del mayorista (sha256+md5+sha1)'
);
check(Account::login('', '') === null && Account::login('no-existe@verificacion.test', 'x') === null, 'credenciales invalidas no dan cuenta');
check(Registration::isOpen(), 'registro abierto (' . (Registration::isOpen() ? 'si' : 'no') . ')');
check(Registration::defaultPlanId() !== null, 'plan por defecto del registro (' . (Registration::defaultPlanId() ?? '?') . ')');
check(
    Registration::uniqueSlug('Mi Tienda SL') === 'mi-tienda-sl'
        && Registration::uniqueSlug('idirecto-demo') === 'idirecto-demo-2'
        && Registration::uniqueSlug('') === 'tienda',
    'slug unico para la tienda nueva (evita repetidos)'
);
check(Registration::existingStoreFor(0) === null, 'sin cuenta no hay tienda registrada');

// Datos de la tienda a partir de la cuenta: no escribe nada, solo copia.
$cuentaPrueba = [
    'id' => 1, 'nombre' => 'Tienda de prueba', 'nombre_sociedad' => 'Tienda de Prueba S.L.',
    'email' => 'Prueba@Ejemplo.test', 'id_pais' => 66, 'id_provincia' => 52,
    'direccion' => 'Calle Mayor 1', 'cp' => '50001', 'poblacion' => 'Zaragoza',
    'telefono' => '976000000', 'id_margen' => 12,
];
$borrador = Registration::dataFromAccount($cuentaPrueba);
check(
    ($borrador['id_tienda_idirecto'] ?? 0) === 1
        && ($borrador['id_margen'] ?? 0) === 12
        && $borrador['status'] === Registration::STORE_ACTIVE
        && $borrador['name'] === 'Tienda de prueba'
        && $borrador['legal_name'] === 'Tienda de Prueba S.L.'
        && $borrador['email'] === 'prueba@ejemplo.test'
        && $borrador['province'] === 'Zaragoza'
        && $borrador['country'] === 'España'
        && $borrador['slug'] !== '',
    'la tienda nace enlazada a la cuenta y con sus datos'
);
check(
    Registration::dataFromAccount($cuentaPrueba, 'otro-slug')['slug'] === 'otro-slug',
    'se respeta la direccion de tienda pedida'
);

// La cadena completa (comprobar cuenta -> crear tienda -> usuario de panel) se
// prueba en una transaccion que se DESHACE: no queda ninguna tienda de prueba.
$pdoRegistro = Database::pdo();
$pdoRegistro->beginTransaction();
try {
    $emailPrueba = 'verificacion-' . bin2hex(random_bytes(4)) . '@ejemplo.test';
    $resultado = Registration::register($cuentaPrueba, $emailPrueba, 'clave-verificacion-1234');

    $creada = Store::findWithPlan($resultado['store_id']);
    $usuario = StoreUser::findBy('email', $emailPrueba);
    check(
        $creada !== null && (int) $creada['id_tienda_idirecto'] === 1 && (int) $creada['id_margen'] === 12
            && $usuario !== null && (int) $usuario['store_id'] === $resultado['store_id'],
        'registro: tienda + usuario de panel enlazados a la cuenta'
    );
    check(Auth::attempt($emailPrueba, 'clave-verificacion-1234'), 'despues del registro se puede entrar al panel');
    check(Auth::storeId() === $resultado['store_id'], 'la sesion apunta a la tienda recien creada');
} catch (\Throwable $e) {
    check(false, 'registro completo: ' . $e->getMessage());
} finally {
    $pdoRegistro->rollBack();
}
check(StoreUser::findBy('email', $emailPrueba) === null, 'la prueba de registro no deja rastro (rollback)');

echo "\n== Compra del cliente final (carrito, cuenta y pedido) ==\n";

// -----------------------------------------------------------------------------
// Precio de venta de la tienda: tarifa + beneficio, con IVA en la web
// -----------------------------------------------------------------------------
Catalog::forStore(12, 15, 21, true);
$precioWeb = Catalog::find(254967);
check(
    abs(Catalog::saleFromNet(100) - 121.0) < 0.01 && abs(Catalog::netFromSale(121) - 100.0) < 0.01,
    'el precio de la web lleva el IVA incluido y se puede volver a la base'
);
check(
    abs(Catalog::salePriceFromCost(100, 21) - 139.15) < 0.01,
    'sin tarifa publicada el precio sale del coste con el beneficio de la tienda'
);

Catalog::forStore(12, 15, 21, false);
$precioPanel = Catalog::find(254967);
Catalog::forStore(null, 0, 21, false);
$precioSinContexto = Catalog::find(254967);

check(
    Catalog::markupFactor() === 1.0 && $precioSinContexto['price_final'] == 311.17,
    'sin contexto se mantiene el precio de siempre (' . $precioSinContexto['price_final'] . ')'
);

Catalog::forStore(12, 15, 21, true);
$web = Catalog::find(254967);
Catalog::forStore(12, 15, 21, false);
$panel = Catalog::find(254967);

check(
    $web['price_final'] > $panel['price_final']
        && abs($panel['price_final'] - round($web['price_net'], 2)) < 0.02
        && abs($panel['price_final'] - 363.02) < 0.02,
    'precio de tienda: web ' . $web['price_final'] . ' con IVA / panel ' . $panel['price_final'] . ' sin IVA'
);
check(
    abs($web['price_net'] - 363.02) < 0.02 && abs($web['tax_rate'] - 21.0) < 0.01,
    'la base del pedido no lleva IVA y guarda el tipo aplicado'
);

// -----------------------------------------------------------------------------
// Envio y formas de pago
// -----------------------------------------------------------------------------
$tiendaEnvio = ['shipping_flat' => 4.95, 'free_shipping_from' => 60.0];
check(
    abs(Shipping::cost($tiendaEnvio, 59.99) - 4.95) < 0.01
        && Shipping::cost($tiendaEnvio, 60.0) === 0.0
        && Shipping::cost(['shipping_flat' => 0], 10.0) === 0.0,
    'gastos de envio: tarifa plana y gratis desde el minimo'
);
check(
    abs(Shipping::missingForFree($tiendaEnvio, 50.0) - 10.0) < 0.01,
    'aviso de cuanto falta para el envio gratis'
);

$formas = Checkout::paymentMethods(['pay_transfer' => 1, 'pay_cod' => 0, 'pay_pickup' => 1]);
check(
    array_keys($formas) === [Order::PAY_TRANSFER, Order::PAY_PICKUP]
        && $formas[Order::PAY_TRANSFER]['label'] === 'Transferencia bancaria',
    'formas de pago: solo las que la tienda tiene activadas'
);
check(
    Order::paymentLabel(Order::PAY_COD) === 'Contra reembolso'
        && Order::paymentStatusLabel(0) === 'Pendiente de pago'
        && Order::paymentStatusLabel(1) === 'Pagado',
    'etiquetas de cobro del pedido'
);

// -----------------------------------------------------------------------------
// Carrito (en la sesion, con precios y stock en vivo)
// -----------------------------------------------------------------------------
$carritoAntes = $_SESSION['cart'] ?? null;
Catalog::forStore(null, 15, 21, true);

$anadido = Cart::add(1, 'catalog', 254967, 2);
$items = Cart::items(1, $descartados);
$totales = Cart::totals($items, ['shipping_flat' => 4.95, 'free_shipping_from' => 60.0]);
check(
    $anadido['ok'] && Cart::count(1) === 2 && count($items) === 1 && (int) $items[0]['qty'] === 2,
    'el carrito guarda producto y cantidad (' . Cart::count(1) . ' uds)'
);
check(
    $items[0]['name'] !== '' && $items[0]['price'] > 0 && $items[0]['price_net'] < $items[0]['price'],
    'el carrito trae nombre, precio con IVA y base sin IVA'
);
check(
    abs($totales['subtotal'] - round((float) $items[0]['price'] * 2, 2)) < 0.02
        && abs($totales['total'] - ($totales['subtotal'] + $totales['shipping'])) < 0.02
        && abs($totales['tax_total'] - ($totales['subtotal'] - $totales['subtotal_net'])) < 0.02,
    'totales del carrito: productos + envio = total'
);
Cart::updateQuantities(1, [Cart::key('catalog', 254967) => 3]);
check(Cart::count(1) === 3, 'se puede cambiar la cantidad desde el carrito');
Cart::remove(1, Cart::key('catalog', 254967));
check(Cart::count(1) === 0, 'se puede quitar una linea del carrito');
check(
    Cart::add(1, 'catalog', 0, 1)['ok'] === false && Cart::add(1, 'own', 0, 1)['ok'] === false,
    'no se puede anadir un producto que no existe'
);

// -----------------------------------------------------------------------------
// Cliente, direcciones y pedido (en una transaccion que se deshace)
// -----------------------------------------------------------------------------
$pdoCompra = Database::pdo();
$pdoCompra->beginTransaction();
try {
    $emailCompra = 'compra-' . bin2hex(random_bytes(4)) . '@ejemplo.test';
    $cliente = Customer::findOrCreateGuest(1, [
        'email' => $emailCompra, 'name' => 'Cliente de Prueba', 'phone' => '600111222',
    ]);
    check(
        $cliente !== null && (int) $cliente['is_guest'] === 1 && empty($cliente['password_hash']),
        'la compra como invitado crea el cliente sin contrasena'
    );

    $dirId = CustomerAddress::save((int) $cliente['id'], 1, [
        'name' => 'Cliente de Prueba', 'address' => 'Calle de la Prueba 22', 'detail' => 'Portal 2',
        'postal_code' => '50002', 'city' => 'Zaragoza', 'province' => 'Zaragoza',
        'phone' => '600111222', 'is_default_ship' => true,
    ]);
    check(
        CustomerAddress::countForCustomer((int) $cliente['id'], 1) === 1
            && (int) CustomerAddress::findForCustomer($dirId, (int) $cliente['id'], 1)['is_default_ship'] === 1,
        'la direccion se guarda en la libreta del cliente'
    );

    $pedido = Checkout::place(
        ['id' => 1, 'allow_orders' => 1, 'pay_transfer' => 1, 'shipping_flat' => 4.95],
        $cliente,
        ['email' => $emailCompra, 'name' => 'Cliente de Prueba', 'address' => 'Calle de la Prueba 22',
         'detail' => 'Portal 2', 'postal_code' => '50002', 'city' => 'Zaragoza', 'province' => 'Zaragoza',
         'country' => 'Espana', 'phone' => '600111222'],
        [],
        ['payment_method' => Order::PAY_TRANSFER, 'comment' => 'Dejar en porteria, gracias.'],
        $itemsPrueba = [[
            'source' => 'catalog', 'product_id' => 254967, 'sku' => 'PRUEBA', 'name' => 'Producto de prueba',
            'qty' => 2, 'price_net' => 100.0, 'tax_rate' => 21.0, 'price' => 121.0,
        ]],
        ['shipping' => 4.95]
    );

    $row = Order::findForStore($pedido['order_id'], 1, true);
    check(
        $row !== null && (int) $row['customer_id'] === (int) $cliente['id']
            && $row['payment_method'] === Order::PAY_TRANSFER && (int) $row['payment_status'] === 0,
        'el pedido de la web nace ligado al cliente y pendiente de pago'
    );
    check(
        abs((float) $row['subtotal'] - 200.0) < 0.01 && abs((float) $row['tax_total'] - 42.0) < 0.01
            && abs((float) $row['total'] - 246.95) < 0.01,
        'totales del pedido: base 200 + IVA 42 + envio 4,95 = ' . $row['total']
    );
    check(
        $row['ship_address'] === 'Calle de la Prueba 22' && $row['ship_detail'] === 'Portal 2'
            && $row['bill_address'] === 'Calle de la Prueba 22' && $row['customer_note'] === 'Dejar en porteria, gracias.',
        'el pedido guarda direccion de envio, de facturacion y el comentario'
    );
    check(
        count($row['items']) === 1 && (float) $row['items'][0]['price_customer'] === 100.0
            && (float) $row['items'][0]['price_idirecto'] > 0,
        'la linea guarda la base para el cliente y la tarifa del mayorista'
    );
    check(
        Order::findForCode(1, $pedido['code']) !== null
            && Order::findForCustomer(1, (int) $cliente['id'], $pedido['code']) !== null,
        'el pedido se puede consultar por su numero'
    );
    check(
        count(Customer::orders((int) $cliente['id'], 1)) === 1 && count(Customer::forStore(1, $emailCompra)) === 1,
        'el pedido aparece en la cuenta del cliente y en el panel'
    );

    Order::setPaymentStatus((int) $row['id'], 1);
    check(
        (int) Order::find((int) $row['id'])['payment_status'] === 1,
        'el tendero puede marcar el pedido como pagado'
    );

    // El pedido se puede enviar al mayorista con la direccion DEL CLIENTE:
    // se comprueba el mapeo de las direcciones (pedidos_addr) sin escribir nada.
    $shipping = new ReflectionMethod(OrderGateway::class, 'shippingAddress');
    $shipping->setAccessible(true);
    $billing = new ReflectionMethod(OrderGateway::class, 'billingAddress');
    $billing->setAccessible(true);

    $cuentaPrueba = [
        'id_tienda' => 7881, 'nombre' => 'Tienda', 'razon_social' => 'Tienda S.L.', 'nif' => 'B12345678',
        'direccion' => 'Calle de la Tienda 1', 'cp' => '50001', 'poblacion' => 'Zaragoza',
        'id_pais' => 66, 'id_provincia' => 52, 'telefono' => '976000000',
    ];
    $envio = $shipping->invoke(null, $cuentaPrueba, $row);
    check(
        $envio !== null && $envio['nombre'] === 'Cliente de Prueba'
            && $envio['direccion'] === 'Calle de la Prueba 22, Portal 2'
            && $envio['cp'] === '50002' && $envio['poblacion'] === 'Zaragoza'
            && $envio['localidad'] === $emailCompra && $envio['telefono'] === '600111222',
        'al mayorista se le pasa la direccion de envio del cliente, no la de la tienda'
    );
    $factura = $billing->invoke(null, $cuentaPrueba, $row);
    check(
        $factura['nombre'] === 'Cliente de Prueba' && $factura['direccion'] === 'Calle de la Prueba 22, Portal 2'
            && $factura['nombre_sociedad'] === 'Tienda S.L.',
        'la direccion de facturacion del pedido tambien viaja a pedidos_addr'
    );

    // Reclamar la cuenta de invitado: se le pone contrasena y conserva pedidos.
    Customer::updateById((int) $cliente['id'], [
        'password_hash' => password_hash('clave-de-prueba-1234', PASSWORD_DEFAULT), 'is_guest' => 0,
    ]);
    $reclamado = Customer::find((int) $cliente['id']);
    check(
        (int) $reclamado['is_guest'] === 0 && password_verify('clave-de-prueba-1234', (string) $reclamado['password_hash'])
            && count(Customer::orders((int) $reclamado['id'], 1)) === 1,
        'el invitado que se registra conserva sus pedidos'
    );
} catch (\Throwable $e) {
    check(false, 'compra completa: ' . $e->getMessage());
} finally {
    $pdoCompra->rollBack();
}
check(
    Customer::findByEmail(1, $emailCompra ?? '') === null && Cart::count(1) <= 0,
    'la prueba de compra no deja rastro (rollback)'
);

// El carrito de las pruebas no debe quedarse en la sesion.
if ($carritoAntes === null) {
    unset($_SESSION['cart']);
} else {
    $_SESSION['cart'] = $carritoAntes;
}
Catalog::forStore(null, 0, 21, false);

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

echo "\n== Navegacion: Menú Compacto y Menú Catalogo ==\n";

// El ajuste de menu tiene EXACTAMENTE dos valores: no hay tercera variante.
$menuStyles = Menu::styles();
check(count($menuStyles) === 2 && isset($menuStyles['compacto'], $menuStyles['catalogo']),
    'solo existen dos estilos de menu: Compacto y Catalogo');
check(Menu::style(['menu_style' => 'compacto']) === 'compacto'
    && Menu::style(['menu_style' => 'catalogo']) === 'catalogo'
    && Menu::style(['menu_style' => 'otro-inventado']) === (string) config('menu.fallback_style', 'catalogo'),
    'el estilo de menu se sanea contra la lista de dos');

// Tablas y columnas de la migracion 005.
check(Database::tableExists('mt_menu_items'), 'tabla mt_menu_items creada (migracion 005)');
check((bool) Database::scalar(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'menu_style'"
), 'columna mt_stores.menu_style creada');

// Arbol comun: los dos menus pintan lo mismo.
$demoStoreId = (int) (Database::scalar('SELECT id FROM mt_stores ORDER BY id ASC LIMIT 1') ?? 0);
$menuTree = Menu::forStore($demoStoreId);
check(count($menuTree['categories']) > 0 && $menuTree['groups'] > 0 && $menuTree['links'] > 0,
    sprintf('arbol de menu de la tienda demo (%d categorias, %d grupos, %d destinos)',
        count($menuTree['categories']), $menuTree['groups'], $menuTree['links']));

// Todos los enlaces del menu son rutas que el parser reconoce: ningun 404.
$rutasMenu = [];
$niveles = 0;
foreach ($menuTree['categories'] as $cat) {
    $rutasMenu[] = $cat['path'];
    foreach ($cat['groups'] as $group) {
        $rutasMenu[] = $group['path'];
        $niveles = max($niveles, 2);
        foreach ($group['links'] as $link) {
            $rutasMenu[] = $link['path'];
            $niveles = max($niveles, 3);
        }
    }
}
$invalidas = array_values(array_filter($rutasMenu, static fn (string $path): bool => str_starts_with($path, '/')
    && CatalogUrl::parse($path) === null
    && !preg_match('#^/(catalogo|marca/|ofertas|novedades|destacados)#', $path)));
check($invalidas === [],
    'los enlaces del menu son rutas validas' . ($invalidas ? ' (rotas: ' . implode(', ', array_slice($invalidas, 0, 3)) . ')' : ''));
check($niveles === 3, 'el arbol del menu tiene tres niveles');
$rutasFiltro = array_values(array_filter($rutasMenu, static fn (string $p): bool => str_contains($p, '/f/')));
$filtrosMenuValidos = true;
foreach ($rutasFiltro as $rutaFiltro) {
    if (CatalogUrl::parse($rutaFiltro) === null) {
        $filtrosMenuValidos = false;
        break;
    }
}
check($filtrosMenuValidos, 'los destinos de filtro del menu son rutas validas (' . count($rutasFiltro) . ' filtros)');

// Accesos rapidos del menu.
$quickKeys = array_map(static fn (array $q): string => $q['key'], Menu::quickLinks());
sort($quickKeys);
check($quickKeys === ['destacados', 'marcas', 'novedades', 'ofertas'],
    'accesos rapidos del menu: Ofertas, Novedades, Marcas y Destacados');

// Etiquetas de listado.
check(Catalog::tagKey('ofertas') === 'ofertas' && Catalog::tagKey('inventada') === 'todos',
    'las etiquetas de listado se sanean contra la configuracion');

$ofertasResult = Catalog::paginate(1, 3, null, null, null, [], null, null, 'relevance', 'ofertas');
$ofertasOk = $ofertasResult['total'] > 0;
if ($ofertasOk) {
    $ids = array_map(static fn (array $item): int => (int) $item['id'], $ofertasResult['items']);
    $enOferta = (int) Database::scalar(
        'SELECT COUNT(*) FROM ofertas WHERE id_producto IN (' . implode(',', array_map('intval', $ids)) . ')
           AND inicio <= NOW() AND (final IS NULL OR final >= NOW())'
    );
    $ofertasOk = $ids !== [] && $enOferta === count($ids);
}
check($ofertasOk, 'la etiqueta Ofertas devuelve solo productos con oferta activa (' . (int) $ofertasResult['total'] . ')');

$novedadesResult = Catalog::paginate(1, 3, null, null, null, [], null, null, 'relevance', 'novedades');
check($novedadesResult['total'] > 0, 'la etiqueta Novedades tiene resultados (' . (int) $novedadesResult['total'] . ')');

check(count(Catalog::brandList(10)) > 0, 'el directorio de marcas tiene resultados');

// Rutas SEO: construir, parsear y canonicalizar.
$catId = (int) (Database::scalar('SELECT id FROM categorias WHERE id = 9') ?? 0);
$catPath = CatalogUrl::categoryPath($catId);
$rutaParseada = $catPath !== null ? CatalogUrl::parse($catPath) : null;
check($catPath !== null && $rutaParseada !== null && $rutaParseada['cat'] === $catId,
    'la ruta SEO de una categoria vuelve a resolver su id (' . (string) $catPath . ')');

$subId = (int) (Database::scalar('SELECT id FROM subcategorias WHERE id_categoria = 9 ORDER BY orden ASC LIMIT 1') ?? 0);
$subPath = $subId > 0 ? CatalogUrl::subPath($subId) : null;
$conMarca = $subPath !== null ? CatalogUrl::parse($subPath . '/m/35') : null;
check($conMarca !== null && $conMarca['subcat'] === $subId
    && (int) ($conMarca['f'][Catalog::brandFacetKey()][0] ?? 0) === 35,
    'la ruta SEO de subcategoria con marca se parsea (subcategoria + marca)');

$conOrden = $subPath !== null ? CatalogUrl::parse($subPath . '/orden/precio-asc/page/3') : null;
check($conOrden !== null && $conOrden['orden'] === 'precio-asc' && $conOrden['page'] === 3,
    'la ruta SEO admite orden y paginacion');

$rutaConFiltro = $subPath !== null ? CatalogUrl::parse((string) $subPath . '/f/164-1282') : null;
check(CatalogUrl::parse('/categoria-que-no-existe') === null
    && is_array($rutaConFiltro)
    && ($rutaConFiltro['f']['164'][0] ?? '') === '1282',
    'una ruta SEO invalida no resuelve y la de filtro si');

$desdeQuery = CatalogUrl::fromQuery(['cat' => $catId, 'f' => ['socket' => ['am5']]]);
check(str_starts_with($desdeQuery, (string) $catPath) && str_contains($desdeQuery, 'socket'),
    'la query del catalogo se convierte en ruta SEO conservando las facetas');
check(CatalogUrl::canonicalFromQuery(['q' => 'teclado']) === null
    && CatalogUrl::canonicalFromQuery(['cat' => $catId]) === $catPath,
    'la busqueda no se redirige y la categoria si');

// Vistas de los dos menus.
foreach (['_menu.php', '_menu_panel.php', 'marcas.php', 'menu_preview_frame.php'] as $vista) {
    check(is_file(TIENDA_BASE . '/app/Views/themes/idirecto/' . $vista), 'vista del tema base: ' . $vista);
}
check(is_file(TIENDA_BASE . '/app/Views/panel/_menu_tree.php'), 'vista compartida del editor: panel/_menu_tree.php');

// ---------------------------------------------------------------------------
echo "\n== Panel del menu: editor, publicacion y aislamiento entre tiendas ==\n";
// ---------------------------------------------------------------------------
// Todo lo que sigue escribe en tablas PROPIAS dentro de una transaccion que se
// deshace al final: la verificacion no deja ni un dato de prueba.
$editorStore = (int) (Database::scalar('SELECT id FROM mt_stores ORDER BY id ASC LIMIT 1') ?? 0);

if (!$editorStore) {
    check(false, 'hay una tienda para probar el editor del menu');
} else {
    // --- Estructura de las migraciones 006 y 007 ---
    $columnas = [];
    foreach (Database::select(
        "SELECT COLUMN_NAME c, COLUMN_TYPE t, IS_NULLABLE n FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mt_menu_items'"
    ) as $col) {
        $columnas[$col['c']] = ['type' => (string) $col['t'], 'nullable' => (string) $col['n']];
    }
    $nuevas = ['icon', 'badge_color', 'banner_id', 'banner_url', 'min_customer_level', 'hide_empty', 'visibility'];
    $faltan = array_values(array_filter($nuevas, static fn (string $c): bool => !isset($columnas[$c])));
    check($faltan === [], 'mt_menu_items tiene los campos del editor' . ($faltan ? ' (faltan: ' . implode(', ', $faltan) . ')' : ''));
    check(($columnas['store_id']['nullable'] ?? 'NO') === 'YES', 'store_id admite NULL (nodo compartido de la plataforma)');
    check((int) preg_replace('/\D/', '', $columnas['badge_color']['type'] ?? '0') >= 32,
        'badge_color admite un token de la identidad visual (' . ($columnas['badge_color']['type'] ?? '?') . ')');
    check(Database::tableExists('mt_menu_item_stores') && Database::tableExists('mt_menu_published')
        && Database::tableExists('mt_menu_revisions'), 'tablas de visibilidad, publicacion e historico creadas');

    // --- Rol de plataforma ---
    $sesionPrevia = $_SESSION['auth_user'] ?? null;
    $_SESSION['auth_user'] = ['id' => 999, 'store_id' => $editorStore, 'name' => 'prueba', 'email' => 'p@t', 'role' => 'platform'];
    $esPlataforma = Auth::isPlatform();
    $_SESSION['auth_user']['role'] = 'owner';
    $esDueno = Auth::isPlatform();
    if ($sesionPrevia === null) {
        unset($_SESSION['auth_user']);
    } else {
        $_SESSION['auth_user'] = $sesionPrevia;
    }
    check($esPlataforma && !$esDueno, 'Auth::isPlatform() distingue el rol platform del owner');

    // --- Transaccion: lo que sigue se deshace ---
    $pdo = Database::pdo();
    $pdo->beginTransaction();

    try {
        // Segunda tienda, solo para probar el aislamiento.
        $otraStore = (int) Database::insert('mt_stores', [
            'slug'   => 'aislamiento-' . substr((string) time(), -5),
            'name'   => 'Tienda de prueba (aislamiento)',
            'status' => 1,
        ]);

        $base = MenuAdmin::create($editorStore, false, [
            'parent_id' => null, 'label' => 'Categoria de prueba', 'target_type' => 'categoria',
            'target_id' => 9, 'icon' => 'grid', 'badge' => 'NUEVO', 'badge_color' => '#e30613',
            'active' => 1,
        ]);
        check($base['ok'] && $base['id'] !== null, 'se crea una categoria desde el panel');
        $catId = (int) ($base['id'] ?? 0);

        $nivel2 = MenuAdmin::create($editorStore, false, [
            'parent_id' => $catId, 'label' => 'Grupo de prueba', 'target_type' => 'categoria',
            'target_id' => 9, 'active' => 1,
        ]);
        $nivel3 = MenuAdmin::create($editorStore, false, [
            'parent_id' => (int) ($nivel2['id'] ?? 0), 'label' => 'Enlace de prueba',
            'target_type' => 'subcategoria', 'target_id' => 102, 'active' => 1,
        ]);
        $filaN2 = MenuAdmin::node((int) ($nivel2['id'] ?? 0), $editorStore, false);
        $filaN3 = MenuAdmin::node((int) ($nivel3['id'] ?? 0), $editorStore, false);
        check((int) ($filaN2['level'] ?? 0) === 2 && (int) ($filaN3['level'] ?? 0) === 3,
            'el nivel se calcula por el padre (1 -> 2 -> 3)');

        $cuarto = MenuAdmin::create($editorStore, false, [
            'parent_id' => (int) ($nivel3['id'] ?? 0), 'label' => 'Cuarto nivel',
            'target_type' => 'categoria', 'target_id' => 9,
        ]);
        check($cuarto['ok'] === false, 'no se puede crear un cuarto nivel');

        $repetido = MenuAdmin::create($editorStore, false, [
            'parent_id' => $catId, 'label' => 'Grupo de prueba', 'slug' => 'grupo-de-prueba',
            'target_type' => 'categoria', 'target_id' => 9,
        ]);
        check($repetido['ok'] === false, 'el slug no se puede repetir entre hermanos');

        $malDestino = MenuAdmin::create($editorStore, false, [
            'parent_id' => $catId, 'label' => 'Destino inexistente', 'target_type' => 'categoria',
            'target_id' => 999999,
        ]);
        check($malDestino['ok'] === false, 'se rechaza un destino que no existe en el catalogo');

        $malColor = MenuAdmin::create($editorStore, false, [
            'parent_id' => $catId, 'label' => 'Color invalido', 'target_type' => 'categoria',
            'target_id' => 9, 'badge_color' => 'rojo chillon',
        ]);
        check($malColor['ok'] === false, 'se rechaza un color de badge que no es hex ni token');

        $editado = MenuAdmin::update($catId, $editorStore, false, [
            'parent_id' => null, 'label' => 'Categoria editada', 'slug' => 'categoria-editada',
            'target_type' => 'categoria', 'target_id' => 24, 'icon' => 'bolt', 'badge' => 'OFERTA',
            'badge_color' => '--c-accent', 'min_customer_level' => '', 'hide_empty' => 1, 'active' => 1,
        ]);
        $filaCat = MenuAdmin::node($catId, $editorStore, false);
        check($editado['ok'] && (string) $filaCat['label'] === 'Categoria editada'
            && (string) $filaCat['icon'] === 'bolt' && (string) $filaCat['badge'] === 'OFERTA'
            && (string) $filaCat['badge_color'] === '--c-accent' && (int) $filaCat['hide_empty'] === 1,
            'se editan nombre, slug, icono, badge, color y categoria vacia');

        MenuAdmin::toggle($catId, $editorStore, false);
        $trasToggle = (int) MenuAdmin::node($catId, $editorStore, false)['active'];
        MenuAdmin::toggle($catId, $editorStore, false);
        check($trasToggle === 0 && (int) MenuAdmin::node($catId, $editorStore, false)['active'] === 1,
            'activar y desactivar un nodo');

        $movido = MenuAdmin::move((int) $nivel2['id'], null, 0, $editorStore, false);
        $filaMovida = MenuAdmin::node((int) $nivel2['id'], $editorStore, false);
        $hijoMovido = MenuAdmin::node((int) $nivel3['id'], $editorStore, false);
        check($movido['ok'] && (int) $filaMovida['level'] === 1 && (int) $hijoMovido['level'] === 2,
            'mover un nodo de nivel arrastra a toda su rama');

        // Volver a colgar el grupo de la categoria (la rama vuelve a su sitio).
        MenuAdmin::move((int) $nivel2['id'], $catId, 0, $editorStore, false);
        $ciclo = MenuAdmin::move((int) $nivel2['id'], (int) $nivel3['id'], 0, $editorStore, false);
        check($ciclo['ok'] === false, 'no se puede mover una categoria dentro de su propia rama');

        // Orden: la lista COMPLETA de hermanos, como la manda el drag & drop.
        $otraSub = (int) (Database::scalar('SELECT id FROM subcategorias WHERE id_categoria = 9 AND id <> 102 ORDER BY orden ASC LIMIT 1') ?? 0);
        $hijoA = MenuAdmin::create($editorStore, false, [
            'parent_id' => (int) $nivel2['id'], 'label' => 'Hermano A',
            'target_type' => 'subcategoria', 'target_id' => 102, 'active' => 1,
        ]);
        $hijoB = MenuAdmin::create($editorStore, false, [
            'parent_id' => (int) $nivel2['id'], 'label' => 'Hermano B',
            'target_type' => 'subcategoria', 'target_id' => $otraSub, 'active' => 1,
        ]);
        $orden = MenuAdmin::reorder(
            [(int) $hijoB['id'], (int) $hijoA['id'], (int) $nivel3['id']],
            (int) $nivel2['id'],
            $editorStore,
            false
        );
        $sorts = [
            (int) MenuAdmin::node((int) $hijoB['id'], $editorStore, false)['sort'],
            (int) MenuAdmin::node((int) $hijoA['id'], $editorStore, false)['sort'],
            (int) MenuAdmin::node((int) $nivel3['id'], $editorStore, false)['sort'],
        ];
        check($orden['ok'] && $sorts === [0, 1, 2], 'el orden del drag & drop se guarda (sort 0,1,2)');

        // --- Aislamiento entre tiendas ---
        check(MenuAdmin::node($catId, $otraStore, false) === null, 'una tienda no ve los nodos de otra');
        $roboUpdate = MenuAdmin::update($catId, $otraStore, false, [
            'label' => 'HACKEADO', 'target_type' => 'categoria', 'target_id' => 9, 'active' => 1,
        ]);
        MenuAdmin::delete($catId, $otraStore, false);
        $intacto = MenuAdmin::node($catId, $editorStore, false);
        check($roboUpdate['ok'] === false && $intacto !== null && (string) $intacto['label'] === 'Categoria editada',
            'otra tienda no puede editar ni borrar un nodo ajeno');

        $propioOtra = MenuAdmin::create($otraStore, false, [
            'parent_id' => null, 'label' => 'Categoria de la otra tienda', 'target_type' => 'categoria',
            'target_id' => 9, 'active' => 1,
        ]);
        $storeIdOtra = (int) ($propioOtra['id'] ?? 0);
        $arrancado = MenuAdmin::update($storeIdOtra, $editorStore, false, [
            'label' => 'HACKEADO', 'target_type' => 'categoria', 'target_id' => 9, 'active' => 1,
        ]);
        $visibleParaSuDueno = in_array($storeIdOtra, array_map('intval', array_column(Menu::visibleRows($otraStore), 'id')), true);
        $invisibleParaOtra = in_array($storeIdOtra, array_map('intval', array_column(Menu::visibleRows($editorStore), 'id')), true);
        check($propioOtra['ok'] && $arrancado['ok'] === false && $visibleParaSuDueno && !$invisibleParaOtra,
            'los nodos de otra tienda no se pueden modificar (aunque se conozca el id)');

        // --- Visibilidad por tienda (solo plataforma) ---
        $compartido = MenuAdmin::create(0, true, [
            'parent_id' => null, 'label' => 'Solo para una tienda', 'target_type' => 'categoria',
            'target_id' => 9, 'active' => 1, 'owner' => 0, 'visibility' => 'todas',
        ]);
        $compId = (int) ($compartido['id'] ?? 0);
        $vis = MenuAdmin::saveVisibility($compId, 'solo', [$otraStore], 0, true);
        $idsEditor = array_map('intval', array_column(Menu::visibleRows($editorStore), 'id'));
        $idsOtra = array_map('intval', array_column(Menu::visibleRows($otraStore), 'id'));
        check($vis['ok'] && !in_array($compId, $idsEditor, true) && in_array($compId, $idsOtra, true),
            'visibility=solo: el nodo compartido se ve en la tienda marcada y no en la otra');

        $visExcepto = MenuAdmin::saveVisibility($compId, 'excepto', [$otraStore], 0, true);
        $idsEditor = array_map('intval', array_column(Menu::visibleRows($editorStore), 'id'));
        $idsOtra = array_map('intval', array_column(Menu::visibleRows($otraStore), 'id'));
        check($visExcepto['ok'] && in_array($compId, $idsEditor, true) && !in_array($compId, $idsOtra, true),
            'visibility=excepto: se ve en todas menos en la marcada');

        check(MenuAdmin::saveVisibility($compId, 'solo', [$otraStore], $editorStore, false)['ok'] === false,
            'una tienda no puede repartir el menu entre tiendas');

        // --- Nivel minimo de cliente ---
        $nivelTelefonia = 0;
        foreach (Menu::customerLevels() as $nivel) {
            if ((int) $nivel['order'] === 2) {
                $nivelTelefonia = (int) $nivel['id'];
            }
        }
        $nivelado = MenuAdmin::create(0, true, [
            'parent_id' => null, 'label' => 'Solo niveles altos', 'target_type' => 'categoria',
            'target_id' => 9, 'active' => 1, 'owner' => 0, 'visibility' => 'todas',
            'min_customer_level' => $nivelTelefonia,
        ]);
        $nivelId = (int) ($nivelado['id'] ?? 0);
        $idsEditor = array_map('intval', array_column(Menu::visibleRows($editorStore), 'id'));
        check($nivelado['ok'] && $nivelId > 0 && !in_array($nivelId, $idsEditor, true),
            'un nodo con nivel minimo no se muestra a una tienda sin nivel asignado');
        check(Menu::storeLevelOrder($editorStore) === null, 'una tienda sin cuenta del mayorista no tiene nivel');

        // --- Publicacion (y cache) ---
        $versionAntes = Menu::publishedVersion($editorStore);
        $patronCache = TIENDA_BASE . '/storage/cache/menu_tree_v*_' . $editorStore . '_*.json';
        $pub = Menu::publish($editorStore, null);
        check($pub['ok'] && $pub['version'] === $versionAntes + 1, 'publicar sube la version (' . (int) $pub['version'] . ')');
        check(Menu::publishedVersion($editorStore) === $versionAntes + 1 && Menu::publishedAt($editorStore) !== null,
            'la version publicada queda guardada con su fecha');
        check(Menu::hasDraftChanges($editorStore) === false, 'justo despues de publicar no hay cambios pendientes');
        check((int) (Menu::publishedTree($editorStore)['links'] ?? 0) > 0, 'lo publicado incluye los tres niveles');
        check((array) glob($patronCache) === [], 'publicar invalida la cache del arbol');
        $publicado = Menu::forStore($editorStore);
        check(((array) ($publicado['categories'] ?? [])) !== [], 'la tienda sirve el arbol publicado');
        check((int) Database::scalar(
            'SELECT COUNT(*) FROM mt_menu_revisions WHERE store_id = :s AND version = :v',
            ['s' => $editorStore, 'v' => $pub['version']]
        ) === 1, 'cada publicacion deja su revision en el historico');

        // Un cambio en el borrador NO se ve hasta publicar (sobre un destino de
        // tercer nivel, que es el que aparece en el arbol pintado).
        MenuAdmin::update((int) $nivel3['id'], $editorStore, false, [
            'parent_id' => (int) $nivel2['id'], 'label' => 'Cambio sin publicar',
            'target_type' => 'subcategoria', 'target_id' => 102, 'active' => 1,
        ]);
        $publicadoAhora = Menu::publishedTree($editorStore);
        $labelsPublicados = array_column((array) ($publicadoAhora['categories'] ?? []), 'label');
        check(Menu::hasDraftChanges($editorStore) === true && !in_array('Cambio sin publicar', $labelsPublicados, true),
            'el borrador no se ve en la tienda hasta publicar');

        // --- Borrado en cascada ---
        $hijosAntes = (int) Database::scalar('SELECT COUNT(*) FROM mt_menu_items WHERE parent_id = :p', ['p' => $catId])
            + (int) Database::scalar('SELECT COUNT(*) FROM mt_menu_items WHERE parent_id = :p', ['p' => (int) $nivel2['id']]);
        $borrado = MenuAdmin::delete($catId, $editorStore, false);
        $hijosDespues = (int) Database::scalar('SELECT COUNT(*) FROM mt_menu_items WHERE parent_id = :p', ['p' => $catId])
            + (int) Database::scalar('SELECT COUNT(*) FROM mt_menu_items WHERE parent_id = :p', ['p' => (int) $nivel2['id']]);
        check($borrado['ok'] && $hijosAntes > 0 && $hijosDespues === 0,
            'borrar un nodo se lleva su rama por delante');
    } catch (\Throwable $e) {
        check(false, 'el editor del menu no lanza excepciones (' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . ')');
    }

    $pdo->rollBack();
    check(true, 'la prueba del editor deshace sus datos de prueba (transaccion)');
}

echo "\n==============================================================\n";
if ($fail === 0) {
    echo " RESULTADO: TODO OK ($ok comprobaciones)\n";
    exit(0);
}
echo " RESULTADO: $fail fallo(s) de " . ($ok + $fail) . " comprobaciones\n";
exit(1);
