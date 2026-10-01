<?php
/**
 * Listado de pedidos de la tienda con pestanas por estado.
 *
 * @var array $orders @var array $pagination @var array $counts @var string $group
 * @var string $q @var int $activeCount @var array $account @var bool $idirectoReady
 * @var string $base
 */
use Tienda\Models\Order;

$groups = Order::statusGroups();
$totalTodos = array_sum($counts);
$enlace = static function (string $clave, string $busqueda) use ($base): string {
    $url = $base . '/panel/pedidos?estado=' . rawurlencode($clave);
    return $busqueda !== '' ? $url . '&q=' . rawurlencode($busqueda) : $url;
};
?>
<section class="card">
    <div class="card-head">
        <h2>Pedidos</h2>
        <div class="card-head-right">
            <span class="badge"><?= (int) $activeCount ?> activos</span>
            <a class="btn btn-primary" href="<?= e($base) ?>/panel/pedidos/nuevo">Nuevo pedido</a>
        </div>
    </div>

    <?php if (!$idirectoReady): ?>
        <div class="alert alert-error">
            El puente con el mayorista no esta disponible en este momento: puedes ver y preparar
            pedidos, pero no enviarlos.
        </div>
    <?php elseif (empty($account['configurada'])): ?>
        <div class="alert alert-error">
            Esta tienda no tiene cuenta en el mayorista, asi que no se pueden enviar pedidos.
            <a href="<?= e($base) ?>/panel/ajustes">Configura la cuenta en idirecto</a>.
        </div>
    <?php endif; ?>

    <nav class="tabs" aria-label="Filtrar por estado">
        <?php foreach ($groups as $clave => $def): ?>
            <?php $cuenta = Order::countGroup($def['statuses'], $counts); ?>
            <a class="tab <?= $group === $clave ? 'is-active' : '' ?>"
               href="<?= e($enlace($clave, $q)) ?>">
                <?= e($def['label']) ?>
                <span class="tab-count"><?= $clave === 'todos' ? (int) $totalTodos : (int) $cuenta ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="<?= e($base) ?>/panel/pedidos" class="order-search">
        <input type="hidden" name="estado" value="<?= e($group) ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar por numero, cliente o email&hellip;">
        <button class="btn btn-ghost btn-sm" type="submit">Buscar</button>
        <?php if ($q !== ''): ?>
            <a class="btn btn-ghost btn-sm" href="<?= e($enlace($group, '')) ?>">Limpiar</a>
        <?php endif; ?>
    </form>

    <?php if (empty($orders)): ?>
        <p class="muted">
            <?= $q !== '' ? 'Ningun pedido coincide con la busqueda.' : 'Todavia no hay pedidos en este estado.' ?>
        </p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Lineas</th>
                    <th class="ta-r">Total</th>
                    <th>Estado</th>
                    <th>Enviado a idirecto</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($orders as $order): ?>
                <?php
                    $status = (int) $order['status'];
                    $itemsTotal = (int) ($order['items_total'] ?? 0);
                    $itemsSent = (int) ($order['items_sent'] ?? 0);
                ?>
                <tr>
                    <td>
                        <a href="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>">
                            <strong><?= e($order['code'] ?? ('#' . $order['id'])) ?></strong>
                        </a>
                    </td>
                    <td class="muted nowrap"><?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?></td>
                    <td>
                        <?= e($order['customer_name']) ?>
                        <?php if (!empty($order['customer_email'])): ?>
                            <br><small class="muted"><?= e($order['customer_email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="muted"><?= $itemsTotal ?></td>
                    <td class="ta-r"><strong><?= e(euros($order['total'])) ?></strong></td>
                    <td><span class="pill pill-<?= e(Order::statusPill($status)) ?>"><?= e(Order::statusLabel($status)) ?></span></td>
                    <td>
                        <?php if ($itemsSent > 0): ?>
                            <span class="badge"><?= $itemsSent ?>/<?= $itemsTotal ?> lineas</span>
                        <?php else: ?>
                            <span class="muted small">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td class="ta-r nowrap">
                        <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if (($pagination['pages'] ?? 1) > 1): ?>
            <nav class="pager">
                <?php if ($pagination['page'] > 1): ?>
                    <a class="btn btn-ghost btn-sm"
                       href="<?= e($enlace($group, $q)) ?>&pagina=<?= (int) $pagination['page'] - 1 ?>">&larr; Anterior</a>
                <?php endif; ?>
                <span class="muted small">Pagina <?= (int) $pagination['page'] ?> de <?= (int) $pagination['pages'] ?>
                    (<?= (int) $pagination['total'] ?> pedidos)</span>
                <?php if ($pagination['page'] < $pagination['pages']): ?>
                    <a class="btn btn-ghost btn-sm"
                       href="<?= e($enlace($group, $q)) ?>&pagina=<?= (int) $pagination['page'] + 1 ?>">Siguiente &rarr;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Como funciona el envio al mayorista</h2>
    <ol class="steps">
        <li>El pedido guarda lo que pidio tu cliente, con sus lineas.</li>
        <li>En la ficha del pedido eliges <strong>que lineas</strong> quieres mandar a idirecto
            (por ejemplo 2 de 4) y se crea el pedido en el mayorista con los datos de tu tienda.</li>
        <li>Cada linea enviada queda marcada con su numero de pedido: puedes enviar el resto mas tarde
            sin repetir nada.</li>
    </ol>
</section>
