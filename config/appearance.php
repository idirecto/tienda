<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Sistema de diseno (design tokens) de la plataforma.
 *
 * Es el unico sitio donde viven los colores, tipografias, radios y sombras por
 * defecto. El storefront NO escribe colores a mano: solo consume variables CSS
 * que genera `Tienda\Core\Appearance` a partir de este fichero y de lo que cada
 * tienda haya configurado en `mt_stores`.
 *
 * Orden de prioridad (de menos a mas):
 *   1. Estos valores por defecto.
 *   2. Columnas de diseno de la tienda (`color_primary`, `color_accent`...).
 *   3. `mt_stores.theme_tokens` (JSON libre), que permite pisar cualquier token
 *      del sistema sin tocar codigo ni migrar la base de datos.
 *
 * Las claves de `palettes` son los "slots" semanticos del sistema; el nombre de
 * cada token CSS es el mismo con el prefijo `--c-` (bg -> --c-bg).
 */
return [
    // -------------------------------------------------------------------------
    // Paleta clara (por defecto). Blanco/neutro para que valga cualquier marca.
    // -------------------------------------------------------------------------
    'light' => [
        'bg'            => Env::get('THEME_BG', '#f4f6fa'),
        'bg-alt'        => '#eceff5',
        'surface'       => Env::get('THEME_SURFACE', '#ffffff'),
        'surface-2'     => '#f8fafc',
        'surface-inverse' => '#111827',
        'heading'       => '#0f172a',
        'text'          => Env::get('THEME_TEXT', '#1f2937'),
        'muted'         => '#64748b',
        'text-inverse'  => '#f8fafc',
        'border'        => Env::get('THEME_BORDER', '#e2e8f0'),
        'border-strong' => '#cbd5e1',

        'success'      => '#15803d',
        'success-soft' => '#dcfce7',
        'warning'      => '#b45309',
        'warning-soft' => '#fef3c7',
        'danger'       => '#b91c1c',
        'danger-soft'  => '#fee2e2',
        'info'         => '#0369a1',
        'info-soft'    => '#e0f2fe',

        'stock-in'  => '#15803d',
        'stock-low' => '#b45309',
        'stock-out' => '#94a3b8',
    ],

    // -------------------------------------------------------------------------
    // Paleta oscura (modo Caseking). Solo se aplican los tokens de superficie y
    // texto; el color de marca de la tienda se mantiene igual en ambos modos.
    // -------------------------------------------------------------------------
    'dark' => [
        'bg'            => '#0a0e14',
        'bg-alt'        => '#0f151d',
        'surface'       => '#141b25',
        'surface-2'     => '#1b2431',
        'surface-inverse' => '#f1f5f9',
        'heading'       => '#f8fafc',
        'text'          => '#e2e8f0',
        'muted'         => '#94a3b8',
        'text-inverse'  => '#0f172a',
        'border'        => '#26313f',
        'border-strong' => '#3b4a5c',

        'success'      => '#4ade80',
        'success-soft' => '#0f2a1c',
        'warning'      => '#fbbf24',
        'warning-soft' => '#2b2109',
        'danger'       => '#f87171',
        'danger-soft'  => '#2b1414',
        'info'         => '#38bdf8',
        'info-soft'    => '#0b2536',

        'stock-in'  => '#4ade80',
        'stock-low' => '#fbbf24',
        'stock-out' => '#64748b',
    ],

    // -------------------------------------------------------------------------
    // Identidad por defecto de la plataforma (cada tienda la pisa).
    // -------------------------------------------------------------------------
    'brand' => [
        'primary'   => Env::get('THEME_PRIMARY', '#e30613'),
        'secondary' => Env::get('THEME_SECONDARY', '#111827'),
        'accent'    => Env::get('THEME_ACCENT', '#00aff0'),
    ],

    // Esquema por defecto: light | dark | auto (auto = sigue al sistema).
    'scheme' => Env::get('THEME_SCHEME', 'light'),

    // -------------------------------------------------------------------------
    // Radios: el panel elige una escala, no pixeles sueltos.
    // -------------------------------------------------------------------------
    'radius' => [
        'compact'  => ['sm' => '2px',  'md' => '4px',  'lg' => '6px',   'xl' => '10px'],
        'standard' => ['sm' => '6px',  'md' => '10px', 'lg' => '14px',  'xl' => '20px'],
        'rounded'  => ['sm' => '10px', 'md' => '16px', 'lg' => '22px',  'xl' => '30px'],
    ],
    'radius_default' => 'standard',

    // -------------------------------------------------------------------------
    // Tipografias: pilas del sistema (sin CDN ni dependencias).
    // -------------------------------------------------------------------------
    'fonts' => [
        'system' => [
            'label' => 'Sistema (sans)',
            'body'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
            'head'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif",
        ],
        'grotesk' => [
            'label' => 'Neo-grotesca (tech)',
            'body'  => "'Inter', 'Helvetica Neue', Helvetica, Arial, sans-serif",
            'head'  => "'Inter Tight', 'Inter', 'Helvetica Neue', Arial, sans-serif",
        ],
        'condensed' => [
            'label' => 'Condensada (gaming)',
            'body'  => "'Roboto Condensed', 'Arial Narrow', Roboto, Arial, sans-serif",
            'head'  => "'Roboto Condensed', 'Arial Narrow', Roboto, Arial, sans-serif",
        ],
        'serif' => [
            'label' => 'Serif editorial',
            'body'  => "Georgia, 'Times New Roman', serif",
            'head'  => "Georgia, 'Times New Roman', serif",
        ],
        'mono' => [
            'label' => 'Monoespaciada',
            'body'  => "'SFMono-Regular', Consolas, 'Liberation Mono', monospace",
            'head'  => "'SFMono-Regular', Consolas, 'Liberation Mono', monospace",
        ],
    ],
    'font_default' => 'system',

    // -------------------------------------------------------------------------
    // Presets: aplicar una identidad completa en un clic desde el panel.
    // `primary`/`secondary`/`accent` son los tres colores de marca; el resto de
    // la paleta se deriva sola.
    // -------------------------------------------------------------------------
    'presets' => [
        'scan' => [
            'label'     => 'Retail limpio',
            'primary'   => '#e30613',
            'secondary' => '#111827',
            'accent'    => '#00aff0',
            'scheme'    => 'light',
            'radius'    => 'standard',
            'font'      => 'grotesk',
        ],
        'caseking' => [
            'label'     => 'Tecnologico oscuro',
            'primary'   => '#ff5c00',
            'secondary' => '#0b0e13',
            'accent'    => '#00e0c6',
            'scheme'    => 'dark',
            'radius'    => 'compact',
            'font'      => 'grotesk',
        ],
        'gaming' => [
            'label'     => 'Gaming',
            'primary'   => '#7c3aed',
            'secondary' => '#0f0b1f',
            'accent'    => '#22d3ee',
            'scheme'    => 'dark',
            'radius'    => 'rounded',
            'font'      => 'condensed',
        ],
        'pro' => [
            'label'     => 'Profesional',
            'primary'   => '#0b5fff',
            'secondary' => '#0f172a',
            'accent'    => '#14b8a6',
            'scheme'    => 'light',
            'radius'    => 'standard',
            'font'      => 'grotesk',
        ],
        'minimal' => [
            'label'     => 'Minimal',
            'primary'   => '#111827',
            'secondary' => '#374151',
            'accent'    => '#6b7280',
            'scheme'    => 'light',
            'radius'    => 'compact',
            'font'      => 'system',
        ],
    ],

    // -------------------------------------------------------------------------
    // Layout: anchos del contenedor general.
    // -------------------------------------------------------------------------
    'layout' => [
        'container_min'   => '1180px',
        'container_max'   => '1600px',
        'container_ultra' => '1800px',
    ],
];
