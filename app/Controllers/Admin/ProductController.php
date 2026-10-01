<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Models\Media;
use Tienda\Models\OwnProduct;
use Tienda\Models\Store;

/**
 * Productos propios de la tienda, con cuota segun el plan.
 */
final class ProductController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $tenant = $this->tenantFor($storeId);
        $products = OwnProduct::forStore($storeId);

        return $this->view('panel/products', [
            'pageTitle' => 'Productos propios',
            'products'  => $products,
            'used'      => count($products),
            'quota'     => $tenant->ownProductsQuota(),
            'unlimited' => $tenant->quotaIsUnlimited(),
            'canAdd'    => $tenant->canAddOwnProduct(count($products)),
        ], 'panel');
    }

    public function create(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $tenant = $this->tenantFor($storeId);
        $used = OwnProduct::countForStore($storeId);

        if (!$tenant->canAddOwnProduct($used) && !$tenant->quotaIsUnlimited()) {
            Session::flash('error', 'Tu plan no permite mas productos propios (cuota: ' . $tenant->ownProductsQuota() . ').');
            $this->redirect('panel/productos');
        }

        return $this->view('panel/product_form', [
            'pageTitle' => 'Nuevo producto',
            'product'   => null,
        ], 'panel');
    }

    public function store(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $tenant = $this->tenantFor($storeId);
        $used = OwnProduct::countForStore($storeId);

        if (!$tenant->quotaIsUnlimited() && !$tenant->canAddOwnProduct($used)) {
            Session::flash('error', 'Has alcanzado la cuota de productos propios de tu plan.');
            $this->redirect('panel/productos');
        }

        $data = $this->validatedData();
        if ($data === null) {
            $this->redirect('panel/productos/nuevo');
        }

        $data['store_id'] = $storeId;
        OwnProduct::create($data);

        Session::flash('success', 'Producto creado.');
        $this->redirect('panel/productos');
    }

    public function edit(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $product = OwnProduct::findForStore((int) ($params['id'] ?? 0), $storeId);
        if ($product === null) {
            Session::flash('error', 'Producto no encontrado.');
            $this->redirect('panel/productos');
        }

        return $this->view('panel/product_form', [
            'pageTitle' => 'Editar producto',
            'product'   => $product,
        ], 'panel');
    }

    public function update(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $id = (int) ($params['id'] ?? 0);
        $product = OwnProduct::findForStore($id, $storeId);
        if ($product === null) {
            Session::flash('error', 'Producto no encontrado.');
            $this->redirect('panel/productos');
        }

        $data = $this->validatedData();
        if ($data === null) {
            $this->redirect('panel/productos/' . $id . '/editar');
        }

        OwnProduct::updateById($id, $data);

        Session::flash('success', 'Producto actualizado.');
        $this->redirect('panel/productos');
    }

    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $id = (int) ($params['id'] ?? 0);
        if (OwnProduct::findForStore($id, $storeId) !== null) {
            OwnProduct::deleteById($id);
            Session::flash('success', 'Producto eliminado.');
        }

        $this->redirect('panel/productos');
    }

    /** Valida y normaliza los datos del producto. */
    private function validatedData(): ?array
    {
        $name = (string) $this->input('name', '');
        if ($name === '') {
            Session::flash('error', 'El nombre del producto es obligatorio.');
            return null;
        }

        $price = (float) str_replace(',', '.', (string) $this->input('price', '0'));
        $sale = (string) $this->input('sale_price', '');
        $salePrice = $sale === '' ? null : (float) str_replace(',', '.', $sale);

        $status = (int) $this->input('status', 1);
        if (!in_array($status, [0, 1, 2], true)) {
            $status = 1;
        }

        $mediaId = (int) $this->input('media_id', 0);
        $imageUrl = (string) $this->input('image_url', '');

        return [
            'name'        => mb_substr($name, 0, 200),
            'sku'         => mb_substr((string) $this->input('sku', ''), 0, 60) ?: null,
            'description' => (string) $this->input('description', ''),
            'price'       => $price,
            'sale_price'  => $salePrice,
            'stock'       => (int) $this->input('stock', 0),
            'supplier'    => mb_substr((string) $this->input('supplier', ''), 0, 150) ?: null,
            'category'    => mb_substr((string) $this->input('category', ''), 0, 100) ?: null,
            'media_id'    => $mediaId > 0 ? $mediaId : null,
            'image_url'   => $imageUrl !== '' ? $imageUrl : null,
            'status'      => $status,
        ];
    }

    private function tenantFor(int $storeId): \Tienda\Core\Tenant
    {
        return new \Tienda\Core\Tenant(Store::findWithPlan($storeId) ?? []);
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
