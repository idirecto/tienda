<?php

declare(strict_types=1);

namespace Tienda\Controllers\Admin;

use Tienda\Core\Auth;
use Tienda\Core\Controller;
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
            Session::flash('error', 'Credenciales incorrectas.');
            $this->redirect('panel/login');
        }

        // El usuario puede pertenecer a una tienda distinta a la resuelta por host.
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
