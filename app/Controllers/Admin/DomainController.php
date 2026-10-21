<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Dns;
use Tienda\Core\Logger;
use Tienda\Core\Session;
use Tienda\Core\Subdomain;
use Tienda\Core\TenantResolver;
use Tienda\Models\DnsLog;
use Tienda\Models\Domain;
use Tienda\Models\Setting;
use Tienda\Models\Store;

/**
 * Dominios de la tienda:
 *   - el subdominio de plataforma (<slug>.ecom-era.com), que siempre existe;
 *   - los dominios propios, con verificacion DNS.
 */
final class DomainController extends Controller
{
    public function index(array $params = []): string
    {
        $this->requireAuth();
        $storeId = $this->storeId();

        $store = Store::find($storeId) ?? [];
        $label = (string) ($store['slug'] ?? '');
        $subdomainHost = $label !== '' ? Subdomain::host($label) : '';

        $domains = Domain::forStore($storeId);
        $withLogs = [];
        foreach ($domains as $domain) {
            $domain['logs'] = DnsLog::forDomain((int) $domain['id']);
            $withLogs[] = $domain;
        }

        return $this->view('panel/domains', [
            'pageTitle'     => 'Dominios',
            'domains'       => $withLogs,
            'instructions'  => Dns::instructions(''),
            'platformIp'    => Dns::platformIp(),
            'platformCname' => Dns::platformCname(),
            'subdomain'     => $label,
            'subdomainHost' => $subdomainHost,
            'subdomainUrl'  => $label !== '' ? Subdomain::url($label) : '',
            'baseDomain'    => Subdomain::baseDomain(),
            'baseDomains'   => TenantResolver::getBaseDomains(),
            'subdomainCheck' => $this->lastSubdomainCheck($storeId, $subdomainHost),
        ], 'panel');
    }

    /**
     * Cambia el subdominio de la tienda (la etiqueta de `mt_stores.slug`).
     *
     * El aislamiento no depende de esta accion: el `store_id` sale de la sesion
     * del panel y solo se actualiza ESA fila. Un slug repetido se rechaza antes
     * de escribir y, si dos peticiones coinciden, la clave unica de la tabla
     * actua como ultima red de seguridad.
     */
    public function saveSubdomain(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $store = Store::find($storeId);
        if ($store === null) {
            Session::flash('error', 'No se ha encontrado tu tienda.');
            $this->redirect('panel/dominios');
        }

        $label = Subdomain::normalize((string) $this->input('subdomain', ''));
        $error = Subdomain::validate($label, $storeId);
        if ($error !== null) {
            Session::flash('error', $error);
            $this->redirect('panel/dominios');
        }

        $before = (string) ($store['slug'] ?? '');
        if ($before === $label) {
            Session::flash('success', 'Ese ya es tu subdominio.');
            $this->redirect('panel/dominios');
        }

        try {
            Store::updateById($storeId, ['slug' => $label]);
        } catch (\PDOException $e) {
            // 23000 = violacion de la clave unica `uq_mt_stores_slug`.
            if ((string) $e->getCode() === '23000') {
                Session::flash('error', 'Ese subdominio acaba de ocuparlo otra tienda. Elige otro.');
                $this->redirect('panel/dominios');
            }
            throw $e;
        }

        // El diagnostico anterior era de otra direccion: se descarta.
        Setting::set($storeId, 'subdomain_check', null);

        $url = Subdomain::url($label);
        Logger::info('panel', 'Subdominio de la tienda actualizado', [
            'store_id' => $storeId,
            'antes'    => $before,
            'nuevo'   => $label,
            'url'     => $url,
        ]);

        Session::flash('success', 'Subdominio guardado. Tu tienda se vera en ' . $url . '.');
        $this->redirect('panel/dominios');
    }

    /** Comprueba DNS, HTTPS y carga real del subdominio actual. */
    public function checkSubdomain(array $params = []): string
    {
        $this->requireAuth();
        $this->requireCsrf();
        $storeId = $this->storeId();

        $store = Store::find($storeId);
        $label = (string) ($store['slug'] ?? '');
        if ($label === '') {
            Session::flash('error', 'Tu tienda no tiene subdominio configurado.');
            $this->redirect('panel/dominios');
        }

        $result = Subdomain::diagnose($label);
        Setting::set($storeId, 'subdomain_check', json_encode($result, JSON_UNESCAPED_UNICODE));

        Logger::info('panel', 'Comprobacion del subdominio', [
            'store_id' => $storeId,
            'host'     => $result['host'] ?? '',
            'nivel'    => $result['level'] ?? '',
            'mensaje'  => $result['message'] ?? '',
        ]);

        $flash = match ((string) ($result['level'] ?? 'error')) {
            'ok'      => 'success',
            'warning' => 'warning',
            default   => 'error',
        };
        Session::flash($flash, (string) ($result['message'] ?? 'No se pudo comprobar el subdominio.'));
        $this->redirect('panel/dominios');
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

        // Los subdominios del dominio de la plataforma se gestionan con el
        // selector de subdominio, no como dominio propio: si se aceptaran aqui,
        // el resolver por Host podria servir la tienda equivocada.
        if (TenantResolver::isPlatformHost($domain)) {
            Session::flash(
                'error',
                'Los subdominios de ' . Subdomain::baseDomain() . ' se gestionan con el selector de subdominio, no como dominio propio.'
            );
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

    /**
     * Ultimo diagnostico guardado, solo si corresponde al subdominio actual.
     *
     * Se guarda en `mt_settings` (clave/valor por tienda) para poder mostrarlo
     * al volver a la pagina sin repetir DNS/TLS en cada carga.
     */
    private function lastSubdomainCheck(int $storeId, string $currentHost): ?array
    {
        $raw = Setting::get($storeId, 'subdomain_check');
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || (string) ($data['host'] ?? '') !== $currentHost) {
            return null;
        }

        return $data;
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
