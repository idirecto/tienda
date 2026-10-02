<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Database;
use Tienda\Core\Str;

/**
 * Editor del arbol de menu (el borrador).
 *
 * Reglas de aislamiento (duras):
 *   · Un nodo de la plataforma (`store_id` NULL) solo lo edita la plataforma.
 *   · Una tienda solo puede crear, editar, mover y borrar sus PROPIOS nodos
 *     (`store_id` = su id). De los nodos compartidos solo puede leer.
 *   · El `store_id` NUNCA llega por la peticion: lo pone el controlador desde la
 *     sesion. Aqui se comprueba ademas en cada consulta, de modo que manipular
 *     un `id` de otra tienda no sirve de nada.
 *
 * Nada de esto se ve en la tienda hasta publicar (ver `Menu::publish()`).
 */
final class MenuAdmin
{
    /** Tipos de destino admitidos en un nodo. */
    private const TARGET_TYPES = ['categoria', 'subcategoria', 'marca', 'etiqueta', 'url', 'filtro'];

    // =====================================================================
    // CONSULTAS
    // =====================================================================

    /**
     * Arbol completo para el editor: filas planas indexadas por id.
     *
     * @return array<int,array> id => fila
     */
    public static function rows(int $storeId, bool $platform = false): array
    {
        if (!Database::tableExists('mt_menu_items')) {
            return [];
        }

        $where = '1 = 1';
        $params = [];
        if (!$platform) {
            $where = '(store_id IS NULL OR store_id = :store_id)';
            $params['store_id'] = $storeId;
        }

        $out = [];
        foreach (Database::select(
            'SELECT id, store_id, parent_id, level, label, slug, icon, target_type, target_id,
                    target_extra, target_key, url, badge, badge_color, banner_id, banner_url,
                    min_customer_level, hide_empty, visibility, sort, active, note
             FROM mt_menu_items
             WHERE ' . $where . '
             ORDER BY level ASC, sort ASC, id ASC',
            $params
        ) as $row) {
            $row['id'] = (int) $row['id'];
            $row['store_id'] = $row['store_id'] === null ? null : (int) $row['store_id'];
            $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            $row['level'] = (int) $row['level'];
            $row['children'] = [];
            $out[(int) $row['id']] = $row;
        }

        return $out;
    }

    /**
     * Arbol anidado (nivel 1 -> 2 -> 3) listo para pintar en el editor.
     *
     * @return array<int,array> categorias con 'groups' y estos con 'links'
     */
    public static function tree(int $storeId, bool $platform = false): array
    {
        $rows = self::rows($storeId, $platform);

        foreach ($rows as $id => $row) {
            $parent = $row['parent_id'];
            if ($parent !== null && isset($rows[$parent])) {
                $rows[$parent]['children'][] = $id;
            }
        }

        $tree = [];
        foreach ($rows as $id => $row) {
            if ($row['parent_id'] !== null || $row['level'] !== 1) {
                continue;
            }
            $row['groups'] = [];
            foreach ($row['children'] as $gid) {
                $group = $rows[$gid];
                if ($group['level'] !== 2) {
                    continue;
                }
                $group['links'] = [];
                foreach ($group['children'] as $lid) {
                    if ($rows[$lid]['level'] === 3) {
                        $group['links'][] = $rows[$lid];
                    }
                }
                $row['groups'][] = $group;
            }
            $tree[] = $row;
        }

        return $tree;
    }

    /** Un nodo, siempre dentro del alcance permitido. */
    public static function node(int $id, int $storeId, bool $platform = false): ?array
    {
        if ($id <= 0 || !Database::tableExists('mt_menu_items')) {
            return null;
        }

        $where = 'id = :id';
        $params = ['id' => $id];
        if (!$platform) {
            $where .= ' AND (store_id IS NULL OR store_id = :store_id)';
            $params['store_id'] = $storeId;
        }

        return Database::first('SELECT * FROM mt_menu_items WHERE ' . $where . ' LIMIT 1', $params);
    }

    /** ¿Puede este usuario tocar este nodo? */
    public static function canEdit(array $row, int $storeId, bool $platform): bool
    {
        if ($platform) {
            return true;
        }
        $owner = $row['store_id'] === null ? null : (int) $row['store_id'];

        return $owner !== null && $owner === $storeId;
    }

