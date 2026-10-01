<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Catalogo central del mayorista (tablas productos/precios/stock/categorias).
 *
 * Aqui vive tambien la definicion de los FILTROS AVANZADOS y de los ACCESOS
 * RAPIDOS de la portada. Los dos son datos de configuracion: una tienda puede
 * adaptarlos a su publico sin tocar vistas ni modelos.
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

    // Unidades por debajo de las cuales se avisa de "ultimas unidades".
    'low_stock_threshold' => Env::int('CATALOG_LOW_STOCK', 5),

    // -------------------------------------------------------------------------
    // Especificaciones que se muestran en la tarjeta de producto (chips).
    // Se leen de `productos_ext.caracteristicas` (cadena corta "Clave: valor").
    // El orden importa: es el de prioridad. `label` es la etiqueta corta que
    // pinta la tarjeta y `keys` los nombres que usa el mayorista.
    // -------------------------------------------------------------------------
    'card_specs' => [
        ['label' => 'Socket',    'keys' => ['socket de procesador', 'socket', 'zocalo', 'zócalo']],
        ['label' => 'Grafica',   'keys' => ['modelo de adaptador de graficos', 'modelo de adaptador de gráficos',
                                             'procesador grafico', 'procesador gráfico', 'graficos', 'gráficos']],
        ['label' => 'CPU',       'keys' => ['familia de procesador', 'modelo del procesador', 'tipo de procesador', 'procesador']],
        ['label' => 'Memoria',   'keys' => ['tipos de memoria compatibles', 'tipo de memoria interna', 'memoria interna',
                                             'memoria ram', 'memoria instalada', 'tipo de memoria']],
        ['label' => 'Capacidad', 'keys' => ['capacidad total de almacenaje', 'capacidad de almacenamiento',
                                             'capacidad de la unidad de disco', 'capacidad de disco']],
        ['label' => 'Formato',   'keys' => ['factor de forma', 'factor']],
        ['label' => 'Pantalla',  'keys' => ['diagonal de la pantalla', 'tamano de pantalla', 'tamaño de pantalla']],
        ['label' => 'Velocidad', 'keys' => ['velocidad de memoria', 'frecuencia de memoria']],
        ['label' => 'Potencia',  'keys' => ['potencia de salida', 'potencia']],
        ['label' => 'Nucleos',   'keys' => ['numero de nucleos', 'número de núcleos', 'nucleos', 'núcleos']],
        ['label' => 'Color',     'keys' => ['color del producto', 'color']],
    ],

    // -------------------------------------------------------------------------
    // Accesos rapidos de la portada (grid de categorias principales).
    //
    // Se resuelven contra el arbol real de categorias con stock (`menuTree`),
    // asi que un acceso desaparece solo si esa categoria se queda vacia.
    // Admite `cat` (categoria), `subcat` (subcategoria) y `q` (busqueda), para
    // nichos que no tienen una subcategoria propia (p. ej. ultraligeros).
    // -------------------------------------------------------------------------
    'quick_links' => [
        [
            'label'    => 'Tarjetas graficas',
            'subtitle' => 'RTX 50, RX 9000 y workstation',
            'icon'     => 'gpu',
            'cat'      => 9,
            'subcat'   => 181,
        ],
        [
            'label'    => 'Portatiles ultraligeros',
            'subtitle' => 'Ultrabooks y 2 en 1',
            'icon'     => 'laptop',
            'cat'      => 36,
            'subcat'   => 1,
            'q'        => 'ultraligero',
        ],
        [
            'label'    => 'Componentes PC',
            'subtitle' => 'CPU, placas base, RAM y cajas',
            'icon'     => 'chip',
            'cat'      => 9,
        ],
        [
            'label'    => 'Perifericos',
            'subtitle' => 'Teclados, ratones y monitores',
            'icon'     => 'keyboard',
            'cat'      => 24,
        ],
        [
            'label'    => 'Setup gaming',
            'subtitle' => 'Sillas, mesas y mandos',
            'icon'     => 'gamepad',
            'cat'      => 31,
        ],
    ],

    // -------------------------------------------------------------------------
    // Filtros avanzados (facets).
    //
    //   scope  : donde buscar -> name (nombre), spec (caracteristicas), brand
    //   match  : like (contiene) | in (coincidencia exacta, para listas)
    //   terms  : valor => patron(es) de busqueda. La clave es la que viaja en la
    //            URL (?f[socket][]=am5), el valor lo que se muestra.
    //   when   : limita el filtro a ciertas categorias/subcategorias (opcional).
    //            Si no se indica, el filtro esta disponible en todo el catalogo.
    //   type   : terms (por defecto) | price | brand (lista dinamica del mayorista)
    //
    // Los patrones se buscan en el NOMBRE del producto (que trae la mayoria de
    // datos tecnicos) y, cuando hace falta, en `productos_ext.caracteristicas`.
    // -------------------------------------------------------------------------
    'facets' => [
        'socket' => [
            'label' => 'Socket',
            'scope' => ['name', 'spec'],
            'terms' => [
                'am5'      => ['label' => 'AM5', 'patterns' => ['AM5']],
                'am4'      => ['label' => 'AM4', 'patterns' => ['AM4']],
                'am3'      => ['label' => 'AM3', 'patterns' => ['AM3']],
                'lga1851'  => ['label' => 'LGA 1851', 'patterns' => ['LGA 1851', 'LGA1851']],
                'lga1700'  => ['label' => 'LGA 1700', 'patterns' => ['LGA 1700', 'LGA1700']],
                'lga1200'  => ['label' => 'LGA 1200', 'patterns' => ['LGA 1200', 'LGA1200']],
                'fm2'      => ['label' => 'FM2', 'patterns' => ['FM2']],
            ],
            'when' => ['cats' => [9], 'subcats' => [102, 104]],
        ],

        'gpu' => [
            'label' => 'Grafica',
            'scope' => ['name', 'spec'],
            'terms' => [
                'rtx50' => ['label' => 'NVIDIA RTX serie 50', 'patterns' => ['RTX 50']],
                'rtx40' => ['label' => 'NVIDIA RTX serie 40', 'patterns' => ['RTX 40']],
                'rtx30' => ['label' => 'NVIDIA RTX serie 30', 'patterns' => ['RTX 30']],
                'gtx'   => ['label' => 'NVIDIA GTX', 'patterns' => ['GTX ']],
                'rx90'  => ['label' => 'AMD Radeon RX 9000', 'patterns' => ['RX 90']],
                'rx70'  => ['label' => 'AMD Radeon RX 7000', 'patterns' => ['RX 70']],
                'rx60'  => ['label' => 'AMD Radeon RX 6000', 'patterns' => ['RX 60']],
                'arc'   => ['label' => 'Intel Arc', 'patterns' => ['Arc A', 'Arc B']],
            ],
            'when' => ['cats' => [36], 'subcats' => [181]],
        ],

        'memoria' => [
            'label' => 'Memoria',
            'scope' => ['name', 'spec'],
            'terms' => [
                'ddr5' => ['label' => 'DDR5', 'patterns' => ['DDR5']],
                'ddr4' => ['label' => 'DDR4', 'patterns' => ['DDR4']],
                'ddr3' => ['label' => 'DDR3', 'patterns' => ['DDR3']],
            ],
            'when' => ['cats' => [9, 36, 1], 'subcats' => [113, 114, 102]],
        ],

        'factor' => [
            'label' => 'Factor de forma',
            'scope' => ['name', 'spec'],
            'terms' => [
                'eatx'   => ['label' => 'E-ATX', 'patterns' => ['E-ATX', 'EATX']],
                'atx'    => ['label' => 'ATX', 'patterns' => ['ATX']],
                'matx'   => ['label' => 'Micro-ATX', 'patterns' => ['Micro ATX', 'micro ATX', 'Micro-ATX', 'mATX']],
                'itx'    => ['label' => 'Mini-ITX', 'patterns' => ['Mini-ITX', 'Mini ITX', 'mini ITX']],
            ],
            'when' => ['cats' => [], 'subcats' => [102, 112]],
        ],

        'almacenamiento' => [
            'label' => 'Almacenamiento',
            'scope' => ['name', 'spec'],
            'terms' => [
                'nvme' => ['label' => 'NVMe', 'patterns' => ['NVMe', 'NVME']],
                'ssd'  => ['label' => 'SSD', 'patterns' => ['SSD']],
                'hdd'  => ['label' => 'Disco duro', 'patterns' => ['HDD', '3.5"']],
            ],
            'when' => ['cats' => [9, 24, 36], 'subcats' => [109, 107, 106, 297, 91]],
        ],

        // Lista dinamica: las marcas con mas stock de la categoria activa.
        'marca' => [
            'type'     => 'brand',
            'label'    => 'Marca',
            'limit'    => 12,
            'min_items' => 3,
        ],

        // Rango de precio de la tarifa mas baja disponible.
        'precio' => [
            'type'  => 'price',
            'label' => 'Precio',
        ],
    ],

    // -------------------------------------------------------------------------
    // Orden del listado. `sql` se resuelve en Catalog.
    // -------------------------------------------------------------------------
    'sorts' => [
        'relevancia' => ['label' => 'Relevancia', 'sql' => 'relevance'],
        'precio-asc' => ['label' => 'Precio: de menor a mayor', 'sql' => 'price_asc'],
        'precio-desc' => ['label' => 'Precio: de mayor a menor', 'sql' => 'price_desc'],
        'nombre-asc' => ['label' => 'Nombre: A-Z', 'sql' => 'name_asc'],
        'nombre-desc' => ['label' => 'Nombre: Z-A', 'sql' => 'name_desc'],
    ],

    // TTL (segundos) de las caches de facetas (marcas y rango de precios).
    'facets_ttl' => Env::int('CATALOG_FACETS_TTL', 900),
];
