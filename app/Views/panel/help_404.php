<?php
/**
 * Ayuda no encontrada.
 *
 * @var array<string, array<string, mixed>> $categories
 */
use Tienda\Core\Help;

$activeCategory = null;
?>
<div class="help-layout">
    <?php require __DIR__ . '/_help_side.php'; ?>

    <div class="help-main">
        <div class="help-empty help-empty--large">
            <span class="help-empty-icon" aria-hidden="true">?</span>
            <h2>No hemos encontrado esa página de ayuda</h2>
            <p>El enlace puede haber cambiado o el artículo ya no estar disponible. Vuelve al inicio y busca el tema de otra forma.</p>
            <a class="btn btn-primary" href="<?= e(Help::homeUrl()) ?>">Ir al Centro de Ayuda</a>
        </div>
    </div>
</div>
