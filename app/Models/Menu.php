<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\CatalogUrl;
use Tienda\Core\Config;
use Tienda\Core\Database;
use Tienda\Core\Env;
use Tienda\Core\Str;

/**
 * Arbol de navegacion de una tienda (`mt_menu_items`).
 *
 * Es **un solo arbol** con tres niveles y lo usan los DOS menus: el Compacto y
 * el Catalogo. Lo unico que cambia entre ellos es la presentacion; la
 * informacion de categorias es exactamente la misma.
 *
 *   nivel 1  categoria comercial (apunta a una categoria real del catalogo)
 *   nivel 2  grupo editorial    (opcionalmente anclado en una subcategoria)
 *   nivel 3  destino            (subcategoria | marca | etiqueta | url | filtro)
 *
 * El arbol se puede sembrar desde el menu de referencia de PuntoByZE
 * (`websites_menu` -> `websites_list` -> `websites_item`, SOLO LECTURA) y luego
 * se gestiona desde el panel.
 */
final class Menu
{
    /** Web de referencia de la siembra: 2 = PuntoByZE, 1 = I-Directo. */
    private const SEED_WEBSITE = 2;

    /** Ruta de la web de referencia dentro de `websites_item.url`. */
    private const SEED_HOSTS = ['puntobyze.com', 'idirecto.es'];

    /**
     * Version de la forma del arbol cacheado. Se sube cuando cambia la
     * estructura que devuelve `forStore()`, para que la cache antigua no se
     * reutilice con el formato viejo.
     */
    private const CACHE_VERSION = 2;

    /**
     * Slugs de categoria del menu de referencia que no coinciden con
     * `Str::slugify()` del nombre real. Se resuelven por nombre.
     */
    private const CATEGORY_ALIASES = [
        'telefon-a'     => 'TELEFONÍA',
        'telefonia'     => 'TELEFONÍA',
        'ordenadores'   => 'ORDENADORES',
        'portatiles'    => 'PORTATILES',
        'tablets'       => 'TABLETS',
        'componentes'   => 'COMPONENTES',
        'perifericos'   => 'PERIFERICOS',
        'sonido'        => 'SONIDO',
        'impresoras'    => 'IMPRESORAS',
        'papeleria'     => 'PAPELERIA',
        'cables'        => 'CABLES',
        'proyectores'   => 'PROYECTORES',
        'foto-y-video'  => 'FOTO Y VIDEO',
        'gamer'         => 'GAMER',
        'ocio'          => 'Ocio',
        'cuidado'       => 'Cuidado',
        'drones'        => 'DRONES',
        'software'      => 'SOFTWARE',
        'tpv'           => 'TPV',
        'redes-y-sais'  => 'REDES Y SAIS',
        'servidores'    => 'SERVIDORES',
        'seguridad'     => 'SEGURIDAD',
        'rack'          => 'RACK',
        'hogar-oficina' => 'Hogar Oficina',
        'electro'       => 'ELECTRO',
        'deporte'       => 'DEPORTE',
        'gadget'        => 'Gadget',
        'cocina'        => 'COCINA',
        'calefacion'    => 'Calefacion',
    ];

    // =====================================================================
    // ESTILO DE MENU
    // =====================================================================

    /** Los dos (y solo dos) estilos disponibles. */
    public static function styles(): array
    {
        return (array) Config::get('menu.styles', [
            'compacto' => 'Menu Compacto',
            'catalogo' => 'Menu Catalogo',
        ]);
    }

    /** Estilo elegido por la tienda, saneado contra la lista de estilos. */
    public static function style(array $store): string
    {
        $style = strtolower(trim((string) ($store['menu_style'] ?? '')));
        $styles = self::styles();
        if ($style !== '' && isset($styles[$style])) {
            return $style;
        }

        $fallback = (string) Config::get('menu.fallback_style', 'catalogo');
        return isset($styles[$fallback]) ? $fallback : 'catalogo';
    }

    // =====================================================================
    // ARBOL
    // =====================================================================

    /**
     * Arbol completo de la tienda, listo para pintar.
     *
     * Lo que ve el visitante es **siempre la version publicada** (una sola fila
     * en `mt_menu_published`): asi un borrador a medias nunca se ve en la
     * tienda. Si la tienda todavia no ha publicado, se pinta el arbol de trabajo
     * como hasta ahora (nada se rompe al actualizar).
     *
     * @return array{categories:array<int,array>,groups:int,links:int}
     */
    public static function forStore(int $storeId): array
    {
        if ($storeId <= 0 || !Database::tableExists('mt_menu_items')) {
            return ['categories' => [], 'groups' => 0, 'links' => 0];
        }

        $ttl = (int) Config::get('menu.cache_ttl', 900);
        // Version de forma + version publicada: al publicar cambia la clave y la
        // cache vieja se ignora sola.
        $key = 'tree_v' . self::CACHE_VERSION . '_' . $storeId . '_' . self::publishedVersion($storeId);
        $tree = self::cached($key, $ttl, static fn (): array => self::readTree($storeId));

        return (array) $tree;
    }

    /** Arbol publicado si existe; si no, el arbol de trabajo de la tienda. */
    private static function readTree(int $storeId): array
    {
        $published = self::publishedTree($storeId);
        if ($published !== null) {
            return $published;
        }

        return self::buildTree(self::visibleRows($storeId));
    }

