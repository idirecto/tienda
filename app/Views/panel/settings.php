<?php
/**
 * Ajustes de la tienda y usuarios.
 * @var array $store @var array $users @var string $base
 * @var array $account cuenta del mayorista resuelta
 * @var array $nivel estado del nivel de cliente (nivel, cuenta y motivo)
 * @var string $nivelAviso frase accionable cuando la cuenta no tiene nivel
 * @var array<string,array<string,mixed>> $shippingZones
 * @var string $shippingBlockedMessage
 */
use Tienda\Core\Csrf;
$v = static fn (string $k, string $d = '') => e($store[$k] ?? $d);
$zoneValue = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '';
    }
    $numeric = str_replace(',', '.', (string) $value);
    if (is_numeric($numeric)) {
        return number_format((float) $numeric, 2, ',', '');
    }

    return (string) $value;
};
$globalCost = (float) ($store['shipping_flat'] ?? 0);
$globalFree = $store['free_shipping_from'] ?? null;
$globalCostLabel = $globalCost > 0 ? number_format($globalCost, 2, ',', '') . ' EUR' : 'sin coste';
$globalFreeLabel = $globalFree !== null && (float) $globalFree > 0
    ? number_format((float) $globalFree, 2, ',', '') . ' EUR'
    : 'sin envio gratis';
?>
<section class="two-col">
    <div class="card">
        <h2>Datos de la tienda</h2>
        <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
            <?= Csrf::field() ?>
            <div class="field-grid">
                <label>Nombre comercial<input type="text" name="name" value="<?= $v('name') ?>" required></label>
                <label>Razon social<input type="text" name="legal_name" value="<?= $v('legal_name') ?>"></label>
                <label>Email<input type="email" name="email" value="<?= $v('email') ?>"></label>
                <label>Telefono<input type="text" name="phone" value="<?= $v('phone') ?>"></label>
                <label>Codigo postal<input type="text" name="postal_code" value="<?= $v('postal_code') ?>"></label>
                <label>Ciudad<input type="text" name="city" value="<?= $v('city') ?>"></label>
                <label>Provincia<input type="text" name="province" value="<?= $v('province') ?>"></label>
                <label>IVA por defecto (%)<input type="text" name="tax_rate" value="<?= $v('tax_rate', '21') ?>"></label>
            </div>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar datos</button>
            </div>
        </form>

        <div class="hint-box">
            <strong>Tu direccion web</strong>
            <?php $slugActual = (string) ($store['slug'] ?? ''); ?>
            <?php if ($slugActual !== ''): ?>
                <p>Subdominio:
                    <a href="<?= e(\Tienda\Core\Subdomain::url($slugActual)) ?>" target="_blank" rel="noopener">
                        <code><?= e(\Tienda\Core\Subdomain::host($slugActual)) ?></code>
                    </a>
                </p>
                <p class="muted small">Puedes cambiarlo en <a href="<?= e($base) ?>/panel/dominios">Dominios</a>.</p>
            <?php endif; ?>
            <p class="muted small">Plan actual: <strong><?= e($store['plan_name'] ?? 'Sin plan') ?></strong></p>
        </div>
    </div>

    <div class="card">
        <h2>Venta y cobro</h2>
        <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
            <?= Csrf::field() ?>
            <input type="hidden" name="form" value="shop">
            <p class="muted small">
                El precio que ve el cliente es tu <strong>tarifa en el mayorista mas este beneficio</strong>,
                con el IVA incluido. Con beneficio 0 vendes al precio de coste.
            </p>
            <div class="field-grid">
                <label>Beneficio (%)
                    <input type="text" name="markup" value="<?= $v('markup', '15') ?>">
                </label>
                <label>Tarifa de envio general (EUR)
                    <input type="text" name="shipping_flat" value="<?= $v('shipping_flat', '0') ?>">
                    <small class="muted">Se usa en las zonas que no tengan coste propio.</small>
                </label>
                <label>Envio gratis general desde (EUR)
                    <input type="text" name="free_shipping_from" value="<?= $v('free_shipping_from') ?>" placeholder="vacio = nunca">
                    <small class="muted">Se hereda solo en las zonas sin umbral propio.</small>
                </label>
            </div>

            <h3 class="subsection">Formas de pago que aceptas</h3>
            <label class="check-line">
                <input type="checkbox" name="pay_transfer" value="1" <?= !empty($store['pay_transfer']) ? 'checked' : '' ?>>
                Transferencia bancaria
            </label>
            <label class="check-line">
                <input type="checkbox" name="pay_cod" value="1" <?= !empty($store['pay_cod']) ? 'checked' : '' ?>>
                Contra reembolso
            </label>
            <label class="check-line">
                <input type="checkbox" name="pay_pickup" value="1" <?= !empty($store['pay_pickup']) ? 'checked' : '' ?>>
                Recogida en tienda
            </label>

            <label>Datos para la transferencia
                <textarea name="bank_details" rows="3" placeholder="Titular, IBAN y lo que quieras indicar al cliente"><?= $v('bank_details') ?></textarea>
            </label>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar venta y cobro</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Usuarios del panel</h2>
        <ul class="list">
            <?php foreach ($users as $u): ?>
                <li class="list-item">
                    <div class="avatar"><?= e(mb_substr((string) $u['name'], 0, 1)) ?></div>
                    <div class="list-body">
                        <strong><?= e($u['name']) ?></strong>
                        <small><?= e($u['email']) ?> · <?= e($u['role']) ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <h3 class="subsection">Anadir usuario</h3>
        <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
            <?= Csrf::field() ?>
            <input type="hidden" name="form" value="user">
            <label>Nombre<input type="text" name="name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Contrasena<input type="password" name="password" minlength="8" required></label>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Crear usuario</button>
            </div>
        </form>
    </div>
