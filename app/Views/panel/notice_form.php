<?php
/**
 * Alta y edicion de un aviso.
 *
 * @var array $notice
 * @var bool $isEdit
 * @var array<int,string> $errors
 * @var bool $canCreate
 * @var int $used
 * @var int $max
 * @var string $planName
 * @var array<string,string> $types
 * @var array<string,string> $variants
 * @var array<string,string> $textSizes
 * @var array<string,string> $icons
 * @var string $base
 */
use Tienda\Core\Csrf;
use Tienda\Core\Media\MediaRules;

$action = $base . '/panel/avisos' . ($isEdit ? '/' . (int) $notice['id'] : '');
$dateValue = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
};
$type = isset($types[(string) $notice['type']]) ? (string) $notice['type'] : 'info';
$variant = isset($variants[(string) $notice['variant']]) ? (string) $notice['variant'] : 'soft';
$textSize = isset($textSizes[(string) $notice['text_size']]) ? (string) $notice['text_size'] : 'md';
$icon = isset($icons[(string) $notice['icon']]) ? (string) $notice['icon'] : '';
$visible = (string) ($notice['visible'] ?? '1') === '1';
?>
<section class="editor-heading">
    <div>
        <h2><?= $isEdit ? 'Editar aviso' : 'Nuevo aviso' ?></h2>
        <p class="muted">
            Plan <?= e($planName) ?>: <?= (int) $used ?> de <?= (int) $max ?> avisos.
            El limite se calcula siempre desde el plan actual.
        </p>
    </div>
    <a class="btn btn-ghost" href="<?= e($base) ?>/panel/avisos">Volver</a>
</section>

<?php if (!$canCreate): ?>
    <div class="alert alert-error">
        Has alcanzado el limite de avisos de tu plan (<?= (int) $max ?>).
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

