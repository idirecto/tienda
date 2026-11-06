<?php

declare(strict_types=1);

/**
 * Categorias del Centro de Ayuda.
 *
 * El orden de este array es el orden que se muestra en el panel. La categoria
 * `plataforma` solo se carga para usuarios con rol `platform`.
 *
 * @return array<string, array{title:string,description:string,icon:string,order:int,type:string,platform_only?:bool}>
 */
return [
    'primeros-pasos' => [
        'title'       => 'Primeros pasos',
        'description' => 'Crea tu tienda, entra al panel y descubre dónde está cada cosa.',
        'icon'        => 'bolt',
        'order'       => 10,
        'type'        => 'articles',
    ],
    'configuracion' => [
        'title'       => 'Configuración de la tienda',
        'description' => 'Datos fiscales y de contacto, venta, pagos y usuarios del panel.',
        'icon'        => 'wrench',
        'order'       => 20,
        'type'        => 'articles',
    ],
    'menu' => [
        'title'       => 'Menú y catálogo',
        'description' => 'Elige el menú, ordena sus categorías y decide qué productos se muestran.',
        'icon'        => 'list',
        'order'       => 30,
        'type'        => 'articles',
    ],
    'diseno' => [
        'title'       => 'Diseño y comunicación',
        'description' => 'Identidad visual, logotipo, favicon, banners y avisos.',
        'icon'        => 'star',
        'order'       => 40,
        'type'        => 'articles',
    ],
    'productos' => [
        'title'       => 'Productos y precios',
        'description' => 'Productos propios, cuotas, precios, IVA y stock.',
        'icon'        => 'package',
        'order'       => 50,
        'type'        => 'articles',
    ],
    'envios' => [
        'title'       => 'Envíos y zonas de venta',
        'description' => 'Dónde vendes, cuánto cobras por el envío y qué plazos indicas.',
        'icon'        => 'truck',
        'order'       => 60,
        'type'        => 'articles',
    ],
    'pedidos' => [
        'title'       => 'Pedidos y clientes',
        'description' => 'Consulta, cobra y envía los pedidos, y revisa la ficha de tus clientes.',
        'icon'        => 'cart',
        'order'       => 70,
        'type'        => 'articles',
    ],
    'dominios' => [
        'title'       => 'Dominios',
        'description' => 'Cambia tu subdominio o conecta un dominio propio.',
        'icon'        => 'pin',
        'order'       => 80,
        'type'        => 'articles',
    ],
    'preguntas-frecuentes' => [
        'title'       => 'Preguntas frecuentes',
        'description' => 'Respuestas rápidas a las dudas más habituales.',
        'icon'        => 'headset',
        'order'       => 90,
        'type'        => 'faq',
    ],
    'problemas' => [
        'title'       => 'Solución de problemas',
        'description' => 'Qué revisar cuando algo no se guarda, no aparece o no se envía.',
        'icon'        => 'shield',
        'order'       => 100,
        'type'        => 'articles',
    ],
    'plataforma' => [
        'title'       => 'Uso de la plataforma',
        'description' => 'Gestión del menú compartido. Solo para administradores de la plataforma.',
        'icon'        => 'grid',
        'order'       => 110,
        'type'        => 'articles',
        'platform_only' => true,
    ],
];
