<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Controller;
use Tienda\Core\Favicon;

/**
 * Favicon del sitio a una URL fija.
 *
 * Los navegadores (y algunos rastreadores) piden `/favicon.ico` aunque la
 * pagina no lo declare. Esta ruta resuelve el favicon **efectivo de la tienda**
 * con `Core\Favicon` -el suyo si esta disponible, si no el predeterminado de
 * Valduran- y manda al recurso real.
 *
 * Es la red de seguridad de la regla "nunca un favicon roto": aunque la tienda
 * borre su fichero o su URL deje de ser valida, esta ruta siempre responde con
 * un favicon (el predeterminado) en lugar de un 404.
 *
 * Se usa 302 (y no 301) para que, si la tienda cambia su favicon, el navegador
 * lo vuelva a pedir sin quedarse pegado a una redireccion permanente.
 */
final class FaviconController extends Controller
{
    /** GET /favicon.ico */
    public function ico(array $params = []): string
    {
        return $this->redirectToFavicon('image/x-icon');
    }

    /** GET /favicon.svg */
    public function svg(array $params = []): string
    {
        return $this->redirectToFavicon('image/svg+xml');
    }

    /**
     * Manda al favicon efectivo. Si se pide un formato concreto (`.svg`) y la
     * tienda usa el predeterminado, se elige el icono de ese formato cuando
     * existe; en cualquier otro caso, el principal.
     */
    private function redirectToFavicon(string $preferido = ''): string
    {
        $favicon = Favicon::resolve($this->tenant);
        $href = (string) $favicon['href'];

        if (!$favicon['custom'] && $preferido !== '') {
            foreach (Favicon::defaultSources() as $source) {
                if (($source['type'] ?? '') === $preferido) {
                    $href = (string) $source['href'];
                    break;
                }
            }
        }

        // Cache corta y revalidable: el navegador puede guardarlo unos minutos
        // pero un cambio de favicon no tarda en llegar.
        header('Cache-Control: public, max-age=300');
        header('Location: ' . $href, true, 302);

        return '';
    }
}
