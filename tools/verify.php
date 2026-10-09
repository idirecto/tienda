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
use Tienda\Core\Cache;
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
use Tienda\Models\OwnProduct;
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

    // El buscador en vivo (desplegable de la cabecera) tiene su propio tamano,
    // aparte del de los listados: si no, subir los listados lo alargaria.
    $buscador = (int) config('catalog.search_per_page', 16);
    check($buscador >= 8 && $buscador <= 24, 'resultados del buscador en vivo (' . $buscador . ')');

    // El limite de los listados no se queda en 12: es el fallo que se corrigio.
    check((int) config('catalog.per_page') >= 40, 'los listados sirven 40 productos por pagina o mas');

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

// Stock real del catalogo: el carrito nunca deja pedir mas unidades de las que hay.
$stockCatalogo = Catalog::stockTotal(254967);
check(
    $stockCatalogo !== null && $stockCatalogo > 0,
    'el carrito conoce el stock real del catalogo (' . (int) $stockCatalogo . ' uds)'
);
$esperadoStock = min((int) $stockCatalogo, Cart::MAX_QTY);
$sobreStock = Cart::add(1, 'catalog', 254967, $esperadoStock + 50);
check(
    $sobreStock['ok'] && Cart::count(1) === $esperadoStock,
    'anadir mas unidades de las que hay se recorta al stock (' . Cart::count(1) . ' uds)'
);
$miniCarrito = Cart::miniViewData(1, [
    'id' => 1, 'shipping_flat' => 4.95, 'free_shipping_from' => 60.0, 'allow_orders' => 1,
]);
check(
    count($miniCarrito['items']) === 1 && (int) $miniCarrito['count'] === $esperadoStock
        && (int) $miniCarrito['maxQty'] === Cart::MAX_QTY && $miniCarrito['totals']['total'] > 0,
    'el mini-carrito comparte el estado real del carrito (lineas, totales y contador)'
);
$avisosStock = Cart::updateQuantities(1, [Cart::key('catalog', 254967) => $esperadoStock + 50]);
check(
    Cart::count(1) === $esperadoStock && $avisosStock !== [],
    'cambiar la cantidad por encima del stock se recorta y avisa'
);
Cart::remove(1, Cart::key('catalog', 254967));
check(Cart::count(1) === 0, 'el carrito queda limpio tras la prueba de stock');

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

// Destinos propios del menu: /propios, /pagina/{slug} y el enlace de cada nodo.
check(CatalogUrl::ownProductsPath() === '/propios' && CatalogUrl::pagePath('aviso legal') === '/pagina/aviso%20legal',
    'rutas de los destinos propios del menu (/propios y /pagina/{slug})');