    /**
     * Filas del arbol visibles para una tienda.
     *
     * Los nodos de la plataforma (`store_id` NULL) se ven en todas las tiendas
     * salvo que su `visibility` diga lo contrario (`mt_menu_item_stores`) o que
     * pidan un nivel de cliente que la tienda no alcanza. Los nodos propios
     * (`store_id` = tienda) se ven solo en su tienda.
     *
     * @param bool $platform true = sin filtrar (para el editor de la plataforma)
     * @return array<int,array>
     */
    public static function visibleRows(int $storeId, bool $platform = false): array
    {
        if (!Database::tableExists('mt_menu_items')) {
            return [];
        }

        $where = 'active = 1';
        $params = [];
        if (!$platform) {
            $where .= ' AND (store_id IS NULL OR store_id = :store_id)';
            $params['store_id'] = $storeId;
        }

        $rows = Database::select(
            'SELECT id, store_id, parent_id, level, label, slug, icon, target_type, target_id,
                    target_extra, target_key, url, badge, badge_color, banner_id, banner_url,
                    min_customer_level, hide_empty, visibility, sort, active, note
             FROM mt_menu_items
             WHERE ' . $where . '
             ORDER BY level ASC, sort ASC, id ASC',
            $params
        );

        if ($platform || $rows === []) {
            return $rows;
        }

        $scoped = Database::tableExists('mt_menu_item_stores') ? self::storeScopeIndex() : [];
        $level = self::storeLevelOrder($storeId);

        $out = [];
        foreach ($rows as $row) {
            if ((int) ($row['store_id'] ?? 0) === $storeId) {
                $out[] = $row; // Nodo propio de la tienda: siempre visible aqui.
                continue;
            }
            if (!self::matchesVisibility($row, $storeId, $scoped)) {
                continue;
            }
            if (!self::matchesCustomerLevel($row, $level)) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /** item_id => [store_id, ...] de la tabla de visibilidad (una sola consulta). */
    private static function storeScopeIndex(): array
    {
        $index = [];
        foreach (Database::select('SELECT item_id, store_id FROM mt_menu_item_stores') as $row) {
            $index[(int) $row['item_id']][] = (int) $row['store_id'];
        }

        return $index;
    }

    /** Visibilidad por tienda de un nodo (todas | solo | excepto). */
    private static function matchesVisibility(array $row, int $storeId, array $scoped): bool
    {
        $mode = strtolower(trim((string) ($row['visibility'] ?? 'todas')));
        if ($mode === 'todas' || $mode === '') {
            return true;
        }

        $stores = $scoped[(int) $row['id']] ?? [];
        $in = in_array($storeId, $stores, true);

        return $mode === 'solo' ? $in : !$in;
    }

    /**
     * Nivel minimo de cliente: se compara el `orden` de `categoria_cliente`
     * (1 Informatica, 2 Telefonia, 3 Papeleria). Si la tienda no tiene nivel
     * asignado no se puede demostrar que cumpla, asi que el nodo no se muestra.
     */
    private static function matchesCustomerLevel(array $row, ?int $storeLevel): bool
    {
        $min = $row['min_customer_level'] === null ? null : (int) $row['min_customer_level'];
        if ($min === null || $min <= 0) {
            return true;
        }
        if ($storeLevel === null) {
            return false;
        }

        return $storeLevel >= $min;
    }

    /** Nivel de cliente de la tienda (orden de `categoria_cliente`) o null. */
    public static function storeLevelOrder(int $storeId): ?int
    {
        $info = self::storeLevel($storeId);

        return $info['order'] ?? null;
    }

    /**
     * Nivel de cliente de la tienda, con su nombre, para el panel.
     *
     * @return array{id:int,label:string,order:int}|null
     */
    public static function storeLevel(int $storeId): ?array
    {
        if (!Database::tableExists('tiendas') || !Database::tableExists('categoria_cliente')) {
            return null;
        }

        $owner = Database::first(
            'SELECT t.id_categ_cliente FROM mt_stores s
               JOIN tiendas t ON t.id = s.id_tienda_idirecto
              WHERE s.id = :id LIMIT 1',
            ['id' => $storeId]
        );
        $levelId = (int) ($owner['id_categ_cliente'] ?? 0);
        if ($levelId <= 0) {
            return null;
        }

        foreach (self::customerLevels() as $level) {
            if ($level['id'] === $levelId) {
                return $level;
            }
        }

        return null;
    }

    /**
     * Niveles de cliente del mayorista (`categoria_cliente`, solo lectura).
     *
     * @return array<int,array{id:int,label:string,order:int}>
     */
    public static function customerLevels(): array
    {
        if (!Database::tableExists('categoria_cliente')) {
            return [];
        }

        $out = [];
        foreach (Database::select(
            'SELECT id, descripcion, orden FROM categoria_cliente
              WHERE deleted = 0 ORDER BY orden ASC, id ASC'
        ) as $row) {
            $out[] = [
                'id'    => (int) $row['id'],
                'label' => (string) $row['descripcion'],
                'order' => (int) $row['orden'],
            ];
        }

        return $out;
    }

    // =====================================================================
    // PUBLICACION (borrador -> publicado)
    // =====================================================================

    /** Numero de version publicada (0 = nunca se ha publicado). */
    public static function publishedVersion(int $storeId): int
    {
        if (!Database::tableExists('mt_menu_published')) {
            return 0;
        }

        return (int) Database::scalar(
            'SELECT version FROM mt_menu_published WHERE store_id = :id',
            ['id' => $storeId]
        );
    }

    /** Fecha de la ultima publicacion, si la hay. */
    public static function publishedAt(int $storeId): ?string
    {
        if (!Database::tableExists('mt_menu_published')) {
            return null;
        }
        $value = Database::scalar('SELECT published_at FROM mt_menu_published WHERE store_id = :id', ['id' => $storeId]);

        return $value === null || $value === false ? null : (string) $value;
    }

    /** Arbol publicado de la tienda, o null si nunca se ha publicado. */
    public static function publishedTree(int $storeId): ?array
    {
        if (!Database::tableExists('mt_menu_published')) {
            return null;
        }

        $payload = Database::scalar('SELECT payload FROM mt_menu_published WHERE store_id = :id', ['id' => $storeId]);
        if (!is_string($payload) || $payload === '') {
            return null;
        }

        $tree = json_decode($payload, true);
        if (!is_array($tree) || !isset($tree['categories'])) {
            return null;
        }

        return [
            'categories' => (array) $tree['categories'],
            'groups'     => (int) ($tree['groups'] ?? 0),
            'links'      => (int) ($tree['links'] ?? 0),
        ];
    }

    /**
     * Arbol de TRABAJO (el borrador) tal y como quedaria al publicar, con la
     * visibilidad de esta tienda ya aplicada. Lo usa la vista previa del panel:
     * asi se ve exactamente lo que se va a publicar, sin tocar la tienda.
     */
    public static function draftTree(int $storeId): array
    {
        if ($storeId <= 0 || !Database::tableExists('mt_menu_items')) {
            return ['categories' => [], 'groups' => 0, 'links' => 0];
        }

        return self::buildTree(self::visibleRows($storeId));
    }

    /** ¿Hay cambios sin publicar? (compara lo que se publicaria con lo publicado) */
    public static function hasDraftChanges(int $storeId): bool
    {
        $published = self::publishedTree($storeId);
        if ($published === null) {
            return (int) Database::scalar(
                'SELECT COUNT(*) FROM mt_menu_items WHERE store_id IS NULL OR store_id = :id',
                ['id' => $storeId]
            ) > 0;
        }

        $draft = self::draftTree($storeId);

        return json_encode($draft) !== json_encode($published);
    }

    /**
     * Publica el arbol de la tienda: valida, guarda el snapshot, sube version,
     * deja el historico y **invalida la cache** (lo pide el punto 15).
     *
     * @return array{ok:bool,version:int,stats:array,warnings:array<int,string>}
     */
    public static function publish(int $storeId, ?int $userId = null, bool $fromPublished = false): array
    {
        if ($storeId <= 0) {
            return ['ok' => false, 'version' => 0, 'stats' => [], 'warnings' => ['Tienda no valida.']];
        }

        $tree = self::buildTree(self::visibleRows($storeId));
        $warnings = self::review($storeId);
        $payload = json_encode($tree, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return ['ok' => false, 'version' => 0, 'stats' => [], 'warnings' => ['No se ha podido serializar el arbol.']];
        }

        $version = self::publishedVersion($storeId) + 1;
        $now = date('Y-m-d H:i:s');

        Database::execute(
            'INSERT INTO mt_menu_published (store_id, version, payload, published_at, published_by)
             VALUES (:store_id, :version, :payload, :now, :user)
             ON DUPLICATE KEY UPDATE version = VALUES(version), payload = VALUES(payload),
                                     published_at = VALUES(published_at), published_by = VALUES(published_by)',
            ['store_id' => $storeId, 'version' => $version, 'payload' => $payload, 'now' => $now, 'user' => $userId]
        );

        if (Database::tableExists('mt_menu_revisions')) {
            Database::execute(
                'INSERT IGNORE INTO mt_menu_revisions (store_id, version, payload, published_by, created_at)
                 VALUES (:store_id, :version, :payload, :user, :now)',
                ['store_id' => $storeId, 'version' => $version, 'payload' => $payload, 'user' => $userId, 'now' => $now]
            );
        }

        self::invalidate($storeId);

        return ['ok' => true, 'version' => $version, 'stats' => $tree, 'warnings' => $warnings];
    }

    /** Avisos que el editor debe enseñar antes de publicar. */
    public static function review(int $storeId): array
    {
        $warnings = [];
        $rows = self::visibleRows($storeId, true);

        $cats = 0;
        $groups = 0;
        $links = 0;
        $withoutTarget = 0;
        foreach ($rows as $row) {
            if ((int) $row['active'] !== 1) {
                continue;
            }
            $level = (int) $row['level'];
            if ($level === 1) { $cats++; }
            if ($level === 2) { $groups++; }
            if ($level === 3) { $links++; }
            if ($level === 3 && ($row['target_id'] === null || (int) $row['target_id'] <= 0)
                && (string) $row['target_type'] !== 'url' && (string) $row['target_type'] !== 'etiqueta') {
                $withoutTarget++;
            }
        }

        if ($cats === 0) {
            $warnings[] = 'El menu no tiene ninguna categoria de primer nivel activa: la tienda no mostrara menu.';
        }
        if ($groups === 0 && $cats > 0) {
            $warnings[] = 'Ninguna categoria tiene grupos de segundo nivel: el menu quedara casi vacio.';
        }
        if ($withoutTarget > 0) {
            $warnings[] = $withoutTarget . ' destino(s) de tercer nivel no tienen destino elegido y no se pintaran.';
        }
        if (self::storeLevel($storeId) === null) {
            $warnings[] = 'La tienda no tiene nivel de cliente asignado: los nodos con nivel minimo no se le muestran.';
        }
        if (!Database::tableExists('mt_menu_items')) {
            $warnings[] = 'Falta la tabla del menu (migracion 005).';
        }

        return $warnings;
    }

    /** Construye el arbol desde las filas indicadas (sin cache). */
    private static function buildTree(array $rows): array
    {

        // Contadores de stock (arbol del catalogo ya cacheado 30 min).
        $stock = [];
        $stockSub = [];
        foreach (Catalog::menuTree() as $cat) {
            $stock[(int) $cat['id']] = (int) $cat['total'];
            foreach ((array) ($cat['subcategories'] ?? []) as $sub) {
                $stockSub[(int) $sub['id']] = (int) $sub['total'];
            }
        }

        // Indexar por nivel y padre.
        $byId = [];
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['parent_id'] = $row['parent_id'] === null ? null : (int) $row['parent_id'];
            $row['level'] = (int) $row['level'];
            $row['children'] = [];
            $byId[$row['id']] = $row;
        }

        $rootIds = [];
        foreach (array_keys($byId) as $id) {
            $parent = $byId[$id]['parent_id'];
            if ($parent !== null && isset($byId[$parent])) {
                $byId[$parent]['children'][] = $id;
            } else {
                $rootIds[] = $id;
            }
        }

        $categories = [];
        $groups = 0;
        $links = 0;

        foreach ($rootIds as $rootId) {
            $root = $byId[$rootId];
            if ((int) $root['level'] !== 1) {
                continue; // Un nodo suelto de nivel 2/3 sin padre no se pinta.
            }

            $catId = (int) ($root['target_id'] ?? 0);
            $path = CatalogUrl::categoryPath($catId) ?? '/catalogo';

            // Configuracion de categorias vacias: el nodo no se pinta si no
            // tiene productos con stock.
            if ((int) ($root['hide_empty'] ?? 0) === 1 && ($stock[$catId] ?? 0) === 0) {
                continue;
            }

            $groupsOut = [];
            foreach ($root['children'] as $groupId) {
                $group = $byId[$groupId];
                if ((int) $group['level'] !== 2) {
                    continue;
                }
                $anchorSub = (int) ($group['target_id'] ?? 0);
                $groupPath = $anchorSub > 0 && $anchorSub !== $catId
                    ? (CatalogUrl::subPath($anchorSub) ?? $path)
                    : $path;

                $linksOut = [];
                foreach ($group['children'] as $linkId) {
                    $link = $byId[$linkId];
                    if ((int) $link['level'] !== 3) {
                        continue;
                    }
                    if ((int) ($link['hide_empty'] ?? 0) === 1
                        && (string) $link['target_type'] === 'subcategoria'
                        && (int) ($stockSub[(int) $link['target_id']] ?? 0) === 0) {
                        continue; // Subcategoria sin productos: no se pinta.
                    }
                    $resolved = self::resolveLink($link, $groupPath, $path);
                    if ($resolved === null) {
                        continue; // Destino pendiente: no se pinta en la tienda.
                    }
                    $linksOut[] = $resolved;
                }

                if ($linksOut === []) {
                    continue; // Grupo sin destinos: no se pinta una columna vacia.
                }

                $groupsOut[] = [
                    'id'     => (int) $group['id'],
                    'label'  => (string) $group['label'],
                    'path'   => $groupPath,
                    'icon'   => self::icon($group),
                    'badge'  => self::badge($group),
                    'links'  => $linksOut,
                ];
                $groups++;
                $links += count($linksOut);
            }

            if ($groupsOut === []) {
                continue;
            }

            $categories[] = [
                // `id` es la CATEGORIA del catalogo (la usan el panel promocional
                // y las consultas de destacados/marcas); `item_id` es el nodo del
                // arbol del menu, para el editor del panel.
                'id'          => $catId,
                'item_id'     => (int) $root['id'],
                'label'       => (string) $root['label'],
                'path'        => $path,
                'icon'        => self::icon($root),
                'badge'       => self::badge($root),
                'badge_color' => self::badgeColor($root),
                'banner'      => self::banner($root),
                'total'       => $stock[$catId] ?? 0,
                'groups'      => $groupsOut,
            ];
        }

        return ['categories' => $categories, 'groups' => $groups, 'links' => $links];
    }

    /** Icono del nodo (clave del juego propio) o null. */
    private static function icon(array $row): ?string
    {
        $icon = trim((string) ($row['icon'] ?? ''));

        return $icon !== '' ? $icon : null;
    }

    /** Texto del badge o null. */
    private static function badge(array $row): ?string
    {
        $badge = trim((string) ($row['badge'] ?? ''));

        return $badge !== '' ? $badge : null;
    }

    /** Color del badge (#rrggbb o token --c-*) o null (usa el color por defecto). */
    private static function badgeColor(array $row): ?string
    {
        $color = trim((string) ($row['badge_color'] ?? ''));
        if ($color === '') {
            return null;
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color) === 1) {
            return $color;
        }

        return preg_match('/^--c-[a-z0-9-]+$/', $color) === 1 ? 'var(' . $color . ')' : null;
    }

