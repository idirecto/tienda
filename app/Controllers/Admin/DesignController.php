<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Session;
use Tienda\Models\Store;
use Tienda\Models\Theme;

/**
 * Diseno de la tienda: plantilla, colores, tipografia, textos.
 */
final class DesignController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->requireStoreId();

        return $this->view('panel/design', [
            'pageTitle' => 'Diseno',
            'store'     => Store::findWithPlan($storeId),
            'themes'    => Theme::active(),
        ], 'panel');
    }

    public function save(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->requireStoreId();

        $themes = array_column(Theme::active(), 'id');
        $theme = (string) $this->input('theme', 'idirecto');
        if (!in_array($theme, $themes, true)) {
            $theme = 'idirecto';
        }

        $data = [
            'name'             => (string) $this->input('name', 'Mi tienda'),
            'tagline'          => (string) $this->input('tagline', ''),
            'about'            => (string) $this->input('about', ''),
            'theme'            => $theme,
            'color_primary'    => $this->color((string) $this->input('color_primary', '#e30613')),
            'color_secondary'  => $this->color((string) $this->input('color_secondary', '#1f2937')),
            'font'             => (string) $this->input('font', 'system'),
            'header_style'     => (string) $this->input('header_style', 'classic'),
            'phone'            => (string) $this->input('phone', ''),
            'whatsapp'         => (string) $this->input('whatsapp', ''),
            'email'            => (string) $this->input('email', ''),
            'address'          => (string) $this->input('address', ''),
            'city'             => (string) $this->input('city', ''),
            'province'         => (string) $this->input('province', ''),
            'postal_code'      => (string) $this->input('postal_code', ''),
            'meta_title'       => (string) $this->input('meta_title', ''),
            'meta_description' => (string) $this->input('meta_description', ''),
            'show_prices'      => $this->input('show_prices') ? 1 : 0,
            'allow_orders'     => $this->input('allow_orders') ? 1 : 0,
        ];

        // Logo (la URL llega desde el uploader AJAX)
        $logoUrl = (string) $this->input('logo_url', '');
        $logoKey = (string) $this->input('logo_key', '');
        if ($logoUrl !== '') {
            $data['logo_url'] = $logoUrl;
            $data['logo_key'] = $logoKey !== '' ? $logoKey : null;
        }

        Store::updateById($storeId, $data);

        Session::flash('success', 'Diseno actualizado correctamente.');
        $this->redirect('panel/diseno');
    }

    private function color(string $value): string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : '#e30613';
    }

    private function requireStoreId(): int
    {
        $id = Auth::storeId();
        if ($id === null || $id <= 0) {
            $this->redirect('panel/logout');
        }
        return $id;
    }
}