    // =====================================================================
    // VALIDACION
    // =====================================================================

    /**
     * Valida y normaliza los datos del formulario.
     *
     * @param array $input    datos ya recogidos del formulario
     * @param int|null $ignoreId nodo que se esta editando (para el slug unico)
     * @return array{ok:bool,errors:array<int,string>,data:array}
     */
    public static function validate(array $input, ?int $parentId, int $storeId, bool $platform, ?int $ignoreId = null, ?int $position = null): array
    {
        $errors = [];
        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '') {
            $errors[] = 'El nombre es obligatorio.';
        } elseif (mb_strlen($label) > 120) {
            $errors[] = 'El nombre no puede pasar de 120 caracteres.';
        }

        $level = 1;
        if ($parentId !== null) {
            $parent = self::node($parentId, $storeId, $platform);
            if ($parent === null) {
                $errors[] = 'La categoria padre no existe o no es tuya.';
            } else {
                $level = (int) $parent['level'] + 1;
                if ($level > 3) {
                    $errors[] = 'El menu tiene tres niveles: ese padre ya esta en el tercero.';
                }
                if (!$platform && (int) ($parent['store_id'] ?? 0) !== $storeId) {
                    $errors[] = 'No puedes colgar nodos de una categoria de la plataforma.';
                }
            }
        }

        $type = strtolower(trim((string) ($input['target_type'] ?? 'subcategoria')));
        if (!in_array($type, self::TARGET_TYPES, true)) {
            $errors[] = 'Tipo de destino no valido.';
            $type = 'subcategoria';
        }

        $targetId = (int) ($input['target_id'] ?? 0);
        $targetKey = trim((string) ($input['target_key'] ?? ''));
        $url = trim((string) ($input['url'] ?? ''));

        if (in_array($type, ['categoria', 'subcategoria', 'marca'], true) && $targetId <= 0) {
            $errors[] = 'Elige el destino del enlace.';
        }
        if ($type === 'categoria' && !self::categoryExists($targetId)) {
            $errors[] = 'Esa categoria no existe en el catalogo.';
        }
        if ($type === 'subcategoria' && !self::subcategoryExists($targetId)) {
            $errors[] = 'Esa subcategoria no existe en el catalogo.';
        }
        if ($type === 'marca' && !self::brandExists($targetId)) {
            $errors[] = 'Esa marca no existe.';
        }
        if ($type === 'url' && $url === '') {
            $errors[] = 'Escribe la direccion del enlace.';
        }
        if ($type === 'url' && $url !== '' && !preg_match('#^(https?://|/)#i', $url)) {
            $errors[] = 'El enlace debe empezar por http(s):// o por /.';
        }
        if ($type === 'etiqueta') {
            $targetKey = Catalog::tagKey($targetKey);
            if ($targetKey === 'todos') {
                $errors[] = 'Elige una etiqueta (Ofertas, Novedades o Destacados).';
            }
        }
        $targetExtra = (int) ($input['target_extra'] ?? 0);
        if ($type === 'filtro') {
            if ($targetId <= 0) {
                $errors[] = 'Escribe el id del filtro del catalogo.';
            } elseif ($targetExtra <= 0) {
                $errors[] = 'Escribe el id del subfiltro (la ruta es /f/{filtro}-{subfiltro}).';
            } elseif (!self::filterExists($targetId, $targetExtra)) {
                $errors[] = 'Ese filtro no existe en el catalogo.';
            }
        }

        $icon = trim((string) ($input['icon'] ?? ''));
        if ($icon !== '' && !isset(Menu::iconOptions()[$icon])) {
            $icon = '';
        }

        $badge = trim((string) ($input['badge'] ?? ''));
        $badge = mb_substr($badge, 0, 40);

        // El color admite hex (#rgb/#rrggbb) o un token de la identidad visual (--c-*).
        $badgeColor = mb_substr(trim((string) ($input['badge_color'] ?? '')), 0, 32);
        if ($badgeColor !== ''
            && preg_match('/^#[0-9a-fA-F]{3,8}$/', $badgeColor) !== 1
            && preg_match('/^--c-[a-z0-9-]+$/', $badgeColor) !== 1) {
            $errors[] = 'El color del badge debe ser un hex (#rgb o #rrggbb) o un token (--c-...).';
            $badgeColor = '';
        }

