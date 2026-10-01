<?php

declare(strict_types=1);

namespace Tienda\Core;

use Tienda\Models\Customer;
use Tienda\Models\CustomerAddress;
use Tienda\Models\Order;

/**
 * Registro del pedido que hace un cliente en la web de la tienda.
 *
 * Es el equivalente a lo que hace puntobyze al cerrar una compra: se guardan las
 * direcciones (facturacion y envio), la forma de pago elegida, el comentario del
 * cliente y las lineas, y el pedido nace en `mt_orders` con sus `mt_order_items`.
 *
 * A partir de ahi el pedido se ve en el panel como cualquier otro y se puede
 * enviar al mayorista por lineas (`OrderGateway`), que es lo que escribe en las
 * tablas compartidas con idirecto y puntobyze (`pedidos_addr`, `pedidos` y
 * `pedidos_det`) con la direccion real de este cliente.
 *
 * Todo lo que se guarda viene de la base de datos (precios y stock del carrito
 * recalculados), nunca de importes que mande el navegador.
 */
final class Checkout
{
    /** Formas de pago que acepta la tienda, en orden y con su explicacion. */
    public static function paymentMethods(array $store): array
    {
        $methods = [];

        if (!empty($store['pay_transfer'])) {
            $methods[Order::PAY_TRANSFER] = [
                'label' => 'Transferencia bancaria',
                'note'  => 'Te damos el numero de cuenta al terminar. Preparamos el pedido cuando recibimos el ingreso.',
            ];
        }
        if (!empty($store['pay_cod'])) {
            $methods[Order::PAY_COD] = [
                'label' => 'Contra reembolso',
                'note'  => 'Pagas al repartidor cuando recibes el pedido.',
            ];
        }
        if (!empty($store['pay_pickup'])) {
            $methods[Order::PAY_PICKUP] = [
                'label' => 'Recogida en tienda',
                'note'  => 'Recoges y pagas en la tienda: ' . self::pickupAddress($store) . '.',
            ];
        }

        return $methods;
    }

    /** Direccion de recogida (los datos que la tienda tiene en Ajustes). */
    public static function pickupAddress(array $store): string
    {
        $parts = array_filter([
            trim((string) ($store['address'] ?? '')),
            trim((string) ($store['postal_code'] ?? '') . ' ' . (string) ($store['city'] ?? '')),
            trim((string) ($store['province'] ?? '')),
        ], static fn (string $value): bool => $value !== '');

        return $parts === [] ? 'la tienda' : implode(', ', $parts);
    }

    /**
     * Crea el pedido.
     *
     * @param array $store    Fila de `mt_stores`
     * @param array $customer Fila de `mt_customers` (el cliente que compra)
     * @param array $ship     Direccion de envio ya normalizada
     * @param array $bill     Direccion de facturacion (si viene vacia = la de envio)
     * @param array $data     Forma de pago y comentario
     * @param array<int,array<string,mixed>> $items Lineas del carrito
     * @param array $totals   Totales calculados por el carrito
     * @return array{order_id:int,code:string}
     * @throws ValidationException si falta algo o el pedido no se puede registrar
     */
    public static function place(
        array $store,
        array $customer,
        array $ship,
        array $bill,
        array $data,
        array $items,
        array $totals
    ): array {
        $storeId = (int) $store['id'];

        if (empty($store['allow_orders'])) {
            throw new ValidationException('Esta tienda no esta aceptando pedidos en este momento.');
        }
        if ($items === []) {
            throw new ValidationException('Tu carrito esta vacio.');
        }

        $methods = self::paymentMethods($store);
        $method = (string) ($data['payment_method'] ?? '');
        if (!isset($methods[$method])) {
            throw new ValidationException('Elige una forma de pago valida.');
        }

        // Facturacion: si no viene nada, se factura a la direccion de envio.
        $ship = self::normalizeAddress($ship);
        $bill = self::normalizeAddress($bill);
        if ($bill['name'] === '' && $bill['address'] === '') {
            $bill = $ship;
        }

        if ($ship['name'] === '' || $ship['address'] === '') {
            throw new ValidationException('La direccion de envio necesita al menos el nombre y la direccion.');
        }
        if (trim((string) ($customer['email'] ?? '')) === '') {
            throw new ValidationException('Necesitamos un email para poder avisarte del pedido.');
        }

        $lines = [];
        foreach ($items as $item) {
            $qty = max(1, min(Cart::MAX_QTY, (int) ($item['qty'] ?? 1)));
            $lines[] = [
                'source'         => (string) $item['source'],
                'product_id'     => (int) $item['product_id'],
                'sku'            => (string) ($item['sku'] ?? ''),
                'name'           => (string) $item['name'],
                'qty'            => $qty,
                // Base sin IVA: los totales suman el IVA de cada linea.
                'price_customer' => (float) $item['price_net'],
                'tax_rate'       => (float) ($item['tax_rate'] ?? 21),
            ];
        }

        $orderId = Order::createWithItems($storeId, [
            'customer_id'    => (int) $customer['id'],
            'status'         => Order::STATUS_ACTIVE,
            'customer_name'  => $ship['name'] !== '' ? $ship['name'] : (string) $customer['name'],
            'customer_email' => mb_substr((string) $customer['email'], 0, 180),
            'customer_phone' => self::firstFilled([$ship['phone'], $ship['mobile'], (string) ($customer['phone'] ?? '')]),
            'customer_tax_id' => $ship['tax_id'],

            'ship_name'        => $ship['name'],
            'ship_tax_id'      => $ship['tax_id'],
            'ship_address'     => $ship['address'],
            'ship_detail'      => $ship['detail'],
            'ship_city'        => $ship['city'],
            'ship_province'    => $ship['province'],
            'ship_postal_code' => $ship['postal_code'],
            'ship_country'     => $ship['country'],
            'ship_phone'       => $ship['phone'],
            'ship_mobile'      => $ship['mobile'],
            'ship_country_id'  => $ship['id_pais'],
            'ship_province_id' => $ship['id_provincia'],
            'ship_poblacion_id' => $ship['id_poblacion'],

            'bill_name'        => $bill['name'],
            'bill_tax_id'      => $bill['tax_id'],
            'bill_address'     => $bill['address'],
            'bill_detail'      => $bill['detail'],
            'bill_city'        => $bill['city'],
            'bill_province'    => $bill['province'],
            'bill_postal_code' => $bill['postal_code'],
            'bill_country'     => $bill['country'],
            'bill_phone'       => $bill['phone'],

            'customer_note'  => mb_substr(trim((string) ($data['comment'] ?? '')), 0, 1000) ?: null,
            'shipping'       => (float) $totals['shipping'],
            'payment_method' => $method,
            'payment_status' => 0,
        ], $lines);

        // Tarifa del mayorista de cada linea (para la vista previa del envio).
        Order::syncIdirectoPrices($orderId, $store);

        $order = Order::findForStore($orderId, $storeId);

        return ['order_id' => $orderId, 'code' => (string) ($order['code'] ?? '')];
    }

