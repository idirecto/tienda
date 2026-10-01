<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Database;
use Tienda\Core\Session;
use Tienda\Models\Notice;
use Tienda\Models\Store;

/**
 * Avisos / anuncios de la tienda.
 */
final class NoticeController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $store = Store::findWithPlan($storeId);
        $tenant = new \Tienda\Core\Tenant($store ?? []);
        $notices = Notice::forStore($storeId);

        return $this->view('panel/notices', [
            'pageTitle' => 'Avisos',
            'notices'   => $notices,
            'used'      => count($notices),
            'max'       => $tenant->maxNotices(),
        ], 'panel');
    }

    public function store(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $store = Store::findWithPlan($storeId);
        $tenant = new \Tienda\Core\Tenant($store ?? []);

        if (count(Notice::forStore($storeId)) >= $tenant->maxNotices()) {
            Session::flash('error', 'Has alcanzado el limite de avisos de tu plan (' . $tenant->maxNotices() . ').');
            $this->redirect('panel/avisos');
        }

        $message = (string) $this->input('message', '');
        if ($message === '') {
            Session::flash('error', 'El aviso no puede estar vacio.');
            $this->redirect('panel/avisos');
        }

        $type = (string) $this->input('type', 'info');
        if (!in_array($type, ['info', 'promo', 'warning'], true)) {
            $type = 'info';
        }

        Notice::create([
            'store_id'  => $storeId,
            'type'      => $type,
            'message'   => mb_substr($message, 0, 500),
            'visible'   => $this->input('visible') ? 1 : 0,
            'starts_at' => $this->nullableDate((string) $this->input('starts_at', '')),
            'ends_at'   => $this->nullableDate((string) $this->input('ends_at', '')),
        ]);

        Session::flash('success', 'Aviso publicado.');
        $this->redirect('panel/avisos');
    }

    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $id = (int) ($params['id'] ?? 0);
        $notice = Database::first(
            'SELECT id FROM mt_notices WHERE id = :id AND store_id = :s LIMIT 1',
            ['id' => $id, 's' => $storeId]
        );

        if ($notice !== null) {
            Notice::deleteById($id);
            Session::flash('success', 'Aviso eliminado.');
        }

        $this->redirect('panel/avisos');
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