check(menu_href('/tienda', '/propios') === '/tienda/propios'
    && menu_href('/tienda', 'https://ejemplo.test/x') === 'https://ejemplo.test/x',
    'menu_href antepone la base a las rutas y respeta los enlaces absolutos');

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

        // --- Que categorias se ven: modo completo/elegido y anulaciones ---
        check((bool) Database::scalar(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mt_stores' AND COLUMN_NAME = 'menu_scope'"
        ) && Database::tableExists('mt_menu_item_overrides'),
            'migracion 009: modo de menu y anulaciones por tienda');
        check(count(Menu::scopes()) === 2 && isset(Menu::scopes()['completo'], Menu::scopes()['elegido'])
            && Menu::scope(['menu_scope' => 'inventado']) === (string) config('menu.fallback_scope', 'completo'),
            'solo existen dos modos de menu (completo/elegido) y se sanean');

        // Un nodo de la siembra (visible en modo completo y sin anulacion previa)
        // para probar el modo «elegido».
        $idsCompleto = array_map('intval', array_column(Menu::visibleRows($editorStore), 'id'));
        $overridesPrevias = Menu::overridesFor($editorStore);
        $seedId = 0;
        foreach ($idsCompleto as $candidato) {
            if (isset($overridesPrevias[$candidato])) {
                continue;
            }
            $esCategoria = (int) Database::scalar(
                'SELECT COUNT(*) FROM mt_menu_items WHERE id = :id AND level = 1',
                ['id' => $candidato]
            );
            if ($esCategoria === 1) {
                $seedId = $candidato;
                break;
            }
        }

        Database::update('mt_stores', $editorStore, ['menu_scope' => 'elegido']);
        Menu::invalidate($editorStore);
        $idsElegido = array_map('intval', array_column(Menu::visibleRows($editorStore), 'id'));
        Database::update('mt_stores', $editorStore, ['menu_scope' => 'completo']);
        Menu::invalidate($editorStore);
        check($seedId > 0 && in_array($seedId, $idsElegido, true) === false,
            'en modo «elegido» un nodo sin marcar deja de verse');

        $marca = MenuAdmin::setOverride($seedId, $editorStore, 'visible', 'Nombre propio');
        Menu::invalidate($editorStore);
        $porItem = [];
        foreach (Menu::draftTree($editorStore)['categories'] as $c) {
            $porItem[(int) $c['item_id']] = $c;
        }
        check($marca['ok'] && isset($porItem[$seedId]) && $porItem[$seedId]['label'] === 'Nombre propio',
            'marcar una categoria la muestra con el nombre propio de la tienda');
        check((string) Database::scalar('SELECT label FROM mt_menu_items WHERE id = :id', ['id' => $seedId])
            !== 'Nombre propio',
            'la anulacion no toca el nombre del nodo del arbol');

        MenuAdmin::setOverride($seedId, $editorStore, 'oculto', '');
        Menu::invalidate($editorStore);
        $ocultos = array_map('intval', array_column(Menu::visibleRows($editorStore), 'id'));
        check(!in_array($seedId, $ocultos, true), 'ocultar una categoria la quita de la web');

        // Ocultar una categoria se lleva su rama entera.
        MenuAdmin::setOverride($catId, $editorStore, 'oculto', '');
        Menu::invalidate($editorStore);
        $idsConRama = array_map('intval', array_column(Menu::visibleRows($editorStore), 'id'));
        check(!in_array($catId, $idsConRama, true) && !in_array((int) $nivel2['id'], $idsConRama, true),
            'ocultar una categoria se lleva su rama por delante');
        MenuAdmin::clearOverride($catId, $editorStore);
        MenuAdmin::clearOverride($seedId, $editorStore);
        Menu::invalidate($editorStore);

        // --- Aislamiento de las anulaciones ---
        $roboAnulacion = MenuAdmin::setOverride($catId, $otraStore, 'oculto', '');
        check($roboAnulacion['ok'] === false && (Menu::overridesFor($otraStore)[$catId] ?? null) === null,
            'una tienda no puede anular un nodo de otra');

        // --- Destinos propios: /propios, pagina de la tienda y nodo bajo compartido ---
        $propia = MenuAdmin::create($editorStore, false, [
            'parent_id' => null, 'label' => 'Productos propios', 'slug' => 'productos-propios',
            'target_type' => 'propios', 'active' => 1,
        ]);
        $propiaId = (int) ($propia['id'] ?? 0);
        $enArbol = null;
        foreach (Menu::draftTree($editorStore)['categories'] as $c) {
            if ((int) $c['item_id'] === $propiaId) {
                $enArbol = $c;
            }
        }
        check($propia['ok'] && $enArbol !== null && $enArbol['path'] === '/propios' && $enArbol['groups'] === [],
            'una categoria propia sin grupos se pinta con su destino (/propios)');

        $slugPagina = 'pagina-prueba-' . substr((string) time(), -5);
        Database::insert('mt_content_blocks', [
            'store_id' => $editorStore, 'slug' => $slugPagina, 'title' => 'Pagina de prueba', 'active' => 1,
        ]);
        $pagina = MenuAdmin::create($editorStore, false, [
            'parent_id' => null, 'label' => 'Servicio', 'slug' => 'servicio',
            'target_type' => 'pagina', 'target_key' => $slugPagina, 'active' => 1,
        ]);
        $malPagina = MenuAdmin::create($editorStore, false, [
            'parent_id' => null, 'label' => 'Pagina rota', 'target_type' => 'pagina',
            'target_key' => 'no-existe-jamas', 'active' => 1,
        ]);
        check($pagina['ok'] && $malPagina['ok'] === false,
            'el destino «pagina de la tienda» exige una pagina que exista');

        $dentroCompartida = MenuAdmin::create($editorStore, false, [
            'parent_id' => $compId, 'label' => 'Servicio dentro de compartida',
            'target_type' => 'url', 'url' => '/contacto', 'active' => 1,
        ]);
        $filaDentro = MenuAdmin::node((int) ($dentroCompartida['id'] ?? 0), $editorStore, false);
        check($dentroCompartida['ok'] && (int) ($filaDentro['level'] ?? 0) === 2
            && (int) ($filaDentro['store_id'] ?? 0) === $editorStore,
            'la tienda puede colgar un nodo propio dentro de una categoria compartida');
        $compartidaEnArbol = null;
        foreach (Menu::draftTree($editorStore)['categories'] as $c) {
            if ((int) $c['item_id'] === $compId) {
                $compartidaEnArbol = $c;
            }
        }
        $gruposCompartida = array_column((array) ($compartidaEnArbol['groups'] ?? []), 'label');
        check(in_array('Servicio dentro de compartida', $gruposCompartida, true),
            'ese nodo propio se pinta dentro del grupo del nodo compartido');

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

        // --- Buscador: el alcance sigue al menu visible ---
        // La categoria 46 (Ocio) no esta en el menu sembrado: solo entra en el
        // alcance del buscador si la tienda la muestra con un nodo propio.
        $sinNodo = Menu::searchScope($editorStore);
        $nodoOcio = MenuAdmin::create($editorStore, false, [
            'parent_id' => null, 'label' => 'Ocio propio', 'slug' => 'ocio-propio',
            'target_type' => 'categoria', 'target_id' => 46, 'active' => 1,
        ]);
        Menu::invalidate($editorStore);
        $conNodo = Menu::searchScope($editorStore);
        MenuAdmin::setOverride((int) ($nodoOcio['id'] ?? 0), $editorStore, 'oculto', '');
        Menu::invalidate($editorStore);
        $conOculto = Menu::searchScope($editorStore);
        check(!in_array(46, $sinNodo['categories'], true)
            && in_array(46, $conNodo['categories'], true)
            && !in_array(46, $conOculto['categories'], true),
            'el alcance del buscador sigue las categorias que muestra el menu (visible/oculta)');
    } catch (\Throwable $e) {
        check(false, 'el editor del menu no lanza excepciones (' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . ')');
    }

    $pdo->rollBack();
    check(true, 'la prueba del editor deshace sus datos de prueba (transaccion)');
}

