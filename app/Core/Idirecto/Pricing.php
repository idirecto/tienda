<?php

declare(strict_types=1);

namespace Tienda\Core\Idirecto;

use Tienda\Core\Database;

/**
 * Tarifa del mayorista para un producto.
 *
 * Que devuelve, por producto:
 *   - `precio`  : lo que paga la tienda (`precios.precio` de su `id_margen`)
 *   - `coste`   : coste del mayorista (`stock.costo`, mismo criterio que su
 *                 `getCosto()`: almacenes con tarifa para ese margen y stock)
 *   - `id_almacen` / `id_stock`: almacen que sirve el producto
 *   - `iva`, `sujeto`, `canon`: datos fiscales de la linea
 *
 * Con eso se rellenan las lineas de `pedidos_det` tal y como las escribe
 * idirecto: `costo`, `ganancia` (= precio - coste), `impuestos`, `sujeto`,
 * `id_almacen` y `canon`.
 *
 * Si un producto no tiene tarifa para el margen de la tienda se devuelve igual
 * (`tarifa_conocida` = false) para poder avisar en el panel antes de enviar.
 */
final class Pricing
{
    private const REQUIRED_TABLES = ['productos', 'stock', 'almacenes', 'precios', 'tarifas', 'subcategorias'];

    /** Cache por peticion: producto + margen + sucursal. */
    private static array $cache = [];

    public static function isAvailable(): bool
    {
        foreach (self::REQUIRED_TABLES as $table) {
            if (!Database::tableExists($table)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Tarifa y datos fiscales de un producto del catalogo central.
     *
     * @return array{product_id:int,nombre:string,sku:string,precio:float,coste:float,
     *               ganancia:float,id_almacen:?int,id_stock:?int,iva:float,sujeto:int,
     *               canon:float,tarifa_conocida:bool}|null
     */
    public static function forProduct(int $productId, int $idMargen, int $sucursalId = 1, float $fallbackTax = 21.0): ?array
    {
        if ($productId <= 0 || !self::isAvailable()) {
            return null;
        }

        $key = $productId . ':' . $idMargen . ':' . $sucursalId;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $product = Database::first(
            'SELECT p.id, p.nombre, p.part_number, p.impuestos, p.id_subcategoria, p.id_canon,
                    su.sujeto, c.canon
             FROM productos p
             LEFT JOIN subcategorias su ON su.id = p.id_subcategoria
             LEFT JOIN canon c ON c.id = p.id_canon
             WHERE p.id = :id AND p.estado <> 4
             LIMIT 1',
            ['id' => $productId]
        );

        if ($product === null) {
            return self::$cache[$key] = null;
        }

        // Coste del mayorista: elige el almacen mas barato con tarifa para ese
        // margen (misma consulta que getCosto() de idirecto).
        $cost = Database::first(
            'SELECT s.costo, s.id_almacen, a.tipo
             FROM stock s
             INNER JOIN almacenes a ON a.id = s.id_almacen
             INNER JOIN tarifas t ON (t.id_almacen = a.id AND t.id_margen = :margen)
             WHERE s.part_number = :part_number
               AND s.costo > 0 AND s.activo = 1 AND s.stock > 0
               AND a.tipo IN (0, 1, 3, 4, 5)
               AND a.sucursal_id = :sucursal
             ORDER BY s.costo ASC LIMIT 1',
            ['part_number' => (string) $product['part_number'], 'margen' => $idMargen, 'sucursal' => $sucursalId]
        );

        // Precio de tarifa: el mas barato con stock; si no hay stock, el mas
        // barato publicado (asi el pedido se puede preparar igualmente).
        $price = Database::first(
            'SELECT pr.precio, pr.id_almacen, pr.id_stock
             FROM precios pr
             INNER JOIN stock s ON s.id = pr.id_stock
             INNER JOIN almacenes a ON a.id = s.id_almacen
             WHERE pr.id_producto = :id AND pr.id_margen = :margen AND pr.precio > 0
               AND s.stock > 0 AND s.activo = 1 AND a.tipo <> 2
             ORDER BY pr.precio ASC LIMIT 1',
            ['id' => $productId, 'margen' => $idMargen]
        );
        $tarifaConocida = $price !== null;

        if ($price === null) {
            $price = Database::first(
                'SELECT pr.precio, pr.id_almacen, pr.id_stock
                 FROM precios pr
                 WHERE pr.id_producto = :id AND pr.id_margen = :margen AND pr.precio > 0
                 ORDER BY pr.precio ASC LIMIT 1',
                ['id' => $productId, 'margen' => $idMargen]
            );
        }

        $coste = (float) ($cost['costo'] ?? 0);
        $precio = (float) ($price['precio'] ?? 0);

        if ($coste <= 0 && $precio <= 0) {
            return self::$cache[$key] = null;
        }
        if ($precio <= 0) {
            // Sin tarifa publicada: se usa el coste para no enviar un pedido a 0.
            $precio = $coste;
        }
        if ($coste <= 0) {
            $coste = $precio;
        }

        $iva = (float) ($product['impuestos'] ?? 0);
        if ($iva <= 0) {
            $iva = $fallbackTax;
        }

        $almacen = (int) ($price['id_almacen'] ?? 0);
        if ($almacen <= 0) {
            $almacen = (int) ($cost['id_almacen'] ?? 0);
        }

        return self::$cache[$key] = [
            'product_id'      => (int) $product['id'],
            'nombre'          => (string) $product['nombre'],
            'sku'             => (string) ($product['part_number'] ?? ''),
            'precio'          => round($precio, 2),
            'coste'           => round($coste, 2),
            'ganancia'        => round($precio - $coste, 2),
            'id_almacen'      => $almacen > 0 ? $almacen : null,
            'id_stock'        => (int) ($price['id_stock'] ?? 0) ?: null,
            // Tipo del almacen que sirve: 0 y 4 son los que reservan stock.
            'tipo_almacen'    => (int) ($cost['tipo'] ?? 0),
            'iva'             => round($iva, 2),
            'sujeto'          => (int) ($product['sujeto'] ?? 0),
            'canon'           => round((float) ($product['canon'] ?? 0), 2),
            'tarifa_conocida' => $tarifaConocida,
        ];
    }
}
