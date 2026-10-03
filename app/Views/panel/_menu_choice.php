<?php
/**
 * «Que categorias se ven»: el modo del menu y el interruptor de cada categoria.
 *
 * La tienda decide con dos modos:
 *   completo -> se ve todo el menu del catalogo menos lo que oculte
 *   elegido  -> se ve SOLO lo que marque como visible (mas su rama)
 *
 * Cada decision se guarda en `mt_menu_item_overrides` (por tienda y nodo), asi
 * que no toca el nodo del arbol y no afecta a ninguna otra tienda. Las
 * categorias y subcategorias son las del catalogo compartido (solo lectura).
 *
 * @var array<int,array> $choice    categorias con sus grupos (Menu::choiceList)
 * @var array<string,string> $scopes
 * @var string $menuScope
 * @var string $base
 */

use Tienda\Core\Csrf;

$total = 0;
$marked = 0;
foreach ($choice as $cat) {
    $total++;
    if ($cat['shown']) {
        $marked++;
    }
}
?>

<section class="card menu-choice-card">
    <div class="card-head">
        <h2>Que categorias se ven</h2>
        <span class="badge"><?= $marked ?>/<?= $total ?> visibles</span>
    </div>
    <p class="muted">
        Las categorias y subcategorias son las del <strong>catalogo compartido</strong>
        (las mismas que usan idirecto y PuntoByZE): no se modifican, solo se elige
        cuales se ven en tu web. Ocultar o renombrar aqui <strong>no afecta</strong>
        a las demas tiendas.
    </p>

    <form method="post" action="<?= e($base) ?>/panel/menu/alcance" class="stack">
        <?= Csrf::field() ?>
        <div class="menu-scope">
            <?php foreach ($scopes as $key => $label): ?>
                <label class="choice">
                    <input type="radio" name="menu_scope" value="<?= e($key) ?>"
                           <?= $menuScope === $key ? 'checked' : '' ?>>
                    <span class="choice-body">
                        <strong><?= e($label) ?></strong>
                        <small>
                            <?php if ($key === 'elegido'): ?>
                                Se ocultan todas menos las que marques abajo como «Mostrar».
                            <?php else: ?>
                                Se ve todo el menu del catalogo; solo cambia lo que ocultes.
                            <?php endif; ?>
                        </small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="actions">
            <button class="btn btn-primary" type="submit">Guardar modo</button>
            <a class="btn btn-ghost" href="<?= e($base) ?>/panel/menu/previa" target="_blank" rel="noopener">
                Vista previa
            </a>
        </div>
    </form>

    <?php if ($choice === []): ?>
        <p class="empty">Todavia no hay categorias. Copia el menu de referencia (abajo) o crea una.</p>
    <?php else: ?>
        <ul class="menu-choice-list">
            <?php foreach ($choice as $cat): ?>
                <li class="menu-choice<?= $cat['shown'] ? ' is-shown' : ' is-hidden' ?>">
                    <div class="menu-choice-head">
                        <span class="menu-choice-label">
                            <strong><?= e($cat['label']) ?></strong>
                            <?php if ($cat['label'] !== $cat['original']): ?>
                                <em class="muted">(en el arbol: <?= e($cat['original']) ?>)</em>
                            <?php endif; ?>
                            <?php if ($cat['own']): ?>
                                <span class="pill pill-info">De tu tienda</span>
                            <?php endif; ?>
                            <?php if (!$cat['active']): ?>
                                <span class="pill pill-warning">Inactiva en el arbol</span>
                            <?php endif; ?>
                        </span>
                        <span class="menu-choice-state"><?= $cat['shown'] ? 'Visible' : 'Oculta' ?></span>
                    </div>

                    <form method="post" action="<?= e($base) ?>/panel/menu/nodo/<?= (int) $cat['id'] ?>/anular"
                          class="menu-choice-form">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="state" value="<?= $cat['shown'] ? 'visible' : 'oculto' ?>">
                        <input type="text" id="menu-name-<?= (int) $cat['id'] ?>" name="label"
                               maxlength="120" aria-label="Nombre en tu web"
                               value="<?= $cat['label'] !== $cat['original'] ? e($cat['label']) : '' ?>"
                               placeholder="<?= e($cat['original']) ?>">
                        <?php if ($cat['shown']): ?>
                            <button class="btn btn-ghost btn-sm" type="submit" name="state" value="oculto">Ocultar</button>
                        <?php else: ?>
                            <button class="btn btn-primary btn-sm" type="submit" name="state" value="visible">Mostrar</button>
                        <?php endif; ?>
                        <button class="btn btn-ghost btn-sm" type="submit">Guardar nombre</button>
                    </form>
                    <?php if ($cat['state'] !== ''): ?>
                        <form method="post" action="<?= e($base) ?>/panel/menu/nodo/<?= (int) $cat['id'] ?>/anular/quitar"
                              class="inline">
                            <?= Csrf::field() ?>
                            <button class="btn btn-ghost btn-sm" type="submit">Volver al arbol</button>
                        </form>
                    <?php endif; ?>

                    <?php if ($cat['children'] !== []): ?>
                        <ul class="menu-choice-children">
                            <?php foreach ($cat['children'] as $child): ?>
                                <li class="menu-choice<?= $child['shown'] ? ' is-shown' : ' is-hidden' ?>">
                                    <div class="menu-choice-head">
                                        <span class="menu-choice-label">
                                            <span><?= e($child['label']) ?></span>
                                            <?php if ($child['label'] !== $child['original']): ?>
                                                <em class="muted">(<?= e($child['original']) ?>)</em>
                                            <?php endif; ?>
                                            <?php if ($child['own']): ?>
                                                <span class="pill pill-info">De tu tienda</span>
                                            <?php endif; ?>
                                            <?php if (!$child['active']): ?>
                                                <span class="pill pill-warning">Inactiva</span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="menu-choice-state"><?= $child['shown'] ? 'Visible' : 'Oculta' ?></span>
                                    </div>
                                    <form method="post"
                                          action="<?= e($base) ?>/panel/menu/nodo/<?= (int) $child['id'] ?>/anular"
                                          class="menu-choice-form">
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="state" value="<?= $child['shown'] ? 'visible' : 'oculto' ?>">
                                        <input type="text" id="menu-name-<?= (int) $child['id'] ?>" name="label"
                                               maxlength="120" aria-label="Nombre en tu web"
                                               value="<?= $child['label'] !== $child['original'] ? e($child['label']) : '' ?>"
                                               placeholder="<?= e($child['original']) ?>">
                                        <?php if ($child['shown']): ?>
                                            <button class="btn btn-ghost btn-sm" type="submit" name="state" value="oculto">Ocultar</button>
                                        <?php else: ?>
                                            <button class="btn btn-primary btn-sm" type="submit" name="state" value="visible">Mostrar</button>
                                        <?php endif; ?>
                                        <button class="btn btn-ghost btn-sm" type="submit">Guardar</button>
                                    </form>
                                    <?php if ($child['state'] !== ''): ?>
                                        <form method="post"
                                              action="<?= e($base) ?>/panel/menu/nodo/<?= (int) $child['id'] ?>/anular/quitar"
                                              class="inline">
                                            <?= Csrf::field() ?>
                                            <button class="btn btn-ghost btn-sm" type="submit">Volver al arbol</button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <p class="muted">
            Los cambios se guardan como <strong>borrador</strong>: pulsa «Publicar cambios»
            para que los visitantes los vean.
        </p>
    <?php endif; ?>
</section>
