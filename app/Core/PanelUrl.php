<?php

declare(strict_types=1);

namespace Tienda\Core;

/**
 * URL del panel del usuario autenticado.
 *
 * Si el usuario pertenece a una tienda, se le manda a su subdominio para que
 * el panel use el tenant correcto. Así el dominio principal puede ser la
 * portada de plataforma sin romper el acceso.
 */
final class PanelUrl
{
    public static function forUser(?array $user = null): string
    {
        $user ??= Auth::user();
        $storeId = (int) ($user['store_id'] ?? 0);

        if ($storeId > 0) {
            $slug = Database::scalar(
                'SELECT slug FROM mt_stores WHERE id = :id AND status = 1 LIMIT 1',
                ['id' => $storeId]
            );
            if (is_string($slug) && $slug !== '') {
                return Subdomain::url($slug) . '/panel';
            }
        }

        return View::basePath() . '/panel/login';
    }
}
