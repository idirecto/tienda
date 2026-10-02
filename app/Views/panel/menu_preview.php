<?php
/**
 * Vista previa del menu con el BORRADOR actual.
 *
 * El ancho del marco decide la presentacion: a 1280 px se ve el menu de
 * escritorio y a 390 px el cajon de movil, porque las media queries de la CSS
 * responden al ancho del iframe. Asi se ve exactamente lo que se va a publicar
 * sin tocar la tienda.
 *
 * @var array $styles
 * @var string $style
 * @var string $frameUrl
 * @var string $backUrl
 * @var string $base
 */
?>
<div class="card">
    <div class="card-head">
        <h2>Vista previa del menu (borrador)</h2>
        <span class="badge">Sin publicar</span>
    </div>
    <p class="muted">
        Se esta viendo el borrador: los visitantes siguen viendo la version publicada
        hasta que pulses «Publicar».
    </p>

    <div class="actions">
        <a class="btn btn-ghost" href="<?= e($backUrl) ?>">Volver al editor</a>
        <button type="button" class="btn btn-primary" data-menu-preview-style="catalogo">Ver Menu Catalogo</button>
        <button type="button" class="btn btn-ghost" data-menu-preview-style="compacto">Ver Menu Compacto</button>
    </div>

    <h3 class="card-subtitle">Escritorio (1280 px)</h3>
    <div class="mn-frame-scroll">
        <iframe class="mn-frame mn-frame--desktop" title="Vista previa en escritorio"
                src="<?= e($frameUrl . '?estilo=' . rawurlencode($style)) ?>" data-menu-frame></iframe>
    </div>

    <h3 class="card-subtitle">Movil (390 px)</h3>
    <iframe class="mn-frame mn-frame--mobile" title="Vista previa en movil"
            src="<?= e($frameUrl . '?estilo=' . rawurlencode($style)) ?>" data-menu-frame></iframe>
</div>
