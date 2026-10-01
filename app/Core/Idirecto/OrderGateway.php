<?php

declare(strict_types=1);

namespace Tienda\Core\Idirecto;

use Tienda\Core\Config;
use Tienda\Core\Database;
use Tienda\Core\ValidationException;
use Tienda\Models\Order;
use Tienda\Models\OrderItem;

/**
 * Envio de lineas de un pedido de la tienda al mayorista.
 *
 * Escribe en las tablas del mayorista (`pedidos_addr`, `pedidos` y
 * `pedidos_det`) replicando su propio flujo web
 * (`App\Controllers\pedido_controller::guardaPedido()`):
 *
 *   - `pedidos.web = 1`, `estado = NULL` (activo), `fecha = NOW()`
 *   - `referencia` = `TIENDA-<slug>-<codigo del pedido>` (para localizarlo luego)
 *   - direccion de facturacion = datos de la tienda (los de esta web)
 *   - direccion de envio = la del pedido si la tiene; si no, la de la tienda
 *   - lineas con `costo`, `ganancia`, `impuestos`, `sujeto`, `canon` e `id_almacen`
 *   - elige la tarifa con `precios.id_margen` de la tienda
 *
 * Se envia por LINEA: cada linea que va al mayorista queda marcada en
 * `mt_order_items` (`sent_at` + `idirecto_pedido_id`), asi que un pedido de 4
 * lineas se puede mandar en dos envios de 2 y 2 sin repetir nada.
 *
 * `preview()` hace exactamente los mismos calculos que `send()` pero sin
 * escribir: es lo que se ensena en el panel antes de confirmar.
 */
final class OrderGateway
{
    /**
     * Calcula las lineas y los totales del pedido que se enviaria.
     *
     * @param array $store Fila de `mt_stores`
     * @param array $order Fila de `mt_orders`
     * @param array $items Lineas de `mt_order_items` que se enviarian
     * @return array{lines:array<int,array>,errors:array<int,string>,warnings:array<int,string>,
     *               totals:array{subtotal:float,impuestos:float,total:float},cuenta:array}
     */
    public static function preview(array $store, array $order, array $items): array
    {
        return self::computeLines(Account::forStore($store), $items, $store);
    }

