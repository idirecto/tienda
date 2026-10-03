<?php
/**
 * Arbol de menu editable (lo comparten el panel de tienda y el de plataforma).
 *
 * Pinta los tres niveles con sus datos y deja un formulario oculto que rellena
 * el JS: el mismo formulario sirve para crear y para editar (si trae `id`, se
 * envia a la ruta de edicion).
 *
 * @var array $tree        categorias con 'groups' y estos con 'links'
 * @var string $scope      'store' | 'platform'
 * @var string $actionBase base de las rutas del editor
 * @var array $destinos    categorias, subcategorias y etiquetas para el selector
 * @var array $icons       iconos disponibles
 * @var array $badges      atajos de badge
 * @var array $banners     banners de la tienda (solo ambito tienda)
 * @var array $niveles     niveles de cliente
 * @var array $visibility  modos de visibilidad (solo plataforma)
 * @var array $stores      tiendas (solo plataforma)
 * @var string $base
 */

use Tienda\Core\Csrf;

$csrf = Csrf::field();
$isPlatform = $scope === 'platform';

/** Botones de accion de un nodo. */
$nodeActions = static function (array $row, int $level) use ($actionBase, $csrf, $isPlatform): string {
    $id = (int) $row['id'];
    $own = $isPlatform || (int) ($row['store_id'] ?? 0) > 0;
    if (!$own) {
        // Nodo compartido de la plataforma: la tienda lo ve pero no lo toca.
        // Para eso esta la tarjeta «Que categorias se ven» (mostrar/ocultar y
        // renombrar solo para su web) y el panel de plataforma.
        return '<span class="pill">Compartida: la gestiona la plataforma</span>';
    }

    $html = '<form method="post" action="' . e($actionBase . '/nodo/' . $id . '/activar') . '" class="inline">' . $csrf
        . '<button class="btn btn-ghost btn-sm" type="submit" title="' . ((int) $row['active'] === 1 ? 'Desactivar' : 'Activar') . '">'
        . ((int) $row['active'] === 1 ? 'Activo' : 'Inactivo') . '</button></form>';
    $html .= '<button type="button" class="btn btn-ghost btn-sm" data-menu-edit="' . $id . '">Editar</button>';
    // La tienda puede colgar sus nodos dentro de un nodo compartido, pero no
    // borrar el compartido. De primer nivel solo borra sus categorias PROPIAS
    // (enlace, productos propios o pagina): las del catalogo se ocultan.
    $custom = in_array((string) $row['target_type'], ['url', 'propios', 'pagina'], true);
    if ($level !== 1 || $isPlatform || $custom) {
        $html .= '<form method="post" action="' . e($actionBase . '/nodo/' . $id . '/borrar') . '" class="inline"'
            . ' onsubmit="return confirm(\'Se borrara este nodo y todo lo que tenga dentro. ¿Seguir?\')">' . $csrf
            . '<button class="btn btn-danger btn-sm" type="submit">Borrar</button></form>';
    }

    return $html;
};

/** Atributos del nodo para que el JS pueda rellenar el formulario al editar. */
$nodeData = static function (array $row) use ($actionBase): string {
    $attrs = [
        'data-label'       => (string) $row['label'],
        'data-target-type' => (string) $row['target_type'],
        'data-target-id'   => (string) (int) ($row['target_id'] ?? 0),
        'data-target-extra' => (string) (int) ($row['target_extra'] ?? 0),
        'data-target-key'  => (string) ($row['target_key'] ?? ''),
        'data-url'         => (string) ($row['url'] ?? ''),
        'data-icon'        => (string) ($row['icon'] ?? ''),
        'data-badge'       => (string) ($row['badge'] ?? ''),
        'data-badge-color' => (string) ($row['badge_color'] ?? ''),
        'data-banner-id'   => (string) (int) ($row['banner_id'] ?? 0),
        'data-banner-url'  => (string) ($row['banner_url'] ?? ''),
        'data-min-level'   => (string) (int) ($row['min_customer_level'] ?? 0),
        'data-hide-empty'  => (int) ($row['hide_empty'] ?? 0) === 1 ? '1' : '0',
        'data-active'      => (int) $row['active'] === 1 ? '1' : '0',
    ];
    $out = '';
    foreach ($attrs as $key => $value) {
        $out .= ' ' . $key . '="' . e($value) . '"';
    }

    return $out;
};

