<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Models\Catalog;
use Tienda\Models\OwnProduct;

/**
 * Carrito de la tienda (el de su cliente final).
 *
 * En la sesion solo se guarda QUE producto y CUANTA cantidad: el precio, el
 * stock y el nombre se leen del catalogo cada vez que se pinta el carrito. Asi el
 * cliente nunca paga un precio viejo y no hay que preocuparse por re-preciar el
 * carrito cuando la tienda cambia su beneficio.
 *
 * El carrito esta separado por tienda (`$_SESSION['cart'][$storeId]`): la misma
 * sesion puede estar comprando en dos tiendas distintas sin mezclar nada.
 *
 * Estructura en sesion:  [ 'c<id>' => cantidad, 'o<id>' => cantidad ]
 *   c = producto del catalogo central, o = producto propio de la tienda.
 */
final class Cart
{
    private const SESSION_KEY = 'cart';

    /** Tope de unidades por linea (evita pedidos absurdos por un fallo de JS). */
    public const MAX_QTY = 99;

    /**
     * Anade unidades de un producto.
     *
     * @return array{ok:bool,message:string}
     */
    public static function add(int $storeId, string $source, int $productId, int $qty = 1): array
    {
        $qty = max(1, min(self::MAX_QTY, $qty));
        $product = self::loadProduct($storeId, $source, $productId);

        if ($product === null) {
            // Queda en el log de compras (canal `compras`): permite ver intentos
            // sobre productos que ya no estan disponibles.
            Logger::warning('compras', 'Carrito: producto no disponible', [
                'store_id'   => $storeId,
                'source'     => $source,
                'product_id' => $productId,
                'qty'        => $qty,
            ]);

            return ['ok' => false, 'message' => 'Ese producto ya no esta disponible.'];
        }

        $key = self::key($source, $productId);
        $actual = (int) ($_SESSION[self::SESSION_KEY][$storeId][$key] ?? 0);
        $nueva = min($actual + $qty, self::MAX_QTY);

        // Si la tienda publica el stock (producto del catalogo), no se puede
        // pedir mas de lo que hay: se avisa en vez de aceptarlo en silencio.
        $stock = $product['stock_total'] ?? null;
        if ($stock !== null && (int) $stock > 0 && $nueva > (int) $stock) {
            $nueva = (int) $stock;
            $_SESSION[self::SESSION_KEY][$storeId][$key] = $nueva;

            Logger::notice('compras', 'Carrito: cantidad recortada por stock', [
                'store_id'   => $storeId,
                'source'     => $source,
                'product_id' => $productId,
                'pedido'     => $actual + $qty,
                'aplicado'   => $nueva,
            ]);

            return [
                'ok'      => true,
                'message' => 'Solo quedan ' . $nueva . ' unidades de ' . $product['name'] . '.',
            ];
        }

        $_SESSION[self::SESSION_KEY][$storeId][$key] = $nueva;

        return ['ok' => true, 'message' => $product['name'] . ' anadido al carrito.'];
    }

    /**
     * Guarda las cantidades enviadas desde el carrito (0 = quitar la linea).
     *
     * Respeta el stock real: si la tienda publica unidades (catalogo central y
     * productos propios con stock > 0) no deja subir de ahi. Devuelve los avisos
     * para poder explicar por que no se ha aplicado la cantidad pedida.
     *
     * @return array<int,string>
     */
    public static function updateQuantities(int $storeId, array $quantities): array
    {
        $warnings = [];

        foreach ($quantities as $key => $qty) {
            $key = (string) $key;
            if (!array_key_exists($key, $_SESSION[self::SESSION_KEY][$storeId] ?? [])) {
                continue;
            }
            $qty = (int) $qty;
            if ($qty <= 0) {
                unset($_SESSION[self::SESSION_KEY][$storeId][$key]);
                continue;
            }

            $qty = min($qty, self::MAX_QTY);

            [$source, $productId] = self::parseKey($key);
            if ($source !== null) {
                $product = self::loadProduct($storeId, $source, $productId);
                $stock = $product['stock_total'] ?? null;
                if ($stock !== null && (int) $stock > 0 && $qty > (int) $stock) {
                    $qty = (int) $stock;
                    $warnings[] = 'Solo quedan ' . $qty . ' unidades de ' . $product['name'] . '.';
                }
            }

            $_SESSION[self::SESSION_KEY][$storeId][$key] = max(1, $qty);
        }

        return $warnings;
    }

