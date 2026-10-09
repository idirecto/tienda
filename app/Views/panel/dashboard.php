<?php
/**
 * Dashboard del panel.
 * @var array $stats @var array $activity @var bool $catalogReady @var string $base
 * @var \Tienda\Core\Tenant $tenant
 */
$quotaText = $stats['cuota_ilimitada']
    ? $stats['productos_propios'] . ' / ilimitado'
    : $stats['productos_propios'] . ' / ' . $stats['cuota'];
?>
<section class="cards">
    <article class="stat">
        <span class="stat-label">Pedidos activos</span>
        <strong class="stat-value"><?= (int) ($stats['pedidos_activos'] ?? 0) ?></strong>
        <span class="stat-hint"><a href="<?= e($base) ?>/panel/pedidos">Ver pedidos</a></span>
    </article>
    <article class="stat">
        <span class="stat-label">Productos propios</span>
        <strong class="stat-value"><?= e($quotaText) ?></strong>
        <span class="stat-hint"><?= $stats['cuota_ilimitada'] ? 'Plan premium' : 'Cuota de tu plan' ?></span>
    </article>
    <article class="stat">
        <span class="stat-label">Banners</span>
        <strong class="stat-value"><?= (int) $stats['banners'] ?> / <?= (int) $stats['max_banners'] ?></strong>
        <span class="stat-hint">Imagenes de portada</span>
    </article>
    <article class="stat">
        <span class="stat-label">Avisos</span>
        <strong class="stat-value"><?= (int) $stats['avisos'] ?> / <?= (int) $stats['max_avisos'] ?></strong>
        <span class="stat-hint">Anuncios activos</span>
    </article>
    <article class="stat">
        <span class="stat-label">Dominios</span>
        <strong class="stat-value"><?= (int) $stats['dominios'] ?></strong>
        <span class="stat-hint">Direcciones propias</span>
    </article>
    <?php $incidencias = (int) ($stats['incidencias']['total'] ?? 0); ?>
    <article class="stat">
        <span class="stat-label">Incidencias hoy</span>
        <strong class="stat-value"><?= $incidencias ?></strong>
        <span class="stat-hint">
            <?php if ($incidencias > 0): ?>
                <a href="<?= e($base) ?>/panel/logs?nivel=warning">Revisar en Logs</a>
            <?php else: ?>
                <a href="<?= e($base) ?>/panel/logs">Sin avisos ni errores</a>
            <?php endif; ?>
        </span>
    </article>
</section>

<section class="two-col">
    <div class="card">
        <h2>Primeros pasos</h2>
        <ol class="steps">
            <li><a href="<?= e($base) ?>/panel/diseno">Elige tu plantilla y colores</a></li>
            <li><a href="<?= e($base) ?>/panel/banners">Sube un banner de portada</a></li>
            <li><a href="<?= e($base) ?>/panel/productos">Anade tus productos propios</a></li>
            <li><a href="<?= e($base) ?>/panel/dominios">Conecta tu dominio</a></li>
        </ol>
        <?php if ($catalogReady): ?>
            <p class="muted">Tu tienda ya muestra el catalogo del mayorista en tiempo real.</p>
        <?php else: ?>
            <p class="muted">El catalogo central no esta disponible: puedes trabajar con tus productos propios.</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Actividad reciente</h2>
        <?php if (empty($activity)): ?>
            <p class="muted">Todavia no hay productos propios. <a href="<?= e($base) ?>/panel/productos/nuevo">Crear el primero</a>.</p>
        <?php else: ?>
            <ul class="activity">
                <?php foreach ($activity as $p): ?>
                    <li>
                        <div>
                            <strong><?= e($p['name']) ?></strong>
                            <small><?= e($p['created_at']) ?> · <?= e(euros($p['price'])) ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>
