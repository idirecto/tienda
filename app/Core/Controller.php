<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Controlador base: acceso a tenant, vistas, redirecciones, entrada y auth.
 */
abstract class Controller
{
    protected Tenant $tenant;
    protected Router $router;

    public function __construct(Tenant $tenant, Router $router)
    {
        $this->tenant = $tenant;
        $this->router = $router;
    }

    protected function view(string $template, array $data = [], ?string $layout = null): string
    {
        $data['tenant'] = $this->tenant;
        $data['auth_user'] = Auth::user();
        return View::render($template, $data, $layout);
    }

    /** Devuelve un parametro de la peticion (POST o GET). */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $value;
    }

    protected function allInput(): array
    {
        return array_merge($_GET, $_POST);
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . $this->url($path));
        exit;
    }

    protected function url(string $path = ''): string
    {
        return $this->router->basePath() . '/' . ltrim($path, '/');
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Exige usuario de panel autenticado. */
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            Session::flash('error', 'Inicia sesion para continuar.');
            $this->redirect('panel/login');
        }
    }

    /** Valida el token CSRF de la peticion POST. */
    protected function requireCsrf(): void
    {
        if (!Csrf::validate($_POST['_token'] ?? null)) {
            http_response_code(419);
            Session::flash('error', 'Token de seguridad invalido. Vuelve a enviar el formulario.');
            $this->redirect('panel');
        }
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}