    public static function remove(int $storeId, string $key): void
    {
        unset($_SESSION[self::SESSION_KEY][$storeId][$key]);
    }

    public static function clear(int $storeId): void
    {
        unset($_SESSION[self::SESSION_KEY][$storeId]);
    }

    /** Numero de unidades (para el contador de la cabecera). */
    public static function count(int $storeId): int
    {
        return array_sum(array_map('intval', $_SESSION[self::SESSION_KEY][$storeId] ?? []));
    }

    /** Hay lineas en el carrito. */
    public static function isEmpty(int $storeId): bool
    {
        return self::count($storeId) === 0;
    }

    /**
     * Lineas del carrito con los precios y el stock de AHORA.
     *
     * Los productos que ya no se pueden comprar (borrados, ocultos o sin stock)
     * se descartan del carrito y se devuelven en `$dropped` para poder avisar.
     *
     * @param array<int,string>|null $dropped Nombres de productos descartados
     * @return array<int,array<string,mixed>>
     */
    public static function items(int $storeId, ?array &$dropped = null): array
    {
        $dropped = [];
        $items = [];

        foreach (self::raw($storeId) as $key => $qty) {
            [$source, $productId] = self::parseKey($key);
            if ($source === null) {
                unset($_SESSION[self::SESSION_KEY][$storeId][$key]);
                continue;
            }

            $product = self::loadProduct($storeId, $source, $productId);
            if ($product === null || $product['price_net'] === null) {
                $dropped[] = (string) ($product['name'] ?? 'Producto no disponible');
                unset($_SESSION[self::SESSION_KEY][$storeId][$key]);
                continue;
            }

            $qty = max(1, min(self::MAX_QTY, (int) $qty));

            $items[] = $product + [
                'key'        => $key,
                'qty'        => $qty,
                'line_net'   => round((float) $product['price_net'] * $qty, 2),
                'line_total' => round((float) $product['price'] * $qty, 2),
            ];
        }

        return $items;
    }

    /**
     * Totales del carrito (importes de la tienda: en el storefront, con IVA).
     *
     * @param array<int,array<string,mixed>> $items
     * @return array{subtotal_net:float,tax_total:float,subtotal:float,shipping:float,total:float,units:int,lines:int}
     */
    public static function totals(array $items, array $store): array
    {
        $subtotalNet = 0.0;
        $taxTotal = 0.0;
        $subtotal = 0.0;
        $units = 0;

        foreach ($items as $item) {
            $qty = (int) $item['qty'];
            $units += $qty;
            $subtotalNet += (float) $item['price_net'] * $qty;
            $subtotal += (float) $item['price'] * $qty;
            // El IVA sale de la diferencia, para que la suma cuadre al centimo
            // con el precio que ha visto el cliente.
            $taxTotal += (float) $item['price'] * $qty - (float) $item['price_net'] * $qty;
        }

        $subtotalNet = round($subtotalNet, 2);
        $subtotal = round($subtotal, 2);
        $taxTotal = round($subtotal - $subtotalNet, 2);
        $shipping = Shipping::cost($store, $subtotal);

        return [
            'subtotal_net' => $subtotalNet,
            'tax_total'    => $taxTotal,
            'subtotal'     => $subtotal,
            'shipping'     => $shipping,
            'total'        => round($subtotal + $shipping, 2),
            'units'        => $units,
            'lines'        => count($items),
        ];
    }

    /**
     * Estado completo del carrito, listo para pintar el mini-carrito o para
     * responder por AJAX.
     *
     * Usa exactamente las mismas funciones que la pagina del carrito
     * (`items()` + `totals()` + `count()`), asi que el mini-carrito nunca puede
     * desincronizarse: no hay estado paralelo ni datos de ejemplo.
     *
     * @param array<string,mixed>    $store   Fila de la tienda (gastos de envio)
     * @param array<int,string>|null $dropped Nombres de productos descartados
     * @return array{items:array,totals:array,count:int,dropped:array}
     */
    public static function state(int $storeId, array $store, ?array &$dropped = null): array
    {
        $items = self::items($storeId, $dropped);
        $totals = self::totals($items, $store);

        return [
            'items'   => $items,
            'totals'  => $totals,
            'count'   => self::count($storeId),
            'dropped' => $dropped,
        ];
    }