    /**
     * Banner del nodo: o bien un banner de la tienda (`mt_banners`) o bien una
     * imagen externa con su enlace.
     *
     * @return array{image:?string,link:?string,title:?string}|null
     */
    private static function banner(array $row): ?array
    {
        $link = trim((string) ($row['banner_url'] ?? ''));
        $bannerId = (int) ($row['banner_id'] ?? 0);

        if ($bannerId > 0 && Database::tableExists('mt_banners')) {
            $banner = Database::first(
                'SELECT image_url, link, title FROM mt_banners WHERE id = :id AND active = 1 LIMIT 1',
                ['id' => $bannerId]
            );
            if ($banner !== null) {
                return [
                    'image' => $banner['image_url'] !== null && $banner['image_url'] !== '' ? (string) $banner['image_url'] : null,
                    'link'  => $banner['link'] !== null && $banner['link'] !== '' ? (string) $banner['link'] : ($link !== '' ? $link : null),
                    'title' => $banner['title'] !== null ? (string) $banner['title'] : null,
                ];
            }
        }

        return $link !== '' ? ['image' => null, 'link' => $link, 'title' => null] : null;
    }

    /**
     * Destino final de un nodo de nivel 3.
     *
     * @return array{label:string,path:string,badge:?string}|null  null si esta pendiente
     */
    private static function resolveLink(array $link, string $groupPath, string $categoryPath): ?array
    {
        $type = (string) $link['target_type'];
        $targetId = (int) ($link['target_id'] ?? 0);
        $badge = $link['badge'] !== null ? (string) $link['badge'] : null;
        $path = null;

        switch ($type) {
            case 'subcategoria':
                $path = CatalogUrl::subPath($targetId) ?? $categoryPath;
                break;

            case 'marca':
                $path = $targetId > 0 ? $groupPath . '/m/' . $targetId : null;
                break;

            case 'etiqueta':
                $key = CatalogUrl::tagKey((string) ($link['target_key'] ?? ''));
                $path = $key !== 'todos' ? $groupPath . '/etiqueta/' . rawurlencode($key) : null;
                break;

            case 'url':
                $url = trim((string) ($link['url'] ?? ''));
                $path = $url !== '' ? $url : null;
                break;

            case 'filtro':
                // Los filtros estructurados (rel_filtro_producto) aun no estan
                // implementados: el destino queda pendiente y no se pinta.
                return null;

            default:
                return null;
        }

        if ($path === null || $path === '') {
            return null;
        }

        return [
            'label'       => (string) $link['label'],
            'path'        => $path,
            'badge'       => $badge !== '' ? $badge : null,
            'badge_color' => self::badgeColor($link),
            'icon'        => self::icon($link),
        ];
    }

