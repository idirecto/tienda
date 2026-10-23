<?php
/**
 * Listado de banners de la tienda.
 *
 * @var array<int,array<string,mixed>> $banners
 * @var int $used
 * @var int $max
 * @var bool $canCreate
 * @var string $planName
 * @var string $base
 */
use Tienda\Core\Csrf;
use Tienda\Models\Banner;

$statusClass = static fn (string $status): string => match ($status) {
    'active'    => 'pill-ok',
    'scheduled' => 'pill-info',
    'expired'   => 'pill-warning',
    'draft'     => 'pill-warning',
    default     => 'pill-neutral',
};
$fmtDate = static function (mixed $value): string {
    $value = trim((string) ($value ?? ''));
    if ($value === '') {
        return '';
    }
    $ts = strtotime($value);
    return $ts === false ? $value : date('d/m/Y H:i', $ts);
};
?>
<section class="editor-heading">
    <div>
        <h2>Banners</h2>
        <p class="muted">
            Plan <?= e($planName) ?>: <?= (int) $used ?> de <?= (int) $max ?> banners.
            El limite se calcula siempre desde el plan actual.
        </p>
    </div>
    <?php if ($canCreate): ?>
        <a class="btn btn-primary" href="<?= e($base) ?>/panel/banners/nuevo">Nuevo banner</a>
    <?php else: ?>
        <button class="btn btn-primary" type="button" disabled>Nuevo banner</button>
    <?php endif; ?>
</section>

<?php if ($max <= 0): ?>
    <div class="alert alert-warning">Tu plan actual no permite banners.</div>
<?php elseif (!$canCreate): ?>
    <div class="alert alert-warning">
        Has alcanzado el limite de <?= (int) $max ?> banners de tu plan.
        Puedes editar o eliminar uno existente, pero no crear otro.
    </div>
<?php endif; ?>

<section class="card">
    <?php if ($banners === []): ?>
        <div class="empty-state">
            <h3>Todavia no hay banners</h3>
            <p class="muted">Crea el primero para destacar una promocion o un contenido en la portada.</p>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= e($base) ?>/panel/banners/nuevo">Crear banner</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="content-list">
            <?php foreach ($banners as $banner): ?>
                <?php
                $effective = (string) ($banner['effective_status'] ?? Banner::effectiveStatus($banner));
                $position = (string) ($banner['position'] ?? '');
                $isRendered = Banner::isRenderedPosition($position);
                ?>
                <li class="content-item<?= $isRendered ? '' : ' is-legacy' ?>">
                    <div class="content-thumb">
                        <?php if (!empty($banner['image_url'])): ?>
                            <img src="<?= e((string) $banner['image_url']) ?>"
                                 alt="<?= e((string) ($banner['alt_text'] ?: Banner::displayName($banner))) ?>"
                                 loading="lazy" decoding="async">
                        <?php else: ?>
                            <span class="content-thumb-empty" aria-hidden="true">Sin imagen</span>
                        <?php endif; ?>
                    </div>

                    <div class="content-body">
                        <div class="content-title-row">
                            <strong><?= e(Banner::displayName($banner)) ?></strong>
                            <span class="pill <?= e($statusClass($effective)) ?>"><?= e(Banner::statusLabel($effective)) ?></span>
                            <?php if (!$isRendered): ?>
                                <span class="pill pill-danger">No visible</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($banner['title'])): ?>
                            <p class="content-subtitle"><?= e((string) $banner['title']) ?></p>
                        <?php endif; ?>
                        <p class="content-meta">
                            <?= e(Banner::positionLabel($position)) ?>
                            · Orden <?= (int) $banner['sort'] ?>
                            · Velo <?= Banner::overlayOpacity($banner) ?> %
                        </p>
                        <p class="content-dates">
                            <?php if (!empty($banner['starts_at'])): ?>
                                Desde <?= e($fmtDate($banner['starts_at'])) ?>
                            <?php else: ?>
                                Sin fecha de inicio
                            <?php endif; ?>
                            <?php if (!empty($banner['ends_at'])): ?>
                                · Hasta <?= e($fmtDate($banner['ends_at'])) ?>
                            <?php else: ?>
                                · Sin fecha de fin
                            <?php endif; ?>
                        </p>
                        <?php if (!$isRendered): ?>
                            <p class="content-warning">
                                Esta posicion no se pinta en el storefront. Edita el banner y elige
                                Portada para publicarlo.
                            </p>
                        <?php endif; ?>
                        <?php if (empty($banner['media_id']) && !empty($banner['image_url'])): ?>
                            <p class="content-warning">Imagen heredada sin media asociado; se conserva sin cambios.</p>
                        <?php endif; ?>
                    </div>

                    <div class="content-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/banners/<?= (int) $banner['id'] ?>/editar">
                            Editar
                        </a>
                        <form method="post"
                              action="<?= e($base) ?>/panel/banners/<?= (int) $banner['id'] ?>/borrar"
                              onsubmit="return confirm('¿Eliminar este banner? La imagen se conservara en la biblioteca de medios.')">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
