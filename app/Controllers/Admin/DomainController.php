<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Dns;
use Tienda\Core\Session;
use Tienda\Models\DnsLog;
use Tienda\Models\Domain;

/**
 * Dominios propios y verificacion DNS.
 */
final class DomainController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $domains = Domain::forStore($storeId);
        $withLogs = [];
        foreach ($domains as $domain) {
            $domain['logs'] = DnsLog::forDomain((int) $domain['id']);
            $withLogs[] = $domain;
        }

        return $this->view('panel/domains', [
            'pageTitle'    => 'Dominios',
            'domains'      => $withLogs,
            'instructions' => Dns::instructions(''),
            'platformIp'   => Dns::platformIp(),
            'platformCname' => Dns::platformCname(),
        ], 'panel');
    }

    public function store(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $domain = strtolower(trim((string) $this->input('domain', '')));
        $domain = preg_replace('#^https?://#', '', $domain) ?? '';
        $domain = rtrim($domain, '/');

        if (!preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/', $domain)) {
            Session::flash('error', 'El dominio no tiene un formato valido.');
            $this->redirect('panel/dominios');
        }

        if (Domain::findBy('domain', $domain) !== null) {
            Session::flash('error', 'Ese dominio ya esta registrado en la plataforma.');
            $this->redirect('panel/dominios');
        }

        $method = (string) $this->input('method', 'A');
        if (!in_array($method, ['A', 'CNAME', 'BOTH'], true)) {
            $method = 'A';
        }

        Domain::create([
            'store_id'       => $storeId,
            'domain'         => $domain,
            'type'           => 'custom',
            'method'         => $method,
            'expected_a'     => Dns::platformIp(),
            'expected_cname' => Dns::platformCname(),
            'status'         => Dns::PENDING,
            'token'          => bin2hex(random_bytes(16)),
        ]);

        Session::flash('success', 'Dominio anadido. Configura los registros DNS y pulsa "Verificar".');
        $this->redirect('panel/dominios');
    }

    public function verify(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $id = (int) ($params['id'] ?? 0);
        if (Domain::findForStore($id, $storeId) === null) {
            Session::flash('error', 'Dominio no encontrado.');
            $this->redirect('panel/dominios');
        }

        $result = Dns::verify($id);
        Session::flash(
            $result['status'] === Dns::VERIFIED ? 'success' : 'error',
            $result['message']
        );

        $this->redirect('panel/dominios');
    }

    public function destroy(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $id = (int) ($params['id'] ?? 0);
        if (Domain::findForStore($id, $storeId) !== null) {
            Domain::deleteById($id);
            Session::flash('success', 'Dominio eliminado.');
        }

        $this->redirect('panel/dominios');
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