// ---------------------------------------------------------------------------
echo "\n== Buscador: alcance del menu, catalogo y productos propios ==\n";
// ---------------------------------------------------------------------------
// El buscador del storefront (accion `searchLive`) devuelve solo lo que la
// tienda muestra en su menu, mas sus productos propios. Aqui se comprueba el
// modelo (menu -> alcance -> consultas) sin depender de la UI.
try {
    $searchStoreId = (int) (Database::scalar('SELECT id FROM mt_stores ORDER BY id ASC LIMIT 1') ?? 0);
    $searchScope = Menu::searchScope($searchStoreId);
    check($searchScope['restricted'] && $searchScope['categories'] !== [] && $searchScope['subcategories'] !== [],
        sprintf('el alcance del buscador sale del menu visible (%d categorias, %d subcategorias)',
            count($searchScope['categories']), count($searchScope['subcategories'])));

    $allowedSubs = array_flip(array_map('intval', $searchScope['subcategories']));
    $allowedCats = array_flip(array_map('intval', $searchScope['categories']));

    $scoped = Catalog::paginate(1, 20, null, null, null, [], null, null, 'relevancia', 'todos', $searchScope);
    $enAlcance = $scoped['items'] !== [];
    foreach ($scoped['items'] as $item) {
        if (!isset($allowedSubs[(int) $item['id_subcategoria']])
            && !isset($allowedCats[(int) $item['id_categoria']])) {
            $enAlcance = false;
            break;
        }
    }
    check($scoped['total'] > 0 && $enAlcance,
        'el listado del buscador solo trae productos visibles en el menu (' . (int) $scoped['total'] . ')');

    // El alcance recorta de verdad (no es el catalogo entero).
    $sinAlcance = Catalog::paginate(1, 1, null, null, null, [], null, null, 'relevancia', 'todos');
    check((int) $scoped['total'] > 0 && (int) $scoped['total'] < (int) $sinAlcance['total'],
        'el alcance recorta el catalogo (' . (int) $scoped['total'] . ' < ' . (int) $sinAlcance['total'] . ')');

    // Facetas del buscador: subcategorias y marcas dentro del alcance.
    $searchFacets = Catalog::searchFacets('ssd', $searchScope, [], 20, 12);
    $facetsOk = $searchFacets['subcategories'] !== [] && $searchFacets['marcas'] !== [];
    foreach ($searchFacets['subcategories'] as $sub) {
        if (!isset($allowedSubs[(int) $sub['id']])) {
            $facetsOk = false;
        }
    }
    check($facetsOk, 'las facetas del buscador (subcategorias y marcas) salen del alcance');

    // El alcance tambien limita el listado con texto: la busqueda no filtra
    // productos de categorias ocultas.
    $conTexto = Catalog::paginate(1, 5, 'ssd', null, null, [], null, null, 'relevancia', 'todos', $searchScope);
    $textoOk = $conTexto['total'] > 0;
    foreach ($conTexto['items'] as $item) {
        if (!isset($allowedSubs[(int) $item['id_subcategoria']])
            && !isset($allowedCats[(int) $item['id_categoria']])) {
            $textoOk = false;
            break;
        }
    }
    check($textoOk, 'la busqueda por texto respeta el alcance del menu (' . (int) $conTexto['total'] . ' para «ssd»)');

    // Accion y piezas de la UI.
    check(method_exists(\Tienda\Controllers\StorefrontController::class, 'searchLive'),
        'existe la accion searchLive del buscador en vivo');
    $layoutShop = (string) file_get_contents(TIENDA_BASE . '/app/Views/layouts/shop.php');
    check(str_contains($layoutShop, 'id="shop-search"') && str_contains($layoutShop, '/buscar/live'),
        'el layout pinta el panel del buscador y apunta a /buscar/live');
    $shopJs = (string) file_get_contents(TIENDA_BASE . '/public/assets/js/shop.js');
    check(str_contains($shopJs, 'initLiveSearch') && str_contains($shopJs, 'shop-search-chip'),
        'el JS del buscador en vivo (panel, facetas y fichas) esta presente');
    check(str_contains($shopJs, 'state.closing') && str_contains($shopJs, 'if (state.closing) { return; }'),
        'cerrar el buscador (Escape o boton) no lo reabre al devolver el foco al campo');
    // Un solo boton en la cabecera del panel: el de cerrar. El de borrar se quito
    // porque se dibujaban dos aspas iguales y confundian.
    check(str_contains($layoutShop, 'id="shop-search-close"')
        && !str_contains($layoutShop, 'shop-search-clear')
        && !str_contains($shopJs, 'els.clear'),
        'el buscador tiene un unico boton: el de cerrar todo el panel');

    // Rejilla de resultados: sin el contenedor .grid-products las tarjetas caen
    // una por linea en cualquier monitor (era el fallo de visibilidad). El numero
    // de columnas lo decide el CSS segun el ancho del panel.
    $storefrontSrc = (string) file_get_contents(TIENDA_BASE . '/app/Controllers/StorefrontController.php');
    check(substr_count($storefrontSrc, '\'<div class="grid grid-products">\'') === 2,
        'el buscador en vivo envuelve sus tarjetas en la rejilla (catalogo y productos propios)');
    $shopCss = (string) file_get_contents(TIENDA_BASE . '/public/assets/css/shop.css');
    $posGrid = strpos($shopCss, '.shop-search-results .grid-products');
    $bloqueGrid = $posGrid === false ? '' : substr($shopCss, $posGrid, 700);
    check(str_contains($bloqueGrid, 'repeat(auto-fill, minmax(200px, 1fr))'),
        'la rejilla del buscador usa auto-fill y gana columnas en pantallas grandes');
    check(str_contains($bloqueGrid, '@media (max-width: 639px)')
        && str_contains($bloqueGrid, 'grid-template-columns: minmax(0, 1fr)'),
        'en el movil el buscador enseña una tarjeta por linea');

    // --- Tarjeta de producto: toda la tarjeta lleva a la ficha --------------
    // El enlace "estirado" del nombre tiene que quedar POR ENCIMA de la imagen
    // (si no, la foto se come el clic: era el fallo original) y POR DEBAJO de
    // las acciones, para que "Agregar al carrito" siga siendo independiente.
    preg_match('/^\.product-name a::after\s*\{[^}]*z-index:\s*(\d+)/m', $shopCss, $mOverlay);
    preg_match('/^\.product-media img\s*\{[^}]*z-index:\s*(\d+)/m', $shopCss, $mImagen);
    preg_match('/^\.product-actions\s*\{[^}]*z-index:\s*(\d+)/m', $shopCss, $mAcciones);
    $zImagen = (int) ($mImagen[1] ?? 0);
    $zOverlay = (int) ($mOverlay[1] ?? 0);
    $zAcciones = (int) ($mAcciones[1] ?? 0);
    check($zOverlay > $zImagen && $zAcciones > $zOverlay,
        'imagen, marca, specs y precio llevan a la ficha y las acciones quedan por encima (z-index '
        . $zImagen . ' < ' . $zOverlay . ' < ' . $zAcciones . ')');

    $cardViewMarkup = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_card.php');
    check(str_contains($cardViewMarkup, '_eager') && str_contains($cardViewMarkup, 'fetchpriority="high"')
        && str_contains($cardViewMarkup, "'eager' : 'lazy'"),
        'la primera fila de imagenes carga de inmediato (LCP) y el resto va en diferido');

    // --- Paginacion comun a los listados ------------------------------------
    $paginationView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_pagination.php');
    $catalogViewSrc = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/catalog.php');
    check(str_contains($paginationView, 'rel="prev"')
        && str_contains($paginationView, 'rel="next"')
        && str_contains($paginationView, 'aria-current="page"')
        && str_contains($paginationView, '$pageUrl')
        && str_contains($catalogViewSrc, '_pagination.php'),
        'paginacion comun: Anterior/Siguiente, pagina actual y URLs construidas por el listado');
    check(str_contains($paginationView, 'page-info') && str_contains($paginationView, 'Pagina ')
        && str_contains($paginationView, 'productos'),
        'la paginacion dice la pagina actual, el total de paginas y el total de productos');

    // HTML limpio: nada de elementos decorativos vacios (el validador los marca).
    $menuView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_menu.php');
    $cardView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_card.php');
    $heroView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_hero.php');
    $panelCss = (string) file_get_contents(TIENDA_BASE . '/public/assets/css/panel.css');
    check(!str_contains($menuView, 'mn-caret" aria-hidden="true"></span>')
        && !str_contains($menuView, 'mn-arrow" aria-hidden="true"></span>')
        && !str_contains($cardView, '<i class="dot"')
        && !str_contains($heroView, 'aria-label="Banner <?= $i + 1 ?>"></button>')
        && !str_contains($panelCss, '.logo-dot')
        && str_contains(icon_svg('chevron-d', 'mn-caret'), 'class="mn-caret"'),
        'los iconos decorativos (caret, flecha, punto de stock, punto del slider y logo) tienen contenido real');
    check((new Tenant(['phone' => '+34 91 123 45 67']))->phoneHref() === '+34911234567'
        && (new Tenant(['phone' => '91 123 45 67']))->phoneHref() === '911234567'
        && (new Tenant(['phone' => 'sin numeros']))->phoneHref() === '',
        'el telefono se convierte en una URI tel: valida');
} catch (\Throwable $e) {
    check(false, 'el buscador del storefront no lanza excepciones (' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine() . ')');
}