    // =====================================================================
    // ACCESOS RAPIDOS
    // =====================================================================

    /**
     * Accesos comerciales que acompanan al menu: Ofertas, Novedades, Marcas y
     * Destacados. Son rutas propias del storefront.
     *
     * @return array<int,array{key:string,label:string,path:string,icon:string}>
     */
    public static function quickLinks(): array
    {
        $out = [];
        foreach (CatalogUrl::tags() as $key => $def) {
            $key = (string) $key;
            if ($key === 'todos') {
                continue;
            }
            $out[] = [
                'key'   => $key,
                'label' => (string) ($def['label'] ?? $key),
                'path'  => '/' . rawurlencode($key),
                'icon'  => (string) ($def['icon'] ?? 'grid'),
            ];
        }

        $out[] = [
            'key'   => 'marcas',
            'label' => 'Marcas',
            'path'  => '/marcas',
            'icon'  => 'shield',
        ];

        return $out;
    }

    // =====================================================================
    // PANEL DE CATEGORIA (banner + destacados + marcas)
    // =====================================================================

    /**
     * Contenido de apoyo del panel de una categoria: banner, productos
     * destacados y marcas con stock. Todo opcional: si no hay, no se pinta.
     *
     * @return array{banner:?array,products:array,brands:array}
     */
    public static function panel(int $storeId, int $categoryId): array
    {
        $panel = (array) Config::get('menu.panel', []);
        $banner = null;

        if ((bool) ($panel['banner'] ?? true)) {
            $banners = Banner::visibleForStore($storeId, 'menu');
            $banner = $banners[0] ?? null;
        }

        $products = [];
        $limitProducts = (int) ($panel['products'] ?? 3);
        if ($limitProducts > 0) {
            $products = Catalog::featured($limitProducts, $categoryId);
        }

        $brands = [];
        $limitBrands = (int) ($panel['brands'] ?? 6);
        if ($limitBrands > 0) {
            $brands = Catalog::topBrands($categoryId, $limitBrands);
        }

        return ['banner' => $banner, 'products' => $products, 'brands' => $brands];
    }

