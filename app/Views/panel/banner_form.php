<?php
/**
 * Alta y edicion de un banner.
 *
 * @var array $banner
 * @var bool $isEdit
 * @var array<int,string> $errors
 * @var bool $canCreate
 * @var int $used
 * @var int $max
 * @var string $planName
 * @var array<string,string> $statuses
 * @var array<string,string> $positions
 * @var int $overlayDefault
 * @var int $overlayMax
 * @var bool $legacyPosition
 * @var string $base
 */
use Tienda\Core\Csrf;
use Tienda\Core\Media\MediaRules;

$action = $base . '/panel/banners' . ($isEdit ? '/' . (int) $banner['id'] : '');
$dateValue = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
};
$status = isset($statuses[(string) $banner['status']]) ? (string) $banner['status'] : 'active';
$position = isset($positions[(string) $banner['position']]) ? (string) $banner['position'] : '';
$overlay = max(0, min($overlayMax, (int) ($banner['overlay_opacity'] ?? $overlayDefault)));
?>
<section class="editor-heading">
    <div>
        <h2><?= $isEdit ? 'Editar banner' : 'Nuevo banner' ?></h2>
        <p class="muted">
            Plan <?= e($planName) ?>: <?= (int) $used ?> de <?= (int) $max ?> banners.
            El limite lo marca el plan; no se puede ampliar desde la tienda.
        </p>
    </div>
    <a class="btn btn-ghost" href="<?= e($base) ?>/panel/banners">Volver</a>
</section>

<?php if (!$canCreate): ?>
    <div class="alert alert-error">
        Has alcanzado el limite de banners de tu plan (<?= (int) $max ?>).
        Puedes editar o eliminar uno existente, pero no crear otro.
    </div>
<?php endif; ?>

<?php if ($errors !== []): ?>
    <div class="alert alert-error" role="alert">
        <strong>Revisa estos puntos:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($legacyPosition): ?>
    <div class="alert alert-warning">
        La posicion guardada de este banner no tiene render publico en esta version.
        Para mostrarlo, elige <strong>Portada (hero)</strong> y confirma el cambio.
    </div>
<?php endif; ?>

