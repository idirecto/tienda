<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Auth;
use Tienda\Core\Config;
use Tienda\Core\Controller;
use Tienda\Core\Idirecto\Account;
use Tienda\Core\Logger;
use Tienda\Core\Registration;
use Tienda\Core\Session;
use Tienda\Core\Subdomain;
use Tienda\Models\ContractVersion;

/**
 * Registro publico de una tienda nueva.
 *
 * Mientras no exista una version contractual aprobada, el formulario no puede
 * enviarse. El interruptor REGISTRATION_COMPLETION_ENABLED permanece cerrado
 * hasta completar la generacion y envio del PDF y la verificacion por correo.
 *
 * Esta fase no crea tiendas, usuarios ni aceptaciones.
 */
final class RegistrationController extends Controller
{
    public function show(array $params = []): string
    {
        if (Auth::check()) {
            $this->redirect('panel');
        }

        $contract = ContractVersion::current(
            (string) Config::get('registration.contract.document_key', 'access_web')
        );

        if ($contract !== null) {
            Session::set('register_contract_id', (int) $contract['id']);
            Session::set('register_contract_seen_at', time());
        } else {
            Session::forget('register_contract_id');
            Session::forget('register_contract_seen_at');
        }

        $open = Registration::isOpen();
        $ready = $open
            && $contract !== null
            && Registration::completionEnabled()
            && Registration::onboardingSchemaReady();

        return $this->view('register', [
            'pageTitle' => 'Registra tu tienda',
            'open'      => $open,
            'ready'     => $ready,
            'contract'  => $contract,
            'slug'      => (string) $this->input('slug', ''),
        ], 'panel_blank');
    }

