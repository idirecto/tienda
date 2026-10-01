<?php
/**
 * Cierre de la compra: direccion, forma de pago y comentario.
 *
 * @var array $items @var array $totals @var array|null $customer @var array $addresses
 * @var array $methods @var array $store @var int $selectedAddress @var string $base
 */
use Tienda\Core\Csrf;

$pais = (string) ($store['country'] ?? 'Espana');
?>
<section class="container section">
    <div class="page-head">
        <h1>Finalizar compra</h1>
        <p class="muted">Revisa tus datos, elige la forma de pago y confirma el pedido.</p>
    </div>

    <form class="checkout-grid" method="post" action="<?= e($base) ?>/checkout">
        <?= Csrf::field() ?>

        <div class="checkout-main">
            <?php if ($customer === null): ?>
                <div class="panel-box">
                    <h2>¿Como quieres comprar?</h2>
                    <p class="muted">
                        Puedes comprar como invitado o <a href="<?= e($base) ?>/cuenta/registro?volver=/checkout">crear tu cuenta</a>
                        para guardar tus direcciones y ver tus pedidos.
                        ¿Ya tienes cuenta? <a href="<?= e($base) ?>/cuenta/login?volver=/checkout">Entra aqui</a>.
                    </p>
                    <label>Tu email
                        <input type="email" name="email" required maxlength="180" value="<?= e($_POST['email'] ?? '') ?>">
                    </label>
                </div>
            <?php endif; ?>

            <div class="panel-box">
                <h2>Direccion de envio</h2>

                <?php if ($addresses !== []): ?>
                    <div class="address-picker">
                        <?php foreach ($addresses as $i => $address): ?>
                            <label class="address-option">
                                <input type="radio" name="address_id" value="<?= (int) $address['id'] ?>"
                                    <?= ($selectedAddress > 0 ? $selectedAddress === (int) $address['id'] : $i === 0) ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($address['name']) ?></strong>
                                    <?php if (!empty($address['label'])): ?> <span class="pill pill-info"><?= e($address['label']) ?></span><?php endif; ?>
                                    <br><small><?= e($address['address']) ?><?= $address['detail'] ? ', ' . e($address['detail']) : '' ?></small>
                                    <br><small><?= e($address['postal_code']) ?> <?= e($address['city']) ?>, <?= e($address['province']) ?></small>
                                    <?php if ($address['phone'] || $address['mobile']): ?>
                                        <br><small class="muted"><?= e($address['phone'] ?: $address['mobile']) ?></small>
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <p class="muted small">¿Otra direccion? Rellena los campos de abajo y se usara (y se guardara) esa.</p>
                <?php endif; ?>

                <div class="field-grid">
                    <label>Nombre y apellidos<input type="text" name="ship_name" maxlength="150" value="<?= e($_POST['ship_name'] ?? ($customer['name'] ?? '')) ?>"></label>
                    <label>DNI / NIF<input type="text" name="ship_tax_id" maxlength="30" value="<?= e($_POST['ship_tax_id'] ?? ($customer['tax_id'] ?? '')) ?>"></label>
                    <label class="field-wide">Direccion<input type="text" name="ship_address" maxlength="250" value="<?= e($_POST['ship_address'] ?? '') ?>"></label>
                    <label class="field-wide">Portal, escalera, piso<input type="text" name="ship_detail" maxlength="200" value="<?= e($_POST['ship_detail'] ?? '') ?>"></label>
                    <label>Codigo postal<input type="text" name="ship_postal_code" maxlength="20" value="<?= e($_POST['ship_postal_code'] ?? '') ?>"></label>
                    <label>Poblacion<input type="text" name="ship_city" maxlength="120" value="<?= e($_POST['ship_city'] ?? '') ?>"></label>
                    <label>Provincia<input type="text" name="ship_province" maxlength="120" value="<?= e($_POST['ship_province'] ?? '') ?>"></label>
                    <label>Pais<input type="text" name="ship_country" maxlength="60" value="<?= e($_POST['ship_country'] ?? $pais) ?>"></label>
                    <label>Telefono<input type="text" name="ship_phone" maxlength="40" value="<?= e($_POST['ship_phone'] ?? ($customer['phone'] ?? '')) ?>"></label>
                    <label>Movil<input type="text" name="ship_mobile" maxlength="40" value="<?= e($_POST['ship_mobile'] ?? ($customer['mobile'] ?? '')) ?>"></label>
                </div>
            </div>

            <div class="panel-box">
                <h2>Facturacion</h2>
                <label class="check">
                    <input type="checkbox" name="bill_same" value="1" checked data-bill-toggle>
                    Los mismos datos que el envio
                </label>
                <div class="field-grid" data-bill-fields hidden>
                    <label>Nombre / razon social<input type="text" name="bill_name" maxlength="150"></label>
                    <label>DNI / NIF<input type="text" name="bill_tax_id" maxlength="30"></label>
                    <label class="field-wide">Direccion<input type="text" name="bill_address" maxlength="250"></label>
                    <label>Codigo postal<input type="text" name="bill_postal_code" maxlength="20"></label>
                    <label>Poblacion<input type="text" name="bill_city" maxlength="120"></label>
                    <label>Provincia<input type="text" name="bill_province" maxlength="120"></label>
                    <label>Pais<input type="text" name="bill_country" maxlength="60" value="<?= e($pais) ?>"></label>
                </div>
            </div>

            <div class="panel-box">
                <h2>Forma de pago</h2>
                <?php if ($methods === []): ?>
                    <p class="alert alert-error">La tienda no ha configurado ninguna forma de pago. Escribenos y lo solucionamos.</p>
                <?php endif; ?>
                <?php foreach ($methods as $key => $method): ?>
                    <label class="check">
                        <input type="radio" name="payment_method" value="<?= e($key) ?>" <?= $key === array_key_first($methods) ? 'checked' : '' ?>>
                        <span><strong><?= e($method['label']) ?></strong><br><small class="muted"><?= e($method['note']) ?></small></span>
                    </label>
                <?php endforeach; ?>

                <label>Comentario para la tienda (opcional)
                    <textarea name="comment" rows="3" maxlength="1000"
                              placeholder="Horario de entrega, referencias, alguna indicacion..."><?= e($_POST['comment'] ?? '') ?></textarea>
                </label>
            </div>
        </div>

        <aside class="checkout-side">
            <div class="panel-box">
                <h2>Tu pedido</h2>
                <ul class="checkout-lines">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <span class="checkout-line-name"><?= (int) $item['qty'] ?> x <?= e($item['name']) ?></span>
                            <span><?= e(euros($item['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <dl class="totals">
                    <div><dt>Productos</dt><dd><?= e(euros($totals['subtotal'])) ?></dd></div>
                    <div><dt>IVA incluido</dt><dd><?= e(euros($totals['tax_total'])) ?></dd></div>
                    <div><dt>Gastos de envio</dt><dd><?= $totals['shipping'] > 0 ? e(euros($totals['shipping'])) : 'Gratis' ?></dd></div>
                    <div class="totals-grand"><dt>Total</dt><dd><?= e(euros($totals['total'])) ?></dd></div>
                </dl>
                <button class="btn btn-primary btn-lg btn-block" type="submit" <?= $methods === [] ? 'disabled' : '' ?>>
                    Confirmar pedido
                </button>
                <p class="muted small">Al confirmar aceptas las condiciones de venta de la tienda.</p>
                <a class="link" href="<?= e($base) ?>/carrito">Volver al carrito</a>
            </div>
        </aside>
    </form>
</section>
