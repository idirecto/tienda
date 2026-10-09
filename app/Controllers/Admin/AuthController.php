<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
use Tienda\Core\Logger;
use Tienda\Core\Session;

/**
 * Acceso al panel de la tienda.
 */
final class AuthController extends Controller
{
    public function showLogin(array $params = []): string
    {
        if (Auth::check()) {
            $this->redirect('panel');
        }
        return $this->view('panel/login', [
            'pageTitle' => 'Acceso al panel',
        ], 'panel_blank');
    }

    public function login(array $params = []): string
    {
        $email = (string) $this->input('email', '');
        $password = (string) $this->input('password', '');

        if ($email === '' || $password === '') {
            Session::flash('error', 'Introduce tu email y contrasena.');
            $this->redirect('panel/login');
        }

        if (!Auth::attempt($email, $password)) {
            // Entrada fallida al panel: puede ser el tendero equivocandose de
            // contrasena o alguien probando; queda en el log de acceso.
            Logger::warning('acceso', 'Entrada al panel fallida', [
                'email' => mb_substr($email, 0, 180),
            ]);
            Session::flash('error', 'Credenciales incorrectas.');
            $this->redirect('panel/login');
        }

        // El usuario puede pertenecer a una tienda distinta a la resuelta por host.
        $user = Auth::user();
        Logger::info('acceso', 'Entrada al panel correcta', [
            'user_id' => (int) ($user['id'] ?? 0),
            'email'   => (string) ($user['email'] ?? $email),
            'rol'     => Auth::role(),
        ]);
        Session::flash('success', 'Bienvenido de nuevo.');
        $this->redirect('panel');
    }

    public function logout(array $params = []): string
    {
        Auth::logout();
        Session::flash('success', 'Sesion cerrada.');
        $this->redirect('panel/login');
    }
}
