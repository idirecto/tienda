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
 *   - tiene stock valido: `stock.stock > 0`, `stock.activo = 1`,
 *     `stock.costo > 0` y el almacen no es de tipo 2
 *   - se excluye la subcategoria 178
 *
 * Rendimiento: la condicion de stock se expresa como semijoin
 * (`part_number IN (subconsulta)`) en lugar de `EXISTS` correlacionado. Es la
 * misma regla, pero MySQL puede materializar la subconsulta una sola vez: en
 * esta base de datos el listado pasa de ~3 s a milisegundos. La marca se
 * resuelve despues, por lote, para no arrastrar un JOIN a `marcas` por fila.
 *
 * Es defensivo: si las tablas centrales no existen o el catalogo esta
 * desactivado, devuelve listados vacios sin romper el storefront.
 */
final class Catalog
{
    /** Subcategoria excluida del catalogo publico (misma que idirecto). */
    private const SUBCATEGORIA_EXCLUIDA = 178;

    /**
     * TTL de la cache del contador total (segundos).
     *
     * Contar los productos con stock de TODO el catalogo cuesta ~1 s en esta
     * base de datos (el contador recorre `productos` comprobando `stock`), asi
     * que se guarda 10 minutos: es un dato de cabecera ("41.289 productos"),
     * no algo que deba estar al segundo.
     */
    private const COUNT_TTL = 600;

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

    /** Facets normalizados de la peticion (se cachea por peticion). */
    private static ?array $facetDefinitions = null;

    // -------------------------------------------------------------------------
    // PRECIO DE VENTA DE LA TIENDA
    //
    // El catalogo central guarda la tarifa del mayorista (`precios.precio`), que
    // es un precio SIN IVA. Lo que paga el cliente final lo decide cada tienda:
    //
    //     precio de venta = tarifa de la tienda (id_margen)
    //                       + beneficio de la tienda (mt_stores.markup)
    //                       [+ IVA, si se muestra con IVA incluido]
    //
    // El contexto se fija una vez por peticion con `forStore()`:
    //   - Storefront: con IVA incluido (es lo que paga el cliente).
    //   - Panel: sin IVA (es la base que se guarda en el pedido; el IVA se suma
    //     al calcular los totales).
    // Sin contexto (herramientas, chequeos) se usa la tarifa por defecto y sin
    // beneficio, que es como se comportaba el catalogo antes de la tienda.
    // -------------------------------------------------------------------------

    private static ?int $pricingMargin = null;
    private static float $pricingMarkup = 0.0;
    private static float $pricingTax = 21.0;
    private static bool $pricingWithTax = false;
    private static bool $pricingSet = false;

    /**
     * Fija el precio de venta de la tienda para esta peticion.
     *
     * @param int|null $idMargen Tarifa de la tienda en el mayorista (precios.id_margen)
     * @param mixed    $markup   Beneficio de la tienda en % (columna mt_stores.markup)
     * @param float    $tax      IVA por defecto (%) para productos sin tipo propio
     * @param bool     $withTax  true = los precios llevan el IVA incluido
     */
    public static function forStore(?int $idMargen, mixed $markup = 0, float $tax = 21.0, bool $withTax = false): void
    {
        self::$pricingMargin = $idMargen !== null && $idMargen > 0 ? $idMargen : null;
        self::$pricingMarkup = max(0.0, min(200.0, (float) $markup));
        self::$pricingTax = $tax > 0 ? $tax : 21.0;
        self::$pricingWithTax = $withTax;
        self::$pricingSet = true;
    }

    /** Hay contexto de tienda fijado en esta peticion. */
    public static function pricingConfigured(): bool
    {
        return self::$pricingSet;
    }

    /** Beneficio aplicado (1 = sin beneficio). */
    public static function markupFactor(): float
    {
        return 1 + self::$pricingMarkup / 100;
    }

    /** Factor para pasar de base sin IVA a precio con IVA. */
    public static function taxFactor(?float $taxRate = null): float
    {
        $rate = $taxRate !== null && $taxRate > 0 ? $taxRate : self::$pricingTax;
        return 1 + $rate / 100;
    }

    /** Tarifa de la tienda con la que se esta calculando (null = la mas barata). */
    public static function pricingMargin(): ?int
    {
        return self::$pricingMargin;
    }

    /**
     * Precio de venta a partir del COSTE del mayorista.
     *
     * Solo se usa cuando el producto no tiene tarifa publicada para la tarifa de
     * la tienda: entonces el precio de venta se calcula desde el coste con el
     * beneficio de la tienda (y el IVA, si toca).
     */
    public static function salePriceFromCost(float $cost, ?float $taxRate = null): float
    {
        $value = $cost * self::markupFactor();
        if (self::$pricingWithTax) {
            $value *= self::taxFactor($taxRate);
        }

        return round($value, 2);
    }

    /**
     * Base sin IVA que se guarda en la linea del pedido a partir del precio de
     * venta que muestra la web (que ya lleva el beneficio).
     */
    public static function netFromSale(float $salePrice, ?float $taxRate = null): float
    {
        $value = self::$pricingWithTax ? $salePrice / self::taxFactor($taxRate) : $salePrice;

        return round($value, 2);
    }

    /** Precio de venta mostrado (con IVA, si es la web) a partir de la base. */
    public static function saleFromNet(float $net, ?float $taxRate = null): float
    {
        $value = self::$pricingWithTax ? $net * self::taxFactor($taxRate) : $net;

        return round($value, 2);
    }

    /** Clave corta del contexto de precios, para las caches. */
    private static function pricingKey(): string
    {
        return 'p' . (self::$pricingMargin ?? 0) . 'm' . number_format(self::$pricingMarkup, 2, '', '')
            . (self::$pricingWithTax ? 't' . number_format(self::$pricingTax, 2, '', '') : 'n');
    }

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
     * Olvida las caches que dependen del precio de venta de la tienda.
     *
     * Los destacados y el rango de precios van cacheados por tienda (tarifa +
     * beneficio); se llama al guardar los ajustes de venta para que el cambio de
     * beneficio se vea al momento y no cuando caduque la cache.
     */
    public static function forgetPriceCache(): int
    {
        $borrados = 0;
        foreach (glob(TIENDA_BASE . '/storage/cache/catalog_featured_*.json') ?: [] as $file) {
            if (@unlink($file)) {
                $borrados++;
            }
        }
        foreach (glob(TIENDA_BASE . '/storage/cache/catalog_price_bounds_*.json') ?: [] as $file) {
            if (@unlink($file)) {
                $borrados++;
            }
        }

        return $borrados;
    }

    // =====================================================================
    // LISTADOS
    // =====================================================================

