<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Cart;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Core\Shipping;
use Tienda\Core\View;

/**
 * Carrito de la compra del storefront.
 *
 * Sin JavaScript obligatorio: anadir y cambiar cantidades son formularios POST
 * normales, asi que funciona igual con JS desactivado. El carrito vive en la
 * sesion y esta separado por tienda (`Cart`).
 *
 * JavaScript solo lo mejora: los mismos endpoints detectan una peticion AJAX
 * (`X-Requested-With`) y, en ese caso, responden JSON con el estado real del
 * carrito y el HTML del mini-carrito, en lugar de redirigir.
 */
final class CartController extends Controller
{
    /** Pagina del carrito. */
    public function index(array $params = []): string
    {
        $storeId = $this->tenant->id();
        $store = $this->tenant->toArray();

        $dropped = [];
        $items = Cart::items($storeId, $dropped);
        $totals = Cart::totals($items, $store);

        if ($dropped !== []) {
            Session::flash('error', 'Se han quitado del carrito: ' . implode(', ', $dropped) . ' (ya no estan disponibles).');
        }

        return $this->view($this->themeView('cart'), [
            'pageTitle'   => 'Tu carrito',
            'items'       => $items,
            'totals'      => $totals,
            'freeFrom'    => Shipping::hasFreeFrom($store),
            'missingFree' => Shipping::missingForFree($store, (float) $totals['subtotal']),
            'allowOrders' => (bool) $this->tenant->allowOrders(),
            'maxQty'      => Cart::MAX_QTY,
        ], 'shop');
    }

    /**
     * Estado actual del carrito para el mini-carrito (JSON).
     *
     * Solo lectura: no cambia nada. Se usa al abrir el panel por primera vez o
     * para resincronizarlo.
     */
    public function mini(array $params = []): string
    {
        return $this->json($this->cartPayload(true, '', 'success', false));
    }

    /** Anade un producto (ficha del producto o tarjeta del catalogo). */
    public function add(array $params = []): string
    {
        $this->requireCsrf('carrito');

        if (!$this->tenant->allowOrders()) {
            $message = 'Esta tienda no esta aceptando pedidos en este momento.';
            if ($this->isAjax()) {
                return $this->cartJson(false, $message, 'error');
            }
            Session::flash('error', $message);
            $this->redirect('carrito');
        }

        $source = (string) $this->input('source', 'catalog');
        $productId = (int) $this->input('product_id', 0);
        $qty = (int) $this->input('qty', 1);

        $result = Cart::add($this->tenant->id(), $source, $productId, $qty);

        // JavaScript: se devuelve el estado real y el mini-carrito ya pintado.
        // Solo se abre si de verdad se ha podido anadir (nunca tras un error).
        if ($this->isAjax()) {
            return $this->cartJson(
                $result['ok'],
                $result['message'],
                $result['ok'] ? 'success' : 'error',
                $result['ok']
            );
        }

        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);

        // Desde la ficha del producto se vuelve a ella (el cliente sigue mirando);
        // desde una tarjeta del catalogo se va al carrito, que es lo que espera.
        $return = $this->safeReturn((string) $this->input('return', ''));
        if ($result['ok'] && (string) $this->input('stay', '') === '1' && $return !== 'carrito') {
            $this->redirect($return);
        }

        $this->redirect('carrito');
    }

    /** Guarda las cantidades del carrito. */
    public function update(array $params = []): string
    {
        $this->requireCsrf('carrito');

        $quantities = $this->input('qty', []);
        $warnings = Cart::updateQuantities(
            $this->tenant->id(),
            is_array($quantities) ? $quantities : []
        );
        $message = $warnings === [] ? 'Carrito actualizado.' : implode(' ', $warnings);

        if ($this->isAjax()) {
            return $this->cartJson(true, $message, 'success');
        }

        Session::flash('success', $message);
        $this->redirect('carrito');
    }

    /** Quita una linea. */
    public function remove(array $params = []): string
    {
        $this->requireCsrf('carrito');

        Cart::remove($this->tenant->id(), (string) $this->input('key', ''));

        if ($this->isAjax()) {
            return $this->cartJson(true, 'Producto quitado del carrito.', 'success');
        }

        Session::flash('success', 'Producto quitado del carrito.');
        $this->redirect('carrito');
    }

    /** Vacia el carrito. */
    public function clear(array $params = []): string
    {
        $this->requireCsrf('carrito');

        Cart::clear($this->tenant->id());

        if ($this->isAjax()) {
            return $this->cartJson(true, 'Carrito vaciado.', 'success');
        }

        Session::flash('success', 'Carrito vaciado.');
        $this->redirect('carrito');
    }

    // =====================================================================
    // RESPUESTA AJAX DEL CARRITO
    // =====================================================================

    /** Envia el estado del carrito como JSON y termina la peticion. */
    private function cartJson(bool $ok, string $message, string $type = 'success', bool $open = false): never
    {
        $this->json($this->cartPayload($ok, $message, $type, $open));
    }

    /**
     * Estado real del carrito + mini-carrito ya renderizado.
     *
     * El HTML sale del mismo partial que pinta el layout (`_mini_cart.php`), asi
     * que el panel nunca muestra datos distintos de los del backend.
     */
    private function cartPayload(bool $ok, string $message, string $type, bool $open): array
    {
        $storeId = $this->tenant->id();
        $store = $this->tenant->toArray();
        $view = Cart::miniViewData($storeId, $store);
        $totals = $view['totals'];

        $html = View::render($this->themeView('_mini_cart'), $view + [
            'tenant' => $this->tenant,
        ]);

        return [
            'ok'             => $ok,
            'type'           => $type,
            'message'        => $message,
            'open'           => $open,
            'count'          => (int) $view['count'],
            'units'          => (int) $totals['units'],
            'lines'          => (int) $totals['lines'],
            'subtotal'       => (float) $totals['subtotal'],
            'shipping'       => (float) $totals['shipping'],
            'total'          => (float) $totals['total'],
            'subtotal_label' => euros($totals['subtotal']),
            'shipping_label' => $totals['shipping'] > 0 ? euros($totals['shipping']) : 'Gratis',
            'total_label'    => euros($totals['total']),
            'allow_orders'   => (bool) $view['allowOrders'],
            'dropped'        => array_values($view['dropped']),
            'html'           => $html,
        ];
    }

    /**
     * A donde volver tras anadir: solo rutas de esta tienda.
     *
     * Se ignoran las URLs absolutas (solo se acepta una ruta que empiece por "/")
     * para que nadie pueda usar el formulario como redireccion abierta.
     */
    private function safeReturn(string $return): string
    {
        $return = trim($return);
        if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')) {
            return 'carrito';
        }

        return ltrim($return, '/');
    }
}
