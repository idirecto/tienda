<?php
/**
 * Diseno de la tienda: identidad visual (design tokens), plantilla, textos.
 *
 * Los colores no se guardan "a pelo": se guardan como los tokens que
 * `Tienda\Core\Appearance` convierte en variables CSS del storefront. El panel
 * ofrece presets para tener una identidad completa en un clic y una vista
 * previa en vivo (iframe) que usa el mismo generador que la web publica.
 *
 * @var array $store @var array $themes @var string $base
 * @var array $presets @var array $schemes @var array $radii @var array $fonts
 */
use Tienda\Core\Csrf;
use Tienda\Core\Media\MediaRules;

$v = static fn (string $k, string $d = '') => e($store[$k] ?? $d);
$optionalColors = [
    'color_bg'      => ['Fondo de la pagina', '#f4f6fa'],
    'color_surface' => ['Fondo de tarjetas', '#ffffff'],
    'color_text'    => ['Color de texto', '#1f2937'],
    'color_border'  => ['Color de bordes', '#e2e8f0'],
];
?>
<form method="post" action="<?= e($base) ?>/panel/diseno" class="stack" data-design-form>
    <?= Csrf::field() ?>

    <div class="design-layout">
        <div class="design-col">

            <section class="card">
                <h2>Identidad visual</h2>
                <p class="muted">Elige un preset para cambiar toda la identidad de la tienda de golpe
                   (colores, modo, forma y tipografia) o ajusta los colores a mano mas abajo.</p>

                <div class="preset-grid">
                    <?php foreach ($presets as $key => $preset): ?>
                        <button type="submit" name="apply_preset" value="<?= e($key) ?>"
                                class="preset<?= ($store['color_primary'] ?? '') === ($preset['primary'] ?? '') ? ' is-current' : '' ?>"
                                data-preset
                                data-primary="<?= e($preset['primary'] ?? '') ?>"
                                data-secondary="<?= e($preset['secondary'] ?? '') ?>"
                                data-accent="<?= e($preset['accent'] ?? '') ?>"
                                data-scheme="<?= e($preset['scheme'] ?? 'light') ?>"
                                data-radius="<?= e($preset['radius'] ?? 'standard') ?>"
                                data-font="<?= e($preset['font'] ?? 'system') ?>"
                                title="Aplicar y guardar: <?= e($preset['label'] ?? $key) ?>">
                            <span class="preset-swatch"
                                  style="background-image: linear-gradient(115deg, <?= e($preset['secondary'] ?? '#111827') ?> 0 55%, <?= e($preset['primary'] ?? '#e30613') ?> 55% 100%)"></span>
                            <strong><?= e($preset['label'] ?? $key) ?></strong>
                            <small>
                                <?= e($preset['scheme'] === 'dark' ? 'Modo oscuro' : 'Modo claro') ?>
                                · <?= e($radii[$preset['radius']] ?? '') ?>
                            </small>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="muted small">Al pulsar un preset se guarda la tienda con esa identidad.</p>
            </section>

            <section class="card">
                <h2>Colores de marca</h2>
                <div class="field-grid">
                    <label>Color principal
                        <span class="color-field">
                            <input type="color" name="color_primary" value="<?= $v('color_primary', '#e30613') ?>" data-color-for="color_primary">
                            <input type="text" class="color-text" data-color-text="color_primary"
                                   value="<?= $v('color_primary', '#e30613') ?>" spellcheck="false" aria-label="Color principal en hexadecimal">
                        </span>
                    </label>
                    <label>Color secundario
                        <span class="color-field">
                            <input type="color" name="color_secondary" value="<?= $v('color_secondary', '#1f2937') ?>" data-color-for="color_secondary">
                            <input type="text" class="color-text" data-color-text="color_secondary"
                                   value="<?= $v('color_secondary', '#1f2937') ?>" spellcheck="false" aria-label="Color secundario en hexadecimal">
                        </span>
                    </label>
                    <label>Color de acento
                        <span class="color-field">
                            <input type="color" name="color_accent" value="<?= $v('color_accent', '#00aff0') ?>" data-color-for="color_accent">
                            <input type="text" class="color-text" data-color-text="color_accent"
                                   value="<?= $v('color_accent', '#00aff0') ?>" spellcheck="false" aria-label="Color de acento en hexadecimal">
                        </span>
                    </label>
                </div>
                <p class="muted small">El color de acento se usa en detalles, focos y el subrayado de la ficha.
                   El texto sobre cada color se calcula solo para que siempre sea legible.</p>

                <details class="advanced">
                    <summary>Colores avanzados (fondo, tarjetas, texto y bordes)</summary>
                    <p class="muted small">Dejalos en blanco para usar los del tema. Se aplican al modo claro:
                       el modo oscuro calcula su propia paleta para que el contraste sea correcto.</p>
                    <div class="field-grid">
                        <?php foreach ($optionalColors as $key => [$label, $placeholder]): ?>
                            <label><?= e($label) ?>
                                <span class="color-field">
                                    <input type="color" name="<?= e($key) ?>" value="<?= $v($key, $placeholder) ?>" data-color-for="<?= e($key) ?>">
                                    <input type="text" class="color-text" data-color-text="<?= e($key) ?>"
                                           value="<?= $v($key) ?>" placeholder="Tema" spellcheck="false"
                                           aria-label="<?= e($label) ?> en hexadecimal">
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </details>
            </section>

            <section class="card">
                <h2>Modo, forma y tipografia</h2>
                <div class="field-grid">
                    <label>Modo de color
                        <select name="color_scheme" data-preview-input>
                            <?php foreach ($schemes as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($store['color_scheme'] ?? 'light') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Forma (radios)
                        <select name="radius_scale" data-preview-input>
                            <?php foreach ($radii as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($store['radius_scale'] ?? 'standard') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Tipografia
                        <select name="font" data-preview-input>
                            <?php foreach ($fonts as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($store['font'] ?? 'system') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Estilo de cabecera
                        <select name="header_style" data-preview-input>
                            <?php foreach (['classic' => 'Clasica', 'compact' => 'Compacta', 'centered' => 'Centrada'] as $k => $label): ?>
                                <option value="<?= e($k) ?>" <?= ($store['header_style'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </section>

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

                <label class="field">Logotipo
                    <div class="uploader" data-folder="logo">
                        <input type="file" accept="image/*" data-upload data-folder="logo"
                               data-max-bytes="<?= MediaRules::maxBytes('logo') ?>"
                               data-url-target="#logo_url" data-key-target="#logo_key" data-preview="#logo-preview">
                        <input type="hidden" name="logo_url" id="logo_url" value="<?= $v('logo_url') ?>">
                        <input type="hidden" name="logo_key" id="logo_key" value="<?= $v('logo_key') ?>">
                        <div id="logo-preview" class="preview">
                            <?php if (!empty($store['logo_url'])): ?>
                                <img src="<?= e($store['logo_url']) ?>" alt="Logo">
                            <?php endif; ?>
                        </div>
                        <small class="muted">PNG, JPG o SVG. Se convierte a WebP si pesa menos.</small>
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

                <details class="advanced">
                    <summary>Personalizacion avanzada (tokens y CSS propio)</summary>
                    <p class="muted small">
                        Los <strong>tokens</strong> son un JSON que pisa cualquier variable del sistema. Formas admitidas:
                        <code>{"light": {"bg": "#ffffff"}, "dark": {"surface": "#101010"}, "raw": {"--c-container-max": "1400px"}}</code>
                        o un objeto plano de tokens (<code>{"primary": "#0af"}</code>).
                    </p>
                    <label class="field">Tokens personalizados (JSON)
                        <textarea name="theme_tokens" rows="4" spellcheck="false" data-preview-input
                                  placeholder='{"dark": {"surface": "#101820"}}'><?= $v('theme_tokens') ?></textarea>
                    </label>
                    <label class="field">CSS propio (avanzado)
                        <textarea name="custom_css" rows="4" spellcheck="false"
                                  placeholder=".product-card { border-radius: 0; }"><?= $v('custom_css') ?></textarea>
                    </label>
                </details>
            </section>

            <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar cambios</button>
                <a class="btn btn-ghost" href="<?= e($base) ?>/" target="_blank" rel="noopener">Ver tienda</a>
            </div>
        </div>

        <aside class="design-preview-col">
            <div class="card design-preview-card">
                <div class="card-head">
                    <h2>Vista previa</h2>
                    <span class="badge" data-preview-state>Guardado</span>
                </div>
                <iframe class="design-preview-frame" data-design-preview
                        src="<?= e($base) ?>/panel/diseno/previa"
                        title="Vista previa de la identidad visual"
                        loading="lazy"></iframe>
                <p class="muted small">La previa usa el mismo generador de estilos que la tienda publica.</p>
            </div>
        </aside>
    </div>
</form>