    /**
     * Envia al mayorista las lineas indicadas de un pedido.
     *
     * @param array<int,mixed> $itemIds Ids de lineas (`mt_order_items.id`)
     * @return array{pedido_id:int,referencia:string,lineas:int,subtotal:float,impuestos:float,total:float,completo:bool}
     */
    public static function send(array $store, array $order, array $itemIds): array
    {
        if (!self::isAvailable()) {
            throw new ValidationException('Las tablas de pedidos del mayorista no estan disponibles.');
        }

        $cuenta = Account::forStore($store);
        if (!$cuenta['configurada']) {
            throw new ValidationException(
                'Esta tienda no tiene cuenta del mayorista. Configura "Cuenta en idirecto" en Ajustes antes de enviar pedidos.'
            );
        }

        $items = self::loadItems((int) $order['id'], $itemIds);
        if ($items === []) {
            throw new ValidationException('No has seleccionado ninguna linea pendiente de enviar.');
        }

        $computed = self::computeLines($cuenta, $items, $store);
        if ($computed['errors'] !== []) {
            throw new ValidationException(implode(' ', $computed['errors']));
        }

        // Lineas ya calculadas por id de linea, para poder marcarlas despues.
        $porItem = [];
        foreach ($computed['lines'] as $line) {
            $porItem[(int) $line['item_id']] = $line;
        }

        $reference = self::reference($store, $order);
        $ahora = date('Y-m-d H:i:s');
        $idTienda = (int) $cuenta['id_tienda'];
        $orderId = (int) $order['id'];
        $statusActual = (int) $order['status'];

        $pedidoId = self::transactional(function () use (
            $computed, $cuenta, $store, $order, $reference, $ahora, $porItem, $idTienda, $orderId, $statusActual
        ): int {
            $addrFacturacion = self::insertAddress(self::billingAddress($cuenta, $order));
            $envio = self::shippingAddress($cuenta, $order);
            $addrEnvio = $envio === null ? $addrFacturacion : self::insertAddress($envio);

            $pedidoId = (int) Database::insert('pedidos', [
                'id_tienda'      => $idTienda,
                'id_cliente'     => null,
                'id_direccion'   => null,
                'id_facturacion' => $addrFacturacion,
                'id_envio'       => $addrEnvio,
                'id_comercial'   => $cuenta['id_comercial'],
                'subtotal'       => $computed['totals']['subtotal'],
                'subtotal_p'     => 0,
                'impuestos'      => $computed['totals']['impuestos'],
                'equivalencia'   => 0,
                'total'          => $computed['totals']['total'],
                'total_p'        => 0,
                'estado'         => null,
                'fecha'          => $ahora,
                'plazo'          => $cuenta['plazo'],
                'dias'           => $cuenta['dias'],
                'forma'          => $cuenta['forma'],
                'web'            => 1,
                'referencia'     => $reference,
                // El comentario del cliente (si lo dejo en la web) viaja con el
                // pedido, que es donde el mayorista lo lee al prepararlo.
                'detalles'       => mb_substr(trim((string) ($order['customer_note'] ?? '')), 0, 1000),
                'comentario'     => 'Pedido de la tienda ' . $store['name'] . ' (' . $store['slug'] . ') #' . ($order['code'] ?? $orderId),
                'pagada'         => 0,
                'pagada_p'       => 0,
                'sucursal_id'    => $cuenta['sucursal_id'],
            ]);

            foreach ($porItem as $itemId => $line) {
                self::insertLine($pedidoId, $line);
                self::reserveStock($line);

                Database::update('mt_order_items', $itemId, [
                    'sent_at'            => $ahora,
                    'idirecto_pedido_id' => $pedidoId,
                    'price_idirecto'     => $line['precio'],
                    'cost_idirecto'      => $line['coste'],
                ]);
            }

            Order::markSentToIdirecto($orderId, $idTienda, $reference);

            // Si ya no queda ninguna linea del catalogo por enviar, el pedido
            // pasa a "enviado" (salvo que el tendero lo tenga facturado/cancelado).
            if (OrderItem::pendingSendable($orderId) === [] && in_array($statusActual, Order::SENDABLE_STATUSES, true)) {
                Order::setStatus($orderId, Order::STATUS_SENT);
            }

            return $pedidoId;
        });

        return [
            'pedido_id'  => $pedidoId,
            'referencia' => $reference,
            'lineas'     => count($porItem),
            'subtotal'   => $computed['totals']['subtotal'],
            'impuestos'  => $computed['totals']['impuestos'],
            'total'      => $computed['totals']['total'],
            'completo'   => OrderItem::pendingSendable($orderId) === [],
        ];
    }

    public static function isAvailable(): bool
    {
        return Account::enabled() && Pricing::isAvailable();
    }

    /**
     * Ejecuta la escritura en una transaccion propia; si ya hay una abierta
     * (por ejemplo una comprobacion que luego hace rollback) se une a ella.
     */
    private static function transactional(callable $fn): mixed
    {
        if (Database::pdo()->inTransaction()) {
            return $fn();
        }
        return Database::transaction($fn);
    }

    /** Referencia con la que el pedido se localiza en el mayorista. */
    public static function reference(array $store, array $order): string
    {
        $code = trim((string) ($order['code'] ?? ''));
        if ($code === '') {
            $code = (string) ($order['id'] ?? '0');
        }
        return mb_substr('TIENDA-' . $store['slug'] . '-' . $code, 0, 100);
    }

    // =====================================================================
    // CALCULO
    // =====================================================================

