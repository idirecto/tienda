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
            <?php foreach ($ownProducts as $p): ?>
                <?php include $ownCardFile; ?>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Paginacion">
                <?php if ($page > 1): ?>
                    <a class="page-nav" href="<?= e($base) ?>/propios?page=<?= $page - 1 ?>" rel="prev"
                       aria-label="Pagina anterior"><?= icon_svg('chevron-l') ?></a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>"
                       href="<?= e($base) ?>/propios?page=<?= $i ?>"
                       <?= $i === $page ? 'aria-current="page"' : '' ?>>
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $pages): ?>
                    <a class="page-nav" href="<?= e($base) ?>/propios?page=<?= $page + 1 ?>" rel="next"
                       aria-label="Pagina siguiente"><?= icon_svg('chevron-r') ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
