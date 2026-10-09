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
use Tienda\Core\ValidationException;

/**
 * Registro publico de una tienda nueva.
 *
 * Regla: **solo quien ya es cliente del mayorista puede tener tienda aqui**. El
 * tendero entra con el email y la contrasena de su cuenta de idirecto; se
 * comprueban contra `tiendas` (`activo = 2`) y se crea su tienda + su usuario de
 * panel. Su contrasena del mayorista no se guarda: solo se usa para comprobar
 * que la cuenta es suya.
 */
final class RegistrationController extends Controller
{
    public function show(array $params = []): string
    {
        if (Auth::check()) {
            $this->redirect('panel');
        }

        return $this->view('register', [
            'pageTitle' => 'Registra tu tienda',
            'open'      => Registration::isOpen(),
            'slug'      => (string) $this->input('slug', ''),
        ], 'panel_blank');
    }

    public function store(array $params = []): string
    {
        $this->requireCsrf('registro');

        if (!Registration::isOpen()) {
            Session::flash('error', 'El registro de tiendas esta cerrado en este momento.');
            $this->redirect('registro');
        }

        if ($this->blocked()) {
            Session::flash('error', 'Demasiados intentos fallidos. Espera unos minutos y vuelve a probar.');
            $this->redirect('registro');
        }

        $email = mb_strtolower(trim((string) $this->input('email', '')));
        $accountPassword = (string) $this->input('account_password', '');
        $panelPassword = (string) $this->input('password', '');
        $repeat = (string) $this->input('password_confirm', '');
        $slug = trim((string) $this->input('slug', ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $accountPassword === '') {
            Session::flash('error', 'Escribe el email y la contrasena de tu cuenta en idirecto.');
            $this->redirect('registro');
        }
        if (strlen($panelPassword) < 8) {
            Session::flash('error', 'La contrasena de este panel debe tener al menos 8 caracteres.');
            $this->redirect('registro');
        }
        if ($panelPassword !== $repeat) {
            Session::flash('error', 'Las dos contrasenas del panel no coinciden.');
            $this->redirect('registro');
        }

        // La cuenta tiene que existir y estar activa en el mayorista.
        $cuenta = Account::login($email, $accountPassword);
        if ($cuenta === null) {
            $this->noteAttempt();
            Logger::warning('acceso', 'Registro de tienda rechazado: cuenta no valida', [
                'email' => mb_substr($email, 0, 180),
                'ip'    => $this->clientIp(),
            ]);
            Session::flash('error', 'No encontramos ninguna cuenta activa en idirecto con esas credenciales.');
            $this->redirect('registro');
        }

        // Una cuenta solo puede tener una tienda en esta web.
        $existente = Registration::existingStoreFor((int) $cuenta['id']);
        if ($existente !== null) {
            $this->clearAttempts();
            Session::flash('error', 'Esa cuenta de idirecto ya tiene tienda (' . $existente['name'] . '). Entra con tu usuario del panel.');
            $this->redirect('panel/login');
        }

        try {
            $result = Registration::register($cuenta, $email, $panelPassword, $slug !== '' ? $slug : null);
        } catch (ValidationException $e) {
            $this->noteAttempt();
            Session::flash('error', $e->getMessage());
            $this->redirect('registro');
        } catch (\Throwable $e) {
            Logger::error('acceso', 'Fallo al registrar la tienda: ' . $e->getMessage(), [
                'email'     => mb_substr($email, 0, 180),
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);
            Session::flash('error', 'No se ha podido crear la tienda. Intentalo de nuevo en unos minutos.');
            $this->redirect('registro');
        }

        $this->clearAttempts();

        // Sesion de panel con el usuario recien creado.
        if (!Auth::attempt($email, $panelPassword)) {
            Session::flash('success', 'Tu tienda esta creada. Entra con tu email y contrasena.');
            $this->redirect('panel/login');
        }

        // Ya con sesion: el log queda en el fichero de la tienda recien creada.
        Logger::info('acceso', 'Tienda registrada', [
            'store_id' => (int) ($result['store_id'] ?? 0),
            'slug'     => (string) ($result['slug'] ?? ''),
            'email'    => mb_substr($email, 0, 180),
            'cuenta'   => (int) $cuenta['id'],
        ]);

        $publicUrl = $this->publicUrl($result['slug']);
        Session::flash(
            'success',
            'Tu tienda ya esta activa en ' . $publicUrl . '. Revisa Diseno y Ajustes: los datos vienen de tu cuenta de idirecto.'
        );
        $this->redirect('panel');
    }

    // =====================================================================
    // INTERNOS
    // =====================================================================

    /** URL publica que tendra la tienda (subdominio o dominio propio). */
    private function publicUrl(string $slug): string
    {
        $base = (string) (Config::get('tenant.base_domains', ['localhost'])[0] ?? 'localhost');

        return 'https://' . $slug . '.' . $base;
    }

    /**
     * Limite de intentos por sesion.
     *
     * El formulario comprueba credenciales contra la base de datos del
     * mayorista, asi que sin limite serviria para probar contrasenas a la
     * fuerza. Es un limite por sesion (no por IP): suficiente para el uso
     * normal y no necesita almacenamiento compartido.
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

    private function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '-');
    }
}