    // =====================================================================
    // ESTADO Y GESTION
    // =====================================================================

    /** Resumen del arbol para el panel (en el ambito de la tienda o global). */
    public static function stats(int $storeId, bool $platform = false): array
    {
        $empty = [
            'categories' => 0, 'groups' => 0, 'links' => 0, 'pending' => 0, 'total' => 0,
            'shared' => 0, 'own' => 0, 'inactive' => 0, 'version' => 0, 'published' => false,
        ];
        if (!Database::tableExists('mt_menu_items')) {
            return $empty;
        }

        $where = '1 = 1';
        $params = [];
        if (!$platform) {
            // Lo que afecta a la tienda: los nodos compartidos y los suyos.
            $where = '(store_id IS NULL OR store_id = :store_id)';
            $params['store_id'] = $storeId;
        }

        $row = Database::first(
            'SELECT
                SUM(level = 1 AND active = 1) AS categories,
                SUM(level = 2 AND active = 1) AS `groups`,
                SUM(level = 3 AND active = 1) AS links,
                SUM(level = 3 AND active = 0) AS pending,
                SUM(active = 0) AS inactive,
                SUM(store_id IS NULL) AS shared,
                SUM(store_id IS NOT NULL) AS own,
                COUNT(*) AS total
             FROM mt_menu_items WHERE ' . $where,
            $params
        );

        return [
            'categories' => (int) ($row['categories'] ?? 0),
            'groups'     => (int) ($row['groups'] ?? 0),
            'links'      => (int) ($row['links'] ?? 0),
            'pending'    => (int) ($row['pending'] ?? 0),
            'inactive'   => (int) ($row['inactive'] ?? 0),
            'shared'     => (int) ($row['shared'] ?? 0),
            'own'        => (int) ($row['own'] ?? 0),
            'total'      => (int) ($row['total'] ?? 0),
            'version'    => self::publishedVersion($storeId),
            'published'  => self::publishedVersion($storeId) > 0,
        ];
    }

    /** Nodos pendientes (destinos que la siembra no pudo resolver). */
    public static function pending(int $storeId, int $limit = 50, bool $platform = false): array
    {
        if (!Database::tableExists('mt_menu_items')) {
            return [];
        }

        $where = 'active = 0 AND note IS NOT NULL';
        $params = [];
        if (!$platform) {
            $where .= ' AND (store_id IS NULL OR store_id = :store_id)';
            $params['store_id'] = $storeId;
        }

        return Database::select(
            'SELECT id, store_id, label, target_type, note
             FROM mt_menu_items
             WHERE ' . $where . '
             ORDER BY level ASC, sort ASC LIMIT ' . max(1, min(200, $limit)),
            $params
        );
    }

