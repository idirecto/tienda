<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Identidad de la PLATAFORMA (no de cada tienda).
 *
 * Aqui vive el favicon predeterminado de Valduran: el que se muestra en todas
 * las tiendas que todavia no han subido el suyo y el que se usa como ultimo
 * recurso si el favicon propio de una tienda desaparece o no se puede cargar.
 *
 *   - `default` es el icono principal (ICO: lo entienden todos los navegadores).
 *   - `extra`   son iconos adicionales que los navegadores modernos prefieren
 *               cuando los soportan (SVG: nitido en cualquier densidad).
 *   - `apple`   es el icono de la pantalla de inicio en iOS (180x180).
 *   - `version` opcional para forzar la recarga (vacio = fecha del fichero).
 *
 * Los tres admiten una **ruta relativa a `public/`** (`assets/img/...`) o una
 * **URL absoluta** (por ejemplo un recurso en el CDN). Las rutas relativas solo
 * se emiten si el fichero existe: si alguien borra el recurso, la aplicacion no
 * pinta un favicon roto (cae al SVG en linea de `Core\Favicon`).
 *
 * La tienda NUNCA puede tocar esto: su panel solo escribe `favicon_url` en
 * `mt_stores`. El valor predeterminado sale siempre de esta configuracion.
 *
 * Para sustituirlo sin tocar codigo, en el `.env`:
 *
 *   BRAND_FAVICON=https://cdn.ejemplo.com/mi-favicon.ico
 *   BRAND_FAVICON_EXTRA=
 *   BRAND_FAVICON_APPLE=
 *   BRAND_FAVICON_VERSION=1
 */
return [
    'favicon' => [
        'default' => Env::get('BRAND_FAVICON', 'assets/img/favicon-valduran.ico'),
        'extra'   => Env::get('BRAND_FAVICON_EXTRA', 'assets/img/favicon-valduran.svg'),
        'apple'   => Env::get('BRAND_FAVICON_APPLE', 'assets/img/favicon-valduran-180.png'),
        'version' => (string) Env::get('BRAND_FAVICON_VERSION', ''),
    ],
];
