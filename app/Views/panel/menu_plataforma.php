<?php
/**
 * Menu de la PLATAFORMA: arbol compartido, visibilidad por tienda y por nivel
 * de cliente, y publicacion (de una tienda o de todas).
 *
 * Solo entra el rol `platform`; el controlador responde 403 al resto.
 *
 * @var array $stores        tiendas activas
 * @var int $storeId         tienda elegida (para la vista previa y el ambito)
 * @var string $storeName
 * @var array $levels        niveles de cliente del mayorista
 * @var array|null $storeLevel
 * @var array|null $storeLevelStatus estado del nivel (motivo cuando no se resuelve)
 * @var string $storeLevelWarning frase accionable cuando no hay nivel
 * @var array $visibility    modos de visibilidad
 * @var array $stats         resumen del arbol global
 * @var array|null $storeStats resumen de la tienda elegida
 * @var array $tree
 * @var bool $draft
 * @var array $warnings
 * @var array $pendientes
 * @var array $destinos @var array $icons @var array $badges @var array $banners
 * @var string $scope @var string $actionBase
 * @var string $base
 */

use Tienda\Core\Csrf;
?>
<section class="two-col">
    <div class="card">
        <div class="card-head">
            <h2>Arbol compartido de la plataforma</h2>
            <span class="badge"><?= (int) $stats['total'] ?> nodos</span>
        </div>
        <p class="muted">
            Un solo arbol de menu para todas las tiendas. Los nodos <strong>compartidos</strong>
            (sin tienda) los ven todas; los <strong>propios</strong> solo su tienda. Cada tienda
            puede ademas ocultar nodos para si misma desde el apartado de visibilidad.
        </p>
        <ul class="list">
            <li class="list-item"><span class="pill"><?= (int) $stats['categories'] ?></span>
                <div class="list-body"><strong>Categorias</strong><small>Activas</small></div></li>
            <li class="list-item"><span class="pill"><?= (int) $stats['groups'] ?></span>
                <div class="list-body"><strong>Grupos</strong><small>Activos</small></div></li>
            <li class="list-item"><span class="pill"><?= (int) $stats['links'] ?></span>
                <div class="list-body"><strong>Destinos</strong><small>Activos</small></div></li>
            <li class="list-item"><span class="pill"><?= (int) $stats['shared'] ?></span>
                <div class="list-body"><strong>Compartidos</strong><small>Sin tienda asignada</small></div></li>
            <li class="list-item"><span class="pill"><?= (int) $stats['own'] ?></span>
                <div class="list-body"><strong>De una tienda</strong><small>Solo las ve esa tienda</small></div></li>
        </ul>
    </div>

    <div class="card">
        <h2>Tienda y publicacion</h2>
        <form method="get" action="<?= e($base) ?>/panel/plataforma/menu" class="stack">
            <label>Tienda
                <select name="store" onchange="this.form.submit()">
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= (int) $store['id'] ?>" <?= (int) $store['id'] === $storeId ? 'selected' : '' ?>>
                            <?= e((string) $store['label']) ?><?= $store['active'] ? '' : ' (inactiva)' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </form>

        <?php if ($storeId > 0 && $storeStats !== null): ?>
            <p class="muted">
                <strong><?= e($storeName) ?></strong>:
                <?= (int) $storeStats['categories'] ?> categorias,
                <?= (int) $storeStats['groups'] ?> grupos,
                <?= (int) $storeStats['links'] ?> destinos.
                Publicada la version <strong><?= (int) $storeStats['version'] ?></strong>.
                <?php if ($storeLevel !== null): ?>
                    Nivel de cliente: <?= e((string) $storeLevel['label']) ?> (<?= (int) $storeLevel['order'] ?>).
                <?php else: ?>
                    <span class="pill pill-warning">Sin nivel de cliente</span>
                    <?= e((string) $storeLevelWarning) ?>
                <?php endif; ?>
            </p>

            <?php if ($draft): ?>
                <div class="alert alert-warning">Esta tienda tiene cambios sin publicar.</div>
            <?php endif; ?>

            <div class="actions">
                <form method="post" action="<?= e($base) ?>/panel/plataforma/menu/<?= $storeId ?>/publicar" class="inline">
                    <?= Csrf::field() ?>
                    <input type="hidden" name="store" value="<?= $storeId ?>">
                    <button class="btn btn-primary" type="submit">Publicar en esta tienda</button>
                </form>
                <a class="btn btn-ghost" target="_blank" rel="noopener"
                   href="<?= e($base) ?>/panel/plataforma/menu/<?= $storeId ?>/previa">Vista previa</a>
            </div>

            <form method="post" action="<?= e($base) ?>/panel/plataforma/menu/<?= $storeId ?>/compartir" class="stack"
                  onsubmit="return confirm('¿Convertir todo el arbol de esta tienda en arbol compartido?')">
                <?= Csrf::field() ?>
                <input type="hidden" name="store" value="<?= $storeId ?>">
                <div class="actions">
                    <button class="btn btn-ghost" type="submit">Compartir el arbol de esta tienda con todas</button>
                </div>
            </form>

            <form method="post" action="<?= e($base) ?>/panel/plataforma/menu/publicar" class="stack"
                  onsubmit="return confirm('¿Publicar el arbol en TODAS las tiendas?')">
                <?= Csrf::field() ?>
                <input type="hidden" name="store" value="<?= $storeId ?>">
                <div class="actions">
                    <button class="btn btn-primary" type="submit">Publicar en todas las tiendas</button>
                </div>
            </form>

            <?php if ($warnings !== []): ?>
                <h3 class="card-subtitle">Avisos de esta tienda</h3>
                <ul class="list">
                    <?php foreach ($warnings as $warning): ?>
                        <li class="list-item"><span class="pill pill-warning">!</span>
                            <div class="list-body"><small><?= e((string) $warning) ?></small></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>

        <h3 class="card-subtitle">Niveles de cliente del mayorista</h3>
        <ul class="list">
            <?php foreach ($levels as $level): ?>
                <li class="list-item">
                    <span class="pill"><?= (int) $level['order'] ?></span>
                    <div class="list-body">
                        <strong><?= e((string) $level['label']) ?></strong>
                        <small>categoria_cliente.id = <?= (int) $level['id'] ?></small>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php require __DIR__ . '/_menu_tree.php'; ?>

<?php if ($pendientes !== []): ?>
    <section class="card">
        <div class="card-head">
            <h2>Destinos pendientes del arbol global</h2>
            <span class="badge"><?= count($pendientes) ?></span>
        </div>
        <p class="muted">Nodos desactivados de la siembra (filtros del catalogo y nombres sin resolver).</p>
        <ul class="list">
            <?php foreach ($pendientes as $item): ?>
                <li class="list-item">
                    <span class="pill pill-warning"><?= e((string) $item['target_type']) ?></span>
                    <div class="list-body">
                        <strong><?= e((string) $item['label']) ?></strong>
                        <small><?= e((string) ($item['note'] ?? '')) ?></small>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" data-menu-edit="<?= (int) $item['id'] ?>">Editar</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