    /** Borra los nodos propios de una tienda (nunca los de la plataforma). */
    public static function clear(int $storeId): int
    {
        if (!Database::tableExists('mt_menu_items')) {
            return 0;
        }
        $deleted = Database::execute('DELETE FROM mt_menu_items WHERE store_id = :store_id', ['store_id' => $storeId]);
        self::invalidate($storeId);

        return (int) $deleted;
    }

    /**
     * Invalida la cache del arbol de una tienda (se llama SIEMPRE al publicar).
     *
     * Se borran todos los ficheros `menu_tree_*_<tienda>_*.json` porque la clave
     * lleva la version publicada: si solo se borrara la actual quedarian las
     * anteriores ocupando sitio.
     */
    public static function invalidate(int $storeId): void
    {
        $patterns = [
            self::cachePattern('tree_*_' . $storeId . '_*'),
            self::cachePattern('tree_' . $storeId),
            self::cachePattern('tree_v*_' . $storeId),
        ];
        foreach ($patterns as $pattern) {
            foreach ((array) glob($pattern) as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /** Borra la cache de todas las tiendas (al tocar nodos de la plataforma). */
    public static function invalidateAll(): void
    {
        foreach ((array) glob(self::cachePattern('tree_*')) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    // =====================================================================
    // SIEMBRA DESDE EL MENU DE REFERENCIA (PuntoByZE)
    // =====================================================================

    /**
     * Copia el arbol de referencia (nombres y orden) a la tienda.
     *
     * Solo lee las tablas del mayorista. Nunca escribe en ellas.
     *
     * @param bool $replace Borra el arbol actual de la tienda antes de sembrar
     * @return array{ok:bool,message:string,created:array,skipped:array}
     */
    public static function seedFromReference(int $storeId, bool $replace = false): array
    {
        foreach (['websites_menu', 'websites_list', 'websites_item', 'categorias', 'subcategorias'] as $table) {
            if (!Database::tableExists($table)) {
                return [
                    'ok' => false,
                    'message' => 'El catalogo del mayorista no tiene la tabla ' . $table . '.',
                    'created' => [], 'skipped' => [],
                ];
            }
        }
        if (!Database::tableExists('mt_menu_items')) {
            return ['ok' => false, 'message' => 'Falta la migracion 005 (mt_menu_items).', 'created' => [], 'skipped' => []];
        }

        $actual = (int) Database::scalar(
            'SELECT COUNT(*) FROM mt_menu_items WHERE store_id = :store_id',
            ['store_id' => $storeId]
        );
        if ($actual > 0 && !$replace) {
            return [
                'ok' => false,
                'message' => 'La tienda ya tiene un menu propio. Marca «reemplazar» para volver a la semilla de referencia.',
                'created' => [], 'skipped' => [],
            ];
        }

        $websiteId = (int) Env::int('MENU_SEED_WEBSITE', self::SEED_WEBSITE);
        $menus = Database::select(
            'SELECT id, nombre, orden FROM websites_menu WHERE id_websites_owned = :w ORDER BY orden ASC, id ASC',
            ['w' => $websiteId]
        );
        if ($menus === []) {
            return ['ok' => false, 'message' => 'La web de referencia no tiene menu configurado.', 'created' => [], 'skipped' => []];
        }

        $created = ['categories' => 0, 'groups' => 0, 'links' => 0];
        $skipped = [];
        $catIdBySlug = self::categoryIndex();

        if ($replace) {
            self::clear($storeId);
        }

        $sortCat = 0;
        foreach ($menus as $menu) {
            // Los grupos y sus destinos se leen antes de crear el nivel 1: el
            // nombre editorial ("Movil/Tablet") no coincide con ninguna
            // categoria real, asi que la categoria se deduce de sus destinos.
            $lists = [];
            foreach (Database::select(
                'SELECT id, nombre, orden FROM websites_list WHERE id_websites_menu = :m ORDER BY orden ASC, id ASC',
                ['m' => (int) $menu['id']]
            ) as $list) {
                $list['items'] = Database::select(
                    'SELECT nombre, url, orden FROM websites_item WHERE id_websites_list = :l ORDER BY orden ASC, id ASC',
                    ['l' => (int) $list['id']]
                );
                $lists[] = $list;
            }

            $catRealId = self::resolveCategoryId((string) $menu['nombre'], $catIdBySlug)
                ?? self::dominantCategoryId($lists, $catIdBySlug);
            if ($catRealId === null) {
                $skipped[] = 'Categoria de nivel 1 sin equivalencia real: ' . $menu['nombre'];
                continue;
            }

            $catItemId = Database::insert('mt_menu_items', [
                'store_id'    => $storeId,
                'parent_id'   => null,
                'level'       => 1,
                'label'       => (string) $menu['nombre'],
                'slug'        => Str::slugify((string) $menu['nombre']),
                'target_type' => 'categoria',
                'target_id'   => $catRealId,
                'sort'        => ++$sortCat,
                'active'      => 1,
            ]);
            $created['categories']++;

            $sortGroup = 0;
            foreach ($lists as $list) {
                // Resolver primero los destinos para poder deducir el anclaje del grupo.
                $resueltos = [];
                foreach ($list['items'] as $item) {
                    $resueltos[] = self::resolveSeedTarget((string) $item['nombre'], (string) $item['url'], $catRealId, $catIdBySlug, $skipped);
                }

                $anchor = self::anchorOf($resueltos) ?? $catRealId;

                $groupItemId = Database::insert('mt_menu_items', [
                    'store_id'    => $storeId,
                    'parent_id'   => $catItemId,
                    'level'       => 2,
                    'label'       => (string) $list['nombre'],
                    'slug'        => Str::slugify((string) $list['nombre']),
                    'target_type' => 'subcategoria',
                    'target_id'   => $anchor,
                    'sort'        => ++$sortGroup,
                    'active'      => 1,
                ]);
                $created['groups']++;

                $sortLink = 0;
                foreach ($resueltos as $target) {
                    $sortLink++;
                    $isPending = $target['type'] === 'filtro' || !empty($target['note']);
                    Database::insert('mt_menu_items', [
                        'store_id'    => $storeId,
                        'parent_id'   => $groupItemId,
                        'level'       => 3,
                        'label'       => $target['label'],
                        'slug'        => null,
                        'target_type' => $target['type'],
                        'target_id'   => $target['id'],
                        'target_extra' => $target['extra'],
                        'target_key'  => $target['key'],
                        'url'         => $target['url'],
                        'sort'        => $sortLink,
                        'active'      => $isPending ? 0 : 1,
                        'note'        => $target['note'],
                    ]);
                    if (!$isPending) {
                        $created['links']++;
                    }
                }
            }
        }

        self::invalidate($storeId);

        $message = sprintf(
            'Menu de referencia copiado: %d categorias, %d grupos y %d destinos.',
            $created['categories'], $created['groups'], $created['links']
        );
        if ($skipped !== []) {
            $message .= ' ' . count($skipped) . ' destinos quedaron pendientes (ver avisos).';
        }

        return ['ok' => true, 'message' => $message, 'created' => $created, 'skipped' => $skipped];
    }

    /**
     * Resuelve un destino de la web de referencia.
     *
     * @return array{label:string,type:string,id:?int,extra:?int,key:?string,url:?string,note:?string}
     */
    private static function resolveSeedTarget(
        string $label,
        string $url,
        int $fallbackCategoryId,
        array $catIdBySlug,
        array &$skipped
    ): array {
        $base = ['label' => $label, 'type' => 'url', 'id' => null, 'extra' => null, 'key' => null, 'url' => null, 'note' => null];

        // Marca: /m/{id}
        if (preg_match('#/m/(\d+)#', $url, $m)) {
            $base['type'] = 'marca';
            $base['id'] = (int) $m[1];
            return $base;
        }

        // Filtro estructurado: /f/{id_filtro}-{id_subfiltro}
        if (preg_match('#/f/(\d+)(?:-(\d+))?#', $url, $m)) {
            $base['type'] = 'filtro';
            $base['id'] = (int) $m[1];
            $base['extra'] = isset($m[2]) ? (int) $m[2] : null;
            $base['note'] = 'Filtro del catalogo (rel_filtro_producto): pendiente de implementar.';
            $skipped[] = $label . ' (filtro ' . $m[1] . ')';
            return $base;
        }

        // Subcategoria: primer segmento = categoria del catalogo
        $segments = self::urlSegments($url);
        $catId = $fallbackCategoryId;
        if ($segments !== []) {
            $catId = $catIdBySlug[strtolower($segments[0])] ?? $fallbackCategoryId;
        }

        // 1) La URL puede traer el id de la subcategoria (/productos/s/128/...).
        if (preg_match('#/productos/s/(\d+)#', $url, $m) && self::subcategoryExists((int) $m[1])) {
            $base['type'] = 'subcategoria';
            $base['id'] = (int) $m[1];
            return $base;
        }

        // 2) Por nombre (el menu de referencia usa el nombre real o uno corto).
        $subId = self::resolveSubcategoryId($catId, $label);
        if ($subId !== null) {
            $base['type'] = 'subcategoria';
            $base['id'] = $subId;
            return $base;
        }

        // 3) Por el slug de la URL frente al de la subcategoria real.
        if (isset($segments[1])) {
            $subId = self::resolveSubcategoryBySlug($catId, (string) $segments[1]);
            if ($subId !== null) {
                $base['type'] = 'subcategoria';
                $base['id'] = $subId;
                return $base;
            }
        }

        $base['note'] = 'Destino no reconocido en el catalogo: se deja pendiente para revisarlo en el panel.';
        $skipped[] = $label;

        return $base;
    }

    /** ¿Existe esa subcategoria en el catalogo real? */
    private static function subcategoryExists(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return (bool) Database::scalar('SELECT 1 FROM subcategorias WHERE id = :id LIMIT 1', ['id' => $id]);
    }

    /** Subcategoria de una categoria a partir del slug de la URL de referencia. */
    private static function resolveSubcategoryBySlug(int $categoryId, string $slug): ?int
    {
        $slug = Str::slugify($slug);
        if ($slug === '' || $categoryId <= 0) {
            return null;
        }

        foreach (Database::select(
            'SELECT id, subcategoria FROM subcategorias WHERE id_categoria = :c',
            ['c' => $categoryId]
        ) as $row) {
            if (Str::slugify((string) $row['subcategoria']) === $slug) {
                return (int) $row['id'];
            }
        }

        return null;
    }

    /** Anclaje del grupo: la pareja categoria/subcategoria mas repetida. */
    private static function anchorOf(array $targets): ?int
    {
        $count = [];
        foreach ($targets as $target) {
            if ($target['type'] === 'subcategoria' && $target['id'] !== null) {
                $count[(int) $target['id']] = ($count[(int) $target['id']] ?? 0) + 1;
            }
        }
        if ($count === []) {
            return null;
        }
        arsort($count);

        return (int) array_key_first($count);
    }

    /** Segmentos utiles de la URL de referencia. */
    private static function urlSegments(string $url): array
    {
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host !== '' && !in_array(preg_replace('/^www\./', '', $host), self::SEED_HOSTS, true)) {
            return [];
        }
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '');
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn (string $s): bool => $s !== ''));

        return array_values(array_filter($segments, static fn (string $s): bool => !in_array($s, ['productos', 's'], true)));
    }

    /** Indice slug -> id de las categorias reales del catalogo. */
    private static function categoryIndex(): array
    {
        $index = [];
        $byName = [];
        foreach (Database::select('SELECT id, categoria FROM categorias ORDER BY orden ASC, id ASC') as $row) {
            $name = (string) $row['categoria'];
            $slug = Str::slugify($name);
            if ($slug !== '') {
                $index[$slug] ??= (int) $row['id'];
            }
            $normalized = self::normalize($name);
            if ($normalized !== '') {
                $index[$normalized] ??= (int) $row['id'];
                $byName[$normalized] ??= (int) $row['id'];
            }
        }

        // El menu de referencia usa sus propios slugs, que no siempre coinciden
        // con `Str::slugify()` del nombre real (p. ej. `telefon-a`). Se anaden
        // como alias apuntando a la categoria real por nombre.
        foreach (self::CATEGORY_ALIASES as $alias => $realName) {
            $normalized = self::normalize($realName);
            if (isset($byName[$normalized])) {
                $index[strtolower($alias)] = $byName[$normalized];
            }
        }

        return $index;
    }

    /** Id de la categoria real de un nivel 1 editorial. */
    private static function resolveCategoryId(string $label, array $index): ?int
    {
        $normalized = self::normalize($label);
        if ($normalized !== '' && isset($index[$normalized])) {
            return (int) $index[$normalized];
        }

        // El nombre editorial puede ser un grupo ("Movil/Tablet"): en ese caso lo
        // resuelve `dominantCategoryId()` a partir de sus destinos.
        return null;
    }

    /**
     * Categoria real mas repetida entre los destinos de un nivel 1 editorial.
     *
     * @param array<int,array{items:array}> $lists
     */
    private static function dominantCategoryId(array $lists, array $catIdBySlug): ?int
    {
        $count = [];
        foreach ($lists as $list) {
            foreach ((array) ($list['items'] ?? []) as $item) {
                $segments = self::urlSegments((string) $item['url']);
                if ($segments === []) {
                    continue;
                }
                $id = $catIdBySlug[strtolower($segments[0])] ?? null;
                if ($id !== null) {
                    $count[$id] = ($count[$id] ?? 0) + 1;
                }
            }
        }
        if ($count === []) {
            return null;
        }
        arsort($count);

        return (int) array_key_first($count);
    }

    /**
     * Busca la subcategoria de una categoria por su nombre.
     *
     * El menu de referencia acorta los nombres ("Smartphone" frente a
     * "Moviles/Smartphone"), asi que ademas de la igualdad exacta se acepta
     * contencion en cualquiera de los dos sentidos.
     */
    private static function resolveSubcategoryId(int $categoryId, string $label): ?int
    {
        $label = self::normalize($label);
        if ($label === '' || $categoryId <= 0) {
            return null;
        }

        $candidates = [];
        foreach (Database::select(
            'SELECT id, subcategoria FROM subcategorias WHERE id_categoria = :c ORDER BY orden ASC, id ASC',
            ['c' => $categoryId]
        ) as $row) {
            $candidates[] = ['id' => (int) $row['id'], 'name' => self::normalize((string) $row['subcategoria'])];
        }

        $best = null;
        foreach ($candidates as $candidate) {
            if ($candidate['name'] === $label) {
                return $candidate['id'];
            }
            if ($candidate['name'] === '') {
                continue;
            }
            if (str_contains($candidate['name'], $label) || str_contains($label, $candidate['name'])) {
                // Se prefiere el nombre mas parecido en longitud.
                $distance = abs(strlen($candidate['name']) - strlen($label));
                if ($best === null || $distance < $best['distance']) {
                    $best = ['id' => $candidate['id'], 'distance' => $distance];
                }
            }
        }

        return $best['id'] ?? null;
    }

    /** Texto comparable: minusculas, sin acentos y sin signos. */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false && $converted !== '') {
            $text = $converted;
        }

