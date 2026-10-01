<?php

declare(strict_types=1);

namespace Tienda\Models;

use Tienda\Core\Config;
use Tienda\Core\Database;
use Tienda\Core\Specs;
use Tienda\Core\Str;

/**
 * Lector del CATALOGO CENTRAL (tablas del mayorista).
 *
 * La tienda muestra el catalogo central y, ademas, sus productos propios
 * (tabla mt_own_products). Este modelo se ocupa del catalogo central.
 *
 * Reglas de visibilidad (mismas que idirecto):
 *   - `productos.estado <> 4`
 *   - existe stock valido: `stock.stock > 0`, `stock.activo = 1`,
 *     `stock.costo > 0` y el almacen no es de tipo 2
 *   - se excluye la subcategoria 178
 *
 * Es defensivo: si las tablas centrales no existen o el catalogo esta
 * desactivado, devuelve listados vacios sin romper el storefront.
 */
final class Catalog
{
    /** Subcategoria excluida del catalogo publico (misma que idirecto). */
    private const SUBCATEGORIA_EXCLUIDA = 178;

    /** TTL de la cache del contador total (segundos). */
    private const COUNT_TTL = 300;

    /** TTL de la cache del arbol de categorias del menu (segundos). */
    private const MENU_TTL = 1800;

    /**
     * Tablas del catalogo central que se consultan siempre (listado, filtros,
     * precios, stock y taxonomia). Si falta cualquiera, el catalogo se trata
     * como no disponible: mejor listados vacios que un error 500 (p. ej. con
     * una base de datos a medio restaurar).
     */
    private const REQUIRED_TABLES = [
        'productos', 'stock', 'almacenes', 'precios', 'marcas', 'categorias', 'subcategorias',
    ];

    private static ?bool $available = null;

    public static function enabled(): bool
    {
        return (bool) Config::get('catalog.enabled', true);
    }

