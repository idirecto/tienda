<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Idirecto\Account;
use Tienda\Core\Idirecto\OrderGateway;
use Tienda\Core\Logger;
use Tienda\Core\Session;
use Tienda\Core\ValidationException;
use Tienda\Models\Catalog;
use Tienda\Models\Order;
use Tienda\Models\OrderItem;
use Tienda\Models\OwnProduct;
use Tienda\Models\Store;

/**
 * Pedidos de la tienda.
 *
 * Listado con pestanas por estado (activos, facturados, borrados...), ficha del
 * pedido y envio al mayorista de SOLO las lineas que se elijan. Cada linea
 * enviada queda marcada, asi que un pedido de 4 lineas se puede mandar en dos
 * veces (2 + 2) sin repetir ninguna.
 */
final class OrderController extends Controller
{
    private const PER_PAGE = 20;

    // =====================================================================
    // LISTADO
    // =====================================================================

    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $group = (string) $this->input('estado', 'todos');
        $q = (string) $this->input('q', '');
        $page = max(1, (int) $this->input('pagina', 1));

        $list = Order::paginateForStore($storeId, ['group' => $group, 'q' => $q], $page, self::PER_PAGE);
        $counts = Order::countsForStore($storeId);
        $store = $this->storeRow($storeId);

        return $this->view('panel/orders', [
            'pageTitle' => 'Pedidos',
            'orders'    => $list['items'],
            'pagination' => $list,
            'counts'    => $counts,
            'group'     => $group,
            'q'         => $q,
            'activeCount' => Order::countActiveForStore($storeId),
            'account'   => Account::forStore($store),
            'idirectoReady' => OrderGateway::isAvailable(),
        ], 'panel');
    }

    // =====================================================================
    // ALTA MANUAL
    // =====================================================================

    public function create(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        return $this->view('panel/order_form', [
            'pageTitle' => 'Nuevo pedido',
            'order'     => null,
            'items'     => [],
            'store'     => $this->storeRow($storeId),
        ], 'panel');
    }

    public function store(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $data = $this->orderData();
        if ($data === null) {
            $this->redirect('panel/pedidos/nuevo');
        }

        $raw = $this->input('lines', []);
        $normalized = OrderItem::normalizeLines(is_array($raw) ? $raw : []);
        if ($normalized['lines'] === []) {
            Session::flash('error', 'Anade al menos una linea al pedido.');
            $this->redirect('panel/pedidos/nuevo');
        }
        if ($normalized['errors'] !== []) {
            Session::flash('error', implode(' ', $normalized['errors']));
            $this->redirect('panel/pedidos/nuevo');
        }

        $orderId = Order::createWithItems($storeId, $data, $normalized['lines']);
        Order::syncIdirectoPrices($orderId, $this->storeRow($storeId));

        Logger::info('pedidos', 'Pedido creado a mano en el panel', [
            'store_id' => $storeId,
            'order_id' => $orderId,
            'cliente'  => (string) ($data['customer_name'] ?? ''),
            'lineas'   => count($normalized['lines']),
        ]);

        Session::flash('success', 'Pedido creado. Ya puedes enviarlo al mayorista.');
        $this->redirect('panel/pedidos/' . $orderId);
    }

    // =====================================================================
    // FICHA
    // =====================================================================

    public function show(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);
        $store = $this->storeRow($storeId);
        $items = $order['items'] ?? [];

        $pending = OrderItem::pendingSendable((int) $order['id']);
        $preview = OrderGateway::preview($store, $order, $pending);

        return $this->view('panel/order', [
            'pageTitle' => 'Pedido ' . ($order['code'] ?? ('#' . $order['id'])),
            'order'     => $order,
            'items'     => $items,
            'summary'   => OrderItem::summary($items),
            'preview'   => $preview,
            'store'     => $store,
            'account'   => $preview['cuenta'],
            'idirectoReady' => OrderGateway::isAvailable(),
            // Se puede enviar mientras no este cancelado ni en la papelera y
            // quede alguna linea del catalogo sin enviar.
            'canSend'   => !in_array((int) $order['status'], [Order::STATUS_DELETED, Order::STATUS_CANCELLED], true) && $pending !== [],
        ], 'panel');
    }

    /** Anade lineas a un pedido ya creado. */
    public function addLines(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);

        $raw = $this->input('lines', []);
        $normalized = OrderItem::normalizeLines(is_array($raw) ? $raw : []);
        if ($normalized['lines'] === []) {
            Session::flash('error', 'No has anadido ninguna linea.');
            $this->redirect('panel/pedidos/' . $order['id']);
        }

        $lineNo = OrderItem::nextLineNo((int) $order['id']);
        foreach ($normalized['lines'] as $line) {
            OrderItem::add((int) $order['id'], $storeId, $lineNo++, $line);
        }
        Order::recalculateTotals((int) $order['id']);
        Order::syncIdirectoPrices((int) $order['id'], $this->storeRow($storeId));

        $aviso = $normalized['errors'] === [] ? '' : ' (' . implode(' ', $normalized['errors']) . ')';
        Session::flash('success', count($normalized['lines']) . ' linea(s) anadida(s).' . $aviso);
        $this->redirect('panel/pedidos/' . $order['id']);
    }

    /** Borra una linea que todavia no se ha enviado al mayorista. */
    public function destroyLine(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);
        $item = OrderItem::findForOrder((int) ($params['line'] ?? 0), (int) $order['id']);

        if ($item === null) {
            Session::flash('error', 'Esa linea no existe.');
        } elseif (!empty($item['sent_at'])) {
            Session::flash('error', 'Esa linea ya se envio a idirecto (pedido #' . (int) $item['idirecto_pedido_id'] . '): no se puede borrar.');
        } else {
            OrderItem::deleteLine((int) $item['id'], (int) $order['id']);
            Order::recalculateTotals((int) $order['id']);
            Session::flash('success', 'Linea eliminada.');
        }

        $this->redirect('panel/pedidos/' . $order['id']);
    }

    // =====================================================================
    // ESTADO / PAPELERA
    // =====================================================================

    public function status(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);
        $status = (int) $this->input('status', Order::STATUS_ACTIVE);

        Order::setStatus((int) $order['id'], $status);
        Logger::info('pedidos', 'Estado de pedido cambiado', [
            'store_id' => $storeId,
            'order_id' => (int) $order['id'],
            'pedido'   => (string) ($order['code'] ?? ''),
            'estado'   => Order::statusLabel(Order::normalizeStatus($status)),
        ]);
        Session::flash('success', 'Pedido marcado como "' . Order::statusLabel(Order::normalizeStatus($status)) . '".');
        $this->redirect('panel/pedidos/' . $order['id']);
    }

    /**
     * Marca el cobro del pedido (pagado / pendiente).
     *
     * El pedido de la web nace pendiente de pago: el tendero lo confirma aqui
     * cuando recibe la transferencia, el contra reembolso o el pago en tienda.
     */
    public function payment(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);
        $pagado = (string) $this->input('payment_status', '1') === '1';

        Order::setPaymentStatus((int) $order['id'], $pagado ? 1 : 0);
        Logger::info('pedidos', $pagado ? 'Pedido marcado como pagado' : 'Pedido vuelto a pendiente de pago', [
            'store_id' => $storeId,
            'order_id' => (int) $order['id'],
            'pedido'   => (string) ($order['code'] ?? ''),
            'importe'  => (float) ($order['total'] ?? 0),
        ]);
        Session::flash('success', $pagado ? 'Pedido marcado como pagado.' : 'Pedido pendiente de pago.');
        $this->redirect('panel/pedidos/' . $order['id']);
    }

    /** Papelera: borrado logico (se puede restaurar). */
    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);
        Order::setStatus((int) $order['id'], Order::STATUS_DELETED);

        Session::flash('success', 'Pedido movido a la papelera. Puedes restaurarlo desde la pestana "Borrados".');
        $this->redirect('panel/pedidos');
    }

    public function restore(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);
        Order::setStatus((int) $order['id'], Order::STATUS_ACTIVE);

        Session::flash('success', 'Pedido restaurado.');
        $this->redirect('panel/pedidos/' . $order['id']);
    }

    // =====================================================================
    // ENVIO AL MAYORISTA
    // =====================================================================

    public function send(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $order = $this->loadOrder((int) ($params['id'] ?? 0), $storeId);
        $store = $this->storeRow($storeId);

        $ids = $this->input('items', []);
        $ids = is_array($ids) ? $ids : [];

        try {
            $result = OrderGateway::send($store, $order, $ids);

            Logger::info('pedidos', 'Lineas enviadas a idirecto', [
                'store_id'    => $storeId,
                'order_id'    => (int) $order['id'],
                'pedido'      => (string) ($order['code'] ?? ''),
                'idirecto'    => (int) $result['pedido_id'],
                'referencia'  => (string) $result['referencia'],
                'lineas'      => (int) $result['lineas'],
                'importe'     => (float) $result['total'],
                'completo'    => (bool) $result['completo'],
            ]);

            Session::flash(
                'success',
                'Enviado a idirecto como pedido #' . $result['pedido_id'] . ' (' . $result['referencia'] . '): '
                . $result['lineas'] . ' linea(s) por ' . euros($result['total']) . '.'
                . ($result['completo'] ? ' El pedido ya esta completo en el mayorista.' : ' Quedan lineas por enviar.')
            );
        } catch (ValidationException $e) {
            Logger::warning('pedidos', 'Envio a idirecto rechazado: ' . $e->getMessage(), [
                'store_id' => $storeId,
                'order_id' => (int) $order['id'],
                'pedido'   => (string) ($order['code'] ?? ''),
                'lineas'   => count($ids),
            ]);
            Session::flash('error', $e->getMessage());
        } catch (\Throwable $e) {
            Logger::error('pedidos', 'Fallo al enviar el pedido a idirecto: ' . $e->getMessage(), [
                'store_id'  => $storeId,
                'order_id'  => (int) $order['id'],
                'pedido'    => (string) ($order['code'] ?? ''),
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
                'lineas'    => count($ids),
            ]);
            Session::flash('error', 'No se pudo crear el pedido en el mayorista: ' . $e->getMessage());
        }

        $this->redirect('panel/pedidos/' . $order['id']);
    }

    // =====================================================================
    // BUSCADOR DE PRODUCTOS (JSON)
    // =====================================================================

    public function search(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        // Precios de la tienda SIN IVA: es la base que se guarda en la linea del
        // pedido (el IVA se suma al calcular los totales).
        $this->bootStorePricing($storeId);

        $q = trim((string) $this->input('q', ''));
        $results = [];

        foreach (OwnProduct::searchForStore($storeId, $q, 5) as $own) {
            $results[] = [
                'source'    => OrderItem::SOURCE_OWN,
                'id'        => (int) $own['id'],
                'name'      => (string) $own['name'],
                'sku'       => (string) ($own['sku'] ?? ''),
                'price'     => (float) ($own['sale_price'] ?: $own['price']),
                'tax_rate'  => (float) ($own['tax_rate'] ?? 21),
                'own'       => true,
            ];
        }

        foreach (Catalog::search($q, 10) as $row) {
            $results[] = [
                'source'   => OrderItem::SOURCE_CATALOG,
                'id'       => (int) $row['id'],
                'name'     => (string) $row['nombre'],
                'sku'      => (string) $row['sku'],
                'price'    => $row['precio'] === null ? 0.0 : (float) $row['precio'],
                'tax_rate' => (float) ($row['impuestos'] > 0 ? $row['impuestos'] : 21),
                'own'      => false,
            ];
        }

        return $this->json(['ok' => true, 'results' => $results]);
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    /** Datos del pedido desde el formulario. null = validacion fallida. */
    private function orderData(): ?array
    {
        $name = trim((string) $this->input('customer_name', ''));
        if ($name === '') {
            Session::flash('error', 'El nombre del cliente es obligatorio.');
            return null;
        }

        // Por defecto el envio va a la direccion de la tienda (los datos que
        // maneja esta web): entonces no se rellena direccion de envio y el
        // mayorista reutiliza la de facturacion. Si el tendero la desmarca, se
        // usa la direccion del cliente que ha escrito en el formulario.
        $ship = [
            'ship_name'        => null,
            'ship_tax_id'      => null,
            'ship_address'     => null,
            'ship_city'        => null,
            'ship_province'    => null,
            'ship_postal_code' => null,
            'ship_country'     => 'Espana',
        ];

        if ((string) $this->input('ship_to_store', '1') !== '1') {
            $ship = [
                'ship_name'        => $this->text('ship_name') ?: null,
                'ship_tax_id'      => $this->text('ship_tax_id') ?: null,
                'ship_address'     => $this->text('ship_address') ?: null,
                'ship_city'        => $this->text('ship_city') ?: null,
                'ship_province'    => $this->text('ship_province') ?: null,
                'ship_postal_code' => $this->text('ship_postal_code') ?: null,
                'ship_country'     => $this->text('ship_country') ?: 'Espana',
            ];
        }

        return array_merge($ship, [
            'customer_name'   => mb_substr($name, 0, 150),
            'customer_email'  => mb_substr($this->text('customer_email'), 0, 180) ?: null,
            'customer_phone'  => mb_substr($this->text('customer_phone'), 0, 40) ?: null,
            'customer_tax_id' => mb_substr($this->text('customer_tax_id'), 0, 30) ?: null,
            'notes'           => $this->text('notes') ?: null,
            'shipping'        => (float) str_replace(',', '.', (string) $this->input('shipping', '0')),
            'status'          => (int) $this->input('status', Order::STATUS_ACTIVE),
        ]);
    }

    private function text(string $key): string
    {
        return mb_substr(trim((string) $this->input($key, '')), 0, 250);
    }

    /**
     * Fija el precio de venta de la tienda (sin IVA) para el catalogo del panel.
     *
     * Asi el buscador de productos ofrece el mismo precio base que usara el
     * pedido de la web: tarifa de la tienda + su beneficio. Sin esto, el panel
     * mostraria el precio mas barato publicado, que puede ser menor que el coste.
     */
    private function bootStorePricing(int $storeId): void
    {
        $store = $this->storeRow($storeId);

        Catalog::forStore(
            (int) ($store['id_margen'] ?? 0) ?: null,
            $store['markup'] ?? 0,
            (float) ($store['tax_rate'] ?? 21),
            false
        );
    }

    /** Pedido con sus lineas o redireccion si no existe / no es de la tienda. */
    private function loadOrder(int $id, int $storeId): array
    {
        $order = Order::findForStore($id, $storeId, true);
        if ($order === null) {
            Session::flash('error', 'Pedido no encontrado.');
            $this->redirect('panel/pedidos');
        }
        return $order;
    }

    private function storeRow(int $storeId): array
    {
        $store = Store::findWithPlan($storeId);
        if ($store === null) {
            Session::flash('error', 'No se ha encontrado tu tienda.');
            $this->redirect('panel');
        }
        return $store;
    }

    private function storeId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }
        return $id;
    }
}