// Aislamiento de los productos propios: cada tienda busca solo los suyos.
$pdoPropios = Database::pdo();
$pdoPropios->beginTransaction();
try {
    $tUno = (int) Database::insert('mt_stores', ['slug' => 'propios-uno-' . substr((string) time(), -5), 'name' => 'Tienda propios 1', 'status' => 1]);
    $tDos = (int) Database::insert('mt_stores', ['slug' => 'propios-dos-' . substr((string) time(), -6), 'name' => 'Tienda propios 2', 'status' => 1]);
    Database::insert('mt_own_products', ['store_id' => $tUno, 'sku' => 'Z-' . $tUno, 'name' => 'Producto exclusivo ZETA', 'price' => 10, 'stock' => 1, 'status' => 1]);
    Database::insert('mt_own_products', ['store_id' => $tDos, 'sku' => 'Z-' . $tDos, 'name' => 'Producto exclusivo ZETA', 'price' => 10, 'stock' => 1, 'status' => 1]);
    Database::insert('mt_own_products', ['store_id' => $tUno, 'sku' => 'Z-OCULTO', 'name' => 'Producto exclusivo ZETA oculto', 'price' => 10, 'stock' => 1, 'status' => 0]);

    $uno = OwnProduct::searchPublished($tUno, 'ZETA');
    $dos = OwnProduct::searchPublished($tDos, 'ZETA');
    check(count($uno) === 1 && (int) $uno[0]['store_id'] === $tUno,
        'la busqueda de productos propios se limita a la tienda que se ve');
    check(count($dos) === 1 && (int) $dos[0]['store_id'] === $tDos && (int) $uno[0]['id'] !== (int) $dos[0]['id'],
        'dos tiendas con el mismo nombre de producto no se mezclan');
    check((int) Database::scalar('SELECT COUNT(*) FROM mt_own_products WHERE store_id = :s AND status = 0', ['s' => $tUno]) === 1
        && count(OwnProduct::searchPublished($tUno, 'ZETA oculto')) === 0,
        'los productos propios en borrador u ocultos no salen en el buscador');
} catch (\Throwable $e) {
    check(false, 'la busqueda de productos propios no lanza excepciones (' . $e->getMessage() . ')');
}
$pdoPropios->rollBack();
check(true, 'la prueba del buscador de propios deshace sus datos (transaccion)');

