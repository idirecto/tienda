<?php
/**
 * Libreta de direcciones del cliente.
 *
 * @var array $customer @var array $addresses @var string $base
 */
use Tienda\Core\Csrf;
?>
<section class="container section">
    <div class="page-head">
        <h1>Mis direcciones</h1>
        <p class="muted">Las que uses al comprar se guardan aqui para la proxima vez.</p>
    </div>

    <?php if ($addresses === []): ?>
        <div class="panel-box">
            <p class="muted">Todavia no tienes direcciones guardadas.</p>
        </div>
    <?php else: ?>
        <div class="address-cards">
            <?php foreach ($addresses as $address): ?>
                <article class="address-card">
                    <header>
                        <strong><?= e($address['name']) ?></strong>
                        <?php if (!empty($address['label'])): ?><span class="pill pill-info"><?= e($address['label']) ?></span><?php endif; ?>
                        <?php if ((int) $address['is_default_ship'] === 1): ?><span class="pill pill-info">Envio</span><?php endif; ?>
                        <?php if ((int) $address['is_default_bill'] === 1): ?><span class="pill pill-info">Facturacion</span><?php endif; ?>
                    </header>
                    <p>
                        <?= e($address['address']) ?><?= $address['detail'] ? ', ' . e($address['detail']) : '' ?><br>
                        <?= e($address['postal_code']) ?> <?= e($address['city']) ?><br>
                        <?= e($address['province']) ?> · <?= e($address['country']) ?>
                        <?php if (!empty($address['tax_id'])): ?><br><small class="muted"><?= e($address['tax_id']) ?></small><?php endif; ?>
                        <?php if (!empty($address['phone']) || !empty($address['mobile'])): ?>
                            <br><small class="muted"><?= e($address['phone'] ?: $address['mobile']) ?></small>
                        <?php endif; ?>
                    </p>
                    <footer>
                        <a class="link" href="<?= e($base) ?>/cuenta/direcciones/<?= (int) $address['id'] ?>">Editar</a>
                        <form method="post" action="<?= e($base) ?>/cuenta/direcciones/<?= (int) $address['id'] ?>/borrar"
                              data-confirm="¿Borrar esta direccion?">
                            <?= Csrf::field() ?>
                            <button class="link link-danger" type="submit">Borrar</button>
                        </form>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="account-links">
        <a class="btn btn-primary" href="<?= e($base) ?>/cuenta/direcciones/nueva">Anadir direccion</a>
        <a class="link" href="<?= e($base) ?>/cuenta">Volver a mi cuenta</a>
    </div>
</section>