    /**
     * Lineas con la tarifa del mayorista y los totales del pedido.
     *
     * @return array{lines:array<int,array>,errors:array<int,string>,warnings:array<int,string>,
     *               totals:array{subtotal:float,impuestos:float,total:float},cuenta:array}
     */
    private static function computeLines(array $cuenta, array $items, array $store): array
    {
        $lines = [];
        $errors = [];
        $warnings = [];
        $subtotal = 0.0;
        $impuestos = 0.0;
        $canonTotal = 0.0;

        foreach ($items as $item) {
            $nombre = (string) $item['name'];
            $productId = (int) ($item['product_id'] ?? 0);

            if ((string) $item['source'] !== OrderItem::SOURCE_CATALOG) {
                $errors[] = 'El producto propio "' . $nombre . '" no se puede enviar al mayorista.';
                continue;
            }
            if ($productId <= 0) {
                $errors[] = 'La linea "' . $nombre . '" no tiene un producto del catalogo asociado.';
                continue;
            }

            $tarifa = Pricing::forProduct(
                $productId,
                (int) $cuenta['id_margen'],
                (int) $cuenta['sucursal_id'],
                (float) ($store['tax_rate'] ?? 21)
            );
            if ($tarifa === null) {
                $errors[] = 'No se ha encontrado tarifa para "' . $nombre . '" (producto #' . $productId . ').';
                continue;
            }
            if (!$tarifa['tarifa_conocida']) {
                $warnings[] = '"' . $nombre . '" no tiene precio publicado para la tarifa ' . $cuenta['id_margen'] . ': se usa su coste.';
            }

            $qty = max(1, (int) $item['qty']);
            $base = round($qty * $tarifa['precio'], 2);
            $canon = round($qty * $tarifa['canon'], 2);
            $ivaLinea = round($base * $tarifa['iva'] / 100, 2) + round($canon * $tarifa['iva'] / 100, 2);

            $subtotal += $base;
            $impuestos += $ivaLinea;
            $canonTotal += $canon;

            $lines[] = [
                'item_id'      => (int) $item['id'],
                'id_producto'  => $productId,
                'nombre'       => $nombre,
                'qty'          => $qty,
                'precio'       => $tarifa['precio'],
                'coste'        => $tarifa['coste'],
                'ganancia'     => $tarifa['ganancia'],
                'iva'          => $tarifa['iva'],
                'sujeto'       => $tarifa['sujeto'],
                'canon'        => $tarifa['canon'],
                'id_almacen'   => $tarifa['id_almacen'],
                'tipo_almacen' => $tarifa['tipo_almacen'],
                'base'         => $base,
                'importe_iva'  => round($ivaLinea, 2),
            ];
        }

        return [
            'lines'    => $lines,
            'errors'   => $errors,
            'warnings' => $warnings,
            'totals'   => [
                'subtotal'  => round($subtotal, 2),
                'impuestos' => round($impuestos, 2),
                'total'     => round($subtotal + $impuestos + $canonTotal, 2),
            ],
            'cuenta'   => $cuenta,
        ];
    }

    // =====================================================================
    // ESCRITURA
    // =====================================================================

    /** Direccion de facturacion: los datos de la tienda (los de esta web). */
    /**
     * Direccion de facturacion que va a `pedidos_addr`.
     *
     * Si el pedido trae datos de facturacion propios (el cliente de la web puede
     * facturar a otra direccion) se usan esos; lo que falte se completa con los
     * datos de la cuenta del mayorista, que es quien factura.
     */
    private static function billingAddress(array $cuenta, array $order = []): array
    {
        $nombre = trim((string) ($order['bill_name'] ?? ''));
        $direccion = trim((string) ($order['bill_address'] ?? ''));

        if ($nombre === '' && $direccion === '') {
            return [
                'nombre'          => mb_substr($cuenta['nombre'] !== '' ? $cuenta['nombre'] : $cuenta['razon_social'], 0, 150),
                'nombre_sociedad' => mb_substr($cuenta['razon_social'], 0, 150),
                'nif_cif'         => mb_substr($cuenta['nif'], 0, 50),
                'direccion'       => mb_substr($cuenta['direccion'], 0, 250),
                'cp'              => mb_substr($cuenta['cp'], 0, 40),
                'poblacion'       => mb_substr($cuenta['poblacion'], 0, 150),
                'localidad'       => null,
                'pais'            => $cuenta['id_pais'],
                'provincia'       => $cuenta['id_provincia'],
                'telefono'        => mb_substr($cuenta['telefono'], 0, 50),
                'celular'         => null,
                'id_poblacion'    => null,
            ];
        }

        return [
            'nombre'          => mb_substr($nombre !== '' ? $nombre : $cuenta['nombre'], 0, 150),
            'nombre_sociedad' => mb_substr($cuenta['razon_social'], 0, 150),
            'nif_cif'         => mb_substr((string) ($order['bill_tax_id'] ?? '') ?: $cuenta['nif'], 0, 50),
            'direccion'       => mb_substr(self::withDetail($direccion, (string) ($order['bill_detail'] ?? '')), 0, 250),
            'cp'              => mb_substr((string) ($order['bill_postal_code'] ?? ''), 0, 40),
            'poblacion'       => mb_substr((string) ($order['bill_city'] ?? ''), 0, 150),
            'localidad'       => null,
            'pais'            => (int) ($order['ship_country_id'] ?? 0) > 0 ? (int) $order['ship_country_id'] : $cuenta['id_pais'],
            'provincia'       => (int) ($order['ship_province_id'] ?? 0) > 0 ? (int) $order['ship_province_id'] : $cuenta['id_provincia'],
            'telefono'        => mb_substr((string) ($order['bill_phone'] ?? '') ?: $cuenta['telefono'], 0, 50),
            'celular'         => null,
            'id_poblacion'    => null,
        ];
    }