// =============================================================================
// Carrito: boton «Agregar al carrito» y mini-carrito lateral.
// El mini-carrito es una vista del MISMO carrito de sesion; no hay carrito
// paralelo ni se duplica ninguna ruta funcional.
// =============================================================================
echo "\n== Carrito: boton y mini-carrito ==\n";
$cardView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_card.php');
$cardOwnView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_card_own.php');
$productView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/product.php');
$miniView = (string) file_get_contents(TIENDA_BASE . '/app/Views/themes/idirecto/_mini_cart.php');
$layoutShop = (string) file_get_contents(TIENDA_BASE . '/app/Views/layouts/shop.php');
$shopJs = (string) file_get_contents(TIENDA_BASE . '/public/assets/js/shop.js');
$shopCss = (string) file_get_contents(TIENDA_BASE . '/public/assets/css/shop.css');
$cartController = (string) file_get_contents(TIENDA_BASE . '/app/Controllers/CartController.php');
$indexSrc = (string) file_get_contents(TIENDA_BASE . '/index.php');

check(
    str_contains($cardView, 'Agregar al carrito')
        && str_contains($cardOwnView, 'Agregar al carrito')
        && str_contains($productView, 'Agregar al carrito')
        && !str_contains($productView, 'Añadir al carrito'),
    'el boton dice exactamente «Agregar al carrito» en tarjetas y ficha'
);
check(
    str_contains($layoutShop, 'id="minicart"')
        && str_contains($layoutShop, 'data-minicart-url')
        && str_contains($layoutShop, 'name="csrf-token"'),
    'el layout incluye el mini-carrito y el token para las llamadas AJAX'
);
check(
    str_contains($indexSrc, "'/carrito/mini'")
        && str_contains($indexSrc, "'/carrito/anadir'")
        && str_contains($indexSrc, "'/carrito/actualizar'")
        && str_contains($indexSrc, "'/carrito/quitar'")
        && str_contains($indexSrc, "'/carrito/vaciar'"),
    'el mini-carrito reutiliza los endpoints del carrito y solo anade la lectura /carrito/mini'
);
check(
    method_exists(\Tienda\Controllers\CartController::class, 'mini')
        && str_contains($cartController, 'isAjax()')
        && str_contains($cartController, '_mini_cart'),
    'el carrito responde JSON en AJAX y conserva el formulario normal sin JS'
);
check(
    str_contains($miniView, 'data-minicart-step')
        && str_contains($miniView, 'data-minicart-input')
        && str_contains($miniView, 'data-minicart-remove')
        && str_contains($miniView, 'Ver artículos del carrito')
        && str_contains($miniView, 'Ver carrito'),
    'el mini-carrito tiene +/-, cantidad editable, quitar y la CTA de escritorio y movil'
);
check(
    str_contains($shopJs, 'data-minicart-url')
        && str_contains($shopJs, 'shop-minicart-open')
        && str_contains($shopJs, "e.key === 'Escape'")
        && str_contains($shopJs, 'data-minicart-overlay'),
    'el JS abre y cierra el mini-carrito (boton, Escape y capa exterior) sin bloquear el scroll'
);
check(
    str_contains($shopCss, '100dvh')
        && str_contains($shopCss, 'safe-area-inset-top')
        && str_contains($shopCss, 'env(safe-area-inset-bottom'),
    'el mini-carrito es responsive y respeta las safe-areas del movil'
);