        // Nivel minimo de cliente: tiene que ser uno de los niveles del mayorista.
        $minLevel = $input['min_customer_level'] ?? null;
        $minLevel = ($minLevel === null || $minLevel === '' || (int) $minLevel === 0) ? null : (int) $minLevel;
        if ($minLevel !== null && !in_array($minLevel, array_column(Menu::customerLevels(), 'id'), true)) {
            $errors[] = 'Ese nivel de cliente no existe.';
            $minLevel = null;
        }

        // Banner: si es uno de la tienda, tiene que ser SUYO (aislamiento).
        $bannerId = (int) ($input['banner_id'] ?? 0);
        $bannerUrl = trim((string) ($input['banner_url'] ?? ''));
        if ($bannerId > 0) {
            $banner = Database::first(
                'SELECT id, store_id FROM mt_banners WHERE id = :id LIMIT 1',
                ['id' => $bannerId]
            );
            if ($banner === null || (int) $banner['store_id'] !== $storeId) {
                $errors[] = 'Ese banner no es de esta tienda.';
                $bannerId = 0;
            }
        }

        $visibility = strtolower(trim((string) ($input['visibility'] ?? 'todas')));
        if (!isset(Menu::visibilityModes()[$visibility])) {
            $visibility = 'todas';
        }
        if (!$platform) {
            $visibility = 'todas'; // Solo la plataforma reparte el menu entre tiendas.
        }

        $slug = Str::slugify((string) ($input['slug'] ?? '')) ?: Str::slugify($label);
        if ($slug !== '' && self::slugTaken($slug, $parentId, $ignoreId)) {
            $errors[] = 'Ya hay un nodo con ese slug en el mismo nivel.';
        }

        $sort = $position === null ? self::nextSort($parentId) : max(0, $position);

