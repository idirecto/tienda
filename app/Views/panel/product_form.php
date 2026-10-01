<?php
/**
 * Formulario de producto propio (alta/edicion).
 * @var array|null $product @var string $base
 */
use Tienda\Core\Csrf;
$isEdit = $product !== null;
$action = $isEdit ? $base . '/panel/productos/' . (int) $product['id'] : $base . '/panel/productos';
$v = static fn (string $k, string $d = '') => e($product[$k] ?? $d);
?>
<form method="post" action="<?= e($action) ?>" class="stack">
    <?= Csrf::field() ?>

    <section class="card">
        <h2><?= $isEdit ? 'Editar producto' : 'Nuevo producto' ?></h2>
        <div class="field-grid">
            <label>Nombre<input type="text" name="name" value="<?= $v('name') ?>" required></label>
            <label>SKU / referencia<input type="text" name="sku" value="<?= $v('sku') ?>"></label>
            <label>Proveedor<input type="text" name="supplier" value="<?= $v('supplier') ?>" placeholder="idirecto u otro proveedor"></label>
            <label>Categoria<input type="text" name="category" value="<?= $v('category') ?>"></label>
            <label>Precio (€)<input type="text" name="price" value="<?= $v('price', '0.00') ?>" required></label>
            <label>Precio oferta (€)<input type="text" name="sale_price" value="<?= $v('sale_price') ?>"></label>
            <label>Stock<input type="number" name="stock" value="<?= $v('stock', '0') ?>"></label>
            <label>Estado
                <select name="status">
                    <?php foreach ([1 => 'Publicado', 0 => 'Borrador', 2 => 'Oculto'] as $k => $label): ?>
                        <option value="<?= $k ?>" <?= (int) ($product['status'] ?? 1) === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label class="field">Descripcion
            <textarea name="description" rows="4"><?= $v('description') ?></textarea>
        </label>
    </section>

    <section class="card">
        <h2>Imagen</h2>
        <div class="uploader" data-folder="productos">
            <input type="file" accept="image/*" data-upload data-folder="productos"
                   data-url-target="#product_image_url" data-url-id="#product_media_id" data-preview="#product-preview">
            <input type="hidden" name="image_url" id="product_image_url" value="<?= $v('image_url') ?>">
            <input type="hidden" name="media_id" id="product_media_id" value="<?= $v('media_id') ?>">
            <div id="product-preview" class="preview">
                <?php if (!empty($product['image_url'])): ?>
                    <img src="<?= e($product['image_url']) ?>" alt="">
                <?php endif; ?>
            </div>
            <small class="muted">PNG, JPG o WEBP. Maximo 5 MB.</small>
        </div>
    </section>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Crear producto' ?></button>
        <a class="btn btn-ghost" href="<?= e($base) ?>/panel/productos">Cancelar</a>
    </div>
</form>
