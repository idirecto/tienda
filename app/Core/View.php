<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * Motor de plantillas sencillo: PHP plano con layout opcional.
 *
 *   View::render('panel/dashboard', ['foo' => 1], 'panel')
 *   -> incluye app/Views/panel/dashboard.php y lo envuelve en
 *      app/Views/layouts/panel.php teniendo el contenido en $content.
 */
final class View
{
    private static string $basePath = '';

    public static function setBasePath(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    public static function basePath(): string
    {
        return self::$basePath;
    }

    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $file = TIENDA_BASE . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Vista no encontrada: {$template}");
        }

        // Variables comunes a todas las vistas.
        $data['base'] = self::$basePath;
        $data['app_name'] = (string) Config::get('app.name', 'Tienda');
        $data['app_url'] = (string) Config::get('app.url', '');

        extract($data, EXTR_SKIP);

        ob_start();
        include $file;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = TIENDA_BASE . '/app/Views/layouts/' . $layout . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException("Layout no encontrado: {$layout}");
        }

        ob_start();
        include $layoutFile;
        return (string) ob_get_clean();
    }

    /** Escapa texto para HTML. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Formatea un importe en euros. */
    public static function money(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.') . ' €';
    }
}
