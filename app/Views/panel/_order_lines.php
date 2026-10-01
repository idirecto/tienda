<?php
/**
 * Editor de lineas de un pedido (alta manual y "anadir lineas" de la ficha).
 *
 * Se envia como `lines[i][...]`; el servidor lo normaliza con
 * OrderItem::normalizeLines(). El buscador de productos llama al endpoint JSON
 * `/panel/pedidos/buscar` y va anadiendo filas (panel.js -> initLineEditors).
 *
 * @var array $items Lineas ya guardadas (vacio al crear)
 * @var string $base
 */

/** Fila del editor: inputs con el indice que espera el controlador. */
$fila = static function (int $i, array $line = []): string {
    $source = (string) ($line['source'] ?? 'catalog');
    $name = (string) ($line['name'] ?? '');
    $sku = (string) ($line['sku'] ?? '');
    $qty = (int) ($line['qty'] ?? 1);
    $price = (float) ($line['price_customer'] ?? 0);
    $tax = (float) ($line['tax_rate'] ?? 21);

    $hidden = static fn (string $campo, string $valor): string =>
        '<input type="hidden" name="lines[' . $i . '][' . $campo . ']" value="' . e($valor) . '">';

    $html = '<tr data-line>'
        . '<td class="line-product">'
        . $hidden('source', $source)
        . $hidden('product_id', (string) (int) ($line['product_id'] ?? 0))
        . '<input type="text" class="line-name" name="lines[' . $i . '][name]" value="' . e($name) . '" placeholder="Producto" required>'
        . '<input type="hidden" name="lines[' . $i . '][sku]" value="' . e($sku) . '">'
        . '<small class="muted" data-sku>' . e($sku) . '</small>'
        . '<span class="pill pill-info line-own" data-own' . ($source === 'own' ? '' : ' hidden') . '>propio</span>'
        . '</td>'
        . '<td><input type="number" class="line-qty" name="lines[' . $i . '][qty]" value="' . (int) $qty . '" min="1" step="1"></td>'
        . '<td><input type="text" class="line-price ta-r" name="lines[' . $i . '][price_customer]" value="' . e(number_format($price, 2, ',', '')) . '"></td>'
        . '<td><input type="text" class="line-tax ta-r" name="lines[' . $i . '][tax_rate]" value="' . e(number_format($tax, 2, ',', '')) . '"></td>'
        . '<td class="ta-r nowrap"><strong data-line-total>0,00 &euro;</strong></td>'
        . '<td class="ta-r"><button type="button" class="btn btn-danger btn-sm" data-remove-line title="Quitar linea">&times;</button></td>'
        . '</tr>';

    return $html;
};
?>
<div class="line-editor" data-line-editor data-search-url="<?= e($base) ?>/panel/pedidos/buscar">
    <div class="line-search">
        <label>Buscar producto del catalogo o propio
            <input type="search" data-product-search autocomplete="off"
                   placeholder="Nombre, referencia o marca&hellip;">
        </label>
        <div class="search-results" data-search-results hidden></div>
        <p class="muted small">
            Al elegir un producto se anade una linea. Los productos propios se pueden vender,
            pero no se envian al mayorista.
        </p>
    </div>

    <table class="table order-lines">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="w-qty">Uds.</th>
                <th class="ta-r w-price">Precio cliente</th>
                <th class="ta-r w-tax">IVA %</th>
                <th class="ta-r">Subtotal</th>
                <th></th>
            </tr>
        </thead>
        <tbody data-lines>
            <?php $i = 0; foreach ($items as $line): ?>
                <?= $fila($i++, $line) ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="line-actions">
        <button type="button" class="btn btn-ghost btn-sm" data-add-line>Anadir linea manual</button>
        <span class="muted small">Tambien puedes escribir el nombre a mano.</span>
    </div>

    <div class="line-totals">
        <div><span class="muted">Subtotal</span> <strong data-total-subtotal>0,00 &euro;</strong></div>
        <div><span class="muted">IVA</span> <strong data-total-tax>0,00 &euro;</strong></div>
        <div class="line-totals-total"><span class="muted">Total</span> <strong data-total-grand>0,00 &euro;</strong></div>
    </div>

    <template data-line-template><?= $fila(999999) ?></template>
</div>
