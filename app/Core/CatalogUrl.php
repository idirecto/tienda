<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Models\Catalog;

/**
 * URLs SEO del catalogo, con el esquema de PuntoByZE.
 *
 *   /{categoria}
 *   /{categoria}/{subcategoria}
 *   /{categoria}/{subcategoria}/m/{id_marca}
 *   /{categoria}/{subcategoria}/f/{id_filtro}-{id_subfiltro}
 *   /{categoria}/etiqueta/{ofertas|novedades|destacados}
 *   /{categoria}/orden/{relevancia|precio-asc|precio-desc|nombre-asc|nombre-desc}
 *   /{categoria}/{subcategoria}/page/{n}
 *
 * Los segmentos se resuelven contra `categorias` y `subcategorias` (solo
 * lectura) y se cachean por peticion. Los facetas que todavia no tienen un
 * segmento propio (socket, memoria...) y el rango de precio viajan como query
 * string sobre la ruta SEO: la ruta manda, la query solo afina.
 *
 * Nada de este fichero escribe en la base de datos.
 */
final class CatalogUrl
{
    /** Palabras reservadas de la ruta: no pueden ser un slug de categoria. */
    private const KEYS = ['m', 'f', 'etiqueta', 'orden', 'page', 'q'];

    /** @var array<string,int>|null slug de categoria -> id */
    private static ?array $catBySlug = null;

    /** @var array<int,array{slug:string,category_id:int,category_slug:string}> */
    private static array $subCache = [];

    /** @var array<string,int>|null "catId:slug" -> subcategory id */
    private static ?array $subBySlug = null;

    // =====================================================================
    // CATALOGO (lectura y cache)
    // =====================================================================

    /** Indice de categorias: slug -> id y id -> slug. */
    private static function categories(): array
    {
        if (self::$catBySlug !== null) {
            return self::$catBySlug;
        }
        self::$catBySlug = [];
        if (!Database::tableExists('categorias')) {
            return self::$catBySlug;
        }
        foreach (Database::select('SELECT id, categoria FROM categorias ORDER BY orden ASC, id ASC') as $row) {
            $slug = Str::slugify((string) $row['categoria']);
            if ($slug === '' || in_array($slug, self::KEYS, true)) {
                continue;
            }
            self::$catBySlug[$slug] ??= (int) $row['id'];
        }
        return self::$catBySlug;
    }

    /** Indice de subcategorias: "idCategoria:slug" -> id. */
    private static function subcategories(): array
    {
        if (self::$subBySlug !== null) {
            return self::$subBySlug;
        }
        self::$subBySlug = [];
        if (!Database::tableExists('subcategorias')) {
            return self::$subBySlug;
        }
        foreach (Database::select('SELECT id, id_categoria, subcategoria FROM subcategorias ORDER BY orden ASC, id ASC') as $row) {
            $slug = Str::slugify((string) $row['subcategoria']);
            if ($slug === '' || in_array($slug, self::KEYS, true)) {
                continue;
            }
            self::$subBySlug[(int) $row['id_categoria'] . ':' . $slug] ??= (int) $row['id'];
        }
        return self::$subBySlug;
    }

    /** Slug publico de una categoria; null si no existe. */
    public static function categorySlug(int $categoryId): ?string
    {
        if ($categoryId <= 0) {
            return null;
        }
        $slug = array_search($categoryId, self::categories(), true);
        return $slug === false ? null : (string) $slug;
    }

    /** Ruta SEO de una categoria (`/ordenadores`). */
    public static function categoryPath(int $categoryId): ?string
    {
        $slug = self::categorySlug($categoryId);
        return $slug === null ? null : '/' . $slug;
    }

    /**
     * Datos de navegacion de una subcategoria (con su categoria real).
     *
     * @return array{id:int,slug:string,name:string,category_id:int,category_slug:string}|null
     */
    public static function subcategoryPath(int $subcategoryId): ?array
    {
        if ($subcategoryId <= 0 || !Database::tableExists('subcategorias')) {
            return null;
        }
        if (isset(self::$subCache[$subcategoryId])) {
            return self::$subCache[$subcategoryId];
        }
        $row = Database::first(
            'SELECT s.id, s.id_categoria, s.subcategoria, c.categoria
             FROM subcategorias s
             LEFT JOIN categorias c ON c.id = s.id_categoria
             WHERE s.id = :id LIMIT 1',
            ['id' => $subcategoryId]
        );
        if ($row === null) {
            return null;
        }
        $catId = (int) ($row['id_categoria'] ?? 0);
        $catSlug = self::categorySlug($catId);
        $slug = Str::slugify((string) $row['subcategoria']);
        if ($catSlug === null || $slug === '') {
            return null;
        }

        return self::$subCache[$subcategoryId] = [
            'id'            => (int) $row['id'],
            'slug'          => $slug,
            'name'          => (string) $row['subcategoria'],
            'category_id'   => $catId,
            'category_slug' => $catSlug,
        ];
    }

