<?php
/**
 * Buscador del Centro de Ayuda.
 *
 * @var string $query
 */
use Tienda\Core\Help;

$query = $query ?? '';
?>
<form class="help-search" method="get" action="<?= e(Help::homeUrl()) ?>" role="search">
    <label class="help-search-label" for="help-search-q">Buscar en la ayuda</label>
    <div class="help-search-row">
        <span class="help-search-icon" aria-hidden="true"><?= icon_svg('search') ?></span>
        <input id="help-search-q" type="search" name="q" value="<?= e($query) ?>"
               placeholder="Ej. pedido, banner, envío, dominio…"
               autocomplete="off" aria-describedby="help-search-help">
        <button class="btn btn-primary" type="submit">Buscar</button>
    </div>
    <p id="help-search-help" class="help-search-help">
        Busca en títulos, resúmenes y contenido. No importan las mayúsculas ni los acentos.
        <?php if ($query !== ''): ?>
            <a href="<?= e(Help::homeUrl()) ?>">Ver todas las categorías</a>.
        <?php endif; ?>
    </p>
</form>
