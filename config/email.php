<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Cliente de la API interna de correo con adjuntos.
 *
 * NO contiene credenciales ni configura SMTP. La integracion real permanece
 * desactivada por defecto y toma los mismos nombres que ya usa el sistema:
 * API_EMAIL, EMAIL_USER y EMAIL_PASS. Esas variables deben llegar al proceso
 * desde una fuente segura confirmada; no se copian a este repositorio.
 */
return [
    'api' => [
        'enabled'          => Env::bool('EMAIL_API_ENABLED', false),
        'base_url'         => (string) Env::get('API_EMAIL', ''),
        'username'         => (string) Env::get('EMAIL_USER', ''),
        'password'         => (string) Env::get('EMAIL_PASS', ''),
        'endpoint'         => 'send-email-adjunto',
        'connect_timeout'  => max(1, Env::int('EMAIL_API_CONNECT_TIMEOUT', 5)),
        'timeout'          => max(2, Env::int('EMAIL_API_TIMEOUT', 20)),
        'max_attachment_bytes' => 10 * 1024 * 1024,
    ],

    // Remitente de los correos transaccionales. Si falta, el alta se bloquea:
    // no se inventa una direccion ni se usa la del cliente.
    'from'      => (string) Env::get('EMAIL_FROM', ''),
    'from_name' => (string) Env::get('EMAIL_FROM_NAME', Env::get('APP_NAME', 'Tienda')),
    'reply_to'  => (string) Env::get('EMAIL_REPLY_TO', ''),
];
