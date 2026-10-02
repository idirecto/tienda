<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Router minimo con soporte de parametros {id} y deteccion de subdirectorio.
 */
final class Router
{
    /** @var array<string, array<int, array{regex:string, handler:array}>> */
    private array $routes = ['GET' => [], 'POST' => []];

    private string $basePath;

    public function __construct()
    {
        $this->basePath = self::detectBasePath();
    }

    public function get(string $path, array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, array $handler): void
    {
        $this->routes[$method][] = [
            'regex'   => $this->compile($path),
            'handler' => $handler,
        ];
    }

    /**
     * Convierte /producto/{id} en una expresion regular con grupos nombrados.
     *
     *   {param}     -> un segmento           ([^/]+)
     *   {param...}  -> el resto de la ruta   (.+)   (para rutas SEO de cola)
     */
    private function compile(string $path): string
    {
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\.\.\.\}#', '(?P<$1>.+)', $path);
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', (string) $regex);
        return '#^' . $regex . '$#';
    }

    /** Detecta el subdirectorio base (por si la app no cuelga de la raiz). */
    public static function detectBasePath(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return ($dir === '/' || $dir === '.') ? '' : $dir;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    /** Ruta actual, sin base path ni query string. */
    public function currentPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }

        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    public function dispatch(Tenant $tenant): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $path = $this->currentPath();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            [$class, $action] = $route['handler'];

            if (!class_exists($class)) {
                throw new \RuntimeException("Controlador no encontrado: $class");
            }

            $controller = new $class($tenant, $this);
            echo $controller->{$action}($params);
            return;
        }

        $this->notFound($tenant);
    }

    private function notFound(Tenant $tenant): void
    {
        http_response_code(404);
        echo View::render('errors/404', [
            'tenant' => $tenant,
            'base'   => $this->basePath,
        ], 'shop');
    }
}
