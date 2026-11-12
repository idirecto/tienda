<?php

declare(strict_types=1);

namespace Tienda\Core\Idirecto;

use Tienda\Core\Database;

/**
 * Resuelve la tarifa del mayorista que corresponde mostrar a un producto.
 *
 * Replica `BaseController::getPrecioInternal()` de idirecto:
 *
 *   - solo entran almacenes con stock, activos, con tarifa para el margen y
 *     la sucursal, y de tipo 0, 1, 3, 4 o 5 (el tipo 2 no se muestra nunca);
 *   - se queda con el precio mas barato de cada tipo;
 *   - aplica la prioridad por tipo y las tolerancias de idirecto.
 *
 * No aplica el beneficio de la tienda ni el IVA: de eso se encarga
 * `Catalog` para que el calculo final siga siendo el de esta plataforma.
 */
final class PriceResolver
{
    /** Tipos de almacen que idirecto usa para el precio mostrado. */
    private const TIPOS = [0, 1, 3, 4, 5];

    /** Tamano de lote para no construir un `IN` gigante. */
    private const LOTE = 1000;

    /** Tope de entradas en la cache por peticion (evita crecer sin control). */
    private const MAX_CACHE = 5000;

    private static array $cache = [];
    private static ?bool $available = null;
    private static ?bool $hasLogistica = null;

    /** Limpia la cache de la peticion actual. */
    public static function resetCache(): void
    {
        self::$cache = [];
    }

    public static function isAvailable(): bool
    {
        if (self::$available !== null) {
            return self::$available;
        }

        foreach (['productos', 'precios', 'stock', 'almacenes', 'tarifas'] as $tabla) {
            if (!Database::tableExists($tabla)) {
                return self::$available = false;
            }
        }

        return self::$available = true;
    }

    /**
     * Tarifa y almacen elegidos para un producto.
     *
     * @return array{id_producto:int,precio:float,coste:float,id_almacen:int,
     *               id_stock:int,tipo_almacen:int,stock_almacen:int}|null
     */
    public static function resolve(int $productId, int $idMargen, int $sucursalId = 1): ?array
    {
        $productId = max(0, $productId);
        if ($productId <= 0) {
            return null;
        }

        $resueltos = self::resolveMany([$productId], $idMargen, $sucursalId);

        return $resueltos[$productId] ?? null;
    }