/** Chip de badge. */
$badgeChip = static function (?string $text, ?string $color): string {
    if ($text === null || $text === '') {
        return '';
    }
    $style = '';
    if ($color !== null && $color !== '') {
        $style = preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1
            ? ' style="background:' . e($color) . ';border-color:' . e($color) . ';color:#fff"'
            : ' style="background:var(' . e($color) . ');border-color:var(' . e($color) . ');color:#fff"';
    }

    return '<span class="mn-badge"' . $style . '>' . e($text) . '</span>';
};
?>
<div class="card menu-editor" data-menu-editor data-menu-base="<?= e($actionBase) ?>">
    <div class="card-head">
        <h2>Arbol de categorias</h2>
        <span class="badge"><?= count($tree) ?> categorias</span>
    </div>
    <p class="muted">
        Arrastra para ordenar o para cambiar de nivel (suelta sobre otra categoria).
        Cada cambio se guarda al soltar; la tienda no lo ve hasta publicar.
    </p>

    <?php if ($tree === []): ?>
        <div class="alert alert-warning">
            Todavia no hay menu. Copia el de referencia (abajo) o crea la primera categoria.
        </div>
    <?php endif; ?>

    <ul class="mn-tree" data-menu-level="1">
        <?php foreach ($tree as $cat): ?>
            <li class="mn-node" data-id="<?= (int) $cat['id'] ?>" draggable="true"<?= $nodeData($cat) ?>>
                <div class="mn-node-head">
                    <span class="mn-drag" aria-hidden="true">⠿</span>
                    <span class="mn-node-label">
                        <?php if (!empty($cat['icon'])): ?><?= icon_svg((string) $cat['icon'], 'mn-node-icon') ?><?php endif; ?>
                        <strong><?= e((string) $cat['label']) ?></strong>
                        <?php if ((int) $cat['active'] !== 1): ?><em class="muted">(inactiva)</em><?php endif; ?>
                        <?= $badgeChip($cat['badge'] !== null ? (string) $cat['badge'] : null, $cat['badge_color'] !== null ? (string) $cat['badge_color'] : null) ?>
                        <?php if ($isPlatform): ?>
                            <span class="pill <?= $cat['store_id'] === null ? 'pill-info' : '' ?>">
                                <?= $cat['store_id'] === null ? 'Compartida' : 'Solo tienda #' . (int) $cat['store_id'] ?>
                            </span>
                            <?php if ((string) $cat['visibility'] !== 'todas'): ?>
                                <span class="pill pill-warning">Visibilidad: <?= e((string) $cat['visibility']) ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ((int) ($cat['min_customer_level'] ?? 0) > 0): ?>
                            <span class="pill pill-warning">Nivel <?= (int) $cat['min_customer_level'] ?>+</span>
                        <?php endif; ?>
                    </span>
                    <span class="mn-node-actions">
                        <?= $nodeActions($cat, 1) ?>
                        <button type="button" class="btn btn-ghost btn-sm" data-menu-new="1"
                                data-menu-parent="<?= (int) $cat['id'] ?>">+ Grupo</button>
                    </span>
                </div>

                <ul class="mn-tree" data-menu-level="2">
                    <?php foreach ($cat['groups'] as $group): ?>
                        <li class="mn-node" data-id="<?= (int) $group['id'] ?>" draggable="true"<?= $nodeData($group) ?>>
                            <div class="mn-node-head">
                                <span class="mn-drag" aria-hidden="true">⠿</span>
                                <span class="mn-node-label">
                                    <?php if (!empty($group['icon'])): ?><?= icon_svg((string) $group['icon'], 'mn-node-icon') ?><?php endif; ?>
                                    <strong><?= e((string) $group['label']) ?></strong>
                                    <?php if ((int) $group['active'] !== 1): ?><em class="muted">(inactivo)</em><?php endif; ?>
                                    <?= $badgeChip($group['badge'] !== null ? (string) $group['badge'] : null, $group['badge_color'] !== null ? (string) $group['badge_color'] : null) ?>
                                </span>
                                <span class="mn-node-actions">
                                    <?= $nodeActions($group, 2) ?>
                                    <button type="button" class="btn btn-ghost btn-sm" data-menu-new="1"
                                            data-menu-parent="<?= (int) $group['id'] ?>">+ Enlace</button>
                                </span>
                            </div>

                            <ul class="mn-tree" data-menu-level="3">
                                <?php foreach ($group['links'] as $link): ?>
                                    <li class="mn-node" data-id="<?= (int) $link['id'] ?>" draggable="true"<?= $nodeData($link) ?>>
                                        <div class="mn-node-head">
                                            <span class="mn-drag" aria-hidden="true">⠿</span>
                                            <span class="mn-node-label">
                                                <?php if (!empty($link['icon'])): ?><?= icon_svg((string) $link['icon'], 'mn-node-icon') ?><?php endif; ?>
                                                <span><?= e((string) $link['label']) ?></span>
                                                <?php if ((int) $link['active'] !== 1): ?><em class="muted">(pendiente)</em><?php endif; ?>
                                                <?= $badgeChip($link['badge'] !== null ? (string) $link['badge'] : null, $link['badge_color'] !== null ? (string) $link['badge_color'] : null) ?>
                                                <small class="muted"><?= e(\Tienda\Models\MenuAdmin::targetLabel($link)) ?></small>
                                            </span>
                                            <span class="mn-node-actions"><?= $nodeActions($link, 3) ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="actions">
        <button type="button" class="btn btn-primary" data-menu-new="1">Anadir categoria</button>
        <button type="button" class="btn btn-ghost" data-menu-new="1" data-menu-preset="propios"
                data-menu-label="Productos propios">+ Productos propios</button>
        <button type="button" class="btn btn-ghost" data-menu-new="1" data-menu-preset="pagina"
                data-menu-label="Servicio">+ Pagina de la tienda</button>
    </div>

    <pre class="mn-order-status muted" data-menu-status aria-live="polite"></pre>
