<?php
/**
 * Banners.
 * @var array $banners @var int $used @var int $max @var string $base
 */
use Tienda\Core\Csrf;
?>
<section class="two-col">
    <div class="card">
        <div class="card-head">
            <h2>Banners</h2>
            <span class="badge"><?= (int) $used ?> / <?= (int) $max ?></span>
        </div>

        <?php if (empty($banners)): ?>
            <p class="muted">Todavia no hay banners. Crea el primero con el formulario.</p>
        <?php else: ?>
            <ul class="list">
                <?php foreach ($banners as $b): ?>
                    <li class="list-item">
                        <div class="thumb"<?= $b['image_url'] ? ' style="background-image:url(' . e($b['image_url']) . ')"' : '' ?>></div>
                        <div class="list-body">
                            <strong><?= e($b['title'] ?: '(sin titulo)') ?></strong>
                            <small><?= e($b['position']) ?> · orden <?= (int) $b['sort'] ?> · <?= (int) $b['active'] === 1 ? 'activo' : 'oculto' ?></small>
                        </div>
                        <form method="post" action="<?= e($base) ?>/panel/banners/<?= (int) $b['id'] ?>/borrar"
                              onsubmit="return confirm('¿Eliminar este banner?')">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Nuevo banner</h2>
        <?php if ($used >= $max): ?>
            <div class="alert alert-error">Has alcanzado el limite de banners de tu plan.</div>
        <?php else: ?>
            <form method="post" action="<?= e($base) ?>/panel/banners" class="stack">
                <?= Csrf::field() ?>
                <label>Titulo<input type="text" name="title"></label>
                <label>Subtitulo<input type="text" name="subtitle"></label>
                <label>Enlace<input type="url" name="link" placeholder="https://..."></label>
                <label>Posicion
                    <select name="position">
                        <option value="hero">Portada (hero)</option>
                        <option value="middle">Intermedio</option>
                        <option value="footer">Pie</option>
                    </select>
                </label>
                <label class="field">Imagen
                    <div class="uploader" data-folder="banners">
                        <input type="file" accept="image/*" data-upload data-folder="banners"
                               data-url-target="#banner_image_url" data-preview="#banner-preview">
                        <input type="hidden" name="image_url" id="banner_image_url">
                        <div id="banner-preview" class="preview"></div>
                    </div>
                </label>
                <div class="field-grid">
                    <label>Orden<input type="number" name="sort" value="0"></label>
                    <label class="check"><input type="checkbox" name="active" value="1" checked> Activo</label>
                </div>
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Crear banner</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>
