<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Cart;
use Tienda\Core\Checkout;
use Tienda\Core\Controller;
use Tienda\Core\CustomerAuth;
use Tienda\Core\Logger;
use Tienda\Core\Session;
use Tienda\Core\ValidationException;
use Tienda\Models\CustomerAddress;
use Tienda\Models\Order;

/**
 * Cierre de la compra: direccion, forma de pago, comentario y registro del pedido.
 *
 * Se puede comprar con cuenta o como invitado: en los dos casos el pedido queda
 * ligado a un cliente (`mt_customers`) con su direccion guardada, para que la
 * proxima vez sea mas rapido y la tienda pueda contactarle.
 */
final class CheckoutController extends Controller
{
    /** Formulario de cierre (dos columnas: direccion + resumen del pedido). */
    public function start(array $params = []): string
    {
        $storeId = $this->tenant->id();
        $store = $this->tenant->toArray();

        if (!$this->tenant->allowOrders()) {
            Session::flash('error', 'Esta tienda no esta aceptando pedidos en este momento.');
            $this->redirect('carrito');
        }

        $dropped = [];
        $items = Cart::items($storeId, $dropped);
        if ($items === []) {
            Session::flash('error', 'Tu carrito esta vacio.');
            $this->redirect('carrito');
        }
        $totals = Cart::totals($items, $store);

        $customer = CustomerAuth::customer($storeId);
        $addresses = $customer === null
            ? []
            : CustomerAddress::forCustomer((int) $customer['id'], $storeId);

        return $this->view($this->themeView('checkout'), [
            'pageTitle'  => 'Finalizar compra',
            'items'      => $items,
            'totals'     => $totals,
            'customer'   => $customer,
            'addresses'  => $addresses,
            'methods'    => Checkout::paymentMethods($store),
            'store'      => $store,
            'selectedAddress' => (int) $this->input('direccion', 0),
        ], 'shop');
    }

    /** Registra el pedido. */
    public function place(array $params = []): string
    {
        $this->requireCsrf('checkout');

        $storeId = $this->tenant->id();
        $store = $this->tenant->toArray();

        if (!$this->tenant->allowOrders()) {
            Session::flash('error', 'Esta tienda no esta aceptando pedidos en este momento.');
            $this->redirect('carrito');
        }

        $dropped = [];
        $items = Cart::items($storeId, $dropped);
        if ($items === []) {
            Session::flash('error', 'Tu carrito esta vacio.');
            $this->redirect('carrito');
        }

        $ship = [];
        $totals = [];
        try {
            // Direccion de envio: la elegida de la libreta o la escrita a mano.
            $ship = $this->shipAddress($storeId);
            $customer = Checkout::resolveCustomer($store, $ship);
            $totals = Cart::totals($items, $store);

            $result = Checkout::place($store, $customer, $ship, $this->billAddress($ship), [
                'payment_method' => (string) $this->input('payment_method', ''),
                'comment'        => (string) $this->input('comment', ''),
            ], $items, $totals);
        } catch (ValidationException $e) {
            // Compra rechazada por datos del cliente (direccion, pago, email...):
            // queda en el log de compras para poder explicar al cliente que fallo.
            Logger::warning('compras', 'Compra rechazada: ' . $e->getMessage(), [
                'store_id' => $storeId,
                'cliente'  => (string) ($ship['email'] ?? ''),
                'lineas'   => count($items),
                'importe'  => (float) ($totals['total'] ?? 0),
                'pago'     => (string) $this->input('payment_method', ''),
            ]);
            Session::flash('error', $e->getMessage());
            $this->redirect('checkout');
        } catch (\Throwable $e) {
            // Fallo inesperado al registrar el pedido: se anota con el contexto
            // de negocio y se deja subir (el front controller respondera 500).
            Logger::critical('compras', 'Fallo al registrar la compra: ' . $e->getMessage(), [
                'store_id'  => $storeId,
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'cliente'   => (string) ($ship['email'] ?? ''),
                'lineas'    => count($items),
                'importe'   => (float) ($totals['total'] ?? 0),
            ]);
            throw $e;
        }

        // Solo cuando el pedido ya esta registrado se guarda la direccion en la
        // libreta del cliente (asi no queda una direccion vacia si algo falla).
        Checkout::rememberAddress($storeId, (int) $customer['id'], $ship);

        Logger::info('compras', 'Pedido registrado', [
            'store_id' => $storeId,
            'pedido'   => (string) $result['code'],
            'order_id' => (int) $result['order_id'],
            'cliente'  => (string) ($customer['email'] ?? ''),
            'lineas'   => count($items),
            'unidades' => (int) ($totals['units'] ?? 0),
            'importe'  => (float) ($totals['total'] ?? 0),
            'pago'     => (string) $this->input('payment_method', ''),
        ]);

        Cart::clear($storeId);
        Session::flash('success', 'Gracias. Hemos registrado tu pedido ' . $result['code'] . '.');

        $this->redirect('checkout/gracias/' . $result['code']);
    }

