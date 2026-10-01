<?php
/**
 * Clientes de la tienda.
 *
 * @var string $q @var array $customers @var float $total @var string $base
 */
use Tienda\Models\Order;
?>
<section class="card">
    <div class="card-head">
        <div>
            <h2>Clientes</h2>
            <p class="muted small">
                <?= count($customers) ?> cliente(s) · <?= e(euros($total)) ?> en pedidos.
                Los datos los mantiene cada cliente desde su cuenta en la web.
            </p>
        </div>
        <div class="card-head-right">
            <form method="get" action="<?= e($base) ?>/panel/clientes" class="inline-form">
                <label class="inline-label">
                    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre, email o telefono">
                </label>
                <button class="btn btn-ghost btn-sm" type="submit">Buscar</button>
            </form>
        </div>
    </div>

    <?php if ($customers === []): ?>
        <p class="muted"><?= $q !== '' ? 'Ningun cliente coincide con la busqueda.' : 'Todavia no tienes clientes: apareceran en cuanto compren en tu web.' ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Pedidos</th>
                        <th class="ta-r">Comprado</th>
                        <th>Desde</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td>
                            <strong><?= e($customer['name'] !== '' ? $customer['name'] : $customer['email']) ?></strong>
                            <?php if ((int) $customer['is_guest'] === 1): ?>
                                <span class="pill pill-warning">Invitado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small><?= e($customer['email']) ?></small>
                            <?php if (!empty($customer['phone']) || !empty($customer['mobile'])): ?>
                                <br><small class="muted"><?= e($customer['phone'] ?: $customer['mobile']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $customer['orders_count'] ?></td>
                        <td class="ta-r"><?= e(euros((float) $customer['orders_total'])) ?></td>
                        <td><small class="muted"><?= e(date('d/m/Y', strtotime((string) $customer['created_at']))) ?></small></td>
                        <td class="ta-r">
                            <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/clientes/<?= (int) $customer['id'] ?>">Ver ficha</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