    /**
     * Guarda la direccion usada en el pedido en la libreta del cliente, para no
     * tener que escribirla otra vez. Si ya existe una igual, no la duplica.
     */
    public static function rememberAddress(int $storeId, int $customerId, array $address): int
    {
        $address = self::normalizeAddress($address);

        foreach (CustomerAddress::forCustomer($customerId, $storeId) as $saved) {
            if (mb_strtolower((string) $saved['address']) === mb_strtolower($address['address'])
                && (string) $saved['postal_code'] === $address['postal_code']
                && (string) $saved['city'] === $address['city']
            ) {
                return (int) $saved['id'];
            }
        }

        $isFirst = CustomerAddress::countForCustomer($customerId, $storeId) === 0;

        return CustomerAddress::save($customerId, $storeId, $address + [
            'is_default_ship' => $isFirst,
            'is_default_bill' => $isFirst,
        ]);
    }

    /**
     * Cliente que compra: el de la sesion o (si compra como invitado) uno nuevo
     * con el email y el nombre de la direccion. Los invitados quedan marcados
     * como tales hasta que se registren.
     */
    public static function resolveCustomer(array $store, array $address): array
    {
        $storeId = (int) $store['id'];
        $customer = CustomerAuth::customer($storeId);

        if ($customer !== null) {
            return $customer;
        }

        $email = mb_strtolower(trim((string) ($address['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Necesitamos un email valido para registrar el pedido.');
        }

        $customer = Customer::findOrCreateGuest($storeId, [
            'email' => $email,
            'name'  => (string) ($address['name'] ?? ''),
            'tax_id' => $address['tax_id'] ?? null,
            'phone' => $address['phone'] ?? null,
            'mobile' => $address['mobile'] ?? null,
        ]);

        CustomerAuth::login($storeId, $customer);

        return $customer;
    }

    /** Quita espacios y deja la direccion en el formato que espera el pedido. */
    public static function normalizeAddress(array $address): array
    {
        $trim = static fn (mixed $value): string => trim((string) ($value ?? ''));

        return [
            'email'        => mb_strtolower($trim($address['email'] ?? '')),
            'name'         => mb_substr($trim($address['name'] ?? ''), 0, 150),
            'tax_id'       => mb_substr($trim($address['tax_id'] ?? ''), 0, 30),
            'address'      => mb_substr($trim($address['address'] ?? ''), 0, 250),
            'detail'       => mb_substr($trim($address['detail'] ?? ''), 0, 200),
            'postal_code'  => mb_substr($trim($address['postal_code'] ?? ''), 0, 20),
            'city'         => mb_substr($trim($address['city'] ?? ''), 0, 120),
            'province'     => mb_substr($trim($address['province'] ?? ''), 0, 120),
            'country'      => mb_substr($trim($address['country'] ?? '') !== '' ? $trim($address['country']) : 'Espana', 0, 60),
            'phone'        => mb_substr($trim($address['phone'] ?? ''), 0, 40),
            'mobile'       => mb_substr($trim($address['mobile'] ?? ''), 0, 40),
            'id_pais'      => (int) ($address['id_pais'] ?? 0) ?: null,
            'id_provincia' => (int) ($address['id_provincia'] ?? 0) ?: null,
            'id_poblacion' => (int) ($address['id_poblacion'] ?? 0) ?: null,
            'label'        => mb_substr($trim($address['label'] ?? ''), 0, 60),
        ];
    }

    /** Primer valor no vacio (el telefono del envio, si no el del cliente). */
    private static function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return mb_substr($value, 0, 40);
            }
        }

        return null;
    }
}