</section>

<section class="card">
    <h2>Zonas de venta y envio</h2>
    <p class="muted small">
        Elige donde aceptas pedidos. Las cuatro zonas son independientes: puedes activar una, varias o todas.
        El coste, el envio gratis y el plazo de cada zona son opcionales; si los dejas vacios se usa la
        tarifa general de arriba. Un valor <strong>0</strong> en "Envio gratis desde" desactiva el envio
        gratis para esa zona. La zona solo decide el envio: no cambia el IVA ni ningun otro impuesto.
    </p>

    <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
        <?= Csrf::field() ?>
        <input type="hidden" name="form" value="shipping_zones">

        <div class="shipping-zones-grid">
            <?php foreach ($shippingZones as $zoneCode => $zone): ?>
                <section class="shipping-zone-card">
                    <label class="check check-line">
                        <input type="checkbox" name="zones[<?= e($zoneCode) ?>][enabled]" value="1"
                               <?= (int) ($zone['enabled'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <span>
                            <strong><?= e((string) $zone['label']) ?></strong>
                            <small class="zone-toggle-hint">Activar zona</small>
                        </span>
                    </label>
                    <small class="muted zone-state">Inactiva: no acepta pedidos; su configuracion se conserva.</small>

                    <div class="field-grid">
                        <label>Coste (EUR)
                            <input type="text" inputmode="decimal" name="zones[<?= e($zoneCode) ?>][cost]"
                                   value="<?= e($zoneValue($zone['cost'] ?? '')) ?>" placeholder="Heredar general (<?= e($globalCostLabel) ?>)">
                            <small class="muted">Vacio = usa la tarifa general de la tienda.</small>
                        </label>
                        <label>Envio gratis desde (EUR)
                            <input type="text" inputmode="decimal" name="zones[<?= e($zoneCode) ?>][free_from]"
                                   value="<?= e($zoneValue($zone['free_from'] ?? '')) ?>" placeholder="Heredar general (<?= e($globalFreeLabel) ?>)">
                            <small class="muted">Vacio = hereda el umbral general; 0 = sin envio gratis; importe = umbral propio.</small>
                        </label>
                        <label>Plazo minimo (dias)
                            <input type="number" min="0" step="1" name="zones[<?= e($zoneCode) ?>][delivery_days_min]"
                                   value="<?= e((string) ($zone['delivery_days_min'] ?? '')) ?>" placeholder="Sin definir">
                        </label>
                        <label>Plazo maximo (dias)
                            <input type="number" min="0" step="1" name="zones[<?= e($zoneCode) ?>][delivery_days_max]"
                                   value="<?= e((string) ($zone['delivery_days_max'] ?? '')) ?>" placeholder="Sin definir">
                        </label>
                    </div>

                    <label>Condiciones de envio de esta zona
                        <textarea name="zones[<?= e($zoneCode) ?>][conditions]" rows="3" maxlength="1000"
                                  placeholder="Ej. condiciones de reparto, incidencias, recogidas..."><?= e((string) ($zone['conditions'] ?? '')) ?></textarea>
                    </label>
                </section>
            <?php endforeach; ?>
        </div>

        <label>Mensaje cuando la zona no esta disponible
            <textarea name="shipping_blocked_message" rows="3" maxlength="500" required><?= e($shippingBlockedMessage) ?></textarea>
            <small class="muted">Se muestra en el checkout si la direccion no pertenece a una zona activa.</small>
        </label>

        <div class="actions">
            <button class="btn btn-primary" type="submit">Guardar zonas y envio</button>
        </div>
    </form>
</section>

<section class="card">
    <h2>Cuenta en idirecto (mayorista)</h2>
    <p class="muted small">
        Para enviar los pedidos de tus clientes al mayorista, indica con que cuenta compras.
        El id de cuenta es el que aparece en idirecto (<code>tiendas.id</code>) y la tarifa es
        el margen con el que te factura (<code>precios.id_margen</code>). Si dejas la tarifa
        vacia se usa la de la propia cuenta.
    </p>

    <?php if (!$idirectoReady): ?>
        <div class="alert alert-error">
            No se encuentran las tablas de pedidos del mayorista, asi que el envio esta desactivado.
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e($base) ?>/panel/ajustes" class="stack">
        <?= Csrf::field() ?>
        <div class="field-grid">
            <label>Id de cuenta en idirecto
                <input type="number" name="id_tienda_idirecto" min="0" step="1"
                       value="<?= (int) ($store['id_tienda_idirecto'] ?? 0) ?>">
            </label>
            <label>Tarifa / margen (opcional)
                <input type="number" name="id_margen" min="0" step="1"
                       value="<?= (int) ($store['id_margen'] ?? 0) ?>">
            </label>
        </div>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Guardar cuenta</button>
        </div>
    </form>

    <?php if (!empty($account['configurada'])): ?>
        <div class="hint-box">
            <strong>Cuenta <?= (int) $account['id_tienda'] ?>: <?= e($account['razon_social'] ?: $account['nombre']) ?></strong>
            <p>
                NIF/CIF: <?= e($account['nif'] ?: 'sin datos') ?> ·
                Tarifa efectiva: <strong><?= (int) $account['id_margen'] ?></strong>
                <span class="muted small">(<?= e($account['margen_origen'] === 'tienda' ? 'puesta en la tienda' : ($account['margen_origen'] === 'cuenta' ? 'la de la cuenta' : 'por defecto')) ?>)</span>
            </p>
            <p class="muted small">
                Los pedidos se facturan a: <?= e($account['razon_social']) ?> ·
                <?= e($account['direccion'] ?: 'sin direccion') ?>
                <?= e(trim(($account['cp'] ?? '') . ' ' . ($account['poblacion'] ?? ''))) ?>
            </p>
            <p class="muted small">
                Nivel de cliente de la tienda:
                <?php if (($nivel['level'] ?? null) !== null): ?>
                    <strong><?= e((string) $nivel['level']['label']) ?></strong>
                    (nivel <?= (int) $nivel['level']['order'] ?>).
                    Los nodos del menu con nivel minimo igual o menor se te muestran.
                <?php else: ?>
                    <strong>sin asignar</strong>.
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php /* El nivel no se edita aqui: `tiendas.id_categ_cliente` es del
             mayorista. Se explica el motivo y quien tiene que cambiarlo. */ ?>
    <?php if (($nivel['level'] ?? null) === null): ?>
        <div class="alert alert-warning">
            <?= e((string) $nivelAviso) ?>
        </div>
    <?php endif; ?>
</section>
