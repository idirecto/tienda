<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Catalogo central del mayorista (tablas productos/precios/stock/categorias).
 */
return [
    'enabled'   => Env::bool('CATALOG_ENABLED', true),

    // URL base de las imagenes del catalogo central (p.ej. https://idirecto.es/img_products)
    'image_url' => rtrim((string) Env::get('CATALOG_IMAGE_URL', ''), '/'),

    // Ruta en disco de esas imagenes (opcional). Si se informa, la ficha solo
    // muestra las imagenes que existen realmente (util en local).
    'image_path' => rtrim((string) Env::get('CATALOG_IMAGE_PATH', ''), '/'),

    // Tamano de la imagen que se usa en los listados (catalogo y destacados).
    //   c ~3 KB (miniatura) | l ~7 KB (listados) | f ~38 KB (ficha) | g ~4 MB (zoom)
    'card_image_size' => (string) Env::get('CATALOG_CARD_IMAGE_SIZE', 'l'),

    // Margen (%) aplicado cuando el producto no tiene precio propio de tarifa.
    'markup'    => (float) Env::get('CATALOG_MARKUP', 30),

    'per_page'  => Env::int('CATALOG_PER_PAGE', 12),
];
