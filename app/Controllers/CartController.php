<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Cart;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Core\Shipping;

/**
 * Carrito de la compra del storefront.
 *
 * Sin JavaScript obligatorio: anadir y cambiar cantidades son formularios POST
 * normales, asi que funciona igual con JS desactivado. El carrito vive en la
 * sesion y esta separado por tienda (`Cart`).
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

    /** Anade un producto (ficha del producto o tarjeta del catalogo). */
    public function add(array $params = []): string
    {
        $this->requireCsrf('carrito');

        if (!$this->tenant->allowOrders()) {
            Session::flash('error', 'Esta tienda no esta aceptando pedidos en este momento.');
            $this->redirect('carrito');
        }

        $source = (string) $this->input('source', 'catalog');
        $productId = (int) $this->input('product_id', 0);
        $qty = (int) $this->input('qty', 1);

        $result = Cart::add($this->tenant->id(), $source, $productId, $qty);
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
        Cart::updateQuantities($this->tenant->id(), is_array($quantities) ? $quantities : []);
        Session::flash('success', 'Carrito actualizado.');

        $this->redirect('carrito');
    }

    /** Quita una linea. */
    public function remove(array $params = []): string
    {
        $this->requireCsrf('carrito');

        Cart::remove($this->tenant->id(), (string) $this->input('key', ''));
        Session::flash('success', 'Producto quitado del carrito.');

        $this->redirect('carrito');
    }

    /** Vacia el carrito. */
    public function clear(array $params = []): string
    {
        $this->requireCsrf('carrito');

        Cart::clear($this->tenant->id());
        Session::flash('success', 'Carrito vaciado.');

        $this->redirect('carrito');
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
