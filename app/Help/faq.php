<?php

declare(strict_types=1);

/**
 * Preguntas frecuentes agrupadas por temas.
 *
 * Cada pregunta puede apuntar a un articulo mas completo con `article`.
 *
 * @return array<int, array{title:string,items:array<int,array<string,mixed>>}>
 */
return [
    [
        'title' => 'Tienda y configuración',
        'items' => [
            [
                'slug' => 'crear-tienda',
                'question' => '¿Cómo creo mi tienda?',
                'answer' => <<<'HTML'
                    <p>Necesitas una cuenta activa de idirecto y entrar en la página pública de registro. Allí se piden las credenciales de esa cuenta, un subdominio opcional y una contraseña nueva para este panel.</p>
                    <p>No hay un asistente único: después debes revisar Ajustes, Diseño, Menú, Banners, Avisos, zonas de envío y, si procede, productos propios.</p>
                HTML,
                'keywords' => ['crear tienda', 'registro', 'alta'],
                'article' => 'primeros-pasos/crear-tienda',
            ],
            [
                'slug' => 'subir-logo',
                'question' => '¿Cómo subo mi logotipo?',
                'answer' => <<<'HTML'
                    <p>Entra en <strong>Diseno</strong>, busca la tarjeta «Plantilla», selecciona la imagen y pulsa al final <strong>Guardar cambios</strong>. Subir la imagen no basta: hay que guardar el formulario.</p>
                    <p>El logotipo actual no tiene un botón de eliminación; solo se puede sustituir por otro.</p>
                HTML,
                'keywords' => ['logo', 'logotipo', 'subir logo', 'marca'],
                'article' => 'diseno/logo-favicon',
            ],
            [
                'slug' => 'activar-venta',
                'question' => '¿Cómo activo la tienda para vender?',
                'answer' => <<<'HTML'
                    <p>La tienda se crea activa técnicamente, pero la venta real también depende del <strong>modo global de pruebas</strong> de la plataforma. Mientras esté activo, el carrito y el checkout no permiten comprar.</p>
                    <p>La casilla «Permitir pedidos online» de Diseño no desactiva ese modo global. Debe revisarlo quien administra la plataforma; no hay un botón de activación en el panel de la tienda.</p>
                HTML,
                'keywords' => ['activar tienda', 'modo pruebas', 'vender', 'pedidos online'],
                'article' => 'problemas/no-recibo-pedidos',
            ],
            [
                'slug' => 'cambiar-contrasena',
                'question' => '¿Cómo cambio mi contraseña del panel?',
                'answer' => <<<'HTML'
                    <p>En esta versión no hay una pantalla para cambiar la contraseña de un usuario existente ni un enlace de recuperación. Tampoco se envía un correo de restablecimiento.</p>
                    <p>Si no puedes entrar, debe revisarlo la persona que administra la plataforma.</p>
                HTML,
                'keywords' => ['contraseña', 'clave', 'cambiar contraseña', 'recuperar acceso'],
                'article' => 'primeros-pasos/acceso-contrasena',
            ],
            [
                'slug' => 'editar-legal',
                'question' => '¿Dónde edito el aviso legal, la privacidad o las condiciones?',
                'answer' => <<<'HTML'
                    <p>En esta versión no existe un editor de páginas legales ni del pie de página. No hay que presentar esos textos como si se pudieran publicar desde el panel.</p>
                    <p>Deben resolverse fuera de esta pantalla con la persona o asesoría correspondiente. Esta ayuda no redacta contenido legal ni fiscal.</p>
                HTML,
                'keywords' => ['aviso legal', 'privacidad', 'cookies', 'condiciones', 'textos legales'],
                'article' => 'problemas/funciones-no-disponibles',
            ],
        ],
    ],
    [
        'title' => 'Productos, precios y envíos',
        'items' => [
            [
                'slug' => 'no-veo-productos',
                'question' => '¿Por qué no veo mis productos?',
                'answer' => <<<'HTML'
                    <p>Si es un producto propio, revisa que esté <strong>Publicado</strong>, que el plan permita productos propios y que exista una entrada de menú hacia Productos propios. Si es del catálogo central, puede no tener stock o estar en una categoría que hayas ocultado.</p>
                    <p>Publicar el menú es necesario cuando ya existe una versión publicada y has hecho cambios.</p>
                HTML,
                'keywords' => ['no veo productos', 'producto oculto', 'sin stock', 'menú'],
                'article' => 'productos/cuotas-productos-no-aparecen',
            ],
            [
                'slug' => 'stock-cero',
                'question' => '¿Qué significa que un producto propio tenga stock 0?',
                'answer' => <<<'HTML'
                    <p>La tarjeta muestra «Sin stock», pero el carrito actual interpreta el 0 como ausencia de control de existencias y permite añadir el producto. Es una incoherencia conocida.</p>
                    <p>No uses ese valor como bloqueo fiable de compra hasta que la función se corrija.</p>
                HTML,
                'keywords' => ['stock cero', 'stock 0', 'sin stock', 'producto propio'],
                'article' => 'productos/precios-iva-stock',
            ],
            [
                'slug' => 'cambiar-planes',
                'question' => '¿Cómo amplío el plan o la cuota de productos?',
                'answer' => <<<'HTML'
                    <p>No hay una pantalla de cambio de plan en el panel. El botón «Ampliar plan» lleva a Ajustes, pero allí solo se muestra el plan actual.</p>
                    <p>La gestión del plan debe hacerla quien administra la plataforma. La ayuda no inventa un canal de venta o soporte que no aparece en el sistema.</p>
                HTML,
                'keywords' => ['plan', 'cuota', 'ampliar plan', 'productos propios'],
                'article' => 'productos/cuotas-productos-no-aparecen',
            ],
            [
                'slug' => 'configurar-zonas',
                'question' => '¿Cómo configuro las zonas donde vendo?',
                'answer' => <<<'HTML'
                    <p>Ve a <strong>Ajustes</strong> y busca «Zonas de venta y envio». Hay cuatro zonas fijas: Península, Ceuta, Melilla y Canarias. Activa al menos una, revisa su coste y plazo y guarda.</p>
                    <p>Si dejas el coste vacío, se usa la tarifa general. Recuerda que las zonas no cambian impuestos ni crean transportistas.</p>
                HTML,
                'keywords' => ['zonas', 'envío', 'tarifa', 'vender'],
                'article' => 'envios/zonas-tarifas',
            ],
            [
                'slug' => 'anadir-banner',
                'question' => '¿Cómo añado un banner?',
                'answer' => <<<'HTML'
                    <p>Entra en <strong>Banners</strong>, pulsa «Nuevo banner», rellena título, enlace e imagen, elige estado y fechas y guarda. La única posición que se publica es la portada principal (hero).</p>
                    <p>La imagen debe respetar el límite de 8 MB y conviene usar una medida de 1920 × 600 píxeles.</p>
                HTML,
                'keywords' => ['banner', 'portada', 'imagen', 'añadir banner'],
                'article' => 'diseno/banners',
            ],
        ],
    ],
    [
        'title' => 'Pedidos y cobros',
        'items' => [
            [
                'slug' => 'cambiar-estado-pedido',
                'question' => '¿Cómo cambio el estado de un pedido?',
                'answer' => <<<'HTML'
                    <p>Abre el pedido desde Pedidos, elige el nuevo estado en el selector superior y pulsa <strong>Guardar estado</strong>.</p>
                    <p>Cambiar el estado no envía un email, no cobra, no genera factura y no devuelve stock. El cobro se marca por separado con «Marcar como pagado».</p>
                HTML,
                'keywords' => ['estado pedido', 'cambiar estado', 'pedido activo', 'enviado'],
                'article' => 'pedidos/estados-y-cobro',
            ],
            [
                'slug' => 'enviar-mayorista',
                'question' => '¿Cómo envío un pedido al mayorista?',
                'answer' => <<<'HTML'
                    <p>Solo se envían líneas del catálogo central. Abre el pedido, selecciona las líneas pendientes, revisa la cuenta y los importes y pulsa <strong>Enviar lineas a idirecto</strong>.</p>
                    <p>La acción crea un pedido real en el mayorista y no se deshace desde este panel. Los productos propios no se envían.</p>
                HTML,
                'keywords' => ['enviar idirecto', 'mayorista', 'pedido real'],
                'article' => 'pedidos/enviar-idirecto',
            ],
            [
                'slug' => 'direccion-pedido',
                'question' => '¿Puedo cambiar la dirección de un pedido ya creado?',
                'answer' => <<<'HTML'
                    <p>No. La ficha actual permite cambiar estado y cobro, pero no editar el cliente, la dirección ni los gastos de envío de un pedido ya creado.</p>
                    <p>Si la dirección es incorrecta, no envíes el pedido al mayorista. Anota el número y pide ayuda por el canal externo que la plataforma te haya indicado.</p>
                HTML,
                'keywords' => ['dirección', 'cambiar dirección', 'pedido', 'error'],
                'article' => 'pedidos/incidencias-stock-direccion',
            ],
            [
                'slug' => 'factura',
                'question' => '¿Qué hago si un cliente me pide factura?',
                'answer' => <<<'HTML'
                    <p>El panel no genera facturas ni documentos PDF. El estado «Facturado» solo cambia una etiqueta y no crea un documento.</p>
                    <p>La respuesta fiscal o comercial debe resolverse fuera de esta ayuda, con la persona o asesoría que llevéis la facturación. No inventamos un procedimiento ni un documento que el sistema no ofrece.</p>
                HTML,
                'keywords' => ['factura', 'facturar', 'documento', 'cliente'],
                'article' => 'problemas/funciones-no-disponibles',
            ],
            [
                'slug' => 'emails-pedido',
                'question' => '¿El cliente recibe un email cuando compra?',
                'answer' => <<<'HTML'
                    <p>No. Esta versión no envía emails automáticos de confirmación, cambios de estado ni avisos a la tienda.</p>
                    <p>El cliente ve la confirmación en la página de gracias y puede consultar sus pedidos en su cuenta. La tienda los ve en el panel.</p>
                HTML,
                'keywords' => ['email', 'correo', 'confirmación', 'aviso'],
                'article' => 'problemas/funciones-no-disponibles',
            ],
            [
                'slug' => 'pedido-manual-clientes',
                'question' => '¿Por qué un pedido manual no aparece en Clientes?',
                'answer' => <<<'HTML'
                    <p>El alta manual no se asocia a una cuenta de comprador. Clientes muestra las personas que han comprado desde la web, no los nombres introducidos en un pedido manual.</p>
                    <p>Esta es una limitación actual de la relación entre pedidos y clientes.</p>
                HTML,
                'keywords' => ['pedido manual', 'clientes', 'no aparece'],
                'article' => 'pedidos/pedido-manual',
            ],
        ],
    ],
    [
        'title' => 'Problemas y límites',
        'items' => [
            [
                'slug' => 'no-guarda',
                'question' => '¿Por qué no me deja guardar un cambio?',
                'answer' => <<<'HTML'
                    <p>Puede faltar un campo obligatorio, haber caducado la sesión, haberse alcanzado una cuota, repetir un email o subdominio, superar el límite de una imagen o haber un importe o plazo no válido.</p>
                    <p>Lee el aviso exacto, recarga si el formulario lleva mucho tiempo abierto y no envíes el formulario repetidamente.</p>
                HTML,
                'keywords' => ['no guarda', 'error', 'formulario', 'sesión'],
                'article' => 'problemas/no-guardar',
            ],
            [
                'slug' => 'menu-no-se-ve',
                'question' => 'He cambiado el menú y no se ve.',
                'answer' => <<<'HTML'
                    <p>El árbol y las categorías visibles se guardan como borrador. Si ya hay una versión publicada, pulsa <strong>Publicar cambios</strong>. Revisa también que la categoría no esté oculta y que su destino exista.</p>
                HTML,
                'keywords' => ['menú', 'no se ve', 'publicar', 'cambios'],
                'article' => 'menu/estilos-y-publicacion',
            ],
            [
                'slug' => 'soporte',
                'question' => '¿Dónde contacto con soporte?',
                'answer' => <<<'HTML'
                    <p>Este panel no incluye un formulario de soporte ni muestra una dirección de contacto confirmada. Tampoco hay un chat.</p>
                    <p>Usa el canal que la plataforma te haya comunicado fuera de la aplicación. Si no lo tienes, pídeselo a la persona que administra la plataforma. No inventamos un email ni un teléfono de ayuda.</p>
                HTML,
                'keywords' => ['soporte', 'contacto', 'ayuda', 'incidencia'],
                'article' => 'problemas/funciones-no-disponibles',
            ],
            [
                'slug' => 'imagen-error',
                'question' => 'No puedo subir una imagen.',
                'answer' => <<<'HTML'
                    <p>Comprueba el formato y el tamaño. Los banners admiten hasta 8 MB, el favicon hasta 512 KB y el resto normalmente hasta 5 MB. Después de subirla, hay que guardar el formulario.</p>
                HTML,
                'keywords' => ['imagen', 'subir', 'error', 'tamaño'],
                'article' => 'problemas/errores-imagenes',
            ],
            [
                'slug' => 'cancelar-pedido-enviado',
                'question' => 'Si cancelo un pedido, ¿se cancela también en el mayorista?',
                'answer' => <<<'HTML'
                    <p>No. El estado Cancelado es una etiqueta local. Si las líneas ya se enviaron al mayorista, no se eliminan allí ni se recupera automáticamente el stock.</p>
                HTML,
                'keywords' => ['cancelar pedido', 'mayorista', 'stock', 'cancelado'],
                'article' => 'pedidos/papelera',
            ],
        ],
    ],
];
