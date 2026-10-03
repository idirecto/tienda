<?php

declare(strict_types=1);

use Tienda\Core\Env;

/**
 * Navegacion de la tienda.
 *
 * El arbol de categorias vive en `mt_menu_items` (por tienda) y lo comparten
 * los DOS menus: lo unico que cambia es como se presenta. La tienda elige cual
 * quiere con `mt_stores.menu_style`, y no hay una tercera opcion.
 *
 *   compacto -> barra horizontal bajo la cabecera, con megamenu al desplegar
 *   catalogo -> boton «Todas las categorias» que abre un panel a pantalla
 *
 * En pantallas menores de 1024 px los dos se convierten en el mismo menu
 * lateral (drawer) con acordeones, boton de volver y de cerrar.
 */
return [
    // Los dos (y solo dos) estilos de menu que puede elegir una tienda.
    'styles' => [
        'compacto' => 'Menu Compacto',
        'catalogo' => 'Menu Catalogo',
    ],

    // Estilo con el que nace una tienda nueva.
    'default_style' => Env::get('MENU_DEFAULT_STYLE', 'catalogo'),

    // Estilo que se usa si la tienda trae un valor que no esta en la lista.
    'fallback_style' => 'catalogo',

    // -------------------------------------------------------------------------
    // ALCANCE DEL MENU: la tienda decide que categorias se ven en su web.
    //
    //   completo -> se ve todo el menu del catalogo menos lo que la tienda oculte
    //   elegido  -> se ve SOLO lo que la tienda marque como visible (+ su rama)
    //
    // La eleccion vive en `mt_stores.menu_scope` y las anulaciones por nodo en
    // `mt_menu_item_overrides` (mostrar/ocultar y renombrar).
    // -------------------------------------------------------------------------
    'scopes' => [
        'completo' => 'Menu completo (todo el catalogo)',
        'elegido'  => 'Solo las categorias que yo elija',
    ],

    // Modo con el que nace una tienda nueva.
    'fallback_scope' => 'completo',

    // Menu Compacto: cuantas categorias de primer nivel caben en la barra. El
    // resto se agrupa en «Mas categorias» (nunca se pierde ninguna).
    'compact_max' => Env::int('MENU_COMPACT_MAX', 7),

    // Punto de corte de movil (px). Debe coincidir con el de shop.css.
    'mobile_breakpoint' => 1024,

    // Contenido del panel de cada categoria (banner, destacados y marcas).
    'panel' => [
        'products' => Env::int('MENU_PANEL_PRODUCTS', 3),
        'brands'   => Env::int('MENU_PANEL_BRANDS', 6),
        // Banner de menu: usa los banners de la tienda con posicion `menu`.
        'banner'   => Env::bool('MENU_PANEL_BANNER', true),
    ],

    // TTL de la cache en fichero del arbol de menu (segundos).
    'cache_ttl' => Env::int('MENU_CACHE_TTL', 900),

    // Niveles del arbol. Tres: categoria -> grupo -> destino.
    'levels' => 3,

    // -------------------------------------------------------------------------
    // EDITOR DEL MENU (panel)
    // -------------------------------------------------------------------------

    // Iconos que puede elegir la tienda en cada nodo. La clave es la del juego
    // SVG propio (`icon_svg()` en app/bootstrap.php): si aqui se anade una que
    // no existe, el icono simplemente no se pinta.
    'icons' => [
        ''           => 'Sin icono',
        'grid'       => 'Cuadricula (categorias)',
        'list'       => 'Lista',
        'star'       => 'Estrella (destacados)',
        'bolt'       => 'Rayo (ofertas)',
        'refresh'    => 'Novedades',
        'tag'        => 'Etiqueta',
        'package'    => 'Paquete (productos)',
        'cart'       => 'Carrito',
        'truck'      => 'Envio',
        'shield'     => 'Escudo (marcas)',
        'check'      => 'Marca de visto',
        'pin'        => 'Ubicacion',
        'wrench'     => 'Herramientas',
        'cpu'        => 'Procesador',
        'chip'       => 'Chip (memoria)',
        'gpu'        => 'Tarjeta grafica',
        'laptop'     => 'Portatil',
        'keyboard'   => 'Teclado',
        'gamepad'    => 'Mando (gaming)',
        'headset'    => 'Auriculares',
        'phone'      => 'Telefono',
        'sun'        => 'Claro',
        'moon'       => 'Oscuro',
        'filter'     => 'Filtro',
        'search'     => 'Buscar',
        'user'       => 'Usuario',
        'chevron-l'  => 'Flecha izquierda',
        'chevron-r'  => 'Flecha derecha',
        'arrow-r'    => 'Flecha derecha (fina)',
        'close'      => 'Cerrar',
    ],

    // Atajos de badge del editor (el texto es libre; estos solo rellenan el campo).
    'badge_presets' => ['NUEVO', 'OFERTA', 'TOP'],

    // Modos de visibilidad por tienda (punto 10: que ve cada tienda).
    'visibility_modes' => [
        'todas'   => 'Todas las tiendas',
        'solo'    => 'Solo las tiendas marcadas',
        'excepto' => 'Todas menos las marcadas',
    ],
];