    /** Ruta SEO de una subcategoria (`/ordenadores/pc-sobremesa`). */
    public static function subPath(int $subcategoryId): ?string
    {
        $sub = self::subcategoryPath($subcategoryId);
        return $sub === null ? null : '/' . $sub['category_slug'] . '/' . $sub['slug'];
    }

    /** Id de categoria a partir de su slug. */
    public static function categoryIdBySlug(string $slug): ?int
    {
        $slug = strtolower(trim($slug));
        $all = self::categories();
        return $all[$slug] ?? null;
    }

    /** Id de subcategoria a partir del slug de su categoria y el suyo. */
    public static function subcategoryIdBySlug(int $categoryId, string $slug): ?int
    {
        $slug = strtolower(trim($slug));
        $all = self::subcategories();
        return $all[$categoryId . ':' . $slug] ?? null;
    }

    // =====================================================================
    // CONSTRUIR
    // =====================================================================

    /** Clave del facet de marca (dinamico) declarado en config/catalog.php. */
    public static function brandFacetKey(): string
    {
        return Catalog::brandFacetKey();
    }

    /** Etiquetas de listado declaradas en config/catalog.php. */
    public static function tags(): array
    {
        return (array) Config::get('catalog.tags', []);
    }

    public static function tagKey(?string $key): string
    {
        $key = $key === null ? '' : strtolower(trim($key));
        $tags = self::tags();
        return $key !== '' && isset($tags[$key]) ? $key : 'todos';
    }

    /**
     * Ruta SEO a partir del estado del listado.
     *
     * @param array $state cat, subcat, marca, etiqueta, orden, page
     */
    public static function build(array $state): string
    {
        $catId = (int) ($state['cat'] ?? 0);
        $subId = (int) ($state['subcat'] ?? 0);

        $path = null;
        if ($subId > 0) {
            $path = self::subPath($subId);
        }
        if ($path === null && $catId > 0) {
            $path = self::categoryPath($catId);
        }
        if ($path === null) {
            // Sin categoria no hay ruta jerarquica: se queda en /catalogo.
            return '/catalogo' . self::query(array_diff_key($state, ['cat' => 1, 'subcat' => 1]));
        }

        $extra = '';
        $brandId = (int) ($state['marca'] ?? 0);
        if ($brandId > 0) {
            $extra .= '/m/' . $brandId;
        }

        $tag = self::tagKey($state['etiqueta'] ?? null);
        if ($tag !== 'todos') {
            $extra .= '/etiqueta/' . rawurlencode($tag);
        }

        $orden = (string) ($state['orden'] ?? '');
        if ($orden !== '' && $orden !== 'relevancia') {
            $extra .= '/orden/' . rawurlencode($orden);
        }

        $page = (int) ($state['page'] ?? 1);
        if ($page > 1) {
            $extra .= '/page/' . $page;
        }

        return $path . $extra . self::query(array_diff_key($state, [
            'cat' => 1, 'subcat' => 1, 'marca' => 1, 'etiqueta' => 1, 'orden' => 1, 'page' => 1,
        ]));
    }