</div>

<!-- Formulario del nodo: lo rellena y lo envia el JS -->
<dialog class="mn-dialog" data-menu-dialog>
    <form method="post" action="<?= e($actionBase . '/nodo') ?>" class="stack menu-form" data-menu-form>
        <?= $csrf ?>
        <input type="hidden" name="id" value="">
        <input type="hidden" name="parent_id" value="">
        <?php if ($isPlatform): ?>
            <input type="hidden" name="store" value="<?= (int) ($storeId ?? 0) ?>">
        <?php endif; ?>

        <div class="mn-dialog-head">
            <h3 data-menu-dialog-title>Nuevo nodo</h3>
            <button type="button" class="btn btn-ghost btn-sm" data-menu-close aria-label="Cerrar">Cerrar</button>
        </div>

        <div class="mn-form-grid">
            <label>Nombre
                <input type="text" name="label" maxlength="120" required data-menu-field="label">
            </label>
            <label>Slug (URL)
                <input type="text" name="slug" maxlength="160" placeholder="se genera del nombre" data-menu-field="slug">
            </label>

            <label>Tipo de destino
                <select name="target_type" data-menu-field="target_type">
                    <option value="categoria">Categoria del catalogo</option>
                    <option value="subcategoria">Subcategoria del catalogo</option>
                    <option value="marca">Marca</option>
                    <option value="etiqueta">Etiqueta comercial</option>
                    <option value="filtro">Filtro del catalogo</option>
                    <option value="propios">Productos propios de la tienda</option>
                    <option value="pagina">Pagina de la tienda</option>
                    <option value="url">Enlace libre</option>
                </select>
            </label>
            <label>Destino
                <select name="target_id" data-menu-field="target_id">
                    <option value="0">— Elige —</option>
                    <optgroup label="Categorias">
                        <?php foreach ($destinos['categories'] as $cat): ?>
                            <option value="<?= (int) $cat['id'] ?>" data-kind="categoria"><?= e((string) $cat['label']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php
                    $byCategory = [];
                    foreach ($destinos['subcategories'] as $sub) {
                        $byCategory[(string) $sub['category']][] = $sub;
                    }
                    foreach ($byCategory as $categoryName => $subs): ?>
                        <optgroup label="<?= e($categoryName) ?>">
                            <?php foreach ($subs as $sub): ?>
                                <option value="<?= (int) $sub['id'] ?>" data-kind="subcategoria"><?= e((string) $sub['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>Etiqueta o pagina de la tienda
                <select name="target_key" data-menu-field="target_key">
                    <option value="">— Elige —</option>
                    <optgroup label="Etiquetas comerciales">
                        <?php foreach ($destinos['tags'] as $tag): ?>
                            <option value="<?= e((string) $tag['key']) ?>"><?= e((string) $tag['label']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php if (!empty($destinos['pages'])): ?>
                        <optgroup label="Paginas de tu tienda">
                            <?php foreach ($destinos['pages'] as $page): ?>
                                <option value="<?= e((string) $page['slug']) ?>"><?= e((string) $page['label']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </label>
            <label>Enlace libre
                <input type="text" name="url" maxlength="500" placeholder="https://... o /ruta"
                       data-menu-field="url">
            </label>

            <?php /* Solo para el tipo "Filtro del catalogo": dos ids numericos
                     (filtro y subfiltro del mayorista). */ ?>
            <label>Filtro (id)
                <input type="number" name="filter_id" min="0" step="1" placeholder="164"
                       list="mn-filter-presets" data-menu-field="filter_id">
            </label>
            <label>Subfiltro (id)
                <input type="number" name="filter_extra" min="0" step="1" placeholder="1282"
                       data-menu-field="filter_extra">
            </label>
            <?php if (!empty($destinos['filters'])): ?>
                <datalist id="mn-filter-presets">
                    <?php foreach ($destinos['filters'] as $fil): ?>
                        <option value="<?= (int) $fil['id'] ?>" label="<?= e((string) $fil['label']) ?> (subfiltro <?= (int) $fil['extra'] ?>)"></option>
                    <?php endforeach; ?>
                </datalist>
            <?php endif; ?>

            <label>Icono
                <select name="icon" data-menu-field="icon">
                    <?php foreach ($icons as $key => $label): ?>
                        <option value="<?= e((string) $key) ?>"><?= e((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Texto del badge
                <input type="text" name="badge" maxlength="40" list="mn-badge-presets"
                       placeholder="NUEVO, OFERTA, TOP..." data-menu-field="badge">
                <datalist id="mn-badge-presets">
                    <?php foreach ($badges as $preset): ?>
                        <option value="<?= e((string) $preset) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </label>

            <label>Color del badge
                <input type="text" name="badge_color" maxlength="9" placeholder="#e30613 o --c-primary"
                       data-menu-field="badge_color">
            </label>

            <?php if (!$isPlatform): ?>
                <label>Banner de la tienda
                    <select name="banner_id" data-menu-field="banner_id">
                        <option value="0">— Sin banner —</option>
                        <?php foreach ($banners as $banner): ?>
                            <option value="<?= (int) $banner['id'] ?>">
                                <?= e((string) ($banner['title'] !== null && $banner['title'] !== '' ? $banner['title'] : 'Banner #' . (int) $banner['id'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label>Enlace del banner
                <input type="text" name="banner_url" maxlength="500" placeholder="/ofertas"
                       data-menu-field="banner_url">
            </label>

            <label>Nivel minimo de cliente
                <select name="min_customer_level" data-menu-field="min_customer_level">
                    <option value="">Para todos los niveles</option>
                    <?php foreach ($niveles as $nivel): ?>
                        <option value="<?= (int) $nivel['id'] ?>">
                            Desde <?= e((string) $nivel['label']) ?> (nivel <?= (int) $nivel['order'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <?php if ($isPlatform): ?>
                <label>Propiedad del nodo
                    <select name="owner" data-menu-field="owner">
                        <option value="0">Compartida (todas las tiendas)</option>
                        <?php foreach ((array) ($stores ?? []) as $store): ?>
                            <option value="<?= (int) $store['id'] ?>">Solo para <?= e((string) $store['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Visibilidad por tienda
                    <select name="visibility" data-menu-field="visibility">
                        <?php foreach ((array) ($visibility ?? []) as $key => $label): ?>
                            <option value="<?= e((string) $key) ?>"><?= e((string) $label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>

        <?php if ($isPlatform): ?>
            <fieldset class="mn-stores">
                <legend>Tiendas afectadas por «solo las marcadas / todas menos las marcadas»</legend>
                <?php if ((array) ($stores ?? []) === []): ?>
                    <p class="muted">Todavia no hay tiendas registradas.</p>
                <?php else: ?>
                    <?php foreach ((array) $stores as $store): ?>
                        <label class="check">
                            <input type="checkbox" name="visibility_stores[]" value="<?= (int) $store['id'] ?>"
                                   data-menu-field="visibility_stores" data-store-id="<?= (int) $store['id'] ?>">
                            <?= e((string) $store['label']) ?>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
            </fieldset>
        <?php endif; ?>

        <label class="check"><input type="checkbox" name="hide_empty" value="1" data-menu-field="hide_empty">
            Ocultarla si no tiene productos</label>
        <label class="check"><input type="checkbox" name="active" value="1" checked data-menu-field="active">
            Activa (visible en la tienda al publicar)</label>

        <div class="actions">
            <button class="btn btn-primary" type="submit">Guardar</button>
            <button class="btn btn-ghost" type="button" data-menu-close>Cancelar</button>
        </div>
    </form>
</dialog>
