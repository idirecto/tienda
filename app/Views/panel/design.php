<?php
/**
 * Diseno de la tienda.
 * @var array $store @var array $themes @var string $base
 */
use Tienda\Core\Csrf;
$v = static fn (string $k, string $d = '') => e($store[$k] ?? $d);
?>
<form method="post" action="<?= e($base) ?>/panel/diseno" class="stack">
    <?= Csrf::field() ?>

    <section class="card">
        <h2>Plantilla</h2>
        <p class="muted">Elige entre las plantillas disponibles. Todas son responsive.</p>
        <div class="theme-picker">
            <?php foreach ($themes as $t): ?>
                <label class="theme-option">
                    <input type="radio" name="theme" value="<?= e($t['id']) ?>"
                        <?= ($store['theme'] ?? '') === $t['id'] ? 'checked' : '' ?>>
                    <span class="theme-swatch" style="background:<?= e($t['accent'] ?? '#e30613') ?>"></span>
                    <strong><?= e($t['name']) ?></strong>
                    <small><?= e($t['description']) ?></small>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card">
        <h2>Identidad visual</h2>
        <div class="field-grid">
            <label>Color principal
                <input type="color" name="color_primary" value="<?= $v('color_primary', '#e30613') ?>">
            </label>
            <label>Color secundario
                <input type="color" name="color_secondary" value="<?= $v('color_secondary', '#1f2937') ?>">
            </label>
            <label>Tipografia
                <select name="font">
                    <?php foreach (['system' => 'Sistema', 'serif' => 'Serif', 'mono' => 'Monoespaciada'] as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($store['font'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Estilo de cabecera
                <select name="header_style">
                    <?php foreach (['classic' => 'Clasica', 'compact' => 'Compacta', 'centered' => 'Centrada'] as $k => $label): ?>
                        <option value="<?= e($k) ?>" <?= ($store['header_style'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label class="field">Logotipo
            <div class="uploader" data-folder="logo">
                <input type="file" accept="image/*" data-upload data-folder="logo"
                       data-url-target="#logo_url" data-key-target="#logo_key" data-preview="#logo-preview">
                <input type="hidden" name="logo_url" id="logo_url" value="<?= $v('logo_url') ?>">
                <input type="hidden" name="logo_key" id="logo_key" value="<?= $v('logo_key') ?>">
                <div id="logo-preview" class="preview">
                    <?php if (!empty($store['logo_url'])): ?>
                        <img src="<?= e($store['logo_url']) ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                <small class="muted">PNG, JPG o SVG. Maximo 5 MB.</small>
            </div>
        </label>
    </section>

    <section class="card">
        <h2>Textos y datos</h2>
        <div class="field-grid">
            <label>Nombre de la tienda<input type="text" name="name" value="<?= $v('name') ?>" required></label>
            <label>Eslogan<input type="text" name="tagline" value="<?= $v('tagline') ?>"></label>
        </div>
        <label class="field">Sobre nosotros
            <textarea name="about" rows="4"><?= $v('about') ?></textarea>
        </label>
        <div class="field-grid">
            <label>Telefono<input type="text" name="phone" value="<?= $v('phone') ?>"></label>
            <label>WhatsApp<input type="text" name="whatsapp" value="<?= $v('whatsapp') ?>"></label>
            <label>Email<input type="email" name="email" value="<?= $v('email') ?>"></label>
            <label>Direccion<input type="text" name="address" value="<?= $v('address') ?>"></label>
            <label>Ciudad<input type="text" name="city" value="<?= $v('city') ?>"></label>
            <label>Provincia<input type="text" name="province" value="<?= $v('province') ?>"></label>
            <label>Codigo postal<input type="text" name="postal_code" value="<?= $v('postal_code') ?>"></label>
        </div>
    </section>

    <section class="card">
        <h2>Opciones y SEO</h2>
        <div class="field-grid">
            <label>Meta titulo<input type="text" name="meta_title" value="<?= $v('meta_title') ?>"></label>
            <label>Meta descripcion<input type="text" name="meta_description" value="<?= $v('meta_description') ?>"></label>
        </div>
        <div class="checks">
            <label class="check">
                <input type="checkbox" name="show_prices" value="1" <?= (int) ($store['show_prices'] ?? 1) === 1 ? 'checked' : '' ?>>
                Mostrar precios
            </label>
            <label class="check">
                <input type="checkbox" name="allow_orders" value="1" <?= (int) ($store['allow_orders'] ?? 1) === 1 ? 'checked' : '' ?>>
                Permitir pedidos online
            </label>
        </div>
    </section>

    <div class="actions">
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
        <a class="btn btn-ghost" href="<?= e($base) ?>/" target="_blank" rel="noopener">Ver tienda</a>
    </div>
</form>
