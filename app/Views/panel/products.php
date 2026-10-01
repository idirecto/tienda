<?php
/**
 * Listado de productos propios.
 * @var array $products @var int $used @var int $quota @var bool $unlimited @var bool $canAdd @var string $base
 */
use Tienda\Core\Csrf;
$quotaLabel = $unlimited ? $used . ' / ilimitado' : $used . ' / ' . $quota;
?>
<section class="card">
    <div class="card-head">
        <h2>Productos propios</h2>
        <div class="card-head-right">
            <span class="badge"><?= e($quotaLabel) ?></span>
            <?php if ($canAdd || $unlimited): ?>
                <a class="btn btn-primary" href="<?= e($base) ?>/panel/productos/nuevo">Nuevo producto</a>
            <?php else: ?>
                <a class="btn btn-ghost" href="<?= e($base) ?>/panel/ajustes">Ampliar plan</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$unlimited && $quota <= 0): ?>
        <div class="alert alert-error">
            Tu plan actual no permite productos propios. Puedes seguir vendiendo el catalogo del mayorista.
        </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <p class="muted">No tienes productos propios todavia.</p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th></th><th>Producto</th><th>SKU</th><th>Proveedor</th>
                    <th class="ta-r">Precio</th><th class="ta-r">Stock</th><th>Estado</th><th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr>
                    <td>
                        <div class="thumb thumb-sm"<?= $p['image_url'] ? ' style="background-image:url(' . e($p['image_url']) . ')"' : '' ?>></div>
                    </td>
                    <td><strong><?= e($p['name']) ?></strong></td>
                    <td class="muted"><?= e($p['sku'] ?? '-') ?></td>
                    <td class="muted"><?= e($p['supplier'] ?? '-') ?></td>
                    <td class="ta-r"><?= e(euros($p['price'])) ?></td>
                    <td class="ta-r"><?= (int) $p['stock'] ?></td>
                    <td>
                        <span class="pill pill-<?= (int) $p['status'] === 1 ? 'promo' : 'info' ?>">
                            <?= (int) $p['status'] === 1 ? 'publicado' : ((int) $p['status'] === 0 ? 'borrador' : 'oculto') ?>
                        </span>
                    </td>
                    <td class="ta-r nowrap">
                        <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/productos/<?= (int) $p['id'] ?>/editar">Editar</a>
                        <form method="post" action="<?= e($base) ?>/panel/productos/<?= (int) $p['id'] ?>/borrar"
                              class="inline" onsubmit="return confirm('¿Eliminar este producto?')">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">Borrar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
