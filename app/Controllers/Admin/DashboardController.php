<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Models\Banner;
use Tienda\Models\Catalog;
use Tienda\Models\Domain;
use Tienda\Models\Notice;
use Tienda\Models\Order;
use Tienda\Models\OwnProduct;
use Tienda\Models\Store;

/**
 * Panel de control de la tienda: resumen.
 */
final class DashboardController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->requireStoreId();

        $store = Store::findWithPlan($storeId);
        $tenant = $store ? new \Tienda\Core\Tenant($store) : $this->tenant;

        $ownCount = OwnProduct::countForStore($storeId);

        return $this->view('panel/dashboard', [
            'pageTitle' => 'Panel',
            'store'     => $store,
            'tenant'    => $tenant,
            'stats'     => [
                'productos_propios' => $ownCount,
                'cuota'             => $tenant->ownProductsQuota(),
                'cuota_ilimitada'   => $tenant->quotaIsUnlimited(),
                'banners'           => count(Banner::forStore($storeId)),
                'max_banners'       => $tenant->maxBanners(),
                'avisos'            => count(Notice::forStore($storeId)),
                'max_avisos'        => $tenant->maxNotices(),
                'dominios'          => count(Domain::forStore($storeId)),
                'pedidos_activos'   => Order::countActiveForStore($storeId),
            ],
            'catalogReady' => Catalog::isAvailable(),
            'activity'     => array_slice(OwnProduct::forStore($storeId), 0, 5),
        ], 'panel');
    }

    /** Id de tienda del usuario autenticado (la sesion manda sobre el host). */
    private function requireStoreId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }
        return $id;
    }
}
