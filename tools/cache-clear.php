<?php

declare(strict_types=1);

/**
 * Gestion de la cache de datos desde la linea de comandos.
 *
 *   php tools/cache-clear.php                 -> estado (driver, ficheros, peso)
 *   php tools/cache-clear.php all             -> vacia TODA la cache
 *   php tools/cache-clear.php catalog         -> solo las claves del catalogo
 *   php tools/cache-clear.php menu            -> solo los arboles de menu
 *   php tools/cache-clear.php precios         -> rango de precios (lo que guarda
 *                                                importes; util tras un cambio
 *                                                de tarifa del mayorista)
 *   php tools/cache-clear.php patron '<glob>' -> un patron propio (p.ej. 'count_page_*')
 *   php tools/cache-clear.php gc              -> pasa la limpieza ahora mismo
 *
 * Con el driver APCu el `flush` afecta a todo el pool de la aplicacion; con el
 * de fichero, a `storage/cache`.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\Cache;

$accion = strtolower(trim((string) ($argv[1] ?? 'estado')));
$arg = (string) ($argv[2] ?? '');

$dir = (string) config('cache.path', TIENDA_BASE . '/storage/cache');
$ficheros = is_dir($dir) ? (glob($dir . '/*.json') ?: []) : [];
$peso = 0;
foreach ($ficheros as $f) {
    $peso += (int) @filesize($f);
}

echo "Cache de datos\n";
echo "  driver   : " . Cache::driverName() . "  (configurado: " . config('cache.driver', 'auto') . ")\n";
echo "  APCu     : " . (Cache::apcuAvailable() ? 'disponible' : 'no instalada (se usa fichero)') . "\n";
echo "  carpeta  : $dir\n";
printf("  ficheros : %d (%.2f MB)\n", count($ficheros), $peso / 1048576);

switch ($accion) {
    case 'estado':
        break;

    case 'all':
        Cache::flush();
        echo "  -> cache vaciada.\n";
        break;

    case 'catalog':
        $n = Cache::forgetPattern('catalog_*');
        echo "  -> $n clave(s) del catalogo borradas.\n";
        break;

    case 'menu':
        $n = Cache::forgetPattern('menu_*');
        echo "  -> $n clave(s) de menu borradas.\n";
        break;

    case 'precios':
    case 'price':
        $n = Cache::forgetPattern('catalog_price_bounds_*');
        echo "  -> $n clave(s) con importes borradas (el precio de cada producto ya se lee en vivo).\n";
        break;

    case 'patron':
    case 'pattern':
        if ($arg === '') {
            fwrite(STDERR, "Falta el patron. Ejemplo: php tools/cache-clear.php patron 'count_page_*'\n");
            exit(1);
        }
        $n = Cache::forgetPattern($arg);
        echo "  -> $n clave(s) borradas con el patron '$arg'.\n";
        break;

    case 'gc':
        $n = Cache::gc(true);
        echo "  -> limpieza ejecutada: $n fichero(s) borrados.\n";
        break;

    default:
        fwrite(STDERR, "Accion desconocida: '$accion'. Usa estado|all|catalog|menu|precios|patron|gc.\n");
        exit(1);
}

exit(0);