    /**
     * Direccion de envio: la del cliente que ha comprado en la web; si el pedido
     * no trae direccion propia (alta manual del panel) devuelve null y se
     * reutiliza la de facturacion, como antes.
     *
     * Se respetan los ids del mayorista (pais/provincia/poblacion) cuando el
     * cliente los tiene: asi el pedido lleva su direccion real y no la de la
     * tienda. `localidad` va con su email, igual que hace puntobyze, para que el
     * comercial sepa a quien avisar.
     */
    private static function shippingAddress(array $cuenta, array $order): ?array
    {
        $nombre = trim((string) ($order['ship_name'] ?? ''));
        $direccion = trim((string) ($order['ship_address'] ?? ''));

        if ($nombre === '' && $direccion === '') {
            return null;
        }

        $pais = (int) ($order['ship_country_id'] ?? 0) > 0 ? (int) $order['ship_country_id'] : $cuenta['id_pais'];
        $provincia = (int) ($order['ship_province_id'] ?? 0) > 0
            ? (int) $order['ship_province_id']
            : (Account::provinceId((string) ($order['ship_province'] ?? ''), $pais) ?? $cuenta['id_provincia']);

        return [
            'nombre'          => mb_substr($nombre !== '' ? $nombre : $cuenta['nombre'], 0, 150),
            'nombre_sociedad' => mb_substr($cuenta['razon_social'], 0, 150),
            'nif_cif'         => mb_substr((string) ($order['ship_tax_id'] ?? ''), 0, 50),
            'direccion'       => mb_substr(self::withDetail($direccion, (string) ($order['ship_detail'] ?? '')), 0, 250),
            'cp'              => mb_substr((string) ($order['ship_postal_code'] ?? ''), 0, 40),
            'poblacion'       => mb_substr((string) ($order['ship_city'] ?? ''), 0, 150),
            'localidad'       => mb_substr(trim((string) ($order['customer_email'] ?? '')), 0, 100) ?: null,
            'pais'            => $pais,
            'provincia'       => $provincia,
            'telefono'        => mb_substr((string) ($order['ship_phone'] ?? '') ?: (string) ($order['customer_phone'] ?? '') ?: $cuenta['telefono'], 0, 50),
            'celular'         => mb_substr((string) ($order['ship_mobile'] ?? ''), 0, 50) ?: null,
            'id_poblacion'    => (int) ($order['ship_poblacion_id'] ?? 0) ?: null,
        ];
    }

    /** Une la direccion con el portal/escalera/piso, que `pedidos_addr` no tiene. */
    private static function withDetail(string $address, string $detail): string
    {
        $detail = trim($detail);

        return $detail === '' ? $address : $address . ', ' . $detail;
    }

