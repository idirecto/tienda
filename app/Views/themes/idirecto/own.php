<?php
/**
 * Productos propios de la tienda (/propios).
 *
 * Es el destino de un nodo de menu de tipo `propios`: la tienda puede crear su
 * propia categoria «Productos propios» y solo se ve en su web. Reutiliza la
 * misma tarjeta que la portada y el catalogo, para no duplicar el diseno.
 *
 * @var array<int,array> $ownProducts  pagina actual de mt_own_products
 * @var int $total @var int $page @var int $pages
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */
$ownCardFile = TIENDA_BASE . '/app/Views/themes/idirecto/_card_own.php';
?>


<div class="container section">
    <nav class="breadcrumbs" aria-label="Migas de pan">
        <a href="<?= e($base) ?>/">Inicio</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Productos propios</span>
    </nav>

    <header class="page-head">
        <h1>Productos propios</h1>
        <p class="muted">
            <?= (int) $total ?> producto<?= (int) $total === 1 ? '' : 's' ?> exclusivo<?= (int) $total === 1 ? '' : 's' ?>
            de <?= e($tenant->name()) ?>, ademas del catalogo general.
        </p>
    </header>

    <?php if ($ownProducts === []): ?>
        <div class="card-empty">
            <span class="empty-icon" aria-hidden="true"><?= icon_svg('package') ?></span>
            <h2>Todavia no hay productos propios</h2>
            <p>Cuando la tienda publique sus productos, apareceran aqui.</p>
            <a class="btn btn-primary" href="<?= e($base) ?>/catalogo">Ver el catalogo</a>
        </div>
    <?php else: ?>
        <div class="grid grid-products">
            <?php foreach ($ownProducts as $i => $p): ?>
                <?php
                $p['_eager'] = $i < 4;
                $p['_lcp'] = $i === 0;
                include $ownCardFile;
                ?>
            <?php endforeach; ?>
        </div>

        <?php
        // Misma paginacion que el catalogo (enlaces reales, Anterior/Siguiente,
        // ventana de numeros y "Pagina X de Y").
        $pageUrl = static fn (int $n): string => $base . '/propios' . ($n > 1 ? '?page=' . $n : '');
        $ariaLabel = 'Paginacion de productos propios';
        include __DIR__ . '/_pagination.php';
        ?>
    <?php endif; ?>
</div>