// =============================================================================
// Cache de datos: driver, memo, bloqueo y, sobre todo, precios en vivo.
// =============================================================================
echo "\n== Cache de datos ==\n";
try {
    check(in_array(Cache::driverName(), ['file', 'apcu'], true),
        'la cache resuelve un driver valido (' . Cache::driverName() . ')');

    $clave = 'prueba_verify_' . bin2hex(random_bytes(4));
    Cache::set($clave, ['n' => 42, 'lista' => [1, 2, 3]], 60);
    $leido = Cache::get($clave);
    check(is_array($leido) && ($leido['n'] ?? null) === 42 && ($leido['lista'] ?? []) === [1, 2, 3],
        'la cache guarda y devuelve estructuras complejas');
    check(Cache::get('no_existe_' . $clave, 'defecto') === 'defecto',
        'una clave que no existe devuelve el valor por defecto');
    check(Cache::delete($clave) === true && Cache::get($clave, 'borrado') === 'borrado',
        'la cache borra una clave');

    $calculos = 0;
    $k = 'prueba_verify_remember_' . bin2hex(random_bytes(4));
    $a = Cache::remember($k, 60, static function () use (&$calculos): int { $calculos++; return 7; });
    $b = Cache::remember($k, 60, static function () use (&$calculos): int { $calculos++; return 9; });
    check($a === 7 && $b === 7 && $calculos === 1,
        'remember solo calcula una vez (memo por peticion incluido)');
    check(Cache::forgetPattern($k) >= 1 && Cache::get($k, 'fuera') === 'fuera',
        'forgetPattern borra por patron (conservando los comodines)');

    // Limpieza: un fichero caducado y viejo desaparece al forzar el gc().
    $dir = (string) config('cache.path');
    $viejo = $dir . '/catalog_prueba_gc_verify.json';
    file_put_contents($viejo, json_encode(['v' => 1, 'e' => time() - 10, 't' => time() - 100]));
    touch($viejo, time() - (int) config('cache.gc_max_age', 172800) - 86400);
    Cache::gc(true);
    check(!is_file($viejo), 'la limpieza borra las entradas caducadas y viejas');

    check(is_file(TIENDA_BASE . '/tools/cache-clear.php'),
        'existe la herramienta de linea de comandos para vaciar la cache');
} catch (\Throwable $e) {
    check(false, 'la cache de datos no lanza excepciones (' . $e->getMessage() . ')');
}

