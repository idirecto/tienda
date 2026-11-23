<?php

declare(strict_types=1);

/**
 * Consentimiento de cookies.
 *
 * El nombre, la version, la duracion y el dominio viven en un unico sitio.
 * `domain` se deja vacio para que la cookie sea host-only (sin atributo Domain);
 * el parametro se mantiene para poder cambiarlo de forma controlada en el
 * futuro sin tocar el JS ni el controlador.
 */
return [
    'name'    => 'id_consent',
    'version' => '1',
    'days'    => 180,

    // Host-only: no se envia Domain. No cambiar salvo decision expresa.
    'domain'  => '',

    // Clave de `mt_settings` para el ID de medicion por tienda. Vacio = no se
    // solicita ningun servicio de analitica. No se usa ningun ID global.
    'analytics_setting' => 'analytics_ga4_id',

    // Respaldo si la tienda no tiene un color primario valido.
    'fallback_color' => '#333333',

    // Fecha que se muestra en la politica. Se actualiza al revisar el texto.
    'policy_updated_at' => '23 de noviembre de 2026',

    // Tabla real de cookies propias. La politica se pinta desde aqui.
    'own_cookies' => [
        [
            'name'     => 'tienda_session',
            'category' => 'Tecnica / necesaria',
            'purpose'  => 'Mantiene la sesion: carrito, inicio de sesion, formularios y proteccion CSRF.',
            'duration' => 'Hasta el cierre de la sesion',
        ],
        [
            'name'     => 'id_consent',
            'category' => 'Tecnica / necesaria',
            'purpose'  => 'Guarda la decision sobre las categorias de cookies y la version del consentimiento.',
            'duration' => '180 dias',
        ],
    ],

    // Almacenamiento local (no es una cookie, pero se informa igualmente).
    'local_storage' => [
        [
            'name'     => 'tienda.color-scheme',
            'category' => 'Funcional',
            'purpose'  => 'Recuerda si el visitante prefiere el modo claro u oscuro de la tienda.',
            'duration' => 'Hasta borrar los datos del navegador',
        ],
    ],
];