<form class="stack" method="post" action="<?= e($action) ?>" data-banner-form
      data-preview-url="<?= e($base) ?>/panel/banners/previa">
    <?= Csrf::field() ?>
    <input type="hidden" name="banner_id" value="<?= $isEdit ? (int) $banner['id'] : 0 ?>">
    <input type="hidden" name="media_id" id="banner_media_id"
           value="<?= $banner['media_id'] !== null ? (int) $banner['media_id'] : '' ?>">
    <input type="hidden" name="image_url" id="banner_image_url"
           value="<?= e((string) ($banner['image_url'] ?? '')) ?>">

    <div class="editor-grid">
        <div class="editor-main">
            <section class="card">
                <h2>Contenido</h2>
                <div class="field-grid">
                    <label>Nombre interno
                        <input type="text" name="name" maxlength="120"
                               value="<?= e((string) $banner['name']) ?>"
                               placeholder="Ej. Campana de portada">
                        <small class="muted">Solo aparece en el panel. No se publica.</small>
                    </label>
                    <label>Titulo
                        <input type="text" name="title" maxlength="150"
                               value="<?= e((string) $banner['title']) ?>">
                    </label>
                    <label class="field-wide">Subtitulo
                        <input type="text" name="subtitle" maxlength="255"
                               value="<?= e((string) $banner['subtitle']) ?>">
                    </label>
                    <label class="field-wide">Texto alternativo de la imagen
                        <input type="text" name="alt_text" maxlength="255"
                               value="<?= e((string) $banner['alt_text']) ?>"
                               placeholder="Describe la imagen si aporta informacion">
                    </label>
                    <label class="field-wide">Enlace del boton
                        <input type="text" name="link" maxlength="500"
                               value="<?= e((string) $banner['link']) ?>"
                               placeholder="/catalogo o https://...">
                        <small class="muted">Solo rutas internas que empiezan por / o URLs http/https.</small>
                    </label>
                </div>
            </section>

            <section class="card">
                <h2>Imagen</h2>
                <div class="uploader" data-folder="banners">
                    <input type="file" accept="image/*" data-upload data-folder="banners"
                           data-max-bytes="<?= MediaRules::maxBytes('banners') ?>"
                           data-status="#banner-status"
                           data-url-target="#banner_image_url"
                           data-url-id="#banner_media_id"
                           data-preview="#banner-preview">
                    <div id="banner-preview" class="banner-image-preview">
                        <?php if (!empty($banner['image_url'])): ?>
                            <img src="<?= e((string) $banner['image_url']) ?>" alt="Vista previa de la imagen">
                        <?php endif; ?>
                    </div>
                    <small class="muted" id="banner-status">
                        JPG, PNG, WebP, GIF, SVG o AVIF hasta
                        <?= e(MediaRules::formatoBytes(MediaRules::maxBytes('banners'))) ?>.
                        La imagen se optimiza a WebP cuando corresponde.
                    </small>
                </div>
                <div class="hint-box">
                    <p><strong>Medida recomendada para el hero:</strong> 1920 x 600 px (relacion 16:5).
                       También funciona 1600 x 500 px.</p>
                    <p>La imagen se muestra con recorte tipo <code>cover</code>. Manten el texto o el
                       producto importante dentro del 80 % central. La previsualizacion muestra el recorte
                       real en escritorio y movil.</p>
                    <p class="muted small">No se recorta ni se elimina el archivo original. Al sustituir o
                       borrar un banner, la imagen anterior permanece en la biblioteca de medios.</p>
                </div>
            </section>

            <section class="card">
                <h2>Publicacion</h2>
                <div class="field-grid">
                    <label>Estado
                        <select name="status">
                            <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Posicion
                        <select name="position">
                            <?php foreach ($positions as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $position === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="muted">Solo se ofrecen posiciones que la tienda pinta actualmente.</small>
                    </label>
                    <?php if ($legacyPosition): ?>
                        <label class="check check-line field-wide">
                            <input type="checkbox" name="confirm_move_position" value="1">
                            Confirmo que quiero mover este banner antiguo a Portada.
                        </label>
                    <?php endif; ?>
                    <label>Orden
                        <input type="number" name="sort" min="-32768" max="32767"
                               value="<?= (int) $banner['sort'] ?>">
                        <small class="muted">Los numeros menores aparecen antes.</small>
                    </label>
                    <label>Fecha de inicio
                        <input type="datetime-local" name="starts_at"
                               value="<?= e($dateValue($banner['starts_at'])) ?>">
                        <small class="muted">Obligatoria si el estado es Programado.</small>
                    </label>
                    <label>Fecha de fin
                        <input type="datetime-local" name="ends_at"
                               value="<?= e($dateValue($banner['ends_at'])) ?>">
                    </label>
                </div>
            </section>

            <section class="card">
                <h2>Velo de la imagen</h2>
                <p class="muted">
                    Regula el oscurecimiento general de la fotografia. El texto conserva una base local
                    para seguir siendo legible, pero la imagen ya no queda oscurecida al 88 %.
                </p>
                <label class="range-field">Intensidad
                    <span class="range-row">
                        <input type="range" name="overlay_opacity" id="banner_overlay"
                               min="0" max="<?= (int) $overlayMax ?>" step="5"
                               value="<?= (int) $overlay ?>">
                        <output for="banner_overlay" id="banner-overlay-output"><?= (int) $overlay ?> %</output>
                    </span>
                    <small class="muted">0 % no oscurece la imagen; <?= (int) $overlayMax ?> % es el maximo permitido.</small>
                </label>
            </section>

            <div class="actions">
                <button class="btn btn-primary" type="submit"
                        <?= !$canCreate ? 'disabled' : '' ?>>
                    <?= $isEdit ? 'Guardar cambios' : 'Crear banner' ?>
                </button>
                <a class="btn btn-ghost" href="<?= e($base) ?>/panel/banners">Cancelar</a>
            </div>
        </div>

        <aside class="editor-side">
            <section class="card">
                <h2>Antes de guardar</h2>
                <ul class="steps">
                    <li>Comprueba el estado y las fechas.</li>
                    <li>Revisa el recorte en escritorio y movil.</li>
                    <li>Los cambios no se publican hasta guardar.</li>
                    <li>Borrar un banner no borra su imagen.</li>
                </ul>
            </section>
        </aside>
    </div>
</form>

<section class="card banner-preview-card" data-banner-preview>
    <div class="card-head">
        <div>
            <h2>Previsualizacion</h2>
            <p class="muted small">Mismo CSS y mismo hero que la tienda. Los cambios se actualizan sin guardar.</p>
        </div>
        <span class="muted small" data-banner-preview-state>Esperando cambios...</span>
    </div>
    <div class="banner-preview-grid">
        <div class="banner-preview-device">
            <div class="banner-preview-label">Escritorio · 1280 px</div>
            <div class="banner-preview-scroll">
                <iframe class="banner-preview-frame banner-preview-frame--desktop"
                        title="Previsualizacion del banner en escritorio" sandbox=""></iframe>
            </div>
        </div>
        <div class="banner-preview-device">
            <div class="banner-preview-label">Movil · 390 px</div>
            <iframe class="banner-preview-frame banner-preview-frame--mobile"
                    title="Previsualizacion del banner en movil" sandbox=""></iframe>
        </div>
    </div>
    <noscript><p class="muted">Activa JavaScript para ver la previsualizacion.</p></noscript>
</section>
