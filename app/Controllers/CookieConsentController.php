<?php

declare(strict_types=1);

namespace Tienda\Controllers;

use Tienda\Core\Controller;
use Tienda\Core\CookieConsent;
use Tienda\Core\Session;
use Tienda\Core\View;

/**
 * Politica de cookies y formulario de consentimiento sin JavaScript.
 *
 * El JS mejora el banner y evita recargar, pero aceptar/rechazar/guardar
 * tambien funciona con un POST normal y CSRF.
 */
final class CookieConsentController extends Controller
{
    public function policy(array $params = []): string
    {
        $base = View::basePath();

        return $this->view($this->themeView('cookies'), [
            'pageTitle'       => 'Politica de cookies - ' . $this->tenant->brandName(),
            'pageDescription' => 'Informacion sobre las cookies y el consentimiento de esta tienda.',
            'canonical'       => absolute_url($base . '/cookies'),
            'policy'          => CookieConsent::policy($this->tenant),
            'cookieState'     => CookieConsent::stateFromRequest(),
        ], 'shop');
    }

    public function save(array $params = []): string
    {
        $this->requireCsrf('cookies');

        $decision = (string) $this->input('decision', '');
        if (!in_array($decision, ['accept', 'reject', 'save'], true)) {
            Session::flash('error', 'La opcion de cookies no es valida. Vuelve a intentarlo.');
            $this->redirect('cookies');
        }

        if ($decision === 'accept') {
            $state = [
                'decided'   => true,
                'analytics' => true,
                'marketing' => true,
                'external'  => true,
            ];
        } elseif ($decision === 'reject') {
            $state = [
                'decided'   => true,
                'analytics' => false,
                'marketing' => false,
                'external'  => false,
            ];
        } else {
            $state = [
                'decided'   => true,
                'analytics' => (string) $this->input('analytics', '') === '1',
                'marketing' => (string) $this->input('marketing', '') === '1',
                'external'  => (string) $this->input('external', '') === '1',
            ];
        }

        CookieConsent::setCookie($state);

        // Destino local y validado: nunca un host, esquema o //externo.
        $fallback = $this->url('cookies');
        $return = CookieConsent::safeReturnPath((string) $this->input('return', ''), $fallback);

        header('Location: ' . $return, true, 303);
        exit;
    }
}