        return (string) preg_replace('/[^a-z0-9]+/', '', $text);
    }

    // =====================================================================
    // CACHE EN FICHERO
    // =====================================================================

    private static function cacheFile(string $key): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '_', $key);

        return TIENDA_BASE . '/storage/cache/menu_' . $safe . '.json';
    }

    /**
     * Ruta para un patron de cache (con comodines).
     *
     * Va aparte de `cacheFile()` porque aquel sanea los comodines (`*` -> `_`) y
     * un `glob()` con el patron saneado no encontraria ningun fichero.
     */
    private static function cachePattern(string $pattern): string
    {
        $safe = preg_replace('/[^a-z0-9_\-\*]/i', '_', $pattern);

        return TIENDA_BASE . '/storage/cache/menu_' . $safe . '.json';
    }

    private static function cached(string $key, int $ttl, callable $compute): mixed
    {
        $file = self::cacheFile($key);
        if (is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }

        $data = $compute();

        $dir = dirname($file);
        if (is_dir($dir) && is_writable($dir)) {
            @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        return $data;
    }

    /**
     * Juego de iconos que puede elegir la tienda.
     *
     * @return array<string,string> clave => etiqueta
     */
    public static function iconOptions(): array
    {
        return (array) Config::get('menu.icons', []);
    }

    /** Badges sugeridos: el texto es libre, esto son atajos del editor. */
    public static function badgePresets(): array
    {
        return (array) Config::get('menu.badge_presets', ['NUEVO', 'OFERTA', 'TOP']);
    }

    /** Modos de visibilidad por tienda. */
    public static function visibilityModes(): array
    {
        return (array) Config::get('menu.visibility_modes', [
            'todas'   => 'Todas las tiendas',
            'solo'    => 'Solo las tiendas marcadas',
            'excepto' => 'Todas menos las marcadas',
        ]);
    }
}
