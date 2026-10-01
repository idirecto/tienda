<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Models\Customer;
use Tienda\Models\CustomerAddress;

/**
 * Clientes de la tienda en el panel: quien compra en su web, con sus direcciones
 * y sus pedidos.
 *
 * Es solo lectura: los datos los mantiene el propio cliente desde su cuenta.
 */
final class CustomerController extends Controller
{
    /** Listado de clientes, con buscador por nombre, email o telefono. */
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $q = trim((string) $this->input('q', ''));
        $customers = Customer::forStore($storeId, $q);

        $total = 0.0;
        foreach ($customers as $customer) {
            $total += (float) $customer['orders_total'];
        }

        return $this->view('panel/customers', [
            'pageTitle' => 'Clientes',
            'q'         => $q,
            'customers' => $customers,
            'total'     => $total,
        ], 'panel');
    }

    /** Ficha del cliente: datos, direcciones y pedidos. */
    public function show(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $customer = Customer::findForStore((int) ($params['id'] ?? 0), $storeId);
        if ($customer === null) {
            Session::flash('error', 'Ese cliente no existe.');
            $this->redirect('panel/clientes');
        }

        return $this->view('panel/customer', [
            'pageTitle' => $customer['name'] !== '' ? (string) $customer['name'] : (string) $customer['email'],
            'customer'  => $customer,
            'addresses' => CustomerAddress::forCustomer((int) $customer['id'], $storeId),
            'orders'    => Customer::orders((int) $customer['id'], $storeId),
        ], 'panel');
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    private function storeId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }

        return (int) $id;
    }
}