    /** Pagina de "gracias": resumen del pedido e instrucciones de pago. */
    public function thanks(array $params = []): string
    {
        $storeId = $this->tenant->id();
        $code = (string) ($params['code'] ?? '');
        $order = null;

        $customer = CustomerAuth::customer($storeId);
        if ($customer !== null) {
            $order = Order::findForCustomer($storeId, (int) $customer['id'], $code, true);
        }

        // Si el cliente no tiene sesion (o ha cambiado de navegador), se puede
        // consultar el pedido por su email: no se enseña nada mas.
        if ($order === null) {
            $email = mb_strtolower(trim((string) $this->input('email', '')));
            if ($email !== '') {
                $encontrado = Order::findForCode($storeId, $code);
                if ($encontrado !== null && mb_strtolower((string) $encontrado['customer_email']) === $email) {
                    $order = Order::findForCustomer($storeId, (int) $encontrado['customer_id'], $code, true);
                }
            }
        }

        if ($order === null) {
            http_response_code(404);
            return $this->view('errors/404', ['pageTitle' => 'Pedido no encontrado'], 'shop');
        }

        return $this->view($this->themeView('thanks'), [
            'pageTitle' => 'Pedido ' . $order['code'] . ' registrado',
            'order'     => $order,
            'store'     => $this->tenant->toArray(),
            'askedEmail' => (string) $this->input('email', ''),
        ], 'shop');
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    /**
     * Direccion de envio del formulario: la elegida de la libreta, o una nueva
     * escrita a mano (que ademas se guarda).
     */
    private function shipAddress(int $storeId): array
    {
        $id = (int) $this->input('address_id', 0);
        $customer = CustomerAuth::customer($storeId);

        // La direccion guardada solo vale si es de ESTE cliente.
        if ($id > 0 && $customer !== null) {
            $saved = CustomerAddress::findForCustomer($id, (int) $customer['id'], $storeId);
            if ($saved !== null) {
                $saved['email'] = (string) $customer['email'];

                return $saved;
            }
        }

        return [
            'email'        => (string) $this->input('email', $customer['email'] ?? ''),
            'name'         => (string) $this->input('ship_name', ''),
            'tax_id'       => (string) $this->input('ship_tax_id', ''),
            'address'      => (string) $this->input('ship_address', ''),
            'detail'       => (string) $this->input('ship_detail', ''),
            'postal_code'  => (string) $this->input('ship_postal_code', ''),
            'city'         => (string) $this->input('ship_city', ''),
            'province'     => (string) $this->input('ship_province', ''),
            'country'      => (string) $this->input('ship_country', 'Espana'),
            'phone'        => (string) $this->input('ship_phone', ''),
            'mobile'       => (string) $this->input('ship_mobile', ''),
            'id_pais'      => (int) $this->input('ship_id_pais', 0),
            'id_provincia' => (int) $this->input('ship_id_provincia', 0),
            'id_poblacion' => (int) $this->input('ship_id_poblacion', 0),
        ];
    }

    /**
     * Direccion de facturacion: los mismos datos del envio, salvo que el cliente
     * marque "facturar a otros datos".
     */
    private function billAddress(array $ship): array
    {
        if ((string) $this->input('bill_same', '1') === '1') {
            return $ship;
        }

        return [
            'email'        => (string) ($ship['email'] ?? ''),
            'name'         => (string) $this->input('bill_name', ''),
            'tax_id'       => (string) $this->input('bill_tax_id', ''),
            'address'      => (string) $this->input('bill_address', ''),
            'detail'       => (string) $this->input('bill_detail', ''),
            'postal_code'  => (string) $this->input('bill_postal_code', ''),
            'city'         => (string) $this->input('bill_city', ''),
            'province'     => (string) $this->input('bill_province', ''),
            'country'      => (string) $this->input('bill_country', $ship['country'] ?? 'Espana'),
            'phone'        => (string) $this->input('bill_phone', ''),
            'id_pais'      => (int) $this->input('bill_id_pais', 0),
            'id_provincia' => (int) $this->input('bill_id_provincia', 0),
        ];
    }
}
