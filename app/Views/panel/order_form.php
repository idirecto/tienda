<?php
/**
 * Alta manual de un pedido.
 *
 * Todavia no hay checkout en la tienda (el carrito y el pago estan pendientes),
 * asi que los pedidos se pueden dar de alta a mano para probar el circuito
 * completo: cliente, lineas y envio de lineas al mayorista.
 *
 * @var array|null $order @var array $items @var array $store @var string $base
 */
use Tienda\Core\Csrf;
use Tienda\Models\Order;
?>
<form method="post" action="<?= e($base) ?>/panel/pedidos" class="stack">
    <?= Csrf::field() ?>

    <section class="two-col">
        <div class="card">
            <h2>Cliente</h2>
            <div class="field-grid">
                <label>Nombre y apellidos *
                    <input type="text" name="customer_name" required maxlength="150"
                           value="<?= e($order['customer_name'] ?? '') ?>">
                </label>
                <label>Email
                    <input type="email" name="customer_email" maxlength="180"
                           value="<?= e($order['customer_email'] ?? '') ?>">
                </label>
                <label>Telefono
                    <input type="text" name="customer_phone" maxlength="40"
                           value="<?= e($order['customer_phone'] ?? '') ?>">
                </label>
                <label>NIF / CIF
                    <input type="text" name="customer_tax_id" maxlength="30"
                           value="<?= e($order['customer_tax_id'] ?? '') ?>">
                </label>
            </div>
            <label class="field">Notas internas
                <textarea name="notes" rows="3"><?= e($order['notes'] ?? '') ?></textarea>
            </label>
        </div>

        <div class="card">
            <h2>Entrega y totales</h2>
            <label class="check">
                <input type="checkbox" name="ship_to_store" value="1" data-ship-to-store checked>
                Enviar a la direccion de la tienda
            </label>
            <p class="muted small">
                Si lo dejas marcado, el pedido se manda al mayorista con la direccion de tu tienda
                (la de Ajustes). Desmarcalo para escribir la direccion del cliente.
            </p>

            <div class="field-grid" data-ship-fields hidden>
                <label>Nombre en el envio
                    <input type="text" name="ship_name" maxlength="150">
                </label>
                <label>NIF / CIF del envio
                    <input type="text" name="ship_tax_id" maxlength="30">
                </label>
                <label>Direccion
                    <input type="text" name="ship_address" maxlength="250">
                </label>
                <label>Codigo postal
                    <input type="text" name="ship_postal_code" maxlength="20">
                </label>
                <label>Ciudad
                    <input type="text" name="ship_city" maxlength="120">
                </label>
                <label>Provincia
                    <input type="text" name="ship_province" maxlength="120">
                </label>
                <label>Pais
                    <input type="text" name="ship_country" maxlength="60" value="Espana">
                </label>
            </div>

            <div class="field-grid">
                <label>Gastos de envio (al cliente)
                    <input type="text" name="shipping" value="0,00">
                </label>
                <label>Estado inicial
                    <select name="status">
                        <?php foreach (Order::statuses() as $valor => $etiqueta): ?>
                            <?php if (in_array($valor, [Order::STATUS_DRAFT, Order::STATUS_ACTIVE, Order::STATUS_PREPARING], true)): ?>
                                <option value="<?= (int) $valor ?>" <?= $valor === Order::STATUS_ACTIVE ? 'selected' : '' ?>>
                                    <?= e($etiqueta) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Lineas del pedido</h2>
        <?php require __DIR__ . '/_order_lines.php'; ?>
    </section>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Crear pedido</button>
        <a class="btn btn-ghost" href="<?= e($base) ?>/panel/pedidos">Cancelar</a>
    </div>
</form>