    /** Inserta la direccion en `pedidos_addr` y devuelve su id. */
    private static function insertAddress(array $address): int
    {
        return (int) Database::insert('pedidos_addr', [
            'nombre'          => $address['nombre'] !== '' ? $address['nombre'] : null,
            'nombre_sociedad' => $address['nombre_sociedad'] !== '' ? $address['nombre_sociedad'] : null,
            'nif_cif'         => $address['nif_cif'] !== '' ? $address['nif_cif'] : null,
            'direccion'       => $address['direccion'] !== '' ? $address['direccion'] : null,
            'cp'              => $address['cp'] !== '' ? $address['cp'] : null,
            'poblacion'       => $address['poblacion'] !== '' ? $address['poblacion'] : null,
            'localidad'       => $address['localidad'],
            'pais'            => $address['pais'],
            'provincia'       => $address['provincia'],
            'telefono'        => $address['telefono'] !== '' ? $address['telefono'] : null,
            'celular'         => ($address['celular'] ?? null) !== null && $address['celular'] !== '' ? $address['celular'] : null,
            'id_poblacion'    => $address['id_poblacion'] ?? null,
        ]);
    }

    /** Inserta la linea en `pedidos_det` con los importes del mayorista. */
    private static function insertLine(int $pedidoId, array $line): void
    {
        Database::insert('pedidos_det', [
            'id_pedido'         => $pedidoId,
            'id_producto'       => $line['id_producto'],
            'uds'               => $line['qty'],
            'reserva'           => 0,
            'costo'             => $line['coste'],
            'ganancia'          => $line['ganancia'],
            'impuestos'         => $line['iva'],
            'pagado'            => 0,
            'completo'          => 0,
            'sujeto'            => $line['sujeto'],
            'iva_e'             => 0,
            'canon'             => $line['canon'],
            'id_almacen'        => $line['id_almacen'],
            'id_almacen_compra' => $line['id_almacen'],
            'precio_actual'     => $line['precio'],
            'estado'            => 0,
        ]);
    }

    /**
     * Descuenta stock y suma reserva en el almacen, igual que idirecto.
     *
     * Solo aplica a los almacenes externos (tipo 0 y 4), que son los que se
     * reservan; el resto los gestiona el propio mayorista. Se puede desactivar
     * con IDIRECTO_RESERVE_STOCK=false.
     */
    private static function reserveStock(array $line): void
    {
        if (!(bool) Config::get('idirecto.reserve_stock', true)) {
            return;
        }
        if (!in_array((int) ($line['tipo_almacen'] ?? -1), [0, 4], true)) {
            return;
        }
        $almacen = (int) ($line['id_almacen'] ?? 0);
        if ($almacen <= 0) {
            return;
        }

        $stock = Database::first(
            'SELECT s.id
             FROM stock s
             INNER JOIN productos p ON p.part_number = s.part_number
             WHERE s.id_almacen = :id_almacen AND p.id = :id_producto AND s.stock > 0
             ORDER BY s.costo ASC LIMIT 1',
            ['id_almacen' => $almacen, 'id_producto' => (int) $line['id_producto']]
        );
        if ($stock === null) {
            return;
        }

        $uds = (int) $line['qty'];
        Database::execute(
            'UPDATE stock SET stock = stock - :uds, reserva = reserva + :uds2 WHERE id = :id',
            ['uds' => $uds, 'uds2' => $uds, 'id' => (int) $stock['id']]
        );
    }

    /**
     * Carga las lineas enviadas comprobando que son del pedido, del catalogo y
     * que siguen pendientes (asi un doble clic no duplica lineas en idirecto).
     *
     * @param array<int,mixed> $itemIds
     * @return array<int,array>
     */
    private static function loadItems(int $orderId, array $itemIds): array
    {
        $ids = [];
        foreach ($itemIds as $itemId) {
            $itemId = (int) $itemId;
            if ($itemId > 0) {
                $ids[$itemId] = true;
            }
        }
        if ($ids === [] || $orderId <= 0) {
            return [];
        }

        $placeholders = [];
        $params = ['order_id' => $orderId, 'source' => OrderItem::SOURCE_CATALOG];
        $i = 0;
        foreach (array_keys($ids) as $id) {
            $placeholders[] = ':i' . $i;
            $params['i' . $i] = $id;
            $i++;
        }

        return Database::select(
            'SELECT * FROM mt_order_items
             WHERE order_id = :order_id AND source = :source AND sent_at IS NULL AND product_id IS NOT NULL
               AND id IN (' . implode(',', $placeholders) . ')
             ORDER BY line_no ASC, id ASC',
            $params
        );
    }
}
