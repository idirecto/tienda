<?php
/**
 * Directorio de marcas con stock (/marcas).
 *
 * Cada marca enlaza a su listado propio (`/marca/{id}`); las marcas se ordenan
 * por numero de productos con stock y la lista esta cacheada una hora.
 *
 * @var array<int, array{id:int,label:string,total:int,path:string}> $brands
 * @var \Tienda\Core\Tenant $tenant
 * @var string $base
 */
?>
<div class="container section">
    <nav class="breadcrumbs" aria-label="Migas de pan">
        <a href="<?= e($base) ?>/">Inicio</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Marcas</span>
    </nav>

    <header class="page-head">
        <h1>Marcas</h1>
        <p class="muted">
            <?= count($brands) ?> marcas con stock disponible.
            Elige una para ver todos sus productos.
        </p>
    </header>

    <?php if ($brands === []): ?>
        <p class="empty">Todavia no hay marcas disponibles.</p>
    <?php else: ?>
        <ul class="brand-grid">
            <?php foreach ($brands as $brand): ?>
                <li>
                    <a class="brand-card" href="<?= e($base . $brand['path']) ?>">
                        <span class="brand-card-name"><?= e($brand['label']) ?></span>
                        <span class="brand-card-count"><?= (int) $brand['total'] ?> productos</span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