// Los destacados cachean SOLO la seleccion de ids: el precio va en vivo.
try {
    $fids = Catalog::featured(6);
    check(count($fids) > 0, 'la portada resuelve productos destacados');
    $seleccion = Cache::get('catalog_featured_ids_0_6');
    check(is_array($seleccion) && $seleccion !== [] && $seleccion === array_values(array_filter($seleccion, 'is_int')),
        'los destacados cachean solo la lista de ids (numeros), no el precio');
    $primero = $fids[0] ?? null;
    $ficha = $primero === null ? null : Catalog::find((int) $primero['id']);
    check($primero !== null && $ficha !== null
        && (float) $primero['price_final'] === (float) $ficha['price_final'],
        'el precio de la portada coincide con la ficha en vivo (no hay precio cacheado)');

    // Cambiar el beneficio cambia el precio sin tocar la cache: solo se
    // cachearon los ids, asi que la portada nunca muestra un precio viejo.
    Catalog::forStore(null, 0.0, 21.0, false);
    $sinBeneficio = (float) (Catalog::featured(6)[0]['price_final'] ?? 0);
    Catalog::forStore(null, 50.0, 21.0, false);
    $conBeneficio = (float) (Catalog::featured(6)[0]['price_final'] ?? 0);
    Catalog::forStore(null, 0.0, 21.0, false);
    check($sinBeneficio > 0 && $conBeneficio > $sinBeneficio,
        'el precio de la portada se recalcula en vivo, sin esperar a que caduque la cache');
} catch (\Throwable $e) {
    check(false, 'los destacados no lanzan excepciones (' . $e->getMessage() . ')');
}

// =============================================================================
// Sin credenciales por defecto
// El escaparate no enlaza al panel, el login no llega relleno y la semilla no
// crea el usuario demo. Antes la propia pantalla de acceso publicaba
// «Demo: admin@demo.test / demo1234» y ese usuario existia en la base de datos.
// =============================================================================
echo "\n== Sin credenciales por defecto ==\n";
$layoutShop = (string) file_get_contents(TIENDA_BASE . '/app/Views/layouts/shop.php');
$loginView  = (string) file_get_contents(TIENDA_BASE . '/app/Views/panel/login.php');
$seedSql    = (string) file_get_contents(TIENDA_BASE . '/database/seeds/001_seed.sql');
$shopCss    = (string) file_get_contents(TIENDA_BASE . '/public/assets/css/shop.css');

check(!str_contains($layoutShop, '/panel"') && !str_contains($layoutShop, 'btn-panel'),
    'el escaparate no enlaza al panel (ni conserva el boton «Mi panel»)');
check(!str_contains($shopCss, '.btn-panel'),
    'el CSS no conserva el boton «Mi panel»');
check(!str_contains($loginView, 'admin@demo.test') && !str_contains($loginView, 'demo1234')
    && !preg_match('/name="(email|password)"[^>]*value=/', $loginView),
    'el login no precarga usuario ni contrasena ni muestra credenciales demo');
check(!str_contains($seedSql, 'admin@demo.test') && !str_contains($seedSql, 'demo1234'),
    'la semilla no crea credenciales por defecto');
try {
    $demo = (int) Database::scalar(
        'SELECT COUNT(*) FROM mt_store_users WHERE email = :email',
        ['email' => 'admin@demo.test']
    );
    check($demo === 0, 'la base de datos no tiene el usuario demo (admin@demo.test)');
} catch (\Throwable $e) {
    check(false, 'comprobacion del usuario demo en la base de datos (' . $e->getMessage() . ')');
}

// =============================================================================
// Nivel de cliente: diagnostico accionable
// El aviso del menu ya no se limita a decir que falta el nivel: dice QUE falta
// (cuenta sin enlazar, id inexistente, cuenta sin categoria) y donde se arregla.
// =============================================================================
echo "\n== Nivel de cliente: diagnostico accionable ==\n";

