<?php
/**
 * Contenido del mini-carrito lateral.
 *
 * NO guarda nada por su cuenta: pinta el estado real que le pasa `Cart`
 * (mismo carrito de sesion que la pagina `/carrito`). Se usa en dos sitios:
 *   - dentro del layout `shop.php`, para que el panel ya venga pintado;
 *   - en la respuesta JSON de `/carrito/anadir`, `/carrito/actualizar`,
 *     `/carrito/quitar`, `/carrito/vaciar` y `GET /carrito/mini`, para
 *     refrescarlo sin recargar.
 *
 * @var array $items     Lineas con precio y stock en vivo
 * @var array $totals    Subtotal, IVA, envio y total
 * @var int   $count     Unidades totales
 * @var array $dropped   Productos retirados (ya no disponibles)
 * @var int   $maxQty    Tope de unidades por linea
 * @var bool  $freeFrom  La tienda tiene envio gratis desde X
 * @var float $missingFree  Cuanto falta para el envio gratis
 * @var bool  $allowOrders  La tienda admite pedidos
 * @var string $base
 * @var \Tienda\Core\Tenant $tenant
 */

$hasFree = $freeFrom && (float) $totals['shipping'] <= 0;
?>
<div class="minicart-content" data-minicart-content>
    <header class="minicart-head">
        <h2 class="minicart-title">
            Tu carrito
            <?php if ($count > 0): ?>
                <span class="minicart-title-count"><?= (int) $totals['units'] ?> ud.</span>
            <?php endif; ?>
        </h2>
        <button type="button" class="minicart-close" data-minicart-close aria-label="Cerrar el carrito">
            <?= icon_svg('close') ?>
        </button>
    </header>

    <?php if ($items === []): ?>
        <div class="minicart-empty">
            <span class="minicart-empty-icon" aria-hidden="true"><?= icon_svg('cart') ?></span>
            <p>Tu carrito esta vacio.</p>
            <a class="btn btn-primary" href="<?= e($base) ?>/catalogo" data-minicart-dismiss>Seguir comprando</a>
        </div>
    <?php else: ?>
        <div class="minicart-body" data-minicart-body>
            <ul class="minicart-lines">
                <?php foreach ($items as $item): ?>
                    <?php
                    $name  = (string) $item['name'];
                    $stock = $item['stock_total'];
                    // Tope editable de la linea: stock real si se conoce (solo
                    // recorta cuando hay mas de 0; un propio con stock 0 significa
                    // "la tienda no lleva stock" y mantiene el tope global).
                    $max = ($stock !== null && (int) $stock > 0)
                        ? min((int) $maxQty, (int) $stock)
                        : (int) $maxQty;
                    ?>
                    <li class="minicart-line" data-minicart-line
                        data-key="<?= e($item['key']) ?>"
                        data-max="<?= (int) $max ?>">
                        <a class="minicart-media"
                           <?= !empty($item['url']) ? 'href="' . e($item['url']) . '"' : 'href="' . e($base) . '/catalogo"' ?>
                           tabindex="-1" aria-hidden="true">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= e($item['image_url']) ?>" alt=""
                                     width="72" height="72" loading="lazy" decoding="async"
                                     onerror="this.remove()">
                            <?php else: ?>
                                <span class="minicart-ph"><?= e(mb_strtoupper(mb_substr($name, 0, 2))) ?></span>
                            <?php endif; ?>
                        </a>

                        <div class="minicart-info">
                            <h3 class="minicart-name">
                                <?php if (!empty($item['url'])): ?>
                                    <a href="<?= e($item['url']) ?>"><?= e($name) ?></a>
                                <?php else: ?>
                                    <?= e($name) ?>
                                <?php endif; ?>
                            </h3>

                            <?php if (!empty($item['sku'])): ?>
                                <p class="minicart-sku">Ref. <?= e($item['sku']) ?></p>
                            <?php endif; ?>

                            <?php /* Variante: hoy el catalogo no tiene variantes; si
                                     algun dia existe, basta con rellenar esta clave. */ ?>
                            <?php if (!empty($item['variant_label'])): ?>
                                <p class="minicart-variant"><?= e($item['variant_label']) ?></p>
                            <?php endif; ?>

                            <?php if ((string) $item['source'] === 'own'): ?>
                                <span class="pill pill-info">Propio</span>
                            <?php endif; ?>

                            <p class="minicart-unit">
                                <span data-minicart-unit><?= e(euros($item['price'])) ?></span>
                                <small>IVA incl.</small>
                            </p>
                        </div>

                        <div class="minicart-side">
                            <div class="minicart-qty">
                                <button type="button" class="minicart-step" data-minicart-step="-1"
                                        aria-label="Quitar una unidad de <?= e($name) ?>">&minus;</button>
                                <input class="minicart-qty-input" type="number"
                                       value="<?= (int) $item['qty'] ?>" min="1" max="<?= (int) $max ?>"
                                       inputmode="numeric" data-minicart-input
                                       aria-label="Cantidad de <?= e($name) ?>">
                                <button type="button" class="minicart-step" data-minicart-step="1"
                                        aria-label="Anadir una unidad de <?= e($name) ?>">+</button>
                            </div>

                            <span class="minicart-line-total" data-minicart-line-total>
                                <?= e(euros($item['line_total'])) ?>
                            </span>

                            <button type="button" class="minicart-remove" data-minicart-remove
                                    aria-label="Quitar <?= e($name) ?> del carrito">
                                <?= icon_svg('close') ?><span>Quitar</span>
                            </button>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($dropped !== []): ?>
                <p class="minicart-warn" role="status">
                    Se han quitado del carrito: <?= e(implode(', ', $dropped)) ?> (ya no estan disponibles).
                </p>
            <?php endif; ?>
        </div>

        <footer class="minicart-foot">
            <dl class="minicart-totals">
                <div>
                    <dt>Productos</dt>
                    <dd data-minicart-subtotal><?= e(euros($totals['subtotal'])) ?></dd>
                </div>
                <div>
                    <dt>Gastos de envio</dt>
                    <dd data-minicart-shipping><?= $totals['shipping'] > 0 ? e(euros($totals['shipping'])) : 'Gratis' ?></dd>
                </div>
                <div class="minicart-totals-grand">
                    <dt>Total</dt>
                    <dd data-minicart-total><?= e(euros($totals['total'])) ?></dd>
                </div>
            </dl>

            <?php if ($missingFree > 0): ?>
                <p class="minicart-hint">
                    Te faltan <strong><?= e(euros($missingFree)) ?></strong> para el envio gratis.
                </p>
            <?php elseif ($hasFree): ?>
                <p class="minicart-hint minicart-hint-ok">Envio gratis incluido.</p>
            <?php endif; ?>

            <?php if ($allowOrders): ?>
                <a class="btn btn-primary btn-lg btn-block minicart-cta" href="<?= e($base) ?>/carrito">
                    <span class="minicart-cta-desktop">Ver artículos del carrito</span>
                    <span class="minicart-cta-mobile">Ver carrito</span>
                </a>
            <?php else: ?>
                <p class="muted">Esta tienda no esta aceptando pedidos ahora mismo.</p>
            <?php endif; ?>
        </footer>
    <?php endif; ?>
</div>
