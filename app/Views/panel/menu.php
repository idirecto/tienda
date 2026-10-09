<?php
/**
 * Menu de la tienda: estilo, editor del arbol, publicacion y vista previa.
 *
 * @var array $styles  los dos estilos (y solo dos)
 * @var string $style  estilo activo
 * @var array $stats
 * @var array $tree
 * @var array $pending
 * @var array $warnings
 * @var bool $draft
 * @var int $version
 * @var string|null $publishedAt
 * @var array $destinos @var array $icons @var array $badges @var array $banners @var array $niveles
 * @var array|null $nivelTienda
 * @var array $nivelEstado estado del nivel (motivo cuando no se resuelve)
 * @var string $nivelAviso frase accionable cuando no hay nivel
 * @var string $scope @var string $actionBase
 * @var string $base
 */

use Tienda\Core\Csrf;
?>
<section class="two-col">
    <div class="card">
        <div class="card-head">
            <h2>Estilo de menu</h2>
            <span class="badge"><?= count($styles) ?> estilos</span>
        </div>
        <p class="muted">
            Los dos estilos muestran <strong>las mismas categorias</strong>, subcategorias y
            destinos. Lo unico que cambia es como se presentan.
        </p>

        <form method="post" action="<?= e($base) ?>/panel/menu" class="stack">
            <?= Csrf::field() ?>
            <?php foreach ($styles as $key => $label): ?>
                <label class="choice">
                    <input type="radio" name="menu_style" value="<?= e($key) ?>" <?= $style === $key ? 'checked' : '' ?>>
                    <span class="choice-body">
                        <strong><?= e($label) ?></strong>
                        <small>
                            <?php if ($key === 'compacto'): ?>
                                Barra horizontal bajo la cabecera con megamenu y «Mas categorias».
                            <?php else: ?>
                                Boton «Todas las categorias» que abre el panel con fondo oscuro.
                            <?php endif; ?>
                        </small>
                    </span>
                </label>
            <?php endforeach; ?>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Guardar estilo</button>
                <a class="btn btn-ghost" href="<?= e($base) ?>/panel/menu/previa" target="_blank" rel="noopener">
                    Vista previa (escritorio y movil)
                </a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Borrador y publicacion</h2>
        <?php if ($draft): ?>
            <div class="alert alert-warning">
                Hay cambios sin publicar. La tienda sigue mostrando la
                <?= $version > 0 ? 'version ' . (int) $version : 'version anterior' ?>.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                Todo publicado<?= $version > 0 ? ' (version ' . (int) $version . ')' : '' ?>.
                <?= $publishedAt !== null ? 'Ultima publicacion: ' . e($publishedAt) . '.' : '' ?>
            </div>
        <?php endif; ?>

        <ul class="list">
            <li class="list-item"><span class="pill"><?= (int) $stats['categories'] ?></span>
                <div class="list-body"><strong>Categorias</strong><small>Primer nivel</small></div></li>
            <li class="list-item"><span class="pill"><?= (int) $stats['groups'] ?></span>
                <div class="list-body"><strong>Grupos</strong><small>Segundo nivel</small></div></li>
            <li class="list-item"><span class="pill"><?= (int) $stats['links'] ?></span>
                <div class="list-body"><strong>Destinos</strong><small>Tercer nivel</small></div></li>
            <li class="list-item"><span class="pill"><?= (int) $stats['shared'] ?></span>
                <div class="list-body"><strong>Compartidos</strong>
                    <small>Los pone la plataforma y no se editan aqui</small></div></li>
            <li class="list-item"><span class="pill <?= (int) $stats['inactive'] > 0 ? 'pill-warning' : '' ?>"><?= (int) $stats['inactive'] ?></span>
                <div class="list-body"><strong>Desactivados</strong><small>No se pintan en la tienda</small></div></li>
        </ul>

        <form method="post" action="<?= e($base) ?>/panel/menu/publicar" class="stack"
              onsubmit="return confirm('¿Publicar el menu? Los visitantes veran estos cambios.')">
            <?= Csrf::field() ?>
            <div class="actions">
                <button class="btn btn-primary" type="submit">Publicar cambios</button>
            </div>
        </form>

        <?php if ($warnings !== []): ?>
            <h3 class="card-subtitle">Antes de publicar, revisa</h3>
            <ul class="list">
                <?php foreach ($warnings as $warning): ?>
                    <li class="list-item"><span class="pill pill-warning">!</span>
                        <div class="list-body"><small><?= e((string) $warning) ?></small></div></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3 class="card-subtitle">Nivel de cliente de la tienda</h3>
        <p class="muted">
            <?php if ($nivelTienda !== null): ?>
                <?= e((string) $nivelTienda['label']) ?> (nivel <?= (int) $nivelTienda['order'] ?>).
                Los nodos con nivel minimo igual o menor se te muestran.
            <?php else: ?>
                <?= e((string) $nivelAviso) ?>
            <?php endif; ?>
        </p>
        <?php /* El nivel se arregla desde aqui solo si falta la cuenta (o su id no
                 existe); la categoria de cliente la asigna idirecto en su ficha. */ ?>
        <?php if ($nivelTienda === null
                  && in_array($nivelEstado['reason'] ?? null, ['sin_cuenta', 'cuenta_inexistente'], true)): ?>
            <div class="actions">
                <a class="btn btn-ghost btn-sm" href="<?= e($base) ?>/panel/ajustes">Ir a Ajustes</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
// Que categorias se ven (modo + interruptor por categoria, guardado por tienda).
require __DIR__ . '/_menu_choice.php';
?>

<?php
$storeId = 0;
$visibility = [];
$stores = [];
require __DIR__ . '/_menu_tree.php';
?>

<section class="card">
    <div class="card-head">
        <h2>Menu de referencia</h2>
        <span class="badge">PuntoByZE</span>
    </div>
    <p class="muted">
        Copia el arbol de navegacion de la web de referencia (nombres y orden). Solo se leen
        las tablas del mayorista: nunca se modifican. El resultado queda como borrador.
    </p>
    <form method="post" action="<?= e($base) ?>/panel/menu/regenerar" class="stack"
          onsubmit="return confirm('¿Copiar otra vez el menu de referencia?')">
        <?= Csrf::field() ?>
        <label class="check">
            <input type="checkbox" name="replace" value="1" <?= (int) $stats['total'] === 0 ? 'checked' : '' ?>>
            Reemplazar el menu actual
        </label>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Copiar el menu de referencia</button>
        </div>
    </form>

    <?php if ($pending !== []): ?>
        <h3 class="card-subtitle">Destinos pendientes</h3>
        <p class="muted">
            No se pudieron reconocer en el catalogo, asi que quedan desactivados y no se pintan.
            Edita el nodo para darle destino y activarlo.
        </p>
        <ul class="list">
            <?php foreach ($pending as $item): ?>
                <li class="list-item">
                    <span class="pill <?= $item['target_type'] === 'filtro' ? 'pill-warning' : 'pill-info' ?>">
                        <?= e((string) $item['target_type']) ?>
                    </span>
                    <div class="list-body">
                        <strong><?= e((string) $item['label']) ?></strong>
                        <small><?= e((string) ($item['note'] ?? '')) ?></small>
                    </div>
                    <button type="button" class="btn btn-ghost btn-sm" data-menu-edit="<?= (int) $item['id'] ?>">Editar</button>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
