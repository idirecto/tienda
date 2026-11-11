<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Alta de tiendas con contrato y confirmacion por correo.
 *
 * La activacion completa queda cerrada por defecto. Solo se abrira cuando
 * existan la version contractual autorizada, los valores legales del contrato,
 * el almacenamiento privado y la integracion de correo aprobada. Mientras
 * `completion_enabled` sea false, /registro puede mostrar el formulario pero no
 * crea ninguna tienda.
 */
return [
    'completion_enabled' => Env::bool('REGISTRATION_COMPLETION_ENABLED', false),

    // Guarda de la fase anterior. Se mantiene activa por defecto y solo se
    // desactiva de forma explicita para la prueba controlada del flujo.
    'legacy_guard_enabled' => Env::bool('REGISTRATION_LEGACY_GUARD_ENABLED', true),

    // api = API interna real | file = buzon falso privado (solo no produccion)
    'mail_transport' => (string) Env::get('REGISTRATION_MAIL_TRANSPORT', 'api'),
    'outbox_dir'     => TIENDA_BASE . '/storage/contracts/outbox',

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
        // PDFs individuales: fuera del docroot publico y sin URL.
        'generated_dir'            => TIENDA_BASE . '/storage/contracts/generated',
        // Motor PDF del sistema. Vacio = deteccion automatica.
        'tcpdf_path'               => (string) Env::get('CONTRACT_TCPDF_PATH', ''),
        // Ventana de sesion durante la que se permite descargar el contrato
        // vigente desde el propio flujo de alta.
        'download_session_seconds' => 3600,

        /**
         * Valores legales que deben existir antes de generar ningun PDF.
         * Si falta uno, ContractTemplate lanza ContractConfigurationException
         * y el alta queda bloqueada sin escribir en base de datos.
         */
        'values' => [
            'support_email'         => (string) Env::get('REGISTRATION_CONTRACT_SUPPORT_EMAIL', ''),
            'idirecto_contact'      => (string) Env::get('REGISTRATION_CONTRACT_IDIRECTO_CONTACT', ''),
            'liability_limit'       => (string) Env::get('REGISTRATION_CONTRACT_LIABILITY_LIMIT', ''),
            'preaviso_days'         => Env::int('REGISTRATION_CONTRACT_PREAVISO_DAYS', 0),
            'export_days'           => Env::int('REGISTRATION_CONTRACT_EXPORT_DAYS', 0),
            'negotiation_days'      => Env::int('REGISTRATION_CONTRACT_NEGOTIATION_DAYS', 0),
            'confidentiality_years' => Env::int('REGISTRATION_CONTRACT_CONFIDENTIALITY_YEARS', 0),
        ],
    ],
];
