<?php

declare(strict_types=1);

namespace Tienda\Core\Mail;

use Tienda\Core\Config;

/**
 * Elige el transporte de correo del alta.
 *
 * `api` usa la API interna real; `file` usa el buzon falso privado y solo
 * funciona fuera de produccion. Las pruebas inyectan directamente un cliente
 * falso en `RegistrationFlow`, sin pasar por esta factoria.
 */
final class RegistrationMailer
{
    public static function create(): EmailApiClientInterface
    {
        $transport = strtolower(trim((string) Config::get('registration.mail_transport', 'api')));
        if ($transport === 'file') {
            return new FileOutboxEmailApiClient();
        }

        return new EmailApiClient();
    }

    public static function transport(): string
    {
        return strtolower(trim((string) Config::get('registration.mail_transport', 'api')));
    }
}