    /**
     * Resuelve varios productos en lotes.
     *
     * @param array<int,int|string> $productIds
     * @return array<int,array> Mapa id_producto => fila resuelta
     */
    public static function resolveMany(array $productIds, int $idMargen, int $sucursalId = 1): array
    {
        if ($idMargen <= 0 || !self::isAvailable()) {
            return [];
        }

        $ids = [];
        foreach ($productIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $sucursalId = max(1, $sucursalId);
        $salida = [];
        $pendientes = [];

        foreach (array_keys($ids) as $id) {
            $clave = self::key($id, $idMargen, $sucursalId);
            if (array_key_exists($clave, self::$cache)) {
                if (self::$cache[$clave] !== null) {
                    $salida[$id] = self::$cache[$clave];
                }
                continue;
            }
            $pendientes[] = $id;
        }

        foreach (array_chunk($pendientes, self::LOTE) as $lote) {
            $porProducto = [];
            foreach (self::candidates($lote, $idMargen, $sucursalId) as $fila) {
                $pid = (int) $fila['id_producto'];
                $tipo = (int) $fila['tipo'];
                if (!isset($porProducto[$pid][$tipo])) {
                    $porProducto[$pid][$tipo] = $fila;
                }
            }

            foreach ($lote as $id) {
                $elegido = isset($porProducto[$id]) ? self::pick($porProducto[$id]) : null;
                $clave = self::key($id, $idMargen, $sucursalId);
                if (count(self::$cache) < self::MAX_CACHE || array_key_exists($clave, self::$cache)) {
                    self::$cache[$clave] = $elegido;
                }
                if ($elegido !== null) {
                    $salida[$id] = $elegido;
                }
            }
        }

        return $salida;
    }

    /**
     * Candidatos de idirecto para un lote de productos.
     *
     * El `umv` se resuelve con una subconsulta correlacionada: la tabla tiene
     * un indice unico por `id_producto` y asi el lote de una pagina pasa de
     * ~1 s a milisegundos.
     *
     * @param array<int,int> $ids
     * @return array<int,array>
     */
    private static function candidates(array $ids, int $idMargen, int $sucursalId): array
    {
        $params = [];
        $ph = [];
        foreach ($ids as $i => $id) {
            $clave = 'p' . $i;
            $ph[] = ':' . $clave;
            $params[$clave] = (int) $id;
        }

        $umv = self::hasLogistica()
            ? 'COALESCE((SELECT MAX(pl.umv) FROM productos_logistica pl WHERE pl.id_producto = pr.id_producto), 1)'
            : '1';

        $sql = 'SELECT pr.id_producto, a.tipo, pr.precio, pr.id_almacen, pr.id_stock,
                       s.stock AS stock_almacen, s.costo AS coste
                FROM precios pr
                INNER JOIN stock s ON s.id = pr.id_stock
                INNER JOIN almacenes a ON a.id = s.id_almacen
                INNER JOIN tarifas t ON (t.id_almacen = a.id AND t.id_margen = ' . (int) $idMargen . ')
                WHERE pr.id_producto IN (' . implode(',', $ph) . ')
                  AND pr.id_margen = ' . (int) $idMargen . '
                  AND pr.sucursal_id = ' . (int) $sucursalId . '
                  AND pr.precio > 0
                  AND pr.id_almacen = a.id
                  AND s.stock > 0
                  AND s.stock >= ' . $umv . '
                  AND s.activo = 1
                  AND a.tipo IN (' . implode(',', self::TIPOS) . ')
                ORDER BY pr.id_producto, a.tipo, pr.precio ASC, pr.id ASC';

        return Database::select($sql, $params);
    }

    /**
     * Aplica la prioridad de idirecto a los candidatos de un producto.
     *
     * `$porTipo` llega con una fila por tipo (la mas barata de cada uno).
     * Se reproduce el algoritmo de `getPrecioInternal()`:
     * tipos 1, 3, 4, 0 por tolerancia; tipo 5 solo si no hay otro.
     */
    private static function pick(array $porTipo): ?array
    {
        $n = count($porTipo);
        if ($n === 0) {
            return null;
        }
        if ($n === 1) {
            return self::normalize(reset($porTipo));
        }

        $suma = 0.0;
        foreach ($porTipo as $fila) {
            $suma += (float) $fila['precio'];
        }
        $media = $suma / $n;

        if ($media <= 25) {
            $d3 = 0.5;
            $d4 = 0.75;
            $d0 = 1.0;
            $porcentaje = false;
        } elseif ($media <= 50) {
            $d3 = 0.5;
            $d4 = 1.0;
            $d0 = 2.0;
            $porcentaje = false;
        } else {
            $d3 = 1.02;
            $d4 = 1.03;
            $d0 = 1.04;
            $porcentaje = true;
        }

        $le = static function (float $a, float $b, float $d) use ($porcentaje): bool {
            return $porcentaje ? ($a <= $b * $d) : ($a <= $b + $d);
        };
        $ge = static function (float $a, float $b, float $d) use ($porcentaje): bool {
            return $porcentaje ? ($a * $d <= $b) : ($a + $d <= $b);
        };

        $p = static fn (int $tipo): ?float => isset($porTipo[$tipo]) ? (float) $porTipo[$tipo]['precio'] : null;
        $p1 = $p(1);
        $p0 = $p(0);
        $p3 = $p(3);
        $p4 = $p(4);
        $p5 = $p(5);

        if ($p1 !== null && $p3 !== null && $p4 !== null && $p0 !== null) {
            if ($le($p1, $p3, $d3) && $le($p1, $p4, $d4) && $le($p1, $p0, $d0)) {
                return self::normalize($porTipo[1]);
            }
            if ($ge($p3, $p1, $d3) && $le($p3, $p4, $d4) && $le($p3, $p0, $d0)) {
                return self::normalize($porTipo[3]);
            }
            if ($ge($p4, $p1, $d4) && $le($p4, $p3, $d3) && $le($p4, $p0, $d0)) {
                return self::normalize($porTipo[4]);
            }
            return self::normalize($porTipo[0]);
        }

        if ($p1 !== null && $p3 !== null && $p4 !== null) {
            if ($le($p1, $p3, $d3) && $le($p1, $p4, $d4)) {
                return self::normalize($porTipo[1]);
            }
            if ($ge($p3, $p1, $d3) && $le($p3, $p4, $d4)) {
                return self::normalize($porTipo[3]);
            }
            return self::normalize($porTipo[4]);
        }

        if ($p1 !== null && $p4 !== null && $p0 !== null) {
            if ($le($p1, $p4, $d4) && $le($p1, $p0, $d0)) {
                return self::normalize($porTipo[1]);
            }
            if ($ge($p4, $p1, $d4) && $le($p4, $p0, $d0)) {
                return self::normalize($porTipo[4]);
            }
            return self::normalize($porTipo[0]);
        }

        if ($p3 !== null && $p4 !== null && $p0 !== null) {
            if ($le($p3, $p4, $d4) && $le($p3, $p0, $d0)) {
                return self::normalize($porTipo[3]);
            }
            if ($le($p4, $p3, $d3) && $le($p4, $p0, $d0)) {
                return self::normalize($porTipo[4]);
            }
            return self::normalize($porTipo[0]);
        }

        if ($p1 !== null && $p3 !== null && $p0 !== null) {
            if ($le($p1, $p3, $d3) && $le($p1, $p0, $d0)) {
                return self::normalize($porTipo[1]);
            }
            if ($ge($p3, $p1, $d3) && $le($p3, $p0, $d0)) {
                return self::normalize($porTipo[3]);
            }
            return self::normalize($porTipo[0]);
        }

        if ($p1 !== null && $p3 !== null) {
            return self::normalize($le($p1, $p3, $d3) ? $porTipo[1] : $porTipo[3]);
        }
        if ($p1 !== null && $p0 !== null) {
            return self::normalize($le($p1, $p0, $d0) ? $porTipo[1] : $porTipo[0]);
        }
        if ($p3 !== null && $p0 !== null) {
            return self::normalize($le($p3, $p0, $d0) ? $porTipo[3] : $porTipo[0]);
        }
        if ($p1 !== null && $p4 !== null) {
            return self::normalize($le($p1, $p4, $d4) ? $porTipo[1] : $porTipo[4]);
        }
        if ($p3 !== null && $p4 !== null) {
            return self::normalize($le($p3, $p4, $d4) ? $porTipo[3] : $porTipo[4]);
        }
        if ($p4 !== null && $p0 !== null) {
            return self::normalize($le($p4, $p0, $d0) ? $porTipo[4] : $porTipo[0]);
        }

        if ($p5 !== null && $p0 !== null) {
            return self::normalize($porTipo[0]);
        }
        if ($p5 !== null && $p1 !== null) {
            return self::normalize($porTipo[1]);
        }
        if ($p5 !== null && $p3 !== null) {
            return self::normalize($porTipo[3]);
        }
        if ($p5 !== null && $p4 !== null) {
            return self::normalize($porTipo[4]);
        }

        return self::normalize($porTipo[array_key_first($porTipo)]);
    }

    /** Normaliza la fila elegida para que el resto del codigo no tenga sorpresas. */
    private static function normalize(array $fila): array
    {
        return [
            'id_producto'   => (int) $fila['id_producto'],
            'precio'        => round((float) $fila['precio'], 2),
            'coste'         => round((float) $fila['coste'], 2),
            'id_almacen'    => (int) $fila['id_almacen'],
            'id_stock'      => (int) $fila['id_stock'],
            'tipo_almacen'  => (int) $fila['tipo'],
            'stock_almacen' => max(0, (int) $fila['stock_almacen']),
        ];
    }

    private static function key(int $id, int $idMargen, int $sucursalId): string
    {
        return $id . ':' . $idMargen . ':' . $sucursalId;
    }

    /** `productos_logistica` es opcional: si falta, no se filtra por umv. */
    private static function hasLogistica(): bool
    {
        if (self::$hasLogistica === null) {
            self::$hasLogistica = Database::tableExists('productos_logistica');
        }

        return self::$hasLogistica;
    }
}