<form class="stack" method="post" action="<?= e($action) ?>" data-notice-form
      data-preview-url="<?= e($base) ?>/panel/avisos/previa">
    <?= Csrf::field() ?>
    <input type="hidden" name="notice_id" value="<?= $isEdit ? (int) $notice['id'] : 0 ?>">
    <input type="hidden" name="media_id" id="notice_media_id"
           value="<?= $notice['media_id'] !== null ? (int) $notice['media_id'] : '' ?>">
    <input type="hidden" name="image_url" id="notice_image_url"
           value="<?= e((string) ($notice['image_url'] ?? '')) ?>">

    <div class="editor-grid">
        <div class="editor-main">
            <section class="card">
                <h2>Contenido</h2>
                <div class="field-grid">
                    <label>Tipo
                        <select name="type">
                            <?php foreach ($types as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $type === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Variante visual
                        <select name="variant">
                            <?php foreach ($variants as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $variant === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Tamano del texto
                        <select name="text_size">
                            <?php foreach ($textSizes as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $textSize === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Icono
                        <select name="icon">
                            <option value="">Sin icono</option>
                            <?php foreach ($icons as $value => $label): ?>
                                <option value="<?= e($value) ?>" <?= $icon === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field-span-2">Badge
                        <input type="text" name="badge_text" maxlength="40"
                               value="<?= e((string) $notice['badge_text']) ?>"
                               placeholder="Ej. OFERTA o NUEVO">
                        <small class="muted">Opcional. Lo eliges tu; no se genera automaticamente.</small>
                    </label>
                    <label class="field-wide">Mensaje
                        <textarea name="message" rows="4" maxlength="500" required><?= e((string) $notice['message']) ?></textarea>
                        <small class="muted">Maximo 500 caracteres. Se publica literalmente, sin inventar condiciones.</small>
                    </label>
                </div>
            </section>

            <section class="card">
                <h2>Imagen opcional</h2>
                <div class="uploader" data-folder="avisos">
                    <input type="file" accept="image/*" data-upload data-folder="avisos"
                           data-max-bytes="<?= MediaRules::maxBytes('avisos') ?>"
                           data-status="#notice-status"
                           data-url-target="#notice_image_url"
                           data-url-id="#notice_media_id"
                           data-preview="#notice-preview">
                    <div id="notice-preview" class="notice-image-preview">
                        <?php if (!empty($notice['image_url'])): ?>
                            <img src="<?= e((string) $notice['image_url']) ?>" alt="Vista previa de la imagen">
                        <?php endif; ?>
                    </div>
                    <small class="muted" id="notice-status">
                        Imagen opcional hasta <?= e(MediaRules::formatoBytes(MediaRules::maxBytes('avisos'))) ?>.
                        Se usa el sistema de medios y se asocia a esta tienda. La imagen actual se conserva
                        hasta que subas otra.
                    </small>
                </div>
                <p class="muted small">
                    La imagen es decorativa; el mensaje sigue siendo la informacion principal y debe
                    entenderse sin la imagen. No se elimina el archivo al quitar un aviso.
                </p>
            </section>

            <section class="card">
                <h2>Fechas y estado</h2>
                <div class="field-grid field-grid--pair">
                    <label class="check check-line field-wide">
                        <input type="checkbox" name="visible" value="1" <?= $visible ? 'checked' : '' ?>>
                        <span>
                            Visible
                            <small class="muted">Si lo desmarcas se oculta aunque tenga fechas. Sin fecha de inicio se muestra al guardar.</small>
                        </span>
                    </label>
                    <label>Fecha de inicio
                        <input type="datetime-local" name="starts_at"
                               value="<?= e($dateValue($notice['starts_at'])) ?>">
                        <small class="muted">Sin fecha, se muestra en cuanto se guarde.</small>
                    </label>
                    <label>Fecha de fin
                        <input type="datetime-local" name="ends_at"
                               value="<?= e($dateValue($notice['ends_at'])) ?>">
                        <small class="muted">Sin fecha, permanece visible hasta que la desactives.</small>
                    </label>
                </div>
                <p class="muted small">
                    Sin fechas se muestra mientras este visible. Con una fecha de inicio futura se
                    mostrara como programado; al pasar la fecha de fin quedara caducado.
                </p>
            </section>

            <div class="actions">
                <button class="btn btn-primary" type="submit"
                        <?= !$canCreate ? 'disabled' : '' ?>>
                    <?= $isEdit ? 'Guardar cambios' : 'Crear aviso' ?>
                </button>
                <a class="btn btn-ghost" href="<?= e($base) ?>/panel/avisos">Cancelar</a>
            </div>
        </div>

        <aside class="editor-side">
            <section class="card">
                <h2>Antes de publicar</h2>
                <ul class="steps">
                    <li>Comprueba que el mensaje es exacto.</li>
                    <li>No se generan descuentos ni condiciones automaticamente.</li>
                    <li>Las variantes usan los colores accesibles de la tienda.</li>
                    <li>La previsualizacion usa el mismo CSS que el storefront.</li>
                </ul>
            </section>
        </aside>
    </div>
</form>

<section class="card notice-preview-card" data-notice-preview>
    <div class="card-head">
        <div>
            <h2>Previsualizacion</h2>
            <p class="muted small">Mismo partial y CSS que la tienda. Se actualiza sin guardar.</p>
        </div>
        <span class="muted small" data-notice-preview-state>Esperando cambios...</span>
    </div>
    <div class="notice-preview-grid">
        <div class="banner-preview-device">
            <div class="banner-preview-label">Escritorio · 900 px</div>
            <div class="banner-preview-scroll">
                <iframe class="notice-preview-frame notice-preview-frame--desktop"
                        title="Previsualizacion del aviso en escritorio" sandbox=""></iframe>
            </div>
        </div>
        <div class="banner-preview-device">
            <div class="banner-preview-label">Movil · 390 px</div>
            <iframe class="notice-preview-frame notice-preview-frame--mobile"
                    title="Previsualizacion del aviso en movil" sandbox=""></iframe>
        </div>
    </div>
    <noscript><p class="muted">Activa JavaScript para ver la previsualizacion.</p></noscript>
</section>