    public static function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }
        if (!self::enabled()) {
            return self::$available = false;
        }

        foreach (self::REQUIRED_TABLES as $table) {
            if (!Database::tableExists($table)) {
                return self::$available = false;
            }
        }

        return self::$available = true;
    }

    /**
     * Fragmento SQL: el producto tiene stock valido en algun almacen.
     * Se usa EXISTS para no duplicar filas por almacen.
     */
    private static function stockExistsSql(): string
    {
        return 'EXISTS (
                    SELECT 1 FROM stock s
                    INNER JOIN almacenes a ON a.id = s.id_almacen
                    WHERE s.part_number = p.part_number
                      AND s.stock > 0
                      AND s.activo = 1
                      AND s.costo > 0
                      AND a.tipo <> 2
                )';
    }

    /** Coste minimo entre los almacenes con stock valido. */
    private static function costoSql(): string
    {
        return '(SELECT MIN(s.costo) FROM stock s
                  INNER JOIN almacenes a ON a.id = s.id_almacen
                  WHERE s.part_number = p.part_number
                    AND s.stock > 0 AND s.activo = 1 AND s.costo > 0
                    AND a.tipo <> 2)';
    }

    /** Precio de tarifa mas bajo disponible. */
    private static function precioSql(): string
    {
        return '(SELECT MIN(pr.precio) FROM precios pr
                  WHERE pr.id_producto = p.id AND pr.precio > 0)';
    }

    /** Columnas comunes de listado. */
    private static function selectColumns(): string
    {
        return 'p.id, p.nombre, p.part_number, p.img_name, p.id_categoria,
                p.id_subcategoria, p.id_marca, p.impuestos,
                COALESCE(m.marca, p.marca) AS marca_nombre,
                ' . self::precioSql() . ' AS precio,
                ' . self::costoSql() . ' AS costo,
                ' . self::stockExistsSql() . ' AS in_stock';
    }

    /**
     * Listado paginado de productos centrales con stock.
     *
     * @return array{items:array,total:int,page:int,per_page:int,pages:int}
     */
    public static function paginate(int $page = 1, ?int $perPage = null, ?string $q = null, ?int $categoryId = null, ?int $subcategoryId = null): array
    {
        $perPage = $perPage ?? (int) Config::get('catalog.per_page', 12);
        $page = max(1, $page);

        if (!self::isAvailable()) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 0];
        }

        [$whereSql, $params] = self::buildFilters($q, $categoryId, $subcategoryId);

        $total = self::cachedCount('page:' . md5($whereSql . serialize($params)), static function () use ($whereSql, $params): int {
            return (int) Database::scalar(
                "SELECT COUNT(*) FROM productos p
                 LEFT JOIN marcas m ON m.id = p.id_marca
                 $whereSql",
                $params
            );
        });

        $offset = ($page - 1) * $perPage;

        $items = Database::select(
            'SELECT ' . self::selectColumns() . "
             FROM productos p
             LEFT JOIN marcas m ON m.id = p.id_marca
             $whereSql
             ORDER BY p.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        );

        foreach ($items as &$item) {
            $item = self::decorate($item);
        }
        unset($item);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * Productos destacados (portada). Solo con stock.
     */
    public static function featured(int $limit = 8): array
    {
        if (!self::isAvailable()) {
            return [];
        }
        $limit = max(1, min(48, $limit));

        $items = Database::select(
            'SELECT ' . self::selectColumns() . "
             FROM productos p
             LEFT JOIN marcas m ON m.id = p.id_marca
             WHERE " . self::baseConditions() . '
             ORDER BY p.id DESC
             LIMIT ' . $limit
        );

        foreach ($items as &$item) {
            $item = self::decorate($item);
        }
        unset($item);
        return $items;
    }

    /**
     * Ficha de un producto. NO exige stock (la ficha sigue existiendo aunque se
     * agote), pero informa de su disponibilidad en `in_stock`.
     */
    public static function find(int $id): ?array
    {
        if (!self::isAvailable()) {
            return null;
        }

        $row = Database::first(
            'SELECT p.*, COALESCE(m.marca, p.marca) AS marca_nombre,
                    ' . self::precioSql() . ' AS precio,
                    ' . self::costoSql() . ' AS costo,
                    ' . self::stockExistsSql() . ' AS in_stock
             FROM productos p
             LEFT JOIN marcas m ON m.id = p.id_marca
             WHERE p.id = :id AND p.estado <> 4
             LIMIT 1',
            ['id' => $id]
        );

        return $row === null ? null : self::decorate($row);
    }

    /**
     * Arbol de categorias y subcategorias para el menu del catalogo
     * (inspirado en el megamenu de puntobyze).
     *
     * Solo incluye categorias y subcategorias que tienen productos con stock,
     * con el mismo criterio que el listado, para no enlazar a paginas vacias.
     * La consulta recorre `stock` y es costosa, asi que se cachea en fichero
     * (se refresca sola cada MENU_TTL segundos).
     *
     * @return array<int, array{id:int,name:string,total:int,subcategories:array<int,array{id:int,name:string,total:int}>}>
     */
    public static function menuTree(): array
    {
        if (!self::isAvailable()
            || !Database::tableExists('categorias')
            || !Database::tableExists('subcategorias')) {
            return [];
        }

        $dir = TIENDA_BASE . '/storage/cache';
        $file = $dir . '/catalog_menu.json';

        if (is_file($file) && (time() - (int) filemtime($file)) < self::MENU_TTL) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                return $data;
            }
        }

        $tree = self::buildMenuTree();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode($tree, JSON_UNESCAPED_UNICODE));
        // Escribible por web y por consola (mismo criterio que el contador).
        @chmod($file, 0664);

        return $tree;
    }

    /**
     * Datos minimos de una subcategoria: se usan para el filtro `?subcat=`,
     * el titulo de la pagina y las migas de pan.
     *
     * @return array{id:int,categoria_id:int,name:string}|null
     */
    public static function subcategory(int $id): ?array
    {
        if ($id <= 0 || !self::isAvailable() || !Database::tableExists('subcategorias')) {
            return null;
        }

        $row = Database::first(
            'SELECT id, id_categoria, subcategoria FROM subcategorias WHERE id = :id LIMIT 1',
            ['id' => $id]
        );

        if ($row === null) {
            return null;
        }

        return [
            'id'           => (int) $row['id'],
            'categoria_id' => (int) $row['id_categoria'],
            'name'         => self::menuLabel((string) $row['subcategoria']),
        ];
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    /** Condiciones base de visibilidad del catalogo publico. */
    private static function baseConditions(): string
    {
        return 'p.estado <> 4
                AND p.id_subcategoria <> ' . self::SUBCATEGORIA_EXCLUIDA . '
                AND ' . self::stockExistsSql();
    }

    /**
     * Construye el WHERE y sus parametros.
     * @return array{0:string,1:array}
     */
    private static function buildFilters(?string $q, ?int $categoryId, ?int $subcategoryId = null): array
    {
        $where = [self::baseConditions()];
        $params = [];

        if ($q !== null && $q !== '') {
            // Con PDO::ATTR_EMULATE_PREPARES = false un parametro nombrado no
            // puede repetirse: se usan marcadores distintos.
            $where[] = '(p.nombre LIKE :q1 OR p.part_number LIKE :q2 OR p.marca LIKE :q3 OR m.marca LIKE :q4)';
            $like = '%' . $q . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $where[] = 'p.id_categoria = :cat';
            $params['cat'] = $categoryId;
        }

        if ($subcategoryId !== null && $subcategoryId > 0) {
            $where[] = 'p.id_subcategoria = :subcat';
            $params['subcat'] = $subcategoryId;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    /** Cache en fichero del contador total (evita un COUNT pesado por peticion). */
    private static function cachedCount(string $key, callable $compute): int
    {
        $dir = TIENDA_BASE . '/storage/cache';
        $file = $dir . '/catalog_count_' . sha1($key) . '.json';

        if (is_file($file) && (time() - (int) filemtime($file)) < self::COUNT_TTL) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && isset($data['total'])) {
                return (int) $data['total'];
            }
        }

        $total = $compute();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode(['total' => $total, 'ts' => time()]));
        // Que el fichero sea escribible tanto por el servidor web como por CLI
        // (evita que un fichero creado por www-data bloquee la escritura por consola).
        @chmod($file, 0664);

        return $total;
    }

    /**
     * Construye el arbol de categorias -> subcategorias con stock.
     * Solo entran las subcategorias que aparecen en el listado publico.
     *
     * @return array<int, array{id:int,name:string,total:int,subcategories:array}>
     */
    private static function buildMenuTree(): array
    {
        $rows = Database::select(
            'SELECT p.id_categoria AS categoria_id,
                    p.id_subcategoria AS subcategoria_id,
                    COUNT(*) AS total
             FROM productos p
             WHERE p.estado <> 4
               AND p.id_subcategoria <> ' . self::SUBCATEGORIA_EXCLUIDA . '
               AND ' . self::stockExistsSql() . '
             GROUP BY p.id_categoria, p.id_subcategoria'
        );

        $totalPorPar = [];
        foreach ($rows as $r) {
            // Clave categoria:subcategoria: el enlace del menu lleva los dos
            // valores, asi que solo sirve la pareja real (en el catalogo del
            // mayorista hay productos cuya subcategoria apunta a otra categoria).
            $totalPorPar[(int) $r['categoria_id'] . ':' . (int) $r['subcategoria_id']] = (int) $r['total'];
        }

        if ($totalPorPar === []) {
            return [];
        }

        // Subcategorias con stock agrupadas por categoria, en el orden del mayorista.
        $porCategoria = [];
        foreach (Database::select('SELECT id, id_categoria, subcategoria FROM subcategorias ORDER BY orden ASC, subcategoria ASC') as $s) {
            $catId = (int) $s['id_categoria'];
            $subId = (int) $s['id'];
            $clave = $catId . ':' . $subId;

            if (!isset($totalPorPar[$clave])) {
                continue;
            }

            $porCategoria[$catId][] = [
                'id'    => $subId,
                'name'  => self::menuLabel((string) $s['subcategoria']),
                'total' => $totalPorPar[$clave],
            ];
        }

        $tree = [];
        foreach (Database::select('SELECT id, categoria FROM categorias ORDER BY orden ASC, categoria ASC') as $c) {
            $catId = (int) $c['id'];
            if (empty($porCategoria[$catId])) {
                continue;
            }

            $total = 0;
            foreach ($porCategoria[$catId] as $sub) {
                $total += $sub['total'];
            }

            $tree[] = [
                'id'            => $catId,
                'name'          => self::menuLabel((string) $c['categoria']),
                'total'         => $total,
                'subcategories' => $porCategoria[$catId],
            ];
        }

        return $tree;
    }

    /**
     * Etiqueta legible para el menu. El mayorista escribe los nombres de forma
     * irregular ("ORDENADORES", " TELEFONÍA", "Ocio"); se unifican a Mayuscula
     * inicial respetando acronimos cortos (TPV, USB) y las palabras cortas
     * ("y", "de", "la"...).
     */
    private static function menuLabel(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        if ($name === '' || $name !== mb_strtoupper($name)) {
            return $name; // Ya viene en minusculas o mixto: se respeta tal cual.
        }

        $minusculas = ['a', 'al', 'con', 'de', 'del', 'el', 'en', 'la', 'las', 'los', 'o', 'para', 'por', 'u', 'y'];
        $partes = explode(' ', mb_strtolower($name));

        foreach ($partes as $i => $p) {
            if ($i > 0 && in_array($p, $minusculas, true)) {
                continue; // Se queda en minuscula.
            }
            // Acronimos de hasta 3 letras (TPV, USB, IP) se mantienen en mayusculas.
            $partes[$i] = (mb_strlen($p) <= 3) ? mb_strtoupper($p) : mb_convert_case($p, MB_CASE_TITLE);
        }

        return implode(' ', $partes);
    }

    /** Completa cada producto con slug, URL SEO, imagen y precio final. */
    public static function decorate(array $item): array
    {
        $nombre = (string) ($item['nombre'] ?? '');

        // Slug SEO (mismo criterio que idirecto)
        $item['slug'] = Str::slugify($nombre);

        // Imagen de tarjeta para los listados. `img_name` es una plantilla tipo
        // "<slug>-%d-%s" donde %d = id y %s = tamano:
        //   c ~3 KB (miniatura) | l ~7 KB (listados) | f ~38 KB (ficha) | g ~4 MB (zoom)
        // El tamano de los listados se configura con CATALOG_CARD_IMAGE_SIZE.
        $base = rtrim((string) Config::get('catalog.image_url', ''), '/');
        $img = (string) ($item['img_name'] ?? '');
        $size = (string) Config::get('catalog.card_image_size', 'l');
        $file = $img !== '' ? self::applyImagePattern($img, (int) ($item['id'] ?? 0), $size) : null;
        $item['image_url'] = ($base !== '' && $file !== null) ? $base . '/' . $file . '_1.jpg' : null;

        $markup = (float) Config::get('catalog.markup', 0);
        $precio = $item['precio'] ?? null;
        $costo = $item['costo'] ?? null;

        if ($precio !== null && (float) $precio > 0) {
            $final = (float) $precio;
        } elseif ($costo !== null && (float) $costo > 0) {
            $final = (float) $costo * (1 + $markup / 100);
        } else {
            $final = null;
        }
        $item['price_final'] = $final;

        // Disponibilidad
        $item['in_stock'] = (bool) ($item['in_stock'] ?? false);

        return $item;
    }

    // =====================================================================
    // FICHA DE PRODUCTO
    // =====================================================================

    /**
     * Datos completos para la ficha de producto, con la misma informacion que
     * muestra idirecto: breadcrumb, galeria, especificaciones, descripcion,
     * disponibilidad y resenas.
     *
     * @return array|null
     */
    public static function detail(int $id): ?array
    {
        $product = self::find($id);
        if ($product === null) {
            return null;
        }

        // Categoria / subcategoria (breadcrumb)
        $product['categoria'] = (string) (Database::scalar(
            'SELECT categoria FROM categorias WHERE id = :id',
            ['id' => (int) $product['id_categoria']]
        ) ?? '');

        $product['subcategoria'] = (string) (Database::scalar(
            'SELECT subcategoria FROM subcategorias WHERE id = :id',
            ['id' => (int) $product['id_subcategoria']]
        ) ?? '');

        // Contenido ampliado del producto
        $ext = [];
        if (Database::tableExists('productos_ext')) {
            $ext = Database::first(
                'SELECT razones, caracteristicas, especificaciones, brand_story, contenido_enriquecido
                 FROM productos_ext WHERE id_producto = :id LIMIT 1',
                ['id' => $id]
            ) ?? [];
        }
        $product['razones'] = (string) ($ext['razones'] ?? '');
        $product['caracteristicas'] = (string) ($ext['caracteristicas'] ?? '');
        $product['especificaciones'] = (string) ($ext['especificaciones'] ?? '');
        $product['contenido_enriquecido'] = (string) ($ext['contenido_enriquecido'] ?? '');

        // Brand story de la marca (si el producto no trae la suya)
        $product['brand_story'] = (string) ($ext['brand_story'] ?? '');
        if ($product['brand_story'] === '' && Database::tableExists('marcas')) {
            $product['brand_story'] = (string) (Database::scalar(
                'SELECT brand_story FROM marcas WHERE id = :id',
                ['id' => (int) $product['id_marca']]
            ) ?? '');
        }

        // Descripcion: puntos a partir de `caracteristicas` (cadena corta)
        $product['bullets'] = Specs::bullets($product['caracteristicas'], 6);

        // Especificaciones: grupos parseados del HTML de `especificaciones`
        // (mismo criterio que idirecto). Si no hay HTML, se usa `caracteristicas`.
        $product['spec_groups'] = Specs::groupsFromHtml($product['especificaciones']);
        if ($product['spec_groups'] === []) {
            $fallback = Specs::pairs($product['caracteristicas']);
            if ($fallback !== []) {
                $product['spec_groups'] = [['name' => 'Especificaciones', 'rows' => $fallback]];
            }
        }

        // Resumen corto + valores para "De un vistazo"
        $product['spec_summary'] = Specs::summary($product['spec_groups'], $product);
        $product['highlights'] = Specs::highlights($product['spec_summary']);
        $product['spec_count'] = array_sum(array_map(
            static fn (array $g) => count($g['rows']),
            $product['spec_groups']
        ));

        // Galeria
        $product['images'] = self::gallery($product);

        // Disponibilidad real
        $product['stock_total'] = (int) (Database::scalar(
            'SELECT COALESCE(SUM(s.stock), 0) FROM stock s
             INNER JOIN almacenes a ON a.id = s.id_almacen
             WHERE s.part_number = :pn AND s.stock > 0 AND s.activo = 1 AND s.costo > 0
               AND a.tipo <> 2',
            ['pn' => (string) $product['part_number']]
        ) ?? 0);

        // Resenas de clientes
        $product['reviews'] = self::reviews($id);

        return $product;
    }

    /**
     * Galeria de imagenes. Reutiliza el esquema de nombres de idirecto:
     *   sprintf(img_name, id, tamano) . '_N.jpg'
     *
     * Tamanos reales en el servidor del mayorista (medidos):
     *   c = miniatura (~3 KB)   l = pequena (~7 KB)   f = grande (~38 KB)
     *   g = gigante (~4 MB, para el zoom)             o = original (~12 MB)
     *
     * idirecto usa `f` en el carrusel y `c` en las miniaturas; aqui igual, y se
     * anade `g` solo para la vista ampliada (se carga bajo demanda).
     *
     * Si se configura `catalog.image_path` se comprueba que el fichero exista
     * (util en local); si no, se asumen hasta 5 imagenes.
     *
     * @return array<int, array{thumb:string, large:string, zoom:string}>
     */
    private static function gallery(array $product): array
    {
        $pattern = (string) ($product['img_name'] ?? '');
        $id = (int) ($product['id'] ?? 0);
        $baseUrl = rtrim((string) Config::get('catalog.image_url', ''), '/');
        $basePath = rtrim((string) Config::get('catalog.image_path', ''), '/');

        if ($pattern === '' || $baseUrl === '') {
            return [];
        }

        $build = static function (string $size, int $n) use ($pattern, $id): ?string {
            $name = self::applyImagePattern($pattern, $id, $size);
            return $name === null ? null : $name . '_' . $n . '.jpg';
        };

        $images = [];
        for ($i = 1; $i <= 5; $i++) {
            $large = $build('f', $i);
            if ($large === null) {
                break;
            }

            // La comprobacion de existencia se hace con la variante de miniatura
            // (igual que idirecto) porque pesa mucho menos.
            $probe = $build('c', $i) ?? $large;
            if ($basePath !== '' && !is_file($basePath . '/' . $probe)) {
                continue;
            }

            $images[] = [
                'thumb' => $baseUrl . '/' . $probe,
                'large' => $baseUrl . '/' . $large,
                'zoom'  => $baseUrl . '/' . ($build('g', $i) ?? $large),
            ];
        }

        return $images;
    }

    /** Sustituye los marcadores %d/%s del nombre de imagen (esquema idirecto). */
    private static function applyImagePattern(string $pattern, int $id, string $size): ?string
    {
        if (substr_count($pattern, '%') < 2) {
            return $pattern;
        }
        try {
            return sprintf($pattern, $id, $size);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Resenas activas del producto.
     *
     * @return array<int, array>
     */
    private static function reviews(int $id): array
    {
        if (!Database::tableExists('productos_resenas')) {
            return [];
        }
        return Database::select(
            'SELECT autor_nombre, puntuacion, titulo, comentario, fecha_resena
             FROM productos_resenas
             WHERE id_producto = :id AND activa = 1
             ORDER BY puntuacion DESC, fecha_resena DESC
             LIMIT 10',
            ['id' => $id]
        );
    }
}