    /** Ruta SEO equivalente al estado de una query string de listado. */
    public static function fromQuery(array $get): string
    {
        $marca = 0;
        $facets = (array) ($get['f'] ?? []);
        $brandKey = self::brandFacetKey();
        foreach ((array) ($facets[$brandKey] ?? []) as $value) {
            $marca = (int) $value;
            if ($marca > 0) {
                break;
            }
        }
        // El resto de facetas no tienen segmento propio: viajan en la query.
        unset($facets[$brandKey]);

        $resto = [];
        if ($facets !== []) {
            $resto['f'] = $facets;
        }
        if (isset($get['q']) && trim((string) $get['q']) !== '') {
            $resto['q'] = (string) $get['q'];
        }
        foreach (['pmin', 'pmax'] as $key) {
            if (isset($get[$key]) && (string) $get[$key] !== '') {
                $resto[$key] = (string) $get[$key];
            }
        }

        return self::build([
            'cat'      => (int) ($get['cat'] ?? 0),
            'subcat'   => (int) ($get['subcat'] ?? 0),
            'marca'    => $marca,
            'etiqueta' => (string) ($get['etiqueta'] ?? ''),
            'orden'    => (string) ($get['orden'] ?? ''),
            'page'     => (int) ($get['page'] ?? 1),
        ] + $resto);
    }

    /** Query string de los valores que no caben en la ruta. */
    private static function query(array $params): string
    {
        $params = array_filter($params, static function ($value): bool {
            if (is_array($value)) {
                return $value !== [];
            }
            return $value !== null && $value !== '' && $value !== 0;
        });
        if ($params === []) {
            return '';
        }
        $query = http_build_query($params);
        return $query === '' ? '' : '?' . $query;
    }

    // =====================================================================
    // PARSEAR
    // =====================================================================

    /**
     * Convierte la ruta SEO en parametros equivalentes a la query string.
     *
     * @return array{cat:int,subcat:int,f:array,etiqueta:string,orden:string,page:int}|null
     *         null si la ruta no es un listado valido (404)
     */
    public static function parse(string $path): ?array
    {
        $path = trim(parse_url($path, PHP_URL_PATH) ?: '', '/');
        if ($path === '') {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));
        if ($segments === [] || count($segments) > 12) {
            return null;
        }

        $catId = self::categoryIdBySlug((string) array_shift($segments));
        if ($catId === null) {
            return null;
        }

        $state = [
            'cat'      => $catId,
            'subcat'   => 0,
            'f'        => [],
            'etiqueta' => 'todos',
            'orden'    => '',
            'page'     => 1,
        ];

        // Segundo segmento: subcategoria o primera palabra clave.
        if ($segments !== [] && !in_array((string) $segments[0], self::KEYS, true)) {
            $subId = self::subcategoryIdBySlug($catId, (string) $segments[0]);
            if ($subId === null) {
                return null;
            }
            $state['subcat'] = $subId;
            array_shift($segments);
        }

        while ($segments !== []) {
            $key = (string) array_shift($segments);
            $value = $segments === [] ? '' : (string) array_shift($segments);

            switch ($key) {
                case 'm':
                    $brandId = (int) $value;
                    if ($brandId <= 0) {
                        return null;
                    }
                    $state['f'][self::brandFacetKey()] = [$brandId];
                    break;

                case 'etiqueta':
                    $tag = self::tagKey($value);
                    if ($tag === 'todos' && strtolower($value) !== 'todos') {
                        return null;
                    }
                    $state['etiqueta'] = $tag;
                    break;

                case 'orden':
                    if (!isset(Catalog::sorts(false)[$value]) && !isset(Catalog::sorts(true)[$value])) {
                        return null;
                    }
                    $state['orden'] = $value;
                    break;

                case 'page':
                    $page = (int) $value;
                    if ($page < 1) {
                        return null;
                    }
                    $state['page'] = $page;
                    break;

                case 'f':
                    // Los filtros estructurados (rel_filtro_producto) todavia no
                    // estan implementados: se rechaza la ruta en vez de mostrar un
                    // listado que no corresponde.
                    return null;

                default:
                    return null;
            }
        }

        return $state;
    }

    /**
     * Ruta canonica de una peticion a /catalogo con query string.
     *
     * Solo se redirige cuando el listado se puede expresar como ruta SEO y no es
     * una busqueda (la busqueda se queda en /catalogo?q=).
     */
    public static function canonicalFromQuery(array $get): ?string
    {
        if (isset($get['q']) && trim((string) $get['q']) !== '') {
            return null;
        }

        $cat = (int) ($get['cat'] ?? 0);
        $sub = (int) ($get['subcat'] ?? 0);
        if ($cat <= 0 && $sub <= 0) {
            return null;
        }
        if ($sub > 0) {
            $info = self::subcategoryPath($sub);
            if ($info === null) {
                return null;
            }
            $cat = $info['category_id'];
        }

        return self::fromQuery($get);
    }
}
