<?php
/**
 * Visor de logs del panel: actividad por dia y por tienda.
 *
 * @var array  $entries       Entradas ya filtradas y paginadas
 * @var int    $total         Total de entradas que cumplen los filtros
 * @var array  $counts        Conteo por nivel
 * @var int    $page          Pagina actual
 * @var int    $perPage       Entradas por pagina
 * @var array  $days          Dias con log disponibles
 * @var array  $usedChannels  Canales presentes en disco
 * @var array  $levelLabels   Etiquetas de los niveles
 * @var array  $channelLabels Etiquetas de los canales
 * @var array  $filters       Filtros activos (fecha, canal, nivel, q)
 * @var bool   $isPlatform    El usuario es de la plataforma
 * @var string $tienda        Tienda seleccionada (solo plataforma): 'todas' o id
 * @var array  $stores        Tiendas (solo plataforma)
 * @var int    $retention     Dias de retencion
 * @var int    $maxScanDays   Tope de dias al buscar en todo el historico
 * @var string $ubicacion     Ruta informativa de los ficheros
 * @var string $base
 */
use Tienda\Core\Csrf;

// --- Opciones de fecha: los dias con log + hoy + "todos" --------------------
$opcionesFecha = $days;
$hoy = date('Y-m-d');
if (!in_array($hoy, $opcionesFecha, true)) {
    array_unshift($opcionesFecha, $hoy);
}
if ($filters['fecha'] !== 'todos' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['fecha']) === 1
    && !in_array($filters['fecha'], $opcionesFecha, true)) {
    array_unshift($opcionesFecha, $filters['fecha']);
}

// --- Enlaces conservando los filtros ----------------------------------------
$enlace = static function (array $cambios = []) use ($base, $filters, $isPlatform, $tienda): string {
    $params = [
        'fecha' => $filters['fecha'],
        'canal' => $filters['canal'],
        'nivel' => $filters['nivel'],
        'q'     => $filters['q'],
    ];
    if ($isPlatform) {
        $params['tienda'] = $tienda;
    }
    $params = array_merge($params, $cambios);
    $params = array_filter($params, static fn ($v): bool => $v !== '' && $v !== null);

    return $base . '/panel/logs' . ($params === [] ? '' : '?' . http_build_query($params));
};

$paginas = (int) max(1, (int) ceil($total / max(1, $perPage)));