        return [
            'ok'     => $errors === [],
            'errors' => $errors,
            'data'   => [
                'parent_id'          => $parentId,
                'level'              => $level,
                'label'              => $label,
                'slug'               => $slug,
                'icon'               => $icon !== '' ? $icon : null,
                'target_type'        => $type,
                'target_id'          => in_array($type, ['categoria', 'subcategoria', 'marca', 'filtro'], true) ? $targetId : null,
                'target_extra'       => $type === 'filtro' && $targetExtra > 0 ? $targetExtra : null,
                'target_key'         => $type === 'etiqueta' ? $targetKey : null,
                'url'                => $type === 'url' ? $url : null,
                'badge'              => $badge !== '' ? $badge : null,
                'badge_color'        => $badgeColor !== '' ? $badgeColor : null,
                'banner_id'          => $bannerId > 0 ? $bannerId : null,
                'banner_url'         => $bannerUrl !== '' ? mb_substr($bannerUrl, 0, 500) : null,
                'min_customer_level' => $minLevel,
                'hide_empty'         => !empty($input['hide_empty']) ? 1 : 0,
                'visibility'         => $visibility,
                'active'             => array_key_exists('active', $input) ? (!empty($input['active']) ? 1 : 0) : 1,
                'sort'               => $sort,
            ],
        ];
    }

    // =====================================================================
    // ALTA / EDICION / BORRADO
    // =====================================================================

    /**
     * Crea un nodo. La tienda solo puede crear nodos suyos; la plataforma puede
     * crear compartidos (store_id NULL) o de una tienda concreta.
     *
     * @return array{ok:bool,message:string,id:?int,errors:array<int,string>}
     */
    public static function create(int $storeId, bool $platform, array $input): array
    {
        $parentId = ($input['parent_id'] ?? null) === null || (int) $input['parent_id'] === 0
            ? null
            : (int) $input['parent_id'];

        // Propiedad del nodo nuevo: la plataforma decide; la tienda no puede.
        $owner = $platform && array_key_exists('owner', $input)
            ? (int) $input['owner']
            : $storeId;
        if ($owner === 0) {
            $owner = null; // Nodo compartido de la plataforma.
        }
        if ($owner !== null && !self::storeExists($owner)) {
            return ['ok' => false, 'message' => 'Esa tienda no existe.', 'id' => null, 'errors' => []];
        }

        $checked = self::validate($input, $parentId, $storeId, $platform);
        if (!$checked['ok']) {
            return ['ok' => false, 'message' => 'Revisa los datos del nodo.', 'id' => null, 'errors' => $checked['errors']];
        }

        $data = $checked['data'];
        $data['store_id'] = $owner;
        $data['note'] = null;
        $data['created_at'] = date('Y-m-d H:i:s');

        try {
            $id = Database::insert('mt_menu_items', $data);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se ha podido guardar el nodo.', 'id' => null, 'errors' => [self::dbError($e)]];
        }

        self::normalizeSiblings($parentId);
        Menu::invalidate($storeId);
        if ($owner === null) {
            Menu::invalidateAll();
        }

        return ['ok' => true, 'message' => 'Nodo creado.', 'id' => $id, 'errors' => []];
    }

    /** Edita un nodo existente (comprobando propiedad antes). */
    public static function update(int $id, int $storeId, bool $platform, array $input): array
    {
        $row = self::node($id, $storeId, $platform);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Ese nodo no existe.', 'id' => null, 'errors' => []];
        }
        if (!self::canEdit($row, $storeId, $platform)) {
            return ['ok' => false, 'message' => 'Ese nodo no es de tu tienda.', 'id' => null, 'errors' => []];
        }
        if (!$platform) {
            $input['parent_id'] = $row['parent_id']; // La tienda no cambia de padre por aqui.
        }

        $parentId = ($input['parent_id'] ?? null) === null || (int) $input['parent_id'] === 0
            ? null
            : (int) $input['parent_id'];
        $checked = self::validate($input, $parentId, $storeId, $platform, $id, (int) $row['sort']);
        if (!$checked['ok']) {
            return ['ok' => false, 'message' => 'Revisa los datos del nodo.', 'id' => null, 'errors' => $checked['errors']];
        }

        $data = $checked['data'];
        unset($data['sort']);

        // La plataforma puede cambiar el propietario (compartido <-> de una tienda).
        if ($platform && array_key_exists('owner', $input)) {
            $owner = (int) $input['owner'];
            $data['store_id'] = $owner === 0 ? null : $owner;
        }

        if ($parentId !== ($row['parent_id'] === null ? null : (int) $row['parent_id'])) {
            $data['level'] = $checked['data']['level'];
        }
        $data['note'] = null; // Al tocarlo a mano, el aviso de la siembra ya no aplica.

        try {
            Database::update('mt_menu_items', $id, $data);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se ha podido guardar el nodo.', 'id' => null, 'errors' => [self::dbError($e)]];
        }

        if (isset($data['level'])) {
            self::applyLevel($id, (int) $data['level']);
        }
        self::normalizeSiblings($parentId);
        Menu::invalidate($storeId);
        if ($platform) {
            Menu::invalidateAll();
        }

        return ['ok' => true, 'message' => 'Nodo guardado.', 'id' => $id, 'errors' => []];
    }

    /** Borra un nodo y toda su rama (la clave foranea ya es ON DELETE CASCADE). */
    public static function delete(int $id, int $storeId, bool $platform): array
    {
        $row = self::node($id, $storeId, $platform);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Ese nodo no existe.'];
        }
        if (!self::canEdit($row, $storeId, $platform)) {
            return ['ok' => false, 'message' => 'Ese nodo no es de tu tienda.'];
        }

        $parentId = $row['parent_id'] === null ? null : (int) $row['parent_id'];
        $children = (int) Database::scalar('SELECT COUNT(*) FROM mt_menu_items WHERE parent_id = :id', ['id' => $id]);

        Database::execute('DELETE FROM mt_menu_items WHERE id = :id', ['id' => $id]);
        self::normalizeSiblings($parentId);
        Menu::invalidate($storeId);
        if ($platform) {
            Menu::invalidateAll();
        }

        $extra = $children > 0 ? ' (con ' . $children . ' nodo(s) dentro)' : '';

        return ['ok' => true, 'message' => 'Nodo eliminado' . $extra . '.'];
    }

    /** Activa o desactiva un nodo. */
    public static function toggle(int $id, int $storeId, bool $platform): array
    {
        $row = self::node($id, $storeId, $platform);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Ese nodo no existe.'];
        }
        if (!self::canEdit($row, $storeId, $platform)) {
            return ['ok' => false, 'message' => 'Ese nodo no es de tu tienda.'];
        }

        $active = (int) $row['active'] === 1 ? 0 : 1;
        Database::update('mt_menu_items', $id, ['active' => $active, 'note' => null]);
        Menu::invalidate($storeId);
        if ($platform) {
            Menu::invalidateAll();
        }

        return ['ok' => true, 'message' => $active === 1 ? 'Nodo activado.' : 'Nodo desactivado.'];
    }

    // =====================================================================
    // ORDEN Y NIVEL (drag & drop)
    // =====================================================================

    /**
     * Mueve un nodo a otro padre y posicion (lo que hace el drag & drop).
     *
     * @param int|null $parentId null = primer nivel
     * @param int $position  0 = el primero
     * @return array{ok:bool,message:string}
     */
    public static function move(int $id, ?int $parentId, int $position, int $storeId, bool $platform): array
    {
        $row = self::node($id, $storeId, $platform);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Ese nodo no existe.'];
        }
        if (!self::canEdit($row, $storeId, $platform)) {
            return ['ok' => false, 'message' => 'Ese nodo no es de tu tienda.'];
        }
        if ($parentId === $id) {
            return ['ok' => false, 'message' => 'Un nodo no puede ser hijo de si mismo.'];
        }

        $level = 1;
        if ($parentId !== null) {
            $parent = self::node($parentId, $storeId, $platform);
            if ($parent === null) {
                return ['ok' => false, 'message' => 'La categoria destino no existe.'];
            }
            if (!$platform && (int) ($parent['store_id'] ?? 0) !== $storeId) {
                return ['ok' => false, 'message' => 'No puedes mover nodos dentro de una categoria de la plataforma.'];
            }
            if (self::isDescendant($parentId, $id)) {
                return ['ok' => false, 'message' => 'No puedes mover una categoria dentro de si misma.'];
            }
            $level = (int) $parent['level'] + 1;
        }

        // Profundidad: el nodo y su rama mas larga no pueden pasar de 3 niveles.
        $depth = self::depth($id);
        if ($level + $depth - 1 > 3) {
            return ['ok' => false, 'message' => 'No cabe: el menu tiene tres niveles.'];
        }

        Database::update('mt_menu_items', $id, [
            'parent_id' => $parentId,
            'level'     => $level,
            'sort'      => max(0, $position),
        ]);
        self::applyLevel($id, $level);
        self::normalizeSiblings($parentId);
        Menu::invalidate($storeId);
        if ($platform) {
            Menu::invalidateAll();
        }

        return ['ok' => true, 'message' => 'Orden actualizado.'];
    }

    /**
     * Guarda de golpe el orden de un nivel (lo que envia el drag & drop).
     *
     * @param array<int,int> $order ids en el orden deseado
     */
    public static function reorder(array $order, ?int $parentId, int $storeId, bool $platform): array
    {
        $position = 0;
        $moved = 0;
        foreach ($order as $id) {
            $id = (int) $id;
            $row = self::node($id, $storeId, $platform);
            if ($row === null || !self::canEdit($row, $storeId, $platform)) {
                continue;
            }
            $owner = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            if ($owner !== $parentId) {
                $result = self::move($id, $parentId, $position, $storeId, $platform);
                if (!$result['ok']) {
                    return $result;
                }
            } else {
                Database::update('mt_menu_items', $id, ['sort' => $position]);
            }
            $position++;
            $moved++;
        }

        self::normalizeSiblings($parentId);
        Menu::invalidate($storeId);
        if ($platform) {
            Menu::invalidateAll();
        }

        return ['ok' => true, 'message' => $moved . ' nodo(s) reordenados.'];
    }

    /** Reindexa los hermanos de un padre (0, 1, 2...). */
    private static function normalizeSiblings(?int $parentId): void
    {
        $where = $parentId === null ? 'parent_id IS NULL' : 'parent_id = :parent_id';
        $params = $parentId === null ? [] : ['parent_id' => $parentId];
        $rows = Database::select(
            'SELECT id FROM mt_menu_items WHERE ' . $where . ' ORDER BY sort ASC, id ASC',
            $params
        );
        $sort = 0;
        foreach ($rows as $row) {
            Database::update('mt_menu_items', (int) $row['id'], ['sort' => $sort]);
            $sort++;
        }
    }

    /** Baja el nivel a toda la rama de un nodo. */
    private static function applyLevel(int $id, int $level): void
    {
        foreach (Database::select('SELECT id, level FROM mt_menu_items WHERE parent_id = :id', ['id' => $id]) as $child) {
            $childLevel = $level + 1;
            if ((int) $child['level'] !== $childLevel) {
                Database::update('mt_menu_items', (int) $child['id'], ['level' => $childLevel]);
            }
            self::applyLevel((int) $child['id'], $childLevel);
        }
    }

    /** ¿$maybeChild es descendiente de $id? (evita ciclos al arrastrar) */
    private static function isDescendant(int $maybeChild, int $id): bool
    {
        $current = $maybeChild;
        $guard = 0;
        while ($current > 0 && $guard < 50) {
            if ($current === $id) {
                return true;
            }
            $parent = Database::scalar('SELECT parent_id FROM mt_menu_items WHERE id = :id', ['id' => $current]);
            $current = $parent === null || $parent === false ? 0 : (int) $parent;
            $guard++;
        }

        return false;
    }

    /** Profundidad de la rama que cuelga de un nodo (1 = sin hijos). */
    private static function depth(int $id): int
    {
        $max = 1;
        foreach (Database::select('SELECT id FROM mt_menu_items WHERE parent_id = :id', ['id' => $id]) as $child) {
            $max = max($max, 1 + self::depth((int) $child['id']));
        }

        return $max;
    }

    private static function nextSort(?int $parentId): int
    {
        $where = $parentId === null ? 'parent_id IS NULL' : 'parent_id = :parent_id';
        $params = $parentId === null ? [] : ['parent_id' => $parentId];

        return 1 + (int) Database::scalar('SELECT COALESCE(MAX(sort), -1) FROM mt_menu_items WHERE ' . $where, $params);
    }

    // =====================================================================
    // VISIBILIDAD POR TIENDA (solo plataforma)
    // =====================================================================

    /** Tiendas marcadas para un nodo. @return array<int,int> */
    public static function visibilityStores(int $itemId): array
    {
        if (!Database::tableExists('mt_menu_item_stores')) {
            return [];
        }

        $out = [];
        foreach (Database::select('SELECT store_id FROM mt_menu_item_stores WHERE item_id = :id', ['id' => $itemId]) as $row) {
            $out[] = (int) $row['store_id'];
        }

        return $out;
    }

    /**
     * Guarda "que tiendas ven este nodo" (punto 10). Solo la plataforma.
     *
     * @param array<int,int> $storeIds
     */
    public static function saveVisibility(int $itemId, string $mode, array $storeIds, int $storeId, bool $platform): array
    {
        if (!$platform) {
            return ['ok' => false, 'message' => 'Solo la plataforma reparte el menu entre tiendas.'];
        }

        $row = self::node($itemId, $storeId, true);
        if ($row === null) {
            return ['ok' => false, 'message' => 'Ese nodo no existe.'];
        }

        $mode = strtolower(trim($mode));
        if (!isset(Menu::visibilityModes()[$mode])) {
            $mode = 'todas';
        }

        // Solo tiendas que existan de verdad.
        $valid = [];
        foreach ($storeIds as $id) {
            $id = (int) $id;
            if ($id > 0 && self::storeExists($id)) {
                $valid[$id] = $id;
            }
        }

        Database::update('mt_menu_items', $itemId, ['visibility' => $mode, 'note' => null]);
        Database::execute('DELETE FROM mt_menu_item_stores WHERE item_id = :id', ['id' => $itemId]);
        if ($mode !== 'todas') {
            foreach (array_values($valid) as $id) {
                Database::execute(
                    'INSERT IGNORE INTO mt_menu_item_stores (item_id, store_id) VALUES (:item, :store)',
                    ['item' => $itemId, 'store' => $id]
                );
            }
        }

        Menu::invalidateAll();

        $label = (string) (Menu::visibilityModes()[$mode] ?? $mode);

        return ['ok' => true, 'message' => 'Visibilidad guardada: ' . $label . '.'];
    }

    /**
     * Convierte en COMPARTIDOS todos los nodos propios de una tienda.
     *
     * Sirve para pasar al modelo de plataforma: el arbol que se sembro para una
     * tienda pasa a ser el arbol comun que ven todas. No borra nada.
     */
    public static function shareStoreTree(int $storeId, bool $platform): array
    {
        if (!$platform) {
            return ['ok' => false, 'message' => 'Solo la plataforma puede compartir un arbol.'];
        }
        if (!self::storeExists($storeId)) {
            return ['ok' => false, 'message' => 'Esa tienda no existe.'];
        }

        $total = (int) Database::execute(
            'UPDATE mt_menu_items SET store_id = NULL WHERE store_id = :store_id',
            ['store_id' => $storeId]
        );
        Menu::invalidateAll();

        return ['ok' => true, 'message' => $total . ' nodo(s) pasan a ser compartidos.'];
    }

    // =====================================================================
    // LISTAS PARA EL EDITOR
    // =====================================================================

    /** Tiendas activas, para los selectores de visibilidad y propiedad. */
    public static function storeOptions(): array
    {
        $out = [];
        if (!Database::tableExists('mt_stores')) {
            return $out;
        }

        foreach (Database::select('SELECT id, name, slug, status FROM mt_stores ORDER BY name ASC') as $row) {
            $out[] = [
                'id'     => (int) $row['id'],
                'label'  => (string) $row['name'] !== '' ? (string) $row['name'] : (string) $row['slug'],
                'slug'   => (string) $row['slug'],
                'active' => (int) $row['status'] === 1,
            ];
        }

        return $out;
    }

    /** Banners de la tienda para el selector del nodo. */
    public static function bannerOptions(int $storeId): array
    {
        if (!Database::tableExists('mt_banners')) {
            return [];
        }

        return Database::select(
            'SELECT id, title, image_url FROM mt_banners
              WHERE store_id = :store_id ORDER BY sort ASC, id ASC LIMIT 100',
            ['store_id' => $storeId]
        );
    }

    /**
     * Destinos disponibles para el selector: categorias, subcategorias (con su
     * categoria), etiquetas comerciales y los filtros del catalogo que ya usa
     * algun nodo del menu (los filtros del mayorista son miles: no tiene
     * sentido volcarlos todos). Las marcas se buscan aparte.
     *
     * @return array{categories:array,subcategories:array,tags:array,filters:array}
     */
    public static function destinations(): array
    {
        $categories = [];
        foreach (Database::select('SELECT id, categoria FROM categorias ORDER BY orden ASC, categoria ASC') as $row) {
            $categories[] = ['id' => (int) $row['id'], 'label' => (string) $row['categoria']];
        }

        $subcategories = [];
        foreach (Database::select(
            'SELECT s.id, s.subcategoria, s.id_categoria, c.categoria
               FROM subcategorias s
               LEFT JOIN categorias c ON c.id = s.id_categoria
              ORDER BY c.orden ASC, s.orden ASC, s.subcategoria ASC'
        ) as $row) {
            $subcategories[] = [
                'id'        => (int) $row['id'],
                'label'     => (string) $row['subcategoria'],
                'category'  => (string) ($row['categoria'] ?? ''),
                'cat_id'    => (int) $row['id_categoria'],
            ];
        }

        $tags = [];
        foreach (Catalog::tags() as $key => $def) {
            if ((string) $key === 'todos') {
                continue;
            }
            $tags[] = ['key' => (string) $key, 'label' => (string) ($def['label'] ?? $key)];
        }

        // Filtros del mayorista ya presentes en el menu: se ofrecen para no
        // perderlos al editar un nodo de tipo filtro.
        $filters = [];
        if (Database::tableExists('mt_menu_items') && Database::tableExists('filtros') && Database::tableExists('subfiltros')) {
            foreach (Database::select(
                'SELECT n.target_id AS id_filtro, n.target_extra AS id_subfiltro,
                        f.filtro AS filtro, sf.nombre AS subfiltro
                   FROM mt_menu_items n
                   LEFT JOIN filtros f ON f.id = n.target_id
                   LEFT JOIN subfiltros sf ON sf.id = n.target_extra
                  WHERE n.target_type = :tipo AND n.target_id > 0
                  GROUP BY n.target_id, n.target_extra, f.filtro, sf.nombre
                  ORDER BY f.filtro ASC, sf.nombre ASC',
                ['tipo' => 'filtro']
            ) as $row) {
                $filterId = (int) $row['id_filtro'];
                $subfilterId = (int) $row['id_subfiltro'];
                $filters[] = [
                    'id'        => $filterId,
                    'extra'     => $subfilterId,
                    'label'     => Catalog::structuredChipLabel($filterId, $subfilterId),
                ];
            }
        }

        return ['categories' => $categories, 'subcategories' => $subcategories, 'tags' => $tags, 'filters' => $filters];
    }

    // =====================================================================
    // UTILIDADES
    // =====================================================================

    public static function targetLabel(array $row): string
    {
        $type = (string) $row['target_type'];
        $id = (int) ($row['target_id'] ?? 0);

        switch ($type) {
            case 'categoria':
                return 'Categoria: ' . (string) Database::scalar('SELECT categoria FROM categorias WHERE id = :id', ['id' => $id]);
            case 'subcategoria':
                return 'Subcategoria: ' . (string) Database::scalar('SELECT subcategoria FROM subcategorias WHERE id = :id', ['id' => $id]);
            case 'marca':
                return 'Marca: ' . Catalog::brandLabel($id);
            case 'etiqueta':
                return 'Etiqueta: ' . Catalog::tagLabel((string) ($row['target_key'] ?? ''));
            case 'url':
                return 'Enlace: ' . (string) ($row['url'] ?? '');
            case 'filtro':
                return 'Filtro: ' . Catalog::structuredChipLabel($id, (int) ($row['target_extra'] ?? 0));
        }

        return 'Sin destino';
    }

    /**
     * Mensaje legible de un error de base de datos.
     *
     * Los datos del formulario se validan antes, asi que un error aqui es casi
     * siempre un valor que no cabe o un destino que ya no existe: mejor decirlo
     * en el panel que dejar un 500.
     */
    private static function dbError(\Throwable $e): string
    {
        $mensaje = $e->getMessage();
        if (str_contains($mensaje, 'Data too long')) {
            return 'Algun texto es demasiado largo para su campo.';
        }
        if (str_contains($mensaje, 'foreign key') || str_contains($mensaje, 'Cannot add or update')) {
            return 'El destino elegido ya no existe en el catalogo.';
        }

        return 'Error de base de datos al guardar el nodo.';
    }

    private static function slugTaken(string $slug, ?int $parentId, ?int $ignoreId): bool
    {
        $where = $parentId === null ? 'parent_id IS NULL' : 'parent_id = :parent_id';
        $params = $parentId === null ? [] : ['parent_id' => $parentId];
        $where .= ' AND slug = :slug';
        $params['slug'] = $slug;
        if ($ignoreId !== null) {
            $where .= ' AND id <> :ignore';
            $params['ignore'] = $ignoreId;
        }

        return (int) Database::scalar('SELECT COUNT(*) FROM mt_menu_items WHERE ' . $where, $params) > 0;
    }

    private static function categoryExists(int $id): bool
    {
        return $id > 0 && (bool) Database::scalar('SELECT 1 FROM categorias WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    private static function subcategoryExists(int $id): bool
    {
        return $id > 0 && (bool) Database::scalar('SELECT 1 FROM subcategorias WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    private static function brandExists(int $id): bool
    {
        return $id > 0 && (bool) Database::scalar('SELECT 1 FROM marcas WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** ¿Existe el filtro del mayorista (y, si se indica, ese subfiltro suyo)? */
    private static function filterExists(int $filterId, int $subfilterId): bool
    {
        if ($filterId <= 0
            || !Database::tableExists('filtros')
            || !Database::tableExists('subfiltros')) {
            return false;
        }
        if ($subfilterId <= 0) {
            return (bool) Database::scalar(
                'SELECT 1 FROM filtros WHERE id = :id AND deleted = 0 LIMIT 1',
                ['id' => $filterId]
            );
        }

        return (bool) Database::scalar(
            'SELECT 1 FROM subfiltros WHERE id = :sub AND id_filtro = :filter LIMIT 1',
            ['sub' => $subfilterId, 'filter' => $filterId]
        );
    }

    private static function storeExists(int $id): bool
    {
        return $id > 0 && (bool) Database::scalar('SELECT 1 FROM mt_stores WHERE id = :id LIMIT 1', ['id' => $id]);
    }
}
