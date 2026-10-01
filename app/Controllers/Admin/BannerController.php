<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Core\Storage\StorageManager;
use Tienda\Models\Banner;
use Tienda\Models\Media;
use Tienda\Models\Store;

/**
 * Gestion de banners de la tienda (con limite por plan).
 */
final class BannerController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $store = Store::findWithPlan($storeId);
        $tenant = new \Tienda\Core\Tenant($store ?? []);

        return $this->view('panel/banners', [
            'pageTitle'  => 'Banners',
            'banners'    => Banner::forStore($storeId),
            'used'       => count(Banner::forStore($storeId)),
            'max'        => $tenant->maxBanners(),
        ], 'panel');
    }

    public function store(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $store = Store::findWithPlan($storeId);
        $tenant = new \Tienda\Core\Tenant($store ?? []);
        $current = count(Banner::forStore($storeId));

        if ($current >= $tenant->maxBanners()) {
            Session::flash('error', 'Has alcanzado el limite de banners de tu plan (' . $tenant->maxBanners() . ').');
            $this->redirect('panel/banners');
        }

        [$mediaId, $imageUrl] = $this->resolveImage($storeId);

        Banner::create([
            'store_id'  => $storeId,
            'title'     => $this->input('title', ''),
            'subtitle'  => $this->input('subtitle', ''),
            'link'      => $this->input('link', ''),
            'media_id'  => $mediaId,
            'image_url' => $imageUrl,
            'position'  => $this->position((string) $this->input('position', 'hero')),
            'sort'      => (int) $this->input('sort', 0),
            'active'    => $this->input('active') ? 1 : 0,
            'starts_at' => $this->nullableDate((string) $this->input('starts_at', '')),
            'ends_at'   => $this->nullableDate((string) $this->input('ends_at', '')),
        ]);

        Session::flash('success', 'Banner creado.');
        $this->redirect('panel/banners');
    }

    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $id = (int) ($params['id'] ?? 0);
        $banner = \Tienda\Core\Database::first(
            'SELECT * FROM mt_banners WHERE id = :id AND store_id = :s LIMIT 1',
            ['id' => $id, 's' => $storeId]
        );

        if ($banner !== null) {
            Banner::deleteById($id);
            Session::flash('success', 'Banner eliminado.');
        }

        $this->redirect('panel/banners');
    }

    /** Resuelve la imagen: usa la URL subida o elige una existente de mt_media. */
    private function resolveImage(int $storeId): array
    {
        $imageUrl = (string) $this->input('image_url', '');
        $mediaId = (int) $this->input('media_id', 0);

        if ($mediaId > 0) {
            $media = Media::findForStore($mediaId, $storeId);
            if ($media !== null) {
                return [$mediaId, (string) $media['url']];
            }
        }
        return [$mediaId > 0 ? $mediaId : null, $imageUrl !== '' ? $imageUrl : null];
    }

    private function position(string $value): string
    {
        return in_array($value, ['hero', 'middle', 'footer'], true) ? $value : 'hero';
    }

    private function nullableDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts === false ? null : date('Y-m-d H:i:s', $ts);
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