// Nombre de las tiendas (solo lo necesita la plataforma).
$nombreTienda = [];
foreach ($stores as $s) {
    $nombreTienda[(int) $s['id']] = (string) $s['name'];
}
?>
<section class="card">
    <div class="card-head">
        <h2>Registro de actividad</h2>
        <div class="card-head-right">
            <?php if ($isPlatform): ?>
                <span class="badge">Plataforma: todas las tiendas</span>
            <?php else: ?>
                <span class="badge">Solo tu tienda</span>
            <?php endif; ?>
        </div>
    </div>

    <p class="muted">
        Avisos, errores y actividad, <strong>por dia y por tienda</strong>. Los ficheros se guardan en
        <code><?= e($ubicacion) ?></code> y se conservan <?= (int) $retention ?> dias.
    </p>

    <form method="get" action="<?= e($base) ?>/panel/logs" class="log-filters">
        <label>
            <span>Fecha</span>
            <select name="fecha">
                <option value="todos" <?= $filters['fecha'] === 'todos' ? 'selected' : '' ?>>
                    Todos (ultimos <?= (int) $maxScanDays ?> dias)
                </option>
                <?php foreach ($opcionesFecha as $dia): ?>
                    <option value="<?= e($dia) ?>" <?= $filters['fecha'] === $dia ? 'selected' : '' ?>>
                        <?= e($dia === $hoy ? $dia . ' (hoy)' : $dia) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Canal</span>
            <select name="canal">
                <option value="">Todos</option>
                <?php
                $canales = $channelLabels;
                foreach ($usedChannels as $usado) {
                    if (!isset($canales[$usado])) {
                        $canales[$usado] = ucfirst($usado);
                    }
                }
                ?>
                <?php foreach ($canales as $clave => $etiqueta): ?>
                    <option value="<?= e($clave) ?>" <?= $filters['canal'] === $clave ? 'selected' : '' ?>>
                        <?= e($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            <span>Nivel minimo</span>
            <select name="nivel">
                <option value="">Todos</option>
                <?php foreach ($levelLabels as $clave => $etiqueta): ?>
                    <option value="<?= e($clave) ?>" <?= $filters['nivel'] === $clave ? 'selected' : '' ?>>
                        <?= e($etiqueta) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <?php if ($isPlatform): ?>
            <label>
                <span>Tienda</span>
                <select name="tienda">
                    <option value="todas" <?= $tienda === 'todas' ? 'selected' : '' ?>>Todas las tiendas</option>
                    <?php foreach ($stores as $s): ?>
                        <option value="<?= (int) $s['id'] ?>" <?= $tienda === (string) $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['name']) ?> (<?= e($s['slug']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endif; ?>

        <label class="log-filters-q">
            <span>Buscar</span>
            <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="pedido, email, mensaje&hellip;">
        </label>

        <div class="log-filters-actions">
            <button class="btn btn-primary" type="submit">Filtrar</button>
            <a class="btn btn-ghost" href="<?= e($base) ?>/panel/logs">Limpiar</a>
        </div>
    </form>

    <div class="log-summary">
        <?php foreach ($levelLabels as $clave => $etiqueta): ?>
            <?php $activo = $filters['nivel'] === $clave; ?>
            <a class="log-level log-level-<?= e($clave) ?> <?= $activo ? 'is-active' : '' ?>"
               href="<?= e($enlace(['nivel' => $activo ? '' : $clave, 'pagina' => ''])) ?>">
                <?= e($etiqueta) ?>: <strong><?= (int) ($counts[$clave] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($filters['nivel'] !== ''): ?>
        <p class="muted">
            Mostrando <strong><?= e($levelLabels[$filters['nivel']] ?? $filters['nivel']) ?></strong> y todo lo mas grave
            (nivel minimo). <a href="<?= e($enlace(['nivel' => '', 'pagina' => ''])) ?>">Quitar filtro de nivel</a>.
        </p>
    <?php endif; ?>

    <?php if (empty($entries)): ?>
        <p class="muted">
            No hay actividad con esos filtros. Si acabas de abrir el panel de logs, aqui aparecera
            todo lo que ocurra de ahora en adelante (compras, pedidos, entradas y errores).
        </p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Hora</th>
                    <th>Nivel</th>
                    <th>Canal</th>
                    <?php if ($isPlatform): ?><th>Tienda</th><?php endif; ?>
                    <th>Mensaje</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($entries as $entrada): ?>
                <?php
                    $nivel = strtolower((string) ($entrada['level'] ?? 'info'));
                    $ts = strtotime((string) ($entrada['ts'] ?? '')) ?: null;
                    $storeId = $entrada['_store_id'] ?? null;
                    $storeLabel = $storeId !== null
                        ? ($nombreTienda[(int) $storeId] ?? ($entrada['_store'] ?? ('#' . $storeId)))
                        : 'Plataforma';
                ?>
                <tr>
                    <td class="log-hora"><?= e($ts !== null ? date('H:i:s', $ts) : '-') ?></td>
                    <td>
                        <span class="log-level log-level-<?= e($nivel) ?>">
                            <?= e($levelLabels[$nivel] ?? $nivel) ?>
                        </span>
                    </td>
                    <td class="log-canal"><?= e($channelLabels[$entrada['_channel'] ?? ''] ?? ($entrada['_channel'] ?? '-')) ?></td>
                    <?php if ($isPlatform): ?>
                        <td><?= e($storeLabel) ?></td>
                    <?php endif; ?>
                    <td class="log-msg">
                        <?= e((string) ($entrada['message'] ?? '')) ?>
                        <details class="log-detalle">
                            <summary class="muted">Ver datos</summary>
                            <?php if (!empty($entrada['context'])): ?>
                                <pre class="log-ctx"><?= e((string) json_encode(
                                    $entrada['context'],
                                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                )) ?></pre>
                            <?php endif; ?>
                            <ul class="log-meta">
                                <?php if ($ts !== null): ?>
                                    <li>Fecha: <?= e(date('Y-m-d H:i:s', $ts)) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($entrada['user'])): ?>
                                    <li>Usuario: <?= e((string) $entrada['user']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($entrada['http']['url'])): ?>
                                    <li>Peticion: <?= e((string) ($entrada['http']['method'] ?? '')) ?> <?= e((string) $entrada['http']['url']) ?></li>
                                <?php endif; ?>
                                <?php if (!empty($entrada['http']['ip'])): ?>
                                    <li>IP: <?= e((string) $entrada['http']['ip']) ?></li>
                                <?php endif; ?>
                                <li>Peticion ID: <?= e((string) ($entrada['request_id'] ?? '')) ?></li>
                                <li>Fichero: <?= e((string) ($entrada['_file'] ?? '')) ?></li>
                            </ul>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="pager">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e($enlace(['pagina' => $page - 1])) ?>">&larr; Anterior</a>
            <?php endif; ?>
            <span class="muted">
                Pagina <?= (int) $page ?> de <?= (int) $paginas ?> · <?= (int) $total ?> evento(s)
                <?php if ($total > $perPage): ?>
                    (mostrando <?= count($entries) ?>)
                <?php endif; ?>
            </span>
            <?php if ($page < $paginas): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e($enlace(['pagina' => $page + 1])) ?>">Siguiente &rarr;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if ($isPlatform): ?>
<section class="card">
    <h2>Mantenimiento</h2>
    <p class="muted">
        Los logs se limpian solos al pasar los <?= (int) $retention ?> dias de retencion
        (tambien puedes forzarlo aqui). Borra los dias anteriores a esa fecha; no toca la base de datos.
    </p>
    <form method="post" action="<?= e($base) ?>/panel/logs/limpiar"
          onsubmit="return confirm('¿Borrar los logs anteriores a la retencion?');">
        <input type="hidden" name="_token" value="<?= e(Csrf::token()) ?>">
        <button class="btn btn-danger" type="submit">Limpiar logs antiguos</button>
    </form>
</section>
<?php endif; ?>
