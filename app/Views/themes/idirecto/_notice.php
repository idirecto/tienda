<?php
/**
 * Aviso publico reutilizado por el layout de la tienda y por la previsualizacion
 * del panel. Todo el contenido viene escapado y las variantes usan tokens del
 * tema, sin colores libres.
 *
 * @var array $n
 */
use Tienda\Models\Notice;

$p = Notice::presentation($n);
$hasImage = $p['image_url'] !== '';
?>
<div class="notice notice-<?= e($p['type']) ?> notice--<?= e($p['variant']) ?> notice--size-<?= e($p['text_size']) ?><?= $hasImage ? ' notice--with-media' : '' ?>"
     role="status">
    <?php if ($hasImage): ?>
        <div class="notice-media">
            <img src="<?= e($p['image_url']) ?>" alt="" loading="lazy" decoding="async">
        </div>
    <?php endif; ?>

    <div class="notice-content">
        <?php if ($p['badge'] !== '' || $p['icon'] !== ''): ?>
            <div class="notice-head">
                <?php if ($p['icon'] !== ''): ?>
                    <span class="notice-icon" aria-hidden="true"><?= icon_svg($p['icon']) ?></span>
                <?php endif; ?>
                <?php if ($p['badge'] !== ''): ?>
                    <span class="notice-badge"><?= e($p['badge']) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <p class="notice-message"><?= nl2br(e($p['message']), false) ?></p>
    </div>
</div>