// Coherencia en todas las tiendas: si no hay nivel, siempre hay motivo.
$coherente = true;
$sinCuenta = null;
foreach (Database::select('SELECT id, id_tienda_idirecto FROM mt_stores ORDER BY id') as $fila) {
    $st = Menu::storeLevelStatus((int) $fila['id']);
    if (($st['level'] === null) !== ($st['reason'] !== null)) {
        $coherente = false;
        break;
    }
    if ($sinCuenta === null && (int) $fila['id_tienda_idirecto'] <= 0) {
        $sinCuenta = $st;
    }
}
check($coherente, 'storeLevelStatus: cuando no hay nivel siempre explica el motivo');

// 'sin_cuenta' dice donde se arregla (Ajustes) y lo pinta el editor.
if ($sinCuenta !== null) {
    $aviso = Menu::levelWarning($sinCuenta);
    check(
        ($sinCuenta['reason'] ?? '') === 'sin_cuenta'
            && str_contains($aviso, 'Ajustes')
            && !str_contains($aviso, '<'),
        'una tienda sin cuenta enlazada dice el motivo y que se arregla en Ajustes (texto plano)'
    );
} else {
    check(true, 'una tienda sin cuenta enlazada dice el motivo y que se arregla en Ajustes (no hay tiendas sin cuenta)');
}

// Un id de cuenta que no existe ya no se confunde con «cuenta sin categoria».
// La tienda de prueba se crea en una transaccion que se DESHACE.
$pdoNivel = Database::pdo();
$pdoNivel->beginTransaction();
try {
    $slugPrueba = 'verificacion-nivel-' . bin2hex(random_bytes(3));
    // Un id por encima del maximo real de `tiendas` para que no exista (la
    // columna `mt_stores.id_tienda_idirecto` es smallint unsigned: tope 65535).
    $cuentaFantasma = min(65535, (int) Database::scalar('SELECT COALESCE(MAX(id), 0) + 1 FROM tiendas'));
    Database::execute(
        'INSERT INTO mt_stores (slug, name, id_tienda_idirecto) VALUES (:slug, :name, :cuenta)',
        ['slug' => $slugPrueba, 'name' => 'Verificacion nivel', 'cuenta' => $cuentaFantasma]
    );
    $storePrueba = (int) Database::scalar('SELECT id FROM mt_stores WHERE slug = :s', ['s' => $slugPrueba]);
    $stPrueba = Menu::storeLevelStatus($storePrueba);
    check(
        ($stPrueba['reason'] ?? '') === 'cuenta_inexistente'
            && str_contains(Menu::levelWarning($stPrueba), 'Ajustes'),
        'una cuenta que no existe en el mayorista se detecta aparte y manda a Ajustes'
    );
} catch (\Throwable $e) {
    check(false, 'diagnostico de la cuenta inexistente (' . $e->getMessage() . ')');
} finally {
    $pdoNivel->rollBack();
}

// El caso de la cuenta sin categoria explica quien tiene que asignarla.
$avisoCategoria = Menu::levelWarning(['reason' => 'sin_categoria', 'account_id' => 9363]);
check(
    str_contains($avisoCategoria, '9363') && str_contains($avisoCategoria, 'idirecto')
        && !str_contains($avisoCategoria, '<'),
    'una cuenta sin categoria de cliente explica que la asigna idirecto (texto plano)'
);

// Ajustes muestra el nivel y el editor y la plataforma pintan el aviso.
$settingsView = (string) file_get_contents(TIENDA_BASE . '/app/Views/panel/settings.php');
$menuView = (string) file_get_contents(TIENDA_BASE . '/app/Views/panel/menu.php');
$plataformaView = (string) file_get_contents(TIENDA_BASE . '/app/Views/panel/menu_plataforma.php');
check(
    str_contains($settingsView, 'Nivel de cliente') && str_contains($settingsView, 'nivelAviso'),
    'Ajustes muestra el nivel de cliente de la cuenta y el aviso cuando falta'
);
check(
    str_contains($menuView, 'nivelAviso') && str_contains($menuView, '/panel/ajustes')
        && str_contains($plataformaView, 'storeLevelWarning'),
    'el editor del menu y el panel de plataforma pintan el aviso accionable'
);

echo "\n==============================================================\n";
if ($fail === 0) {
    echo " RESULTADO: TODO OK ($ok comprobaciones)\n";
    exit(0);
}
echo " RESULTADO: $fail fallo(s) de " . ($ok + $fail) . " comprobaciones\n";
exit(1);