    /**
     * Fragmento SQL: el producto tiene stock valido en algun almacen.
     *
     * Se usa un semijoin (`IN`) y no un `EXISTS` correlacionado a proposito: la
     * subconsulta no depende de la fila exterior, asi que el optimizador la
     * resuelve una vez. Mismo resultado, mucho mas rapido.
     */
    private static function stockExistsSql(): string
    {
        return 'p.part_number IN (
                    SELECT s.part_number FROM stock s
                    INNER JOIN almacenes a ON a.id = s.id_almacen
                    WHERE s.stock > 0
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

    /**
     * Precio de VENTA del producto (el que ve el cliente de la tienda).
     *
     * Parte de la tarifa de la tienda (`precios.id_margen`); si la tienda no
     * tiene tarifa asignada se usa la mas barata publicada (como antes). Encima
     * se aplica el beneficio de la tienda y, cuando el contexto es de tienda
     * publica, el IVA del producto. La tarifa se incrusta como numero (es un
     * entero de configuracion) porque con `EMULATE_PREPARES = false` un
     * parametro nombrado no puede repetirse en la misma consulta.
     */
    private static function precioSql(): string
    {
        $margen = self::$pricingMargin;
        $sql = '(SELECT MIN(pr.precio) FROM precios pr
                  WHERE pr.id_producto = p.id AND pr.precio > 0'
            . ($margen !== null ? ' AND pr.id_margen = ' . (int) $margen : '')
            . ')';

        $factor = self::markupFactor();
        if ($factor !== 1.0) {
            $sql = 'ROUND((' . $sql . ') * ' . number_format($factor, 4, '.', '') . ', 2)';
        }
        if (self::$pricingWithTax) {
            $sql = 'ROUND((' . $sql . ') * (1 + COALESCE(NULLIF(p.impuestos, 0), '
                . number_format(self::$pricingTax, 2, '.', '') . ') / 100), 2)';
        }

        return $sql;
    }

    /** Columnas comunes de listado (sin JOIN: la marca se resuelve por lote). */
    private static function selectColumns(): string
    {
        return 'p.id, p.nombre, p.part_number, p.img_name, p.id_categoria,
                p.id_subcategoria, p.id_marca, p.impuestos, p.fecha_alta,
                p.marca AS marca_col,
                ' . self::precioSql() . ' AS precio,
                ' . self::costoSql() . ' AS costo,
                ' . self::stockExistsSql() . ' AS in_stock';
    }

    /**
     * Listado paginado de productos centrales con stock.
     *
     * @param array<int, array{key:string, value:string}> $facetSelection Facets activos
     * @return array{items:array,total:int,page:int,per_page:int,pages:int}
     */
    public static function paginate(
        int $page = 1,
        ?int $perPage = null,
        ?string $q = null,
        ?int $categoryId = null,
        ?int $subcategoryId = null,
        array $facetSelection = [],
        ?float $priceMin = null,
        ?float $priceMax = null,
        string $sort = 'relevance',
        string $tag = 'todos'
    ): array {
        $perPage = $perPage ?? (int) Config::get('catalog.per_page', 12);
        $perPage = max(1, min(60, $perPage));
        $page = max(1, $page);

        if (!self::isAvailable()) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage, 'pages' => 0];
        }

        [$whereSql, $params] = self::buildFilters($q, $categoryId, $subcategoryId, $facetSelection, $priceMin, $priceMax, $tag);
        $orderSql = self::orderSql($sort);

        $total = self::cachedCount(
            'page:' . md5($whereSql . serialize($params)),
            static fn (): int => (int) Database::scalar("SELECT COUNT(*) FROM productos p $whereSql", $params)
        );

        $offset = ($page - 1) * $perPage;

        $items = Database::select(
            'SELECT ' . self::selectColumns() . "
             FROM productos p
             $whereSql
             $orderSql
             LIMIT $perPage OFFSET $offset",
            $params
        );

        self::hydrate($items);

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
     *
     * La seleccion se cachea 10 minutos: es la misma para todos los visitantes
     * (no depende de la tienda) y evita repetir en la portada un recorrido
     * pesado del catalogo cuando el servidor de base de datos esta frio.
     */
    public static function featured(int $limit = 8, ?int $categoryId = null): array
    {
        if (!self::isAvailable()) {
            return [];
        }
        $limit = max(1, min(48, $limit));
        $categoryId = $categoryId !== null && $categoryId > 0 ? $categoryId : null;

        $items = self::cached(
            'featured_' . ($categoryId ?? 0) . '_' . $limit . '_' . self::pricingKey(),
            600,
            static function () use ($limit, $categoryId): array {
                $where = self::baseConditions();
                $params = [];
                if ($categoryId !== null) {
                    $where .= ' AND p.id_categoria = :cat';
                    $params['cat'] = $categoryId;
                }

                $rows = Database::select(
                    'SELECT ' . self::selectColumns() . "
                     FROM productos p
                     WHERE $where
                     ORDER BY p.id DESC
                     LIMIT " . $limit,
                    $params
                );

                self::hydrate($rows);
                return $rows;
            }
        );

        return (array) $items;
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
            'SELECT p.*, p.marca AS marca_col,
                    ' . self::precioSql() . ' AS precio,
                    ' . self::costoSql() . ' AS costo,
                    ' . self::stockExistsSql() . ' AS in_stock
             FROM productos p
             WHERE p.id = :id AND p.estado <> 4
             LIMIT 1',
            ['id' => $id]
        );

