<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use Tienda\Core\Auth;
use Tienda\Core\ClientIp;
use Tienda\Core\Config;
use Tienda\Core\Controller;
use Tienda\Core\Contract\ContractPdfGenerator;
use Tienda\Core\Idirecto\Account;
use Tienda\Core\Logger;
use Tienda\Core\Mail\RegistrationMailer;
use Tienda\Core\Onboarding\RegistrationException;
use Tienda\Core\Onboarding\RegistrationFlow;
use Tienda\Core\Registration;
use Tienda\Core\Session;
use Tienda\Core\Subdomain;
use Tienda\Models\ContractVersion;
use Tienda\Models\RegistrationVerification;
use Tienda\Models\Store;
use Tienda\Models\StoreRegistration;

/**
 * Registro publico de una tienda nueva.
 *
 * El alta crea primero una tienda pendiente y un usuario inactivo, envia el
 * contrato individualizado y un codigo, y solo activa ambos tras verificar el
 * correo. La guarda antigua sigue disponible mediante
 * REGISTRATION_LEGACY_GUARD_ENABLED; por defecto permanece cerrada.
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
        $guardClosed = Registration::legacyGuardEnabled();
        $ready = $open
            && $contract !== null
            && !$guardClosed
            && RegistrationFlow::readinessProblems(RegistrationMailer::create()) === [];

        return $this->view('register', [
            'pageTitle'       => 'Registra tu tienda',
            'open'            => $open,
            'ready'           => $ready,
            'guard_closed'    => $guardClosed,
            'contract'        => $contract,
            'slug'            => (string) $this->input('slug', ''),
            'auth_card_class' => 'auth-card--register',
        ], 'panel_blank');
    }

    /**
     * Entrega el PDF contractual vigente unicamente a la sesión que ha abierto
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

        if (preg_match('/^[0-9+().\-\\s]{6,40}$/', $phone) !== 1) {
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

        // Guarda de fase: se conserva detras de una bandera para poder
        // desactivarla solo durante la prueba controlada del flujo completo.
        if (Registration::legacyGuardEnabled()) {
            $this->fail(
                'Datos validados. La generacion del contrato y la confirmacion por correo todavia no estan habilitadas; no se ha creado ninguna cuenta.'
            );
        }

        $flow = new RegistrationFlow(RegistrationMailer::create());
        try {
            $result = $flow->register(
                $cuenta,
                $email,
                $panelPassword,
                $slug !== '' ? Subdomain::normalize($slug) : null,
                [
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'role'       => $role,
                    'phone'      => $phone,
                ],
                $this->requestMeta()
            );
        } catch (RegistrationException $e) {
            Logger::warning('acceso', 'Alta de tienda bloqueada', ['motivo' => $e->reason()]);
            $this->fail($e->getMessage());
        } catch (Throwable $e) {
            Logger::error('acceso', 'Alta de tienda con error interno', ['excepcion' => get_class($e)]);
            $this->fail('No se pudo completar el alta. No se ha creado ninguna cuenta.');
        }

        $this->clearAttempts();
        Session::flash(
            'success',
            'Hemos enviado el contrato y un codigo de confirmacion a ' . $this->maskEmail($email)
            . '. Revisa tu correo para activar la tienda.'
        );
        $this->redirect('registro/verificar/' . rawurlencode($result['uuid']));
    }

    /** Pantalla para introducir el codigo recibido por correo. */
    public function verification(array $params = []): string
    {
        $registration = $this->registrationOr404($params);

        if ((string) $registration['status'] === StoreRegistration::STATUS_VERIFIED) {
            $this->redirect('registro/verificado/' . rawurlencode((string) $registration['public_uuid']));
        }

        $latest = RegistrationVerification::latestForRegistration((int) $registration['id']);
        $attemptsLeft = null;
        if ($latest !== null) {
            $attemptsLeft = max(0, (int) $latest['max_attempts'] - (int) $latest['attempts']);
        }

        $pending = (string) $registration['status'] === StoreRegistration::STATUS_PENDING;

        return $this->view('verification', [
            'pageTitle'       => 'Confirma tu correo',
            'registration'    => $registration,
            'uuid'            => (string) $registration['public_uuid'],
            'masked_email'    => $this->maskEmail((string) $registration['email']),
            'attempts_left'   => $attemptsLeft,
            'can_verify'      => $pending,
            'expires_label'   => $this->utcLabel($registration['expires_at'] ?? null),
            'auth_card_class' => 'auth-card--register',
        ], 'panel_blank');
    }

    /** Comprueba el codigo y activa tienda + usuario. */
    public function verify(array $params = []): string
    {
        $uuid = (string) ($params['uuid'] ?? '');
        $this->requireCsrf('registro/verificar/' . rawurlencode($uuid));

        $flow = new RegistrationFlow(RegistrationMailer::create());
        try {
            $result = $flow->verify($uuid, (string) $this->input('code', ''));
        } catch (RegistrationException $e) {
            if ($e->reason() === RegistrationFlow::NOT_FOUND) {
                $this->notFound();
            }
            $this->failTo('registro/verificar/' . rawurlencode($uuid), $e->getMessage());
        } catch (Throwable $e) {
            Logger::error('acceso', 'Verificacion de alta con error interno', ['excepcion' => get_class($e)]);
            $this->failTo(
                'registro/verificar/' . rawurlencode($uuid),
                'No se pudo comprobar el codigo. Intentalo de nuevo.'
            );
        }

        $outcome = (string) ($result['outcome'] ?? '');
        if ($outcome === 'verified' || $outcome === 'already_verified') {
            Session::flash('success', 'Correo verificado. Tu tienda ya esta activa.');
            $this->redirect('registro/verificado/' . rawurlencode($uuid));
        }

        $message = match ($outcome) {
            RegistrationFlow::EXPIRED  => 'El codigo ha caducado. Solicita uno nuevo.',
            RegistrationFlow::LOCKED   => 'Demasiados intentos con este codigo. Solicita uno nuevo.',
            RegistrationFlow::NOT_PENDING => 'El alta ya no esta pendiente de verificacion.',
            default => (($result['attempts_left'] ?? null) === 0)
                ? 'El codigo no es valido y se han agotado los intentos. Solicita uno nuevo.'
                : 'El codigo no es valido. Revisalo e intentalo de nuevo.',
        };

        $this->failTo('registro/verificar/' . rawurlencode($uuid), $message);
    }

    /** Reenvia un codigo nuevo al correo del alta. */
    public function resend(array $params = []): string
    {
        $uuid = (string) ($params['uuid'] ?? '');
        $this->requireCsrf('registro/verificar/' . rawurlencode($uuid));

        $flow = new RegistrationFlow(RegistrationMailer::create());
        try {
            $result = $flow->resend($uuid, $this->requestMeta());
        } catch (RegistrationException $e) {
            if ($e->reason() === RegistrationFlow::NOT_FOUND) {
                $this->notFound();
            }
            $this->failTo('registro/verificar/' . rawurlencode($uuid), $e->getMessage());
        } catch (Throwable $e) {
            Logger::error('acceso', 'Reenvio de alta con error interno', ['excepcion' => get_class($e)]);
            $this->failTo(
                'registro/verificar/' . rawurlencode($uuid),
                'No se pudo reenviar el codigo. Intentalo de nuevo.'
            );
        }

        if ((string) ($result['outcome'] ?? '') === 'already_verified') {
            Session::flash('success', 'Tu correo ya estaba verificado y la tienda esta activa.');
            $this->redirect('registro/verificado/' . rawurlencode($uuid));
        }

        Session::flash('success', 'Hemos enviado un codigo nuevo. El anterior ya no sirve.');
        $this->redirect('registro/verificar/' . rawurlencode($uuid));
    }

    /** Confirmacion final, solo si el alta esta verificada. */
    public function verified(array $params = []): string
    {
        $registration = $this->registrationOr404($params);
        if ((string) $registration['status'] !== StoreRegistration::STATUS_VERIFIED) {
            $this->redirect('registro/verificar/' . rawurlencode((string) $registration['public_uuid']));
        }

        $store = Store::find((int) $registration['store_id']);

        return $this->view('verified', [
            'pageTitle'       => 'Tienda activada',
            'registration'    => $registration,
            'store'           => $store,
            'store_url'       => $store !== null ? Subdomain::url((string) $store['slug']) : '',
            'auth_card_class' => 'auth-card--register',
        ], 'panel_blank');
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    private function registrationOr404(array $params): array
    {
        $registration = RegistrationFlow::findByUuid((string) ($params['uuid'] ?? ''));
        if ($registration === null) {
            $this->notFound();
        }

        return $registration;
    }

    /**
     * Metadatos de la peticion para la auditoria. Se guardan en la aceptacion,
     * nunca en los logs.
     *
     * @return array{ip:string,user_agent:string,base_url:string}
     */
    private function requestMeta(): array
    {
        return [
            'ip'         => ClientIp::fromGlobals(),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            'base_url'   => absolute_url('/registro/verificar/'),
        ];
    }

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

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2 || $parts[0] === '') {
            return '***';
        }

        $local = mb_substr($parts[0], 0, 1) . '***';

        return $local . '@' . $parts[1];
    }

    private function utcLabel(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $date = $date->setTimezone(new DateTimeZone((string) Config::get('app.timezone', 'Europe/Madrid')));

            return $date->format('d/m/Y H:i') . ' (hora de Madrid)';
        } catch (Throwable) {
            return '';
        }
    }

    private function fail(string $message): never
    {
        Session::flash('error', $message);
        $this->redirect('registro');
    }

    private function failTo(string $path, string $message): never
    {
        Session::flash('error', $message);
        $this->redirect($path);
    }

    private function notFound(): never
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>404</title></head><body style="font-family:system-ui,sans-serif;padding:2rem">'
            . '<h1>404</h1><p>Alta no disponible.</p></body></html>';
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
