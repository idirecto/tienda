<?php

declare(strict_types=1);

/**
 * Consulta el registro de actividad desde la consola.
 *
 *   php tools/logs.php                          Ultimos 50 eventos de hoy (todas las tiendas)
 *   php tools/logs.php --tienda=1               Solo la tienda 1
 *   php tools/logs.php --fecha=2026-10-08       Un dia concreto
 *   php tools/logs.php --desde=2026-10-01 --hasta=2026-10-08
 *   php tools/logs.php --canal=compras          Un canal (compras, pedidos, acceso, sistema...)
 *   php tools/logs.php --nivel=error            Nivel MINIMO (error incluye critico)
 *   php tools/logs.php --q="pedido 26-00012"    Busca en mensaje y contexto
 *   php tools/logs.php --limit=200
 *
 *   php tools/logs.php --resumen                Cuantos eventos hay de cada nivel
 *   php tools/logs.php --dias                   Dias con log
 *   php tools/logs.php --tiendas                Tiendas con log
 *   php tools/logs.php --gc                     Borra los dias anteriores a la retencion
 *
 * Los ficheros viven en storage/logs/<canal>/<AAAA-MM-DD>/<tienda>.log
 * (una linea JSON por evento), asi que tambien valen:
 *
 *   tail -f storage/logs/compras/$(date +%F)/1_mi-tienda.log
 *   grep -i error storage/logs/compras/2026-10-08/*.log | jq .
 */

require dirname(__DIR__) . '/app/bootstrap.php';

use Tienda\Core\Log\LogReader;
use Tienda\Core\Logger;

// -----------------------------------------------------------------------------
// Argumentos
// -----------------------------------------------------------------------------
$opts = [];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)(?:=(.*))?$/s', $arg, $m) === 1) {
        $opts[$m[1]] = $m[2] ?? true;
    } elseif ($arg === '-h' || $arg === '--help') {
        $opts['ayuda'] = true;
    }
}

$texto = static function (array $opts, string $clave, string $defecto = ''): string {
    $valor = $opts[$clave] ?? $defecto;
    if (!is_string($valor)) {
        return $defecto;
    }
    return trim($valor);
};

if (isset($opts['ayuda'])) {
    echo <<<TXT
Uso: php tools/logs.php [opciones]

  --tienda=ID            Solo esa tienda (sin esto, todas)
  --fecha=AAAA-MM-DD     Un dia (por defecto, hoy)
  --desde=AAAA-MM-DD     Desde esa fecha (con --hasta, o hasta hoy)
  --hasta=AAAA-MM-DD     Hasta esa fecha
  --canal=CANAL          compras | pedidos | acceso | seguridad | panel | catalogo | sistema
  --nivel=NIVEL          Nivel MINIMO: debug | info | notice | warning | error | critical
  --q=TEXTO              Busca en el mensaje, el contexto y la URL
  --limit=N              Cuantos eventos mostrar (por defecto 50, maximo 1000)

  --resumen              Cuantos eventos hay de cada nivel
  --dias                 Dias con log
  --tiendas              Tiendas con log
  --gc                   Limpia los dias anteriores a la retencion
  -h, --help             Esta ayuda

Ficheros: storage/logs/<canal>/<AAAA-MM-DD>/<tienda>.log

TXT;
    exit(0);
}

$reader = LogReader::make();

// -----------------------------------------------------------------------------
// Acciones especiales
// -----------------------------------------------------------------------------
if (isset($opts['gc'])) {
    $borrados = Logger::gc(true);
    echo "Limpieza hecha: $borrados fichero(s) antiguo(s) borrado(s).\n";
    exit(0);
}

if (isset($opts['dias'])) {
    $dias = $reader->days();
    if ($dias === []) {
        echo "Todavia no hay ningun log en {$reader->base()}.\n";
        exit(0);
    }
    foreach ($dias as $dia) {
        $n = count($reader->stores($dia));
        echo $dia . '  ' . $n . " tienda(s)/foco(s)\n";
    }
    exit(0);
}