        return $row === null ? null : self::decorate($row);
    }

    /**
     * Busqueda ligera del catalogo para el selector de productos del panel
     * (pedidos). Devuelve solo lo que necesita el autocompletado: id, nombre,
     * referencia, precio e IVA, sin hidratar ni pintar tarjetas.
     *
     * @return array<int,array{id:int,nombre:string,sku:string,marca:string,precio:?float,impuestos:float}>
     */
    public static function search(string $q, int $limit = 8): array
    {
        $q = trim($q);
        if ($q === '' || !self::isAvailable()) {
            return [];
        }
        $limit = max(1, min(20, $limit));

        $like = '%' . self::escapeLike($q) . '%';
        $rows = Database::select(
            'SELECT p.id, p.nombre, p.part_number AS sku, p.marca AS marca, p.impuestos,
                    ' . self::precioSql() . ' AS precio
             FROM productos p
             WHERE ' . self::baseConditions() . '
               AND (p.nombre LIKE :q1 OR p.part_number LIKE :q2 OR p.marca LIKE :q3
                    OR p.id_marca IN (SELECT mq.id FROM marcas mq WHERE mq.marca LIKE :q4))
             ORDER BY p.id DESC
             LIMIT ' . $limit,
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]
        );

        return array_map(static fn (array $row): array => [
            'id'        => (int) $row['id'],
            'nombre'    => (string) $row['nombre'],
            'sku'       => (string) ($row['sku'] ?? ''),
            'marca'     => (string) ($row['marca'] ?? ''),
            'precio'    => $row['precio'] === null ? null : round((float) $row['precio'], 2),
            'impuestos' => (float) ($row['impuestos'] ?? 0),
        ], $rows);
    }

    // =====================================================================
    // HIDRATACION (consultas por lote)
    // =====================================================================

    /**
     * Completa un listado con lo que la tarjeta necesita, en 3 consultas fijas
     * (marca, stock real y especificaciones) en lugar de N consultas por fila.
     *
     * @param array<int, array> $items
     */
    private static function hydrate(array &$items, bool $withSpecs = true, bool $withStock = true): void
    {
        if ($items === []) {
            return;
        }

        if ($withSpecs) {
            self::attachSpecs($items);
        }

        self::attachBrands($items);

        foreach ($items as &$item) {
            $item = self::decorate($item);
        }
        unset($item);

        if ($withStock) {
            self::attachStock($items);
        }

        self::attachOffers($items);
    }

    /**
     * Marca las filas con oferta activa en el mayorista (`ofertas`). Es una
     * consulta por listado, no una por tarjeta: la tarjeta solo pinta la
     * etiqueta "Oferta" cuando `on_offer` es verdadero.
     *
     * @param array<int, array> $items
     */
    private static function attachOffers(array &$items): void
    {
        foreach ($items as &$item) {
            $item['on_offer'] = false;
        }
        unset($item);

        if (!Database::tableExists('ofertas')) {
            return;
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return;
        }

        $params = [];
        $placeholders = [];
        foreach (array_keys($ids) as $i => $id) {
            $ph = 'of' . $i;
            $placeholders[] = ':' . $ph;
            $params[$ph] = $id;
        }

        $onOffer = [];
        foreach (Database::select(
            'SELECT DISTINCT id_producto FROM ofertas
             WHERE id_producto IN (' . implode(',', $placeholders) . ')
               AND inicio <= NOW() AND (final IS NULL OR final >= NOW())',
            $params
        ) as $row) {
            $onOffer[(int) $row['id_producto']] = true;
        }
        if ($onOffer === []) {
            return;
        }

        foreach ($items as &$item) {
            $item['on_offer'] = isset($onOffer[(int) ($item['id'] ?? 0)]);
        }
        unset($item);
    }

    /**
     * Nombre de marca de cada fila. El JOIN contra `marcas` es caro (obliga a
     * un plan de ejecucion peor), asi que se resuelve en una consulta aparte.
     *
     * @param array<int, array> $items
     */
    private static function attachBrands(array &$items): void
    {
        $ids = [];
        $direct = [];
        foreach ($items as $i => $item) {
            $id = (int) ($item['id_marca'] ?? 0);
            $name = trim((string) ($item['marca_col'] ?? $item['marca'] ?? ''));
            if ($name !== '') {
                $direct[$i] = $name;
            } elseif ($id > 0) {
                $ids[$id] = true;
            }
        }

        $names = [];
        if ($ids !== [] && Database::tableExists('marcas')) {
            $placeholders = [];
            $params = [];
            $n = 0;
            foreach (array_keys($ids) as $id) {
                $ph = 'mb' . $n++;
                $placeholders[] = ':' . $ph;
                $params[$ph] = $id;
            }
            foreach (Database::select('SELECT id, marca FROM marcas WHERE id IN (' . implode(',', $placeholders) . ')', $params) as $row) {
                $names[(int) $row['id']] = (string) $row['marca'];
            }
        }

        foreach ($items as $i => &$item) {
            $name = $direct[$i] ?? $names[(int) ($item['id_marca'] ?? 0)] ?? '';
            $item['marca_nombre'] = $name;
        }
        unset($item);
    }

    /**
     * Stock total real de cada producto del listado (una sola consulta).
     *
     * Se usa para la etiqueta dinamica de la tarjeta (en stock, ultimas
     * unidades, sin stock). Si la consulta falla, la tarjeta se queda con el
     * booleano `in_stock`: nunca se rompe el listado por esto.
     *
     * @param array<int, array> $items
     */
    private static function attachStock(array &$items): void
    {
        $parts = [];
        foreach ($items as $item) {
            $pn = (string) ($item['part_number'] ?? '');
            if ($pn !== '') {
                $parts[$pn] = true;
            }
        }
        if ($parts === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        $n = 0;
        foreach (array_keys($parts) as $pn) {
            $ph = 'pn' . $n++;
            $placeholders[] = ':' . $ph;
            $params[$ph] = $pn;
        }

        try {
            $rows = Database::select(
                'SELECT s.part_number, SUM(s.stock) AS total
                 FROM stock s
                 INNER JOIN almacenes a ON a.id = s.id_almacen
                 WHERE s.part_number IN (' . implode(',', $placeholders) . ')
                   AND s.stock > 0 AND s.activo = 1 AND s.costo > 0 AND a.tipo <> 2
                 GROUP BY s.part_number',
                $params
            );
        } catch (\Throwable) {
            return;
        }

        $totals = [];
        foreach ($rows as $row) {
            $totals[(string) $row['part_number']] = (int) $row['total'];
        }

        foreach ($items as &$item) {
            $pn = (string) ($item['part_number'] ?? '');
            $item['stock_total'] = $totals[$pn] ?? 0;
        }
        unset($item);
    }

    /**
     * Especificaciones clave de cada tarjeta, a partir de la cadena corta
     * `productos_ext.caracteristicas` (una sola consulta y sin HTML pesado).
     *
     * @param array<int, array> $items
     */
    private static function attachSpecs(array &$items): void
    {
        if (!Database::tableExists('productos_ext')) {
            return;
        }

        $ids = [];
        foreach ($items as $item) {
            $id = (int) ($item['id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return;
        }

        $placeholders = [];
        $params = [];
        $n = 0;
        foreach (array_keys($ids) as $id) {
            $ph = 'pe' . $n++;
            $placeholders[] = ':' . $ph;
            $params[$ph] = $id;
        }

        try {
            $rows = Database::select(
                'SELECT id_producto, caracteristicas FROM productos_ext
                 WHERE id_producto IN (' . implode(',', $placeholders) . ')',
                $params
            );
        } catch (\Throwable) {
            return;
        }

        $specs = [];
        foreach ($rows as $row) {
            $specs[(int) $row['id_producto']] = (string) $row['caracteristicas'];
        }

        foreach ($items as &$item) {
            // `caracteristicas` es la fuente fiable; si el producto no las trae
            // (o no llega a 3 datos), se completan con lo que dice el nombre.
            $chips = Specs::quickHighlights(
                $specs[(int) ($item['id'] ?? 0)] ?? '',
                3,
                (string) ($item['nombre'] ?? '')
            );
            $item['specs'] = $chips;
        }
        unset($item);
    }

    // =====================================================================
    // FILTROS AVANZADOS (FACETS)
    // =====================================================================

    /**
     * Definiciones de facet disponibles para el contexto actual, ya preparadas
     * para pintar (opciones, seleccion y, si toca, valores del rango de precio).
     *
     * Los contadores de la marca se calculan en el contexto del listado (etiqueta,
     * busqueda y resto de filtros activos), para que no ofrezcan marcas que no
     * aparecen en la pagina.
     *
     * @return array<int, array>
     */
    public static function facets(
        ?int $categoryId = null,
        ?int $subcategoryId = null,
        array $selection = [],
        string $tag = 'todos',
        ?string $q = null
    ): array {
        if (!self::isAvailable()) {
            return [];
        }

        $definitions = (array) Config::get('catalog.facets', []);
        $selected = self::normalizeSelection($selection);
        $out = [];

        foreach ($definitions as $key => $def) {
            $key = (string) $key;
            $type = (string) ($def['type'] ?? 'terms');

            if (!self::facetVisible($def, $categoryId, $subcategoryId) && $type !== 'price') {
                continue;
            }

            if ($type === 'price') {
                $bounds = self::priceBounds();
                $out[] = [
                    'key'      => $key,
                    'type'     => 'price',
                    'label'    => (string) ($def['label'] ?? 'Precio'),
                    'min'      => $bounds['min'],
                    'max'      => $bounds['max'],
                    'value_min' => $selected['price_min'],
                    'value_max' => $selected['price_max'],
                ];
                continue;
            }

            if ($type === 'brand') {
                // Los contadores de marca se calculan con el resto de filtros
                // activos (sin la propia marca, que es la que se esta eligiendo).
                $otherTerms = (array) ($selection['terms'] ?? []);
                unset($otherTerms[$key]);
                $options = self::brandOptions(
                    $categoryId,
                    $subcategoryId,
                    (int) ($def['limit'] ?? 12),
                    $tag,
                    $q,
                    $otherTerms
                );
                if (count($options) < (int) ($def['min_items'] ?? 1)) {
                    continue;
                }
                $picked = $selected['terms'][$key] ?? [];
                foreach ($options as &$option) {
                    $option['selected'] = in_array((string) $option['value'], $picked, true);
                }
                unset($option);

                $out[] = [
                    'key'      => $key,
                    'type'     => 'brand',
                    'label'    => (string) ($def['label'] ?? 'Marca'),
                    'options'  => $options,
                    'selected' => array_values(array_filter($picked, static fn ($v): bool => $v !== '')),
                ];
                continue;
            }

            // Facet de terminos fijos (los declarados en config/catalog.php).
            $terms = (array) ($def['terms'] ?? []);
            if ($terms === []) {
                continue;
            }
            $picked = $selected['terms'][$key] ?? [];
            $options = [];
            foreach ($terms as $value => $term) {
                $label = is_array($term) ? (string) ($term['label'] ?? $value) : (string) $term;
                $options[] = [
                    'value'    => (string) $value,
                    'label'    => $label,
                    'selected' => in_array((string) $value, $picked, true),
                ];
            }

            $out[] = [
                'key'      => $key,
                'type'     => 'terms',
                'label'    => (string) ($def['label'] ?? $key),
                'options'  => $options,
                'selected' => array_values(array_filter($picked, static fn ($v): bool => $v !== '')),
            ];
        }

        return $out;
    }

    /**
     * Lee la seleccion de facets de la query string y la sanea contra la
     * configuracion (lo que no este declarado se ignora).
     *
     * @param array $get Normalmente $_GET
     * @return array{terms:array<string,array<int,string>>, price_min:?float, price_max:?float, flat:array<int,array{key:string,value:string}>}
     */
    public static function selectionFromQuery(array $get): array
    {
        $definitions = (array) Config::get('catalog.facets', []);
        $terms = [];

        foreach ((array) ($get['f'] ?? []) as $key => $values) {
            $key = (string) $key;

            // Clave numerica: filtro estructurado del mayorista (f[164][]=1282).
            if (self::isStructuredKey($key)) {
                if (!self::filtersAvailable()) {
                    continue;
                }
                $allowed = [];
                foreach ((array) (is_array($values) ? $values : [$values]) as $value) {
                    $id = (int) $value;
                    if ($id > 0) {
                        $allowed[] = (string) $id;
                    }
                }
                $allowed = array_values(array_unique($allowed));
                if ($allowed !== []) {
                    $terms[$key] = $allowed;
                }
                continue;
            }

            $def = $definitions[$key] ?? null;
            if ($def === null) {
                continue;
            }

            $values = is_array($values) ? $values : [$values];
            $allowed = [];
            if ((string) ($def['type'] ?? 'terms') === 'brand') {
                // Las marcas llegan por id; se validan al construir la consulta.
                $allowed = array_map(static fn ($v): string => (string) (int) $v, $values);
            } else {
                $termKeys = array_keys((array) ($def['terms'] ?? []));
                foreach ($values as $value) {
                    $value = (string) $value;
                    if (in_array($value, $termKeys, true)) {
                        $allowed[] = $value;
                    }
                }
            }

            $allowed = array_values(array_unique(array_filter($allowed, static fn (string $v): bool => $v !== '')));
            if ($allowed !== []) {
                $terms[$key] = $allowed;
            }
        }

        $priceMin = self::floatOrNull($get['pmin'] ?? null);
        $priceMax = self::floatOrNull($get['pmax'] ?? null);
        if ($priceMin !== null && $priceMax !== null && $priceMin > $priceMax) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }

        // Chips de filtros activos, con su etiqueta legible (para la vista).
        $flat = [];
        foreach ($terms as $key => $values) {
            if (self::isStructuredKey($key)) {
                foreach ($values as $value) {
                    $flat[] = [
                        'key'   => (string) $key,
                        'value' => (string) $value,
                        'label' => self::structuredChipLabel((int) $key, (int) $value),
                    ];
                }
                continue;
            }

            $def = $definitions[$key] ?? [];
            foreach ($values as $value) {
                $label = null;
                if ((string) ($def['type'] ?? 'terms') === 'brand') {
                    $label = self::brandName((int) $value);
                } else {
                    $term = (array) ($def['terms'][$value] ?? []);
                    $label = is_array($term) ? (string) ($term['label'] ?? $value) : (string) $term;
                }
                $flat[] = ['key' => $key, 'value' => $value, 'label' => (string) ($label ?? $value)];
            }
        }
        if ($priceMin !== null || $priceMax !== null) {
            $flat[] = [
                'key'   => 'precio',
                'value' => '',
                'label' => 'Precio',
                'price' => true,
            ];
        }

        return [
            'terms'     => $terms,
            'price_min' => $priceMin,
            'price_max' => $priceMax,
            'flat'      => $flat,
        ];
    }

    /** Normaliza una seleccion ya parseada (la usan los metodos internos). */
    private static function normalizeSelection(array $selection): array
    {
        $terms = [];
        foreach ((array) ($selection['terms'] ?? []) as $key => $values) {
            $values = array_values(array_filter(array_map('strval', (array) $values), static fn (string $v): bool => $v !== ''));
            if ($values !== []) {
                $terms[(string) $key] = $values;
            }
        }

        return [
            'terms'     => $terms,
            'price_min' => isset($selection['price_min']) ? (float) $selection['price_min'] : null,
            'price_max' => isset($selection['price_max']) ? (float) $selection['price_max'] : null,
        ];
    }

    /** El filtro se muestra solo en las categorias/subcategorias indicadas. */
    private static function facetVisible(array $def, ?int $categoryId, ?int $subcategoryId): bool
    {
        $when = (array) ($def['when'] ?? []);
        $cats = array_map('intval', (array) ($when['cats'] ?? []));
        $subs = array_map('intval', (array) ($when['subcats'] ?? []));

        if ($cats === [] && $subs === []) {
            return true;
        }
        if ($subcategoryId !== null && in_array($subcategoryId, $subs, true)) {
            return true;
        }
        return $categoryId !== null && in_array($categoryId, $cats, true);
    }

    /**
     * Marcas con mas productos con stock en el contexto actual (facet dinamico).
     * La consulta agrupa sobre el catalogo ya filtrado, asi que se cachea.
     *
     * Cuenta en el contexto real del listado: categoria, subcategoria, etiqueta,
     * busqueda y resto de filtros activos (los mismos que aplica el listado).
     *
     * @param array<string,array<int,string>> $facetTerms resto de filtros activos
     * @return array<int, array{value:string,label:string,count:int,selected:bool}>
     */
    private static function brandOptions(
        ?int $categoryId,
        ?int $subcategoryId,
        int $limit,
        string $tag = 'todos',
        ?string $q = null,
        array $facetTerms = []
    ): array {
        $limit = max(1, min(30, $limit));
        $ttl = (int) Config::get('catalog.facets_ttl', 900);
        $tag = self::tagKey($tag);
        $context = md5($tag . '|' . (string) $q . '|' . serialize($facetTerms));
        $cacheKey = 'brands:' . ($categoryId ?? 0) . ':' . ($subcategoryId ?? 0) . ':' . $limit . ':' . $context;

        $data = self::cached($cacheKey, $ttl, static function () use ($categoryId, $subcategoryId, $limit, $tag, $q, $facetTerms): array {
            // Mismo WHERE que el listado (menos la propia marca), para que los
            // contadores no ofrezcan marcas que no aparecen en la pagina.
            [$where, $params] = self::buildFilters($q, $categoryId, $subcategoryId, $facetTerms, null, null, $tag);

            $rows = Database::select(
                "SELECT p.id_marca, COUNT(*) AS total
                 FROM productos p
                 $where AND p.id_marca IS NOT NULL AND p.id_marca > 0
                 GROUP BY p.id_marca
                 ORDER BY total DESC, p.id_marca ASC
                 LIMIT $limit",
                $params
            );

            if ($rows === []) {
                return [];
            }

            $names = [];
            $ids = array_map(static fn (array $r): int => (int) $r['id_marca'], $rows);
            $placeholders = [];
            $brandParams = [];
            foreach ($ids as $i => $id) {
                $ph = 'b' . $i;
                $placeholders[] = ':' . $ph;
                $brandParams[$ph] = $id;
            }
            foreach (Database::select('SELECT id, marca FROM marcas WHERE id IN (' . implode(',', $placeholders) . ')', $brandParams) as $row) {
                $names[(int) $row['id']] = (string) $row['marca'];
            }

            $options = [];
            foreach ($rows as $row) {
                $id = (int) $row['id_marca'];
                $label = trim($names[$id] ?? '');
                if ($label === '') {
                    continue;
                }
                $options[] = [
                    'value'    => (string) $id,
                    'label'    => $label,
                    'count'    => (int) $row['total'],
                    'selected' => false,
                ];
            }
            return $options;
        });

        return (array) $data;
    }

    /** Nombre de una marca por id (para los chips de filtros activos). */
    private static function brandName(int $id): ?string
    {
        return self::brandLabel($id);
    }

    /** Nombre publico de una marca (paginas /marca/{id} y /marcas). */
    public static function brandLabel(int $id): ?string
    {
        if ($id <= 0 || !Database::tableExists('marcas')) {
            return null;
        }
        $name = Database::scalar('SELECT marca FROM marcas WHERE id = :id', ['id' => $id]);
        return $name === null || trim((string) $name) === '' ? null : (string) $name;
    }

    // =====================================================================
    // FILTROS ESTRUCTURADOS DEL MAYORISTA (filtros / subfiltros)
    //
    // El mayorista clasifica sus productos con `rel_filtro_producto`
    // (producto <-> subfiltro) y declara que filtros aplican a cada
    // subcategoria en `rel_filtros_subcat`. Es el mismo modelo que usan sus
    // webs (idirecto y puntobyze): en una subcategoria se ofrecen sus filtros
    // con el numero de productos con stock y, al aplicarlos, se combinan en
    // AND entre filtros y OR dentro del mismo filtro.
    //
    // Solo se leen tablas del mayorista: nunca se escriben.
    // =====================================================================

    /** ¿Estan las tablas de filtros del mayorista disponibles? */
    private static function filtersAvailable(): bool
    {
        foreach (['filtros', 'subfiltros', 'rel_filtros_subcat', 'rel_filtro_producto'] as $table) {
            if (!Database::tableExists($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * En la seleccion de facetas, los ids de filtro del mayorista son claves
     * numericas (`f[164][]=1282`) y los facetas de configuracion, nombres
     * (`f[socket][]=am5`). Esto los distingue.
     */
    public static function isStructuredKey(string|int $key): bool
    {
        return ctype_digit((string) $key);
    }

    /**
     * Grupos de filtros de una subcategoria con sus opciones y el numero de
     * productos con stock de cada una (cacheados).
     *
     * @param array<string,array<int,string>> $selection seleccion activa
     * @return array<int,array{id:int,key:string,label:string,options:array,selected:array}>
     */
    public static function structuredFilters(?int $subcategoryId, array $selection = []): array
    {
        if ($subcategoryId === null || $subcategoryId <= 0 || !self::isAvailable() || !self::filtersAvailable()) {
            return [];
        }
        $subcategoryId = (int) $subcategoryId;
        $ttl = max(1800, (int) Config::get('catalog.facets_ttl', 900) * 4);

        $groups = self::cached('filters_sub_' . $subcategoryId, $ttl, static function () use ($subcategoryId): array {
            // La subconsulta de productos con stock se materializa a proposito
            // (STRAIGHT_JOIN + GROUP BY): si no, el optimizador recorre primero
            // `rel_filtro_producto` y la consulta pasa de decimas a segundos en
            // subcategorias con muchos productos sin stock.
            $rows = Database::select(
                'SELECT f.id AS id_filtro, f.filtro, sf.id AS id_subfiltro, sf.nombre, COUNT(DISTINCT r.id_producto) AS total
                 FROM rel_filtro_producto r
                 INNER JOIN subfiltros sf ON sf.id = r.id_sub_filtro
                 INNER JOIN filtros f ON f.id = sf.id_filtro AND f.deleted = 0
                 INNER JOIN rel_filtros_subcat rfs ON rfs.id_filtro = f.id AND rfs.id_subcategoria = :sub1
                 WHERE r.id_producto IN (
                     SELECT p.id
                     FROM productos p
                     STRAIGHT_JOIN stock sx ON sx.part_number = p.part_number
                     STRAIGHT_JOIN almacenes ax ON ax.id = sx.id_almacen
                     WHERE p.id_subcategoria = :sub2
                       AND p.estado <> 4
                       AND sx.stock > 0 AND sx.activo = 1 AND sx.costo > 0 AND ax.tipo <> 2
                     GROUP BY p.id
                 )
                 GROUP BY f.id, f.filtro, sf.id, sf.nombre
                 HAVING total > 0
                 ORDER BY f.filtro ASC, total DESC, sf.nombre ASC',
                ['sub1' => $subcategoryId, 'sub2' => $subcategoryId]
            );

            $out = [];
            $index = [];
            foreach ($rows as $row) {
                $filterId = (int) $row['id_filtro'];
                if (!isset($index[$filterId])) {
                    $index[$filterId] = count($out);
                    $out[] = [
                        'id'       => $filterId,
                        'key'      => (string) $filterId,
                        'label'    => self::filterLabel((string) $row['filtro']),
                        'options'  => [],
                        'selected' => [],
                    ];
                }
                $out[$index[$filterId]]['options'][] = [
                    'value'    => (string) (int) $row['id_subfiltro'],
                    'label'    => (string) $row['nombre'],
                    'count'    => (int) $row['total'],
                    'selected' => false,
                ];
            }

            return $out;
        });

        // Marca los valores elegidos y los pone delante (como idirecto).
        foreach ($groups as $i => $group) {
            $picked = array_map('strval', (array) ($selection[(string) $group['id']] ?? []));
            if ($picked === []) {
                continue;
            }
            $elegidas = [];
            $resto = [];
            foreach ($group['options'] as $option) {
                $option['selected'] = in_array((string) $option['value'], $picked, true);
                if ($option['selected']) {
                    $elegidas[] = $option;
                } else {
                    $resto[] = $option;
                }
            }
            $groups[$i]['options'] = array_merge($elegidas, $resto);
            $groups[$i]['selected'] = array_map(static fn (array $o): string => (string) $o['value'], $elegidas);
        }

        return array_values((array) $groups);
    }

    /**
     * Subcategoria donde un filtro (y su subfiltro) tiene mas productos con
     * stock. La usan los destinos de filtro del menu para enlazar a un listado
     * con resultados en vez de al ancla del grupo, que puede estar vacia.
     */
    public static function filterSubcategory(int $filterId, int $subfilterId = 0): ?int
    {
        if ($filterId <= 0 || !self::isAvailable() || !self::filtersAvailable()) {
            return null;
        }
        $subfilterId = max(0, $subfilterId);

        $value = self::cached('filter_sub_' . $filterId . '_' . $subfilterId, 3600, static function () use ($filterId, $subfilterId): array {
            if ($subfilterId > 0) {
                $join = 'INNER JOIN rel_filtro_producto r ON r.id_sub_filtro = :subfilter';
                $params = ['filter' => $filterId, 'subfilter' => $subfilterId];
            } else {
                // Sin subfiltro concreto: cualquier valor de ese filtro.
                $join = 'INNER JOIN rel_filtro_producto r
                            ON r.id_sub_filtro IN (SELECT sff.id FROM subfiltros sff WHERE sff.id_filtro = :filter2)';
                $params = ['filter' => $filterId, 'filter2' => $filterId];
            }

            $row = Database::first(
                'SELECT rfs.id_subcategoria AS subcategoria_id, COUNT(DISTINCT p.id) AS total
                 FROM rel_filtros_subcat rfs
                 ' . $join . '
                 INNER JOIN productos p ON p.id = r.id_producto AND p.id_subcategoria = rfs.id_subcategoria
                 WHERE rfs.id_filtro = :filter
                   AND p.estado <> 4
                   AND ' . self::stockExistsSql() . '
                 GROUP BY rfs.id_subcategoria
                 ORDER BY total DESC, rfs.id_subcategoria ASC
                 LIMIT 1',
                $params
            );

            return $row === null ? [] : ['id' => (int) $row['subcategoria_id']];
        });

        return isset($value['id']) ? (int) $value['id'] : null;
    }

    /** Etiqueta de un filtro activo ("Memoria Grafica: 8 GB") para los chips. */
    public static function structuredChipLabel(int $filterId, int $subfilterId): string
    {
        $parts = self::filterLabels($filterId, $subfilterId);
        if ($parts === []) {
            return 'Filtro ' . $filterId . '-' . $subfilterId;
        }

        return self::filterLabel($parts['filter']) . ': ' . $parts['value'];
    }

    /** @return array{filter:string,value:string}|array{} */
    private static function filterLabels(int $filterId, int $subfilterId): array
    {
        if ($filterId <= 0 || $subfilterId <= 0 || !Database::tableExists('filtros') || !Database::tableExists('subfiltros')) {
            return [];
        }

        return (array) self::cached('filter_label_' . $filterId . '_' . $subfilterId, 86400, static function () use ($filterId, $subfilterId): array {
            $row = Database::first(
                'SELECT f.filtro, sf.nombre FROM subfiltros sf
                 INNER JOIN filtros f ON f.id = sf.id_filtro
                 WHERE sf.id = :sub AND f.id = :filter LIMIT 1',
                ['sub' => $subfilterId, 'filter' => $filterId]
            );

            return $row === null ? [] : ['filter' => (string) $row['filtro'], 'value' => (string) $row['nombre']];
        });
    }

    /** Limpia el nombre del filtro que trae el mayorista (espacios, dos puntos). */
    private static function filterLabel(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $name = rtrim($name, " :");

        return $name === '' ? 'Filtro' : $name;
    }

    // =====================================================================
    // ETIQUETAS DE LISTADO Y MARCAS (navegacion)
    // =====================================================================

    /** Etiquetas de listado declaradas en config/catalog.php. */
    public static function tags(): array
    {
        return (array) Config::get('catalog.tags', []);
    }

    /** Etiqueta valida (cualquier cosa desconocida cae a `todos`). */
    public static function tagKey(?string $key): string
    {
        $key = $key === null ? '' : strtolower(trim($key));
        return $key !== '' && isset(self::tags()[$key]) ? $key : 'todos';
    }

    /** Texto legible de una etiqueta. */
    public static function tagLabel(string $key): string
    {
        $tags = self::tags();
        return (string) ($tags[$key]['label'] ?? $key);
    }

    /**
     * Marcas con mas productos con stock, para los paneles del menu.
     * Reutiliza la cache de los facetas (no anade consultas nuevas).
     *
     * @return array<int, array{value:string,label:string,count:int}>
     */
    public static function topBrands(?int $categoryId, int $limit = 6): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        return self::brandOptions($categoryId !== null && $categoryId > 0 ? $categoryId : null, null, $limit);
    }

    /**
     * Listado de marcas con stock para la pagina /marcas.
     *
     * Es una agrupacion sobre todo el catalogo (~1 s en frio), asi que se cachea
     * una hora: no cambia de un minuto a otro.
     *
     * @return array<int, array{id:int,label:string,total:int,path:string}>
     */
    public static function brandList(int $limit = 200): array
    {
        if (!self::isAvailable() || !Database::tableExists('marcas')) {
            return [];
        }
        $limit = max(1, min(500, $limit));

        $rows = self::cached(
            'brand_list_' . $limit,
            3600,
            static function () use ($limit): array {
                $rows = Database::select(
                    'SELECT p.id_marca, COUNT(*) AS total
                     FROM productos p
                     WHERE ' . self::baseConditions() . '
                       AND p.id_marca IS NOT NULL AND p.id_marca > 0
                     GROUP BY p.id_marca
                     ORDER BY total DESC, p.id_marca ASC
                     LIMIT ' . $limit
                );
                if ($rows === []) {
                    return [];
                }

                $names = [];
                $placeholders = [];
                $params = [];
                foreach ($rows as $i => $row) {
                    $ph = 'bm' . $i;
                    $placeholders[] = ':' . $ph;
                    $params[$ph] = (int) $row['id_marca'];
                }
                foreach (Database::select('SELECT id, marca FROM marcas WHERE id IN (' . implode(',', $placeholders) . ')', $params) as $row) {
                    $names[(int) $row['id']] = (string) $row['marca'];
                }

                $out = [];
                foreach ($rows as $row) {
                    $id = (int) $row['id_marca'];
                    $label = trim($names[$id] ?? '');
                    if ($label === '') {
                        continue;
                    }
                    $out[] = [
                        'id'    => $id,
                        'label' => $label,
                        'total' => (int) $row['total'],
                    ];
                }

                return $out;
            }
        );

        // La ruta de cada marca es su pagina propia (/marca/{id}); se resuelve
        // aqui porque no depende de la cache del listado.
        $out = [];
        foreach ((array) $rows as $row) {
            $out[] = [
                'id'    => (int) $row['id'],
                'label' => (string) $row['label'],
                'total' => (int) $row['total'],
                'path'  => '/marca/' . (int) $row['id'],
            ];
        }

        return $out;
    }

    /** Clave del facet de marca declarado en config/catalog.php. */
    public static function brandFacetKey(): string
    {
        foreach ((array) Config::get('catalog.facets', []) as $key => $def) {
            if ((string) ($def['type'] ?? '') === 'brand') {
                return (string) $key;
            }
        }

        return 'marca';
    }

    /**
     * Rango de precios del catalogo (limites del filtro). Es global y cacheado:
     * asi el deslizador no cambia de escala al navegar entre categorias.
     *
     * @return array{min:float,max:float}
     */
    public static function priceBounds(): array
    {
        if (!self::isAvailable()) {
            return ['min' => 0.0, 'max' => 0.0];
        }

        $ttl = max(1800, (int) Config::get('catalog.facets_ttl', 900) * 2);

        return (array) self::cached('price_bounds_' . self::pricingKey(), $ttl, static function (): array {
            $precio = self::precioSql();
            $row = Database::first(
                "SELECT MIN($precio) AS mn, MAX($precio) AS mx
                 FROM productos p
                 WHERE " . self::baseConditions()
            );

            $min = (float) ($row['mn'] ?? 0);
            $max = (float) ($row['mx'] ?? 0);
            if ($max <= 0) {
                return ['min' => 0.0, 'max' => 0.0];
            }
            // Redondeo "comercial" para el deslizador.
            return [
                'min' => floor($min),
                'max' => (float) (ceil($max / 100) * 100),
            ];
        });
    }

    /**
     * Ordenes disponibles para el listado.
     *
     * Ordenar por precio obliga a MySQL a calcular el precio (una subconsulta
     * sobre `precios`) para cada candidato: dentro de una categoria o con
     * filtros es instantaneo, pero sobre las 40.000 referencias del catalogo
     * entero cuesta segundos. Por eso `$includePrice` es falso cuando el
     * listado no esta acotado: mejor no ofrecer un boton que tarde 4 s.
     */
    public static function sorts(bool $includePrice = true): array
    {
        $sorts = (array) Config::get('catalog.sorts', []);
        $out = [];
        foreach ($sorts as $key => $def) {
            $key = (string) $key;
            if (!$includePrice && str_starts_with($key, 'precio')) {
                continue;
            }
            $out[$key] = (string) ($def['label'] ?? $key);
        }
        return $out;
    }

    /** Orden valido a partir de lo que llega por query string. */
    public static function sortKey(?string $key, bool $includePrice = true): string
    {
        $sorts = self::sorts($includePrice);
        return $key !== null && isset($sorts[$key]) ? $key : 'relevancia';
    }

    /** SQL del ORDER BY. */
    private static function orderSql(string $sort): string
    {
        $sorts = (array) Config::get('catalog.sorts', []);
        $mode = (string) ($sorts[$sort]['sql'] ?? 'relevance');

        return match ($mode) {
            'price_asc'   => 'ORDER BY precio IS NULL, precio ASC, p.id DESC',
            'price_desc'  => 'ORDER BY precio IS NULL, precio DESC, p.id DESC',
            'name_asc'    => 'ORDER BY p.nombre ASC, p.id DESC',
            'name_desc'   => 'ORDER BY p.nombre DESC, p.id DESC',
            default       => 'ORDER BY p.id DESC',
        };
    }

    // =====================================================================
    // ACCESOS RAPIDOS DE LA PORTADA
    // =====================================================================

    /**
     * Convierte los accesos rapidos de la configuracion en tarjetas con enlace.
     * Se contrastan con el arbol de categorias con stock, de modo que nunca se
     * enlaza a un listado vacio.
     *
     * @return array<int, array{label:string,subtitle:string,icon:string,url:string,total:int}>
     */
    public static function quickCategories(string $base = ''): array
    {
        $links = (array) Config::get('catalog.quick_links', []);
        if ($links === []) {
            return [];
        }

        $menu = self::menuTree();
        $byId = [];
        foreach ($menu as $cat) {
            $byId[(int) $cat['id']] = $cat;
        }

        $out = [];
        foreach ($links as $link) {
            $catId = (int) ($link['cat'] ?? 0);
            $subId = (int) ($link['subcat'] ?? 0);
            $q = trim((string) ($link['q'] ?? ''));

            $total = 0;
            $params = [];

            if ($catId > 0 && isset($byId[$catId])) {
                $total = (int) $byId[$catId]['total'];
                $params[] = 'cat=' . $catId;
            }

            if ($subId > 0 && $catId > 0) {
                foreach (($byId[$catId]['subcategories'] ?? []) as $sub) {
                    if ((int) $sub['id'] === $subId) {
                        $total = (int) $sub['total'];
                        $params[] = 'subcat=' . $subId;
                        break;
                    }
                }
            }

            // Nichos sin subcategoria propia (ultraligeros...): se afinan con
            // una busqueda. Si esa busqueda no devuelve nada con stock, el
            // acceso se queda con la categoria entera en lugar de desaparecer,
            // para no perder un acceso pedido por la tienda.
            if ($q !== '') {
                $hits = (int) self::cached(
                    'quick_q_' . sha1($q . '|' . $catId . '|' . $subId),
                    3600,
                    static function () use ($q, $catId, $subId): int {
                        [$where, $bindings] = self::buildFilters($q, $catId > 0 ? $catId : null, $subId > 0 ? $subId : null);
                        return (int) Database::scalar("SELECT COUNT(*) FROM productos p $where", $bindings);
                    }
                );
                if ($hits > 0) {
                    $total = $hits;
                    $params[] = 'q=' . rawurlencode($q);
                }
            }

            if ($total <= 0 || $params === []) {
                continue; // Sin stock: el acceso no se pinta.
            }

            $out[] = [
                'label'    => (string) ($link['label'] ?? ''),
                'subtitle' => (string) ($link['subtitle'] ?? ''),
                'icon'     => (string) ($link['icon'] ?? 'chip'),
                'url'      => $base . '/catalogo?' . implode('&', $params),
                'total'    => $total,
            ];
        }

        return $out;
    }

    // =====================================================================
    // TAXONOMIA
    // =====================================================================

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

        $tree = self::cached('menu', self::MENU_TTL, static fn (): array => self::buildMenuTree());
        return (array) $tree;
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
     *
     * @return array{0:string,1:array}
     */
    private static function buildFilters(
        ?string $q,
        ?int $categoryId,
        ?int $subcategoryId = null,
        array $facetSelection = [],
        ?float $priceMin = null,
        ?float $priceMax = null,
        string $tag = 'todos'
    ): array {
        $where = [self::baseConditions()];
        $params = [];
        $counter = 0;

        // Etiqueta de listado (accesos comerciales: ofertas, novedades...).
        $tag = self::tagKey($tag);
        if ($tag === 'ofertas' && Database::tableExists('ofertas')) {
            $where[] = 'p.id IN (SELECT o.id_producto FROM ofertas o
                                 WHERE o.inicio <= NOW() AND (o.final IS NULL OR o.final >= NOW()))';
        } elseif ($tag === 'novedades') {
            $days = (int) (self::tags()['novedades']['days'] ?? 90);
            $days = max(1, min(3650, $days));
            $where[] = 'p.fecha_alta >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
        } elseif ($tag === 'destacados') {
            $where[] = 'p.etiqueta = 1';
        }

        if ($q !== null && $q !== '') {
            // Con PDO::ATTR_EMULATE_PREPARES = false un parametro nombrado no
            // puede repetirse: se usan marcadores distintos.
            $where[] = '(p.nombre LIKE :q1 OR p.part_number LIKE :q2 OR p.marca LIKE :q3
                         OR p.id_marca IN (SELECT mq.id FROM marcas mq WHERE mq.marca LIKE :q4))';
            $like = '%' . self::escapeLike($q) . '%';
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

        $definitions = (array) Config::get('catalog.facets', []);
        // `$facetSelection` puede llegar como el mapa de terminos
        // (`key => valores`, es lo que usa el listado) o como la seleccion
        // completa (`['terms' => ...]`, lo usan los chequeos). Sin normalizarlo,
        // TODOS los filtros se ignoraban en el primer caso.
        if (!isset($facetSelection['terms']) || !is_array($facetSelection['terms'])) {
            $facetSelection = ['terms' => $facetSelection];
        }
        $selection = self::normalizeSelection($facetSelection);

        foreach ($selection['terms'] as $key => $values) {
            // Filtro estructurado del mayorista: OR dentro del filtro, AND
            // entre filtros. El `sf.id_filtro` evita que desde la URL cuele un
            // subfiltro que no pertenece a ese grupo.
            if (self::isStructuredKey($key)) {
                $filterId = (int) $key;
                $ors = [];
                foreach ($values as $value) {
                    $subfilterId = (int) $value;
                    if ($subfilterId <= 0) {
                        continue;
                    }
                    $ph = 'sf' . $counter++;
                    $ors[] = ':' . $ph;
                    $params[$ph] = $subfilterId;
                }
                if ($filterId > 0 && $ors !== []) {
                    $where[] = 'p.id IN (SELECT r.id_producto FROM rel_filtro_producto r
                                         INNER JOIN subfiltros sf ON sf.id = r.id_sub_filtro
                                         WHERE sf.id_filtro = ' . $filterId . '
                                           AND r.id_sub_filtro IN (' . implode(',', $ors) . '))';
                }
                continue;
            }

            $def = $definitions[$key] ?? null;
            if ($def === null) {
                continue;
            }

            // Facet dinamico de marca: los valores son ids.
            if ((string) ($def['type'] ?? 'terms') === 'brand') {
                $ors = [];
                foreach ($values as $value) {
                    $id = (int) $value;
                    if ($id <= 0) {
                        continue;
                    }
                    $ph = 'f' . $key . '_' . $counter++;
                    $ors[] = 'p.id_marca = :' . $ph;
                    $params[$ph] = $id;
                }
                if ($ors !== []) {
                    $where[] = '(' . implode(' OR ', $ors) . ')';
                }
                continue;
            }

            $scopes = (array) ($def['scope'] ?? ['name']);
            $terms = (array) ($def['terms'] ?? []);
            $ors = [];

            foreach ($values as $value) {
                $term = (array) ($terms[$value] ?? []);
                $patterns = $term['patterns'] ?? ($term['pattern'] ?? null);
                if ($patterns === null) {
                    continue;
                }
                foreach ((array) $patterns as $pattern) {
                    $pattern = trim((string) $pattern);
                    if ($pattern === '') {
                        continue;
                    }
                    foreach ($scopes as $scope) {
                        $ph = 'f' . $key . '_' . $counter++;
                        $sql = self::scopeSql((string) $scope, $ph);
                        if ($sql === null) {
                            continue;
                        }
                        $ors[] = $sql;
                        $params[$ph] = '%' . self::escapeLike($pattern) . '%';
                    }
                }
            }

            if ($ors !== []) {
                $where[] = '(' . implode(' OR ', $ors) . ')';
            }
        }

        $precio = self::precioSql();
        if ($priceMin !== null && $priceMin > 0) {
            $where[] = "$precio >= :pmin";
            $params['pmin'] = $priceMin;
        }
        if ($priceMax !== null && $priceMax > 0) {
            $where[] = "$precio <= :pmax";
            $params['pmax'] = $priceMax;
        }

        return ['WHERE ' . implode(' AND ', $where), $params];
    }

    /** SQL de comparacion segun el ambito del facet. */
    private static function scopeSql(string $scope, string $placeholder): ?string
    {
        return match ($scope) {
            'name'  => 'p.nombre LIKE :' . $placeholder,
            'spec'  => 'EXISTS (SELECT 1 FROM productos_ext pe WHERE pe.id_producto = p.id AND pe.caracteristicas LIKE :' . $placeholder . ')',
            'brand' => 'p.id_marca IN (SELECT mb.id FROM marcas mb WHERE mb.marca LIKE :' . $placeholder . ')',
            default => null,
        };
    }

    /** Escapa los comodines de LIKE. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }

    /** Lee un numero de la query string (admite coma decimal). */
    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || is_array($value)) {
            return null;
        }
        $value = str_replace(',', '.', trim((string) $value));
        return is_numeric($value) ? (float) $value : null;
    }

    // =====================================================================
    // CACHE EN FICHERO
    // =====================================================================

    /**
     * Cache en fichero con TTL. Se usa para lo que es caro y cambia poco
     * (contador, arbol de categorias, facetas). Nunca rompe nada: si no se
     * puede escribir, se calcula el valor y se sigue.
     */
    private static function cached(string $key, int $ttl, callable $compute): mixed
    {
        $dir = TIENDA_BASE . '/storage/cache';
        $file = $dir . '/catalog_' . preg_replace('/[^a-z0-9_\-]/i', '_', $key) . '.json';

        if (is_file($file) && (time() - (int) filemtime($file)) < $ttl) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && array_key_exists('value', $data)) {
                return $data['value'];
            }
        }

        $value = $compute();

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode(['value' => $value, 'ts' => time()], JSON_UNESCAPED_UNICODE));
        // Escribible por web y por consola (mismo criterio que el contador).
        @chmod($file, 0664);

        return $value;
    }

    /** Cache en fichero del contador total (evita un COUNT pesado por peticion). */
    private static function cachedCount(string $key, callable $compute): int
    {
        return (int) self::cached('count_' . $key, self::COUNT_TTL, $compute);
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

    // =====================================================================
    // TARJETA DE PRODUCTO
    // =====================================================================

    /**
     * Completa cada producto con slug, URL SEO, imagen, precio final y la
     * etiqueta de disponibilidad que pinta la tarjeta.
     */
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

        // Precio de venta de la tienda. `precio` ya viene de la consulta con la
        // tarifa y el beneficio aplicados (y con el IVA, si es el storefront),
        // asi que aqui solo queda el caso de productos sin tarifa publicada, que
        // se resuelven desde el coste con el mismo beneficio.
        $precio = $item['precio'] ?? null;
        $costo = $item['costo'] ?? null;
        $iva = (float) ($item['impuestos'] ?? 0);
        if ($iva <= 0) {
            $iva = self::$pricingTax;
        }

        if ($precio !== null && (float) $precio > 0) {
            $final = (float) $precio;
        } elseif ($costo !== null && (float) $costo > 0) {
            $final = self::salePriceFromCost((float) $costo, $iva);
        } else {
            $final = null;
        }

        $item['tax_rate'] = round($iva, 2);
        $item['price_final'] = $final;
        // Base sin IVA: es lo que se guarda como precio de la linea del pedido.
        $item['price_net'] = $final === null ? null : self::netFromSale((float) $final, $iva);

        // Disponibilidad
        $item['in_stock'] = (bool) ($item['in_stock'] ?? false);

        $total = $item['stock_total'] ?? null;
        $threshold = max(1, (int) Config::get('catalog.low_stock_threshold', 5));
        if ($total === null) {
            $item['stock_band'] = $item['in_stock'] ? 'in' : 'out';
            $item['stock_label'] = $item['in_stock'] ? 'En stock' : 'Sin stock';
        } elseif ($total <= 0) {
            $item['stock_band'] = 'out';
            $item['stock_label'] = 'Sin stock';
        } elseif ($total <= $threshold) {
            $item['stock_band'] = 'low';
            $item['stock_label'] = 'Ultimas ' . $total . ' ud.';
        } else {
            $item['stock_band'] = 'in';
            $item['stock_label'] = 'En stock';
        }

        // Novedad: alta reciente en el catalogo del mayorista.
        $item['is_new'] = false;
        if (!empty($item['fecha_alta'])) {
            $ts = strtotime((string) $item['fecha_alta']);
            $item['is_new'] = $ts !== false && (time() - $ts) < 60 * 86400;
        }

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
        $found = self::find($id);
        if ($found === null) {
            return null;
        }

        // Marca y stock real, con las mismas consultas por lote que un listado.
        $rows = [$found];
        self::attachBrands($rows);
        self::attachStock($rows);
        $product = self::decorate($rows[0]);

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