    /**
     * Datos que necesita la vista del mini-carrito (y la respuesta AJAX).
     *
     * Reune el estado real del carrito con los avisos de envio y si la tienda
     * admite pedidos, para que el partial no tenga que consultar nada.
     *
     * @param array<string,mixed> $store Fila de la tienda
     * @return array<string,mixed>
     */
    public static function miniViewData(int $storeId, array $store): array
    {
        $dropped = [];
        $state = self::state($storeId, $store, $dropped);
        $totals = $state['totals'];

        return [
            'items'       => $state['items'],
            'totals'      => $totals,
            'count'       => $state['count'],
            'dropped'     => $dropped,
            'maxQty'      => self::MAX_QTY,
            'freeFrom'    => Shipping::hasFreeFrom($store),
            'missingFree' => Shipping::missingForFree($store, (float) $totals['subtotal']),
            'allowOrders' => (int) ($store['allow_orders'] ?? 1) === 1,
        ];
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    /** Cantidades guardadas en sesion. */
    private static function raw(int $storeId): array
    {
        $cart = $_SESSION[self::SESSION_KEY][$storeId] ?? [];

        return is_array($cart) ? $cart : [];
    }

    public static function key(string $source, int $productId): string
    {
        return ($source === 'own' ? 'o' : 'c') . $productId;
    }

    /** @return array{0:?string,1:int} */
    private static function parseKey(string $key): array
    {
        if (!preg_match('/^([co])(\d+)$/', $key, $m)) {
            return [null, 0];
        }

        return [$m[1] === 'o' ? 'own' : 'catalog', (int) $m[2]];
    }

    /**
     * Producto comprable, normalizado para el carrito.
     *
     * Los precios salen del catalogo con el contexto de la tienda ya fijado
     * (`Catalog::forStore()`): en el storefront vienen con IVA y `price_net` es la
     * base que se guarda en el pedido.
     */
    private static function loadProduct(int $storeId, string $source, int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        if ($source === 'own') {
            $own = OwnProduct::findForStore($productId, $storeId);
            if ($own === null || (int) $own['status'] !== 1) {
                return null;
            }
            $price = (float) ($own['sale_price'] ?: $own['price']);
            if ($price <= 0) {
                return null;
            }
            $tax = (float) ($own['tax_rate'] ?? 21);
            $stock = (int) ($own['stock'] ?? 0);

            return [
                'source'      => 'own',
                'product_id'  => $productId,
                'name'        => (string) $own['name'],
                'sku'         => (string) ($own['sku'] ?? ''),
                'price'       => round($price, 2),
                'price_net'   => round($price / (1 + $tax / 100), 2),
                'tax_rate'    => round($tax, 2),
                'image_url'   => $own['image_url'] ?: null,
                // Los productos propios todavia no tienen ficha publica.
                'url'         => null,
                // El stock propio lo lleva la tienda: siempre se puede pedir.
                'stock_total' => $stock > 0 ? $stock : null,
                'is_stock'    => $stock > 0,
            ];
        }

        $product = Catalog::find($productId);
        if ($product === null || empty($product['in_stock'])) {
            return null;
        }

        // Stock real del mayorista: sin esto el carrito no podia recortar las
        // unidades al anadir. `stockTotal()` devuelve `null` si no se puede
        // consultar, y entonces se mantiene el tope global.
        $stockTotal = Catalog::stockTotal($productId);

        return [
            'source'      => 'catalog',
            'product_id'  => $productId,
            'name'        => (string) $product['nombre'],
            'sku'         => (string) ($product['part_number'] ?? ''),
            'price'       => (float) $product['price_final'],
            'price_net'   => (float) $product['price_net'],
            'tax_rate'    => (float) ($product['tax_rate'] ?? 21),
            'image_url'   => $product['image_url'] ?? null,
            'url'         => product_url($product),
            'stock_total' => $stockTotal,
            'is_stock'    => true,
        ];
    }
}
