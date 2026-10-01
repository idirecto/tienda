<?php
/**
 * Ficha de un pedido: datos del cliente, lineas y envio al mayorista.
 *
 * Las casillas de las lineas van asociadas al formulario de envio con el
 * atributo `form="..."` de HTML5: asi la tabla no queda dentro del formulario y
 * cada linea puede tener su propio boton de borrar sin anidar formularios.
 *
 * @var array $order @var array $items @var array $summary @var array $preview
 * @var array $store @var array $account @var bool $idirectoReady @var bool $canSend
 * @var string $base
 */
use Tienda\Core\Csrf;
use Tienda\Models\Order;
use Tienda\Models\OrderItem;

$status = (int) $order['status'];
$borrado = $status === Order::STATUS_DELETED;

// Lineas ya calculadas para el mayorista, por id de linea.
$previa = [];
foreach ($preview['lines'] as $linea) {
    $previa[(int) $linea['item_id']] = $linea;
}

$puedeEnviar = $canSend && $idirectoReady && !empty($account['configurada']);
$sendFormId = 'send-order-' . (int) $order['id'];
?>
<section class="card">
    <div class="card-head">
        <div>
            <h2><?= e($order['code'] ?? ('#' . $order['id'])) ?></h2>
            <p class="muted small">
                Creado el <?= e(date('d/m/Y H:i', strtotime((string) $order['created_at']))) ?>
                <span class="pill pill-<?= e(Order::statusPill($status)) ?>"><?= e(Order::statusLabel($status)) ?></span>
                <?php if (!empty($order['sent_at'])): ?>
                    <span class="badge">a idirecto: <?= e($order['idirecto_ref'] ?? '') ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="card-head-right">
            <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/pedidos">&larr; Pedidos</a>
        </div>
    </div>

    <div class="order-actions">
        <form method="post" action="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>/estado" class="inline-form">
            <?= Csrf::field() ?>
            <label class="inline-label">Estado
                <select name="status">
                    <?php foreach (Order::statuses() as $valor => $etiqueta): ?>
                        <option value="<?= (int) $valor ?>" <?= $valor === $status ? 'selected' : '' ?>>
                            <?= e($etiqueta) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-ghost btn-sm" type="submit">Guardar estado</button>
        </form>

        <?php if ($borrado): ?>
            <form method="post" action="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>/restaurar" class="inline">
                <?= Csrf::field() ?>
                <button class="btn btn-ghost btn-sm" type="submit">Restaurar pedido</button>
            </form>
        <?php else: ?>
            <form method="post" action="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>/borrar" class="inline"
                  onsubmit="return confirm('¿Mover este pedido a la papelera? Podras restaurarlo despues.')">
                <?= Csrf::field() ?>
                <button class="btn btn-danger btn-sm" type="submit">Mover a la papelera</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<section class="two-col">
    <div class="card">
        <h2>Cliente</h2>
        <ul class="list">
            <li class="list-item">
                <div class="avatar"><?= e(mb_substr((string) $order['customer_name'], 0, 1)) ?></div>
                <div class="list-body">
                    <strong><?= e($order['customer_name']) ?></strong>
                    <small>
                        <?= e($order['customer_email'] ?: 'Sin email') ?>
                        <?php if (!empty($order['customer_phone'])): ?> · <?= e($order['customer_phone']) ?><?php endif; ?>
                        <?php if (!empty($order['customer_tax_id'])): ?> · <?= e($order['customer_tax_id']) ?><?php endif; ?>
                    </small>
                </div>
            </li>
        </ul>
        <?php if (!empty($order['notes'])): ?>
            <div class="hint-box">
                <strong>Notas del pedido</strong>
                <p><?= nl2br(e($order['notes'])) ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Entrega</h2>
        <?php if (!empty($order['ship_address'])): ?>
            <p>
                <strong><?= e($order['ship_name'] ?: $order['customer_name']) ?></strong><br>
                <?= e($order['ship_address']) ?><br>
                <?= e(trim(($order['ship_postal_code'] ?? '') . ' ' . ($order['ship_city'] ?? ''))) ?><br>
                <?= e($order['ship_province'] ?? '') ?> · <?= e($order['ship_country'] ?? '') ?>
            </p>
            <p class="muted small">Es la direccion que se enviara al mayorista como direccion de envio.</p>
        <?php else: ?>
            <p class="muted">
                Sin direccion de entrega propia: el pedido se enviara al mayorista con la direccion
                de la tienda.
            </p>
        <?php endif; ?>

        <div class="hint-box">
            <strong>Cuenta del mayorista</strong>
            <?php if (!empty($account['configurada'])): ?>
                <p>
                    Cuenta <?= (int) $account['id_tienda'] ?>
                    (<?= e($account['razon_social'] ?: $account['nombre']) ?>)
                    · tarifa <?= (int) $account['id_margen'] ?>
                    <?php if (($account['margen_origen'] ?? '') !== 'tienda'): ?>
                        <span class="muted small">(<?= e($account['margen_origen'] === 'cuenta' ? 'de la cuenta' : 'por defecto') ?>)</span>
                    <?php endif; ?>
                </p>
                <?php if (!empty($account['nif'])): ?>
                    <p class="muted small">NIF/CIF de la cuenta: <?= e($account['nif']) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="muted">Sin cuenta en idirecto. <a href="<?= e($base) ?>/panel/ajustes">Configurar en Ajustes</a>.</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2>Lineas del pedido</h2>
        <div class="card-head-right">
            <span class="badge"><?= (int) $summary['lines'] ?> lineas</span>
            <?php if ($summary['lines_sent'] > 0): ?>
                <span class="badge"><?= (int) $summary['lines_sent'] ?> enviadas a idirecto</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($preview['errors'] !== []): ?>
        <div class="alert alert-error">
            <strong>Avisos del envio:</strong>
            <ul><?php foreach ($preview['errors'] as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if ($preview['warnings'] !== []): ?>
        <div class="alert">
            <strong>Ten en cuenta:</strong>
            <ul><?php foreach ($preview['warnings'] as $aviso): ?><li><?= e($aviso) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>
        <p class="muted">Este pedido no tiene lineas. Anade alguna mas abajo.</p>
    <?php else: ?>
        <table class="table order-lines">
            <thead>
                <tr>
                    <?php if ($puedeEnviar): ?>
                        <th class="w-check">
                            <input type="checkbox" data-send-all form="<?= e($sendFormId) ?>" title="Seleccionar todo">
                        </th>
                    <?php endif; ?>
                    <th>Producto</th>
                    <th class="ta-r">Uds.</th>
                    <th class="ta-r">Precio cliente</th>
                    <th class="ta-r">Tarifa idirecto</th>
                    <th class="ta-r">Coste</th>
                    <th class="ta-r">Importe</th>
                    <th>Envio</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <?php
                    $itemId = (int) $item['id'];
                    $enviado = !empty($item['sent_at']);
                    $propio = (string) $item['source'] === OrderItem::SOURCE_OWN;
                    $linea = $previa[$itemId] ?? null;
                    $enviable = $puedeEnviar && $linea !== null;
                    $importe = (float) $item['qty'] * (float) $item['price_customer'];
                    $totalLinea = $linea !== null
                        ? round($linea['base'] + $linea['importe_iva'] + (float) $item['qty'] * $linea['canon'], 2)
                        : 0.0;
                ?>
                <tr<?= $enviable ? ' data-send-row data-base="' . e((string) $linea['base']) . '" data-iva="' . e((string) $linea['importe_iva']) . '" data-total="' . e((string) $totalLinea) . '"' : '' ?>>
                    <?php if ($puedeEnviar): ?>
                        <td class="w-check">
                            <?php if ($enviable): ?>
                                <input type="checkbox" name="items[]" value="<?= $itemId ?>" form="<?= e($sendFormId) ?>" data-send-check checked>
                            <?php else: ?>
                                <input type="checkbox" disabled>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                    <td>
                        <strong><?= e($item['name']) ?></strong>
                        <?php if (!empty($item['sku'])): ?><br><small class="muted"><?= e($item['sku']) ?></small><?php endif; ?>
                        <?php if ($propio): ?> <span class="pill pill-info">propio</span><?php endif; ?>
                    </td>
                    <td class="ta-r"><?= (int) $item['qty'] ?></td>
                    <td class="ta-r"><?= e(euros($item['price_customer'])) ?></td>
                    <td class="ta-r">
                        <?php if ($linea !== null): ?>
                            <?= e(euros($linea['precio'])) ?>
                        <?php else: ?>
                            <span class="muted">&mdash;</span>
                        <?php endif; ?>
                    </td>
                    <td class="ta-r muted">
                        <?= $linea !== null ? e(euros($linea['coste'])) : '&mdash;' ?>
                    </td>
                    <td class="ta-r"><strong><?= e(euros($importe)) ?></strong></td>
                    <td>
                        <?php if ($enviado): ?>
                            <span class="pill pill-promo">enviado #<?= (int) $item['idirecto_pedido_id'] ?></span>
                        <?php elseif ($propio): ?>
                            <span class="muted small">no se envia</span>
                        <?php elseif ($linea === null): ?>
                            <span class="muted small">sin tarifa</span>
                        <?php else: ?>
                            <span class="muted small">pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td class="ta-r">
                        <?php if (!$enviado && !$borrado): ?>
                            <form method="post" class="inline"
                                  action="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>/lineas/<?= $itemId ?>/borrar"
                                  onsubmit="return confirm('¿Quitar esta linea del pedido?')">
                                <?= Csrf::field() ?>
                                <button class="btn btn-danger btn-sm" type="submit" title="Quitar linea">&times;</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="order-send">
            <div class="order-send-totals">
                <div><span class="muted">Total del pedido (cliente)</span> <strong><?= e(euros($order['total'])) ?></strong></div>
                <?php if ($puedeEnviar): ?>
                    <div><span class="muted">Base a enviar</span> <strong data-send-subtotal>0,00 &euro;</strong></div>
                    <div><span class="muted">IVA</span> <strong data-send-tax>0,00 &euro;</strong></div>
                    <div class="order-send-grand"><span class="muted">Total a idirecto</span> <strong data-send-total>0,00 &euro;</strong></div>
                    <div class="muted small"><span data-send-count>0</span> linea(s) seleccionada(s)</div>
                <?php endif; ?>
            </div>

            <div class="order-send-action">
                <?php if ($puedeEnviar): ?>
                    <form method="post" id="<?= e($sendFormId) ?>" data-send-form
                          action="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>/idirecto"
                          onsubmit="return confirm('Se creara un pedido REAL en idirecto con las lineas seleccionadas. ¿Continuar?')">
                        <?= Csrf::field() ?>
                        <button class="btn btn-primary" type="submit" data-send-button>Enviar lineas a idirecto</button>
                    </form>
                    <p class="muted small">
                        Se crea un pedido en el mayorista (tablas pedidos y pedidos_det) con los datos de
                        tu tienda y la tarifa <?= (int) $account['id_margen'] ?>.
                        <?php if (!empty($account['cuenta']['nombre_sociedad'])): ?>
                            Facturado a <?= e($account['cuenta']['nombre_sociedad']) ?>.
                        <?php endif; ?>
                        Las lineas ya enviadas no se repiten.
                    </p>
                <?php else: ?>
                    <p class="muted">
                        <?php if (!$idirectoReady): ?>
                            El puente con el mayorista no esta disponible.
                        <?php elseif (empty($account['configurada'])): ?>
                            Configura la cuenta en idirecto en <a href="<?= e($base) ?>/panel/ajustes">Ajustes</a> para poder enviar.
                        <?php elseif ($summary['lines_pending'] === 0): ?>
                            No quedan lineas pendientes de enviar.
                        <?php else: ?>
                            No se puede enviar este pedido en su estado actual.
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<?php if (!$borrado): ?>
<section class="card">
    <h2>Anadir lineas</h2>
    <form method="post" action="<?= e($base) ?>/panel/pedidos/<?= (int) $order['id'] ?>/lineas" class="stack">
        <?= Csrf::field() ?>
        <?php require __DIR__ . '/_order_lines.php'; ?>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Guardar lineas</button>
        </div>
    </form>
</section>
<?php endif; ?>