    /**
     * Entrega el PDF contractual vigente únicamente a la sesión que ha abierto
     * el formulario de alta. No es una URL pública del fichero.
     */
    public function contract(array $params = []): never
    {
        $contract = ContractVersion::current(
            (string) Config::get('registration.contract.document_key', 'access_web')
        );
        $seenAt = (int) Session::get('register_contract_seen_at', 0);
        $sessionVersion = (int) Session::get('register_contract_id', 0);
        $window = max(60, (int) Config::get('registration.contract.download_session_seconds', 3600));

        if ($contract === null
            || $sessionVersion !== (int) $contract['id']
            || $seenAt <= 0
            || (time() - $seenAt) > $window) {
            $this->notFound();
        }

        $path = ContractVersion::templateAbsolutePath($contract);
        if ($path === null) {
            $this->notFound();
        }

        session_write_close();
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="contrato-acceso.pdf"');
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: private, no-store, max-age=0');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function store(array $params = []): string
    {
        $this->requireCsrf('registro');

        if (!Registration::isOpen()) {
            $this->fail('El registro de tiendas esta cerrado en este momento.');
        }

        if ($this->blocked()) {
            $this->fail('Demasiados intentos fallidos. Espera unos minutos y vuelve a probar.');
        }

        $contract = ContractVersion::current(
            (string) Config::get('registration.contract.document_key', 'access_web')
        );
        if ($contract === null
            || !Registration::completionEnabled()
            || !Registration::onboardingSchemaReady()) {
            $this->fail('El alta todavia no esta disponible: falta habilitar el contrato y la verificacion por correo.');
        }

        $email = mb_strtolower(trim((string) $this->input('email', '')));
        $accountPassword = (string) $this->input('account_password', '');
        $panelPassword = (string) $this->input('password', '');
        $repeat = (string) $this->input('password_confirm', '');
        $slug = trim((string) $this->input('slug', ''));

        if (!mb_check_encoding($email, 'UTF-8')
            || mb_strlen($email) > 60
            || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('El correo corporativo no es valido.');
        }
        if ($accountPassword === '') {
            $this->fail('Escribe la contrasena de tu cuenta en idirecto.');
        }
        if (strlen($panelPassword) < 8) {
            $this->fail('La contrasena de este panel debe tener al menos 8 caracteres.');
        }
        if ($panelPassword !== $repeat) {
            $this->fail('Las dos contrasenas del panel no coinciden.');
        }

        $firstName = $this->representativeText('representative_first_name', 80, 'el nombre');
        $lastName = $this->representativeText('representative_last_name', 120, 'los apellidos');
        $role = $this->representativeText('representative_role', 120, 'el cargo');
        $phone = $this->representativeText('representative_phone', 40, 'el telefono');

        if (preg_match('/^[0-9+().\-\s]{6,40}$/', $phone) !== 1) {
            $this->fail('El telefono no tiene un formato valido.');
        }

        if ((string) $this->input('contract_accepted', '') !== '1') {
            $this->fail('Debes marcar la aceptacion expresa del contrato.');
        }
        if ((int) $this->input('contract_version_id', 0) !== (int) $contract['id']) {
            $this->fail('La version del contrato ha cambiado. Recarga la pagina antes de continuar.');
        }

        if ($slug !== '') {
            $normalized = Subdomain::normalize($slug);
            $slugError = Subdomain::formatError($normalized);
            if ($slugError !== null) {
                $this->fail('Direccion de tienda no valida: ' . $slugError);
            }
        }

        // La cuenta tiene que existir y estar activa en el mayorista.
        $cuenta = Account::login($email, $accountPassword);
        if ($cuenta === null) {
            $this->noteAttempt();
            Logger::warning('acceso', 'Registro de tienda rechazado: credenciales no validas');
            $this->fail('No encontramos ninguna cuenta activa en idirecto con esas credenciales.');
        }

        $accountEmail = mb_strtolower(trim((string) ($cuenta['email'] ?? '')));
        if ($accountEmail === '' || !hash_equals($accountEmail, $email)) {
            $this->noteAttempt();
            Logger::warning('acceso', 'Registro de tienda rechazado: el correo no coincide con la cuenta');
            $this->fail('El correo corporativo debe coincidir con el de la cuenta de idirecto.');
        }

        $existente = Registration::existingStoreFor((int) $cuenta['id']);
        if ($existente !== null) {
            $this->clearAttempts();
            $this->fail('Esa cuenta de idirecto ya tiene una tienda o un alta pendiente. Revisa el acceso que te enviamos.');
        }

        // Guarda de fase: aunque se active el interruptor por error, esta
        // version del codigo no puede crear la tienda ni la aceptacion hasta
        // que exista el flujo transaccional de PDF, correo y verificacion.
        $this->fail(
            'Datos validados. La generacion del contrato y la confirmacion por correo todavia no estan habilitadas; no se ha creado ninguna cuenta.'
        );
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    private function representativeText(string $field, int $maxLength, string $label): string
    {
        $value = trim((string) $this->input($field, ''));
        if (!mb_check_encoding($value, 'UTF-8')) {
            $this->fail('Revisa ' . $label . ': el texto no tiene una codificacion valida.');
        }
        $length = mb_strlen($value);
        if ($value === '' || $length > $maxLength || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
            $this->fail('Revisa ' . $label . ': es obligatorio y debe tener como maximo ' . $maxLength . ' caracteres.');
        }

        return $value;
    }

    private function fail(string $message): never
    {
        Session::flash('error', $message);
        $this->redirect('registro');
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>404</title></head><body style="font-family:system-ui,sans-serif;padding:2rem">'
            . '<h1>404</h1><p>Contrato no disponible.</p></body></html>';
        exit;
    }

    /**
     * Limite de intentos por sesion.
     *
     * El formulario comprueba credenciales contra la base de datos del
     * mayorista, asi que sin limite serviria para probar contrasenas a la
     * fuerza. Es un limite por sesion (no por IP).
     */
    private function blocked(): bool
    {
        $intentos = Session::get('register_attempts', []);
        if (!is_array($intentos) || ($intentos['until'] ?? 0) < time()) {
            return false;
        }
        return (int) ($intentos['count'] ?? 0) >= $this->maxAttempts();
    }

    private function noteAttempt(): void
    {
        $intentos = Session::get('register_attempts', []);
        $intentos = is_array($intentos) ? $intentos : [];
        $intentos['count'] = (int) ($intentos['count'] ?? 0) + 1;
        $intentos['until'] = time() + $this->window();
        Session::set('register_attempts', $intentos);
    }

    private function clearAttempts(): void
    {
        Session::forget('register_attempts');
    }

    private function maxAttempts(): int
    {
        return max(1, (int) Config::get('idirecto.register_attempts', 5));
    }

    private function window(): int
    {
        return max(60, (int) Config::get('idirecto.register_window', 900));
    }
}