if (isset($opts['tiendas'])) {
    $tiendas = $reader->stores();
    if ($tiendas === []) {
        echo "Todavia no hay ningun log.\n";
        exit(0);
    }
    printf("%-8s %-24s %s\n", 'TIENDA', 'SLUG', 'FICHEROS');
    foreach ($tiendas as $t) {
        printf("%-8s %-24s %d\n", $t['store_id'] ?? '-', $t['store'], (int) $t['files']);
    }
    exit(0);
}

$filtros = [
    'day'      => $texto($opts, 'fecha') !== '' ? $texto($opts, 'fecha') : null,
    'from'     => $texto($opts, 'desde') !== '' ? $texto($opts, 'desde') : null,
    'to'       => $texto($opts, 'hasta') !== '' ? $texto($opts, 'hasta') : null,
    'channel'  => $texto($opts, 'canal'),
    'level'    => $texto($opts, 'nivel'),
    'store_id' => $texto($opts, 'tienda') !== '' ? (int) $texto($opts, 'tienda') : null,
    'q'        => $texto($opts, 'q'),
];

if (isset($opts['resumen'])) {
    $conteos = $reader->countByLevel($filtros);
    $total = array_sum($conteos);
    echo "Resumen de eventos ({$total} en total)\n";
    foreach ($conteos as $nivel => $n) {
        printf("  %-9s %d\n", $nivel, $n);
    }
    exit(0);
}

// -----------------------------------------------------------------------------
// Listado
// -----------------------------------------------------------------------------
$limit = (int) ($texto($opts, 'limit', '50'));
$limit = max(1, min(1000, $limit));

$resultado = $reader->search($filtros, $limit, 0);
$etiquetas = [
    'debug' => 'DEBUG', 'info' => 'INFO', 'notice' => 'AVISO',
    'warning' => 'WARN', 'error' => 'ERROR', 'critical' => 'CRIT',
];

$rango = $texto($opts, 'fecha');
if ($rango === '') {
    $rango = $texto($opts, 'desde') !== '' || $texto($opts, 'hasta') !== ''
        ? ($texto($opts, 'desde') ?: 'inicio') . ' .. ' . ($texto($opts, 'hasta') ?: 'hoy')
        : 'hoy';
}
echo "Logs de {$reader->base()}  ({$resultado['total']} coincidencia(s), mostrando "
    . count($resultado['entries']) . ")\n";
echo "Filtro: {$rango}"
    . ($texto($opts, 'canal') !== '' ? ' · canal ' . $texto($opts, 'canal') : '')
    . ($texto($opts, 'nivel') !== '' ? ' · nivel >= ' . $texto($opts, 'nivel') : '')
    . ($texto($opts, 'tienda') !== '' ? ' · tienda ' . $texto($opts, 'tienda') : '')
    . ($texto($opts, 'q') !== '' ? ' · q="' . $texto($opts, 'q') . '"' : '')
    . "\n\n";

if ($resultado['entries'] === []) {
    echo "Sin eventos con esos filtros.\n";
    exit(0);
}

foreach ($resultado['entries'] as $e) {
    $ts = strtotime((string) ($e['ts'] ?? '')) ?: null;
    $quien = ($e['_store_id'] ?? null) !== null
        ? (($e['_store_id']) . '_' . ($e['_store'] ?? 'tienda'))
        : 'plataforma';

    $linea = sprintf(
        '%s  %-5s  %-9s  %-11s  %s',
        $ts !== null ? date('Y-m-d H:i:s', $ts) : '-',
        $etiquetas[strtolower((string) ($e['level'] ?? 'info'))] ?? strtoupper((string) ($e['level'] ?? '')),
        (string) ($e['_channel'] ?? ''),
        $quien,
        (string) ($e['message'] ?? '')
    );
    echo $linea . "\n";

    $contexto = (array) ($e['context'] ?? []);
    if ($contexto !== []) {
        $json = json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo '        ' . ($json !== false ? $json : '') . "\n";
    }
    if (!empty($e['http']['url'])) {
        echo '        ' . ($e['http']['method'] ?? '') . ' ' . $e['http']['url']
            . ' (' . ($e['http']['ip'] ?? '-') . ')' . "\n";
    }
}

exit(0);
