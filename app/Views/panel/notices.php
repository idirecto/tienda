<?php
/**
 * Listado de avisos de la tienda.
 *
 * @var array<int,array<string,mixed>> $notices
 * @var int $used
 * @var int $max
 * @var bool $canCreate
 * @var string $planName
 * @var string $base
 */
use Tienda\Core\Csrf;
use Tienda\Models\Notice;

$statusClass = static fn (string $status): string => match ($status) {
    'active'    => 'pill-ok',
    'scheduled' => 'pill-info',
    'expired'   => 'pill-warning',
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
        <h2>Avisos</h2>
        <p class="muted">
            Plan <?= e($planName) ?>: <?= (int) $used ?> de <?= (int) $max ?> avisos.
            El limite se calcula siempre desde el plan actual.
        </p>
    </div>
    <?php if ($canCreate): ?>
        <a class="btn btn-primary" href="<?= e($base) ?>/panel/avisos/nuevo">Nuevo aviso</a>
    <?php else: ?>
        <button class="btn btn-primary" type="button" disabled>Nuevo aviso</button>
    <?php endif; ?>
</section>

<?php if ($max <= 0): ?>
    <div class="alert alert-warning">Tu plan actual no permite avisos.</div>
<?php elseif (!$canCreate): ?>
    <div class="alert alert-warning">
        Has alcanzado el limite de <?= (int) $max ?> avisos de tu plan.
        Puedes editar o eliminar uno existente, pero no crear otro.
    </div>
<?php endif; ?>

<section class="card">
    <?php if ($notices === []): ?>
        <div class="empty-state">
            <h3>Todavia no hay avisos</h3>
            <p class="muted">Publica un mensaje breve para informar a tus clientes.</p>
            <?php if ($canCreate): ?>
                <a class="btn btn-primary" href="<?= e($base) ?>/panel/avisos/nuevo">Crear aviso</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="content-list">
            <?php foreach ($notices as $notice): ?>
                <?php
                $p = Notice::presentation($notice);
                $effective = (string) ($notice['effective_status'] ?? Notice::effectiveStatus($notice));
                $typeLabel = Notice::typeOptions()[$p['type']] ?? 'Informacion';
                ?>
                <li class="content-item">
                    <div class="content-thumb content-thumb--notice">
                        <?php if ($p['image_url'] !== ''): ?>
                            <img src="<?= e($p['image_url']) ?>" alt="" loading="lazy" decoding="async">
                        <?php elseif ($p['icon'] !== ''): ?>
                            <span class="notice-list-icon" aria-hidden="true"><?= icon_svg($p['icon']) ?></span>
                        <?php else: ?>
                            <span class="content-thumb-empty" aria-hidden="true">Sin imagen</span>
                        <?php endif; ?>
                    </div>

                    <div class="content-body">
                        <div class="content-title-row">
                            <strong><?= e($typeLabel) ?></strong>
                            <span class="pill <?= e($statusClass($effective)) ?>"><?= e(Notice::statusLabel($effective)) ?></span>
                            <span class="pill pill-neutral"><?= e(Notice::variantOptions()[$p['variant']] ?? 'Suave') ?></span>
                            <span class="pill pill-neutral"><?= e(Notice::textSizeOptions()[$p['text_size']] ?? 'Normal') ?></span>
                        </div>
                        <?php if ($p['badge'] !== ''): ?>
                            <span class="notice-badge notice-badge--panel"><?= e($p['badge']) ?></span>
                        <?php endif; ?>
                        <p class="content-message"><?= nl2br(e((string) $notice['message']), false) ?></p>
                        <p class="content-dates">
                            <?php if (!empty($notice['starts_at'])): ?>
                                Desde <?= e($fmtDate($notice['starts_at'])) ?>
                            <?php else: ?>
                                Sin fecha de inicio
                            <?php endif; ?>
                            <?php if (!empty($notice['ends_at'])): ?>
                                · Hasta <?= e($fmtDate($notice['ends_at'])) ?>
                            <?php else: ?>
                                · Sin fecha de fin
                            <?php endif; ?>
                        </p>
                        <?php if (empty($notice['media_id']) && $p['image_url'] !== ''): ?>
                            <p class="content-warning">Imagen heredada sin media asociado; se conserva sin cambios.</p>
                        <?php endif; ?>
                    </div>

                    <div class="content-actions">
                        <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/avisos/<?= (int) $notice['id'] ?>/editar">
                            Editar
                        </a>
                        <form method="post"
                              action="<?= e($base) ?>/panel/avisos/<?= (int) $notice['id'] ?>/borrar"
                              onsubmit="return confirm('¿Eliminar este aviso? La imagen se conservara en la biblioteca de medios.')">
                            <?= Csrf::field() ?>
                            <button class="btn btn-danger btn-sm" type="submit">Eliminar</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
