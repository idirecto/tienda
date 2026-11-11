<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Alta de tiendas con contrato y confirmacion por correo.
 *
 * La activacion completa queda cerrada por defecto. Solo se abrira cuando
 * existan la version contractual autorizada, el almacenamiento privado y la
 * integracion de correo aprobada. Mientras `completion_enabled` sea false,
 * /registro puede mostrar el formulario pero no crea ninguna tienda.
 */
return [
    'completion_enabled' => Env::bool('REGISTRATION_COMPLETION_ENABLED', false),

    'verification' => [
        // Codigo numerico generado con random_int. El hash se guarda en BD;
        // el codigo en claro solo vive en memoria durante el envio del correo.
        'digits'                 => max(6, min(12, Env::int('REGISTRATION_CODE_DIGITS', 8))),
        'ttl_seconds'            => max(60, Env::int('REGISTRATION_CODE_TTL', 900)),
        'max_attempts'           => max(1, Env::int('REGISTRATION_CODE_MAX_ATTEMPTS', 5)),
        'resend_cooldown_seconds'=> max(0, Env::int('REGISTRATION_RESEND_COOLDOWN', 60)),
        'max_resends_per_window' => max(1, Env::int('REGISTRATION_MAX_RESENDS', 5)),
        'resend_window_seconds'  => max(60, Env::int('REGISTRATION_RESEND_WINDOW', 3600)),
        'pending_ttl_seconds'    => max(600, Env::int('REGISTRATION_PENDING_TTL', 604800)),
    ],

    'contract' => [
        // Debe coincidir con el document_key de la version aprobada.
        'document_key'             => 'access_web',
        // Ruta privada de las plantillas aprobadas. No se sirve por web.
        'template_dir'             => TIENDA_BASE . '/storage/contracts/templates',
        // Ventana de sesion durante la que se permite descargar el contrato
        // vigente desde el propio flujo de alta.
        'download_session_seconds' => 3600,
    ],
];
