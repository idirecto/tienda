<?php
/**
 * Paginacion reutilizable de los listados (catalogo, categoria, subcategoria,
 * busqueda, filtros, etiquetas y productos propios).
 *
 * Son enlaces HTML reales (`href`), de modo que funcionan sin JavaScript, cada
 * pagina tiene su URL propia y los buscadores pueden rastrearlas. La URL la
 * construye quien incluye el partial (`$pageUrl`), asi que los filtros, el
 * orden y la busqueda activos se conservan sin duplicar logica aqui.
 *
 * @var int      $page      Pagina actual (1..$pages)
 * @var int      $pages     Numero total de paginas
 * @var int      $total     Numero total de productos (0 = no mostrar el dato)
 * @var callable $pageUrl   fn(int $n): string  -> URL de la pagina $n
 * @var string   $ariaLabel Etiqueta del <nav> (opcional)
 */

$page = max(1, (int) ($page ?? 1));
$pages = max(1, (int) ($pages ?? 1));
$total = max(0, (int) ($total ?? 0));
$pageUrl = $pageUrl ?? static fn (int $n): string => '?page=' . $n;
$ariaLabel = (string) ($ariaLabel ?? 'Paginacion');

// Una sola pagina no es paginacion: no se pinta nada (evita ruido y enlaces
// duplicados en categorias con pocos productos).
if ($pages < 2) {
    return;
}

// Ventana de numeros: siempre la primera y la ultima, y +-2 alrededor de la
// actual. Los huecos se rellenan con "…". Asi se puede saltar de pagina sin
// imprimir 1..N (en el catalogo entero son mas de 1000 paginas).
$window = 2;
$numbers = [];
for ($i = 1; $i <= $pages; $i++) {
    if ($i === 1 || $i === $pages || abs($i - $page) <= $window) {
        $numbers[] = $i;
    }
}
?>
<nav class="pagination" aria-label="<?= e($ariaLabel) ?>">
    <?php if ($page > 1): ?>
        <a class="page-nav" href="<?= e($pageUrl($page - 1)) ?>" rel="prev" aria-label="Pagina anterior">
            <?= icon_svg('chevron-l') ?><span class="page-nav-label">Anterior</span>
        </a>
    <?php else: ?>
        <span class="page-nav is-disabled" aria-disabled="true">
            <?= icon_svg('chevron-l') ?><span class="page-nav-label">Anterior</span>
        </span>
    <?php endif; ?>

    <?php $previous = 0; ?>
    <?php foreach ($numbers as $n): ?>
        <?php if ($previous > 0 && $n - $previous > 1): ?>
            <span class="page-gap" aria-hidden="true">&hellip;</span>
        <?php endif; ?>
        <?php if ($n === $page): ?>
            <span class="page-current active" aria-current="page"
                  aria-label="Pagina <?= $n ?> de <?= $pages ?>"><?= $n ?></span>
        <?php else: ?>
            <a href="<?= e($pageUrl($n)) ?>" aria-label="Pagina <?= $n ?> de <?= $pages ?>"><?= $n ?></a>
        <?php endif; ?>
        <?php $previous = $n; ?>
    <?php endforeach; ?>

    <?php if ($page < $pages): ?>
        <a class="page-nav" href="<?= e($pageUrl($page + 1)) ?>" rel="next" aria-label="Pagina siguiente">
            <span class="page-nav-label">Siguiente</span><?= icon_svg('chevron-r') ?>
        </a>
    <?php else: ?>
        <span class="page-nav is-disabled" aria-disabled="true">
            <span class="page-nav-label">Siguiente</span><?= icon_svg('chevron-r') ?>
        </span>
    <?php endif; ?>
</nav>

<p class="page-info">
    Pagina <?= $page ?> de <?= $pages ?>
    <?php if ($total > 0): ?>
        <span class="page-info-sep" aria-hidden="true">&middot;</span>
        <?= number_format($total, 0, ',', '.') ?> productos
    <?php endif; ?>
</p>
