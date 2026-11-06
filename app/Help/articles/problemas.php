<?php

declare(strict_types=1);

/**
 * Articulos de Solucion de problemas.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'no-guardar',
        'title'   => 'No puedo guardar un cambio',
        'summary' => 'Qué revisar cuando un formulario rechaza los datos o vuelve a mostrarse sin aplicar el cambio.',
        'keywords' => ['no guarda', 'error al guardar', 'formulario', 'validación', 'sesión caducada', 'cuota', 'imagen'],
        'location' => 'El aviso de error aparece normalmente en la parte superior del contenido o dentro del formulario que intentas guardar.',
        'body' => <<<'HTML'
            <h3>Lo primero: lee el aviso</h3>
            <p>El panel suele indicar el motivo concreto. No vuelvas a enviar el formulario una y otra vez sin leerlo; algunos errores se repiten y otros pueden crear registros duplicados.</p>

            <h3>Causas habituales</h3>
            <ul>
                <li><strong>La sesión ha caducado:</strong> el aviso de seguridad aparece al enviar un formulario antiguo. Recarga la página y vuelve a introducir los cambios.</li>
                <li><strong>Falta un campo obligatorio:</strong> revisa el nombre, el mensaje o los datos marcados como necesarios.</li>
                <li><strong>Un importe o plazo no es válido:</strong> usa números con hasta dos decimales, sin letras, y plazos enteros.</li>
                <li><strong>La fecha de fin es anterior a la de inicio.</strong></li>
                <li><strong>Se ha alcanzado la cuota del plan:</strong> productos propios, banners o avisos.</li>
                <li><strong>El email ya está en uso:</strong> los usuarios del panel no pueden repetir email.</li>
                <li><strong>El subdominio ya está ocupado</strong> o no cumple las reglas.</li>
                <li><strong>La imagen supera el límite</strong> o no es una imagen válida.</li>
                <li><strong>No hay ninguna zona de venta activa</strong> o falta el mensaje de zona no disponible.</li>
                <li><strong>No queda ninguna línea seleccionada</strong> para enviar al mayorista.</li>
                <li><strong>El JSON de tokens no es válido:</strong> el resto del diseño puede guardarse sin los tokens.</li>
            </ul>

            <h3>Qué hacer sin riesgo</h3>
            <ol class="help-steps">
                <li>Anota el texto exacto del aviso.</li>
                <li>Recarga la pantalla si el formulario llevaba mucho tiempo abierto.</li>
                <li>Corrige solo los campos señalados y vuelve a guardar.</li>
                <li>Si el error se repite, no crees registros duplicados para compensarlo.</li>
                <li>Revisa Logs y reúne la hora, la pantalla y el mensaje antes de pedir ayuda por el canal externo de la plataforma.</li>
            </ol>

            <div class="help-callout help-callout--info">
                <strong>Subir una imagen no guarda el formulario</strong>
                <p>En Diseño, Banners, Avisos y Productos, la imagen puede subirse antes de guardar. El cambio definitivo se aplica al pulsar el botón de guardar de esa pantalla.</p>
            </div>
        HTML,
        'panel_path' => 'panel',
        'panel_label' => 'Volver al Panel',
        'related' => ['problemas/errores-imagenes', 'problemas/registro-actividad', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'no-veo-cambios',
        'title'   => 'No veo mis cambios o mis productos en la web',
        'summary' => 'Comprueba si falta guardar, publicar el menú, cambiar un estado o refrescar el navegador.',
        'keywords' => ['no veo cambios', 'no aparece producto', 'caché', 'publicar menú', 'sin stock', 'borrador', 'oculto', 'dominio'],
        'location' => 'La comprobación se hace entre el panel, la tienda pública y, en el caso del menú, la tarjeta de publicación.',
        'body' => <<<'HTML'
            <h3>1. Comprueba que has guardado</h3>
            <p>Algunos cambios no se aplican hasta pulsar su botón específico: «Guardar datos», «Guardar cambios», «Crear banner», «Guardar zonas y envío», etc. Subir una imagen tampoco guarda el resto del formulario.</p>

            <h3>2. Revisa el menú</h3>
            <ul>
                <li>Los cambios del árbol y de visibilidad se guardan como borrador.</li>
                <li>Si ya existe una versión publicada, pulsa <strong>Publicar cambios</strong>.</li>
                <li>Si nunca has publicado, la tienda puede estar mostrando el árbol de trabajo; revisa la tarjeta «Borrador y publicacion».</li>
                <li>Comprueba que la categoría no está oculta y que el nivel de cliente no la limita.</li>
            </ul>

            <h3>3. Revisa el estado del producto</h3>
            <ul>
                <li>Un producto propio en Borrador u Oculto no se muestra.</li>
                <li>Un producto central sin stock no aparece en los listados.</li>
                <li>Una categoría oculta deja fuera sus productos del menú y del buscador en vivo.</li>
            </ul>

            <h3>4. Comprueba la dirección y el navegador</h3>
            <ul>
                <li>Confirma que estás viendo la dirección actual de la tienda, sobre todo si has cambiado el subdominio.</li>
                <li>Fuerza una recarga completa de la página; el navegador puede conservar estilos, favicon o imágenes antiguas.</li>
                <li>Si has conectado un dominio propio, espera la propagación DNS.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>Campos que se guardan pero no se muestran</strong>
                <p>El campo «Sobre nosotros» y el «Meta título» del diseño pueden guardarse sin aparecer en la web actual. No son un fallo de caché: forman parte de las funciones pendientes de esta versión.</p>
            </div>

            <h3>5. Si sigue sin aparecer</h3>
            <p>Anota la pantalla, el nombre del producto o categoría y la dirección donde esperabas verlo. Revisa Logs y consulta el artículo <a href="{{base}}/panel/ayuda/productos/cuotas-productos-no-aparecen">Cuotas y productos que no aparecen</a>.</p>
        HTML,
        'panel_path' => 'panel/menu',
        'panel_label' => 'Ir a Menu',
        'related' => ['menu/estilos-y-publicacion', 'productos/cuotas-productos-no-aparecen', 'dominios/subdominio'],
    ],

    [
        'slug'    => 'no-recibo-pedidos',
        'title'   => 'No recibo pedidos o el checkout está bloqueado',
        'summary' => 'Comprueba el modo de pruebas, la aceptación de pedidos, las formas de pago y las zonas de venta.',
        'keywords' => ['no recibo pedidos', 'checkout bloqueado', 'modo pruebas', 'carrito', 'pedidos online', 'zonas', 'formas de pago', 'email'],
        'location' => 'La configuración está repartida entre Venta y cobro y Zonas de venta en Ajustes. La aceptación de pedidos también tiene una casilla en Diseño.',
        'body' => <<<'HTML'
            <h3>1. Modo de pruebas global</h3>
            <p>Si la plataforma está en modo de pruebas, el carrito y el checkout se bloquean con un aviso que indica que la web está en pruebas. Esta comprobación se hace en el servidor.</p>
            <div class="help-callout help-callout--warning">
                <strong>No se cambia desde el panel de la tienda</strong>
                <p>La casilla «Permitir pedidos online» no desactiva el modo global de pruebas. Si el aviso de pruebas aparece en la web, debe revisarlo quien administra la plataforma y el archivo de configuración del servidor.</p>
            </div>

            <h3>2. Aceptación de pedidos de la tienda</h3>
            <p>Revisa en Diseño que «Permitir pedidos online» esté marcado. Si está desmarcado, el checkout muestra que la tienda no acepta pedidos.</p>

            <h3>3. Formas de pago</h3>
            <p>El checkout necesita al menos una forma de pago configurada. Revisa en Ajustes que haya una casilla marcada entre transferencia, contra reembolso o recogida. No hay una pasarela de tarjeta.</p>

            <h3>4. Zonas de venta</h3>
            <ul>
                <li>La dirección del cliente debe ser de España.</li>
                <li>La zona debe estar activa.</li>
                <li>El coste general o el de la zona debe estar configurado.</li>
                <li>El mensaje de zona no disponible no sustituye a la configuración: solo explica el bloqueo.</li>
            </ul>

            <h3>5. Estás mirando el sitio correcto</h3>
            <p>Comprueba la dirección pública actual de la tienda. No confundas la tienda de prueba con otra tienda de la plataforma ni esperes recibir pedidos en el email de contacto: el panel no envía avisos por correo.</p>

            <h3>Qué no hace este sistema</h3>
            <ul>
                <li>No envía un email por cada pedido.</li>
                <li>No muestra el pedido en Clientes si se creó manualmente desde el panel.</li>
                <li>No confirma un cobro automáticamente.</li>
            </ul>

            <p>Si el pedido se creó y aparece en Pedidos, revisa su estado y su cobro. Si no aparece, revisa Logs y pide ayuda por el canal externo que la plataforma te haya indicado.</p>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Ajustes',
        'related' => ['configuracion/venta-cobro-pagos', 'envios/calculo-envio', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'no-envio-idirecto',
        'title'   => 'No puedo enviar líneas a idirecto',
        'summary' => 'Revisa la cuenta del mayorista, la tarifa, las líneas pendientes y el estado del pedido.',
        'keywords' => ['no envía idirecto', 'error mayorista', 'cuenta', 'tarifa', 'producto propio', 'línea enviada', 'puente'],
        'location' => 'En la ficha del pedido, sección «Lineas del pedido», y en Ajustes, tarjeta «Cuenta en idirecto».',
        'body' => <<<'HTML'
            <h3>Revisa estos puntos en orden</h3>
            <ol class="help-steps">
                <li><strong>Cuenta configurada:</strong> en Ajustes debe aparecer un id de cuenta válido del mayorista.</li>
                <li><strong>Puente disponible:</strong> si el panel indica que las tablas del mayorista no están disponibles, el envío está apagado o hay un problema técnico.</li>
                <li><strong>Líneas pendientes:</strong> selecciona al menos una línea del catálogo central que todavía no se haya enviado.</li>
                <li><strong>Producto propio:</strong> las líneas propias no se envían al mayorista.</li>
                <li><strong>Tarifa:</strong> si no se encuentra una tarifa para el producto, la línea no se puede enviar.</li>
                <li><strong>Estado:</strong> no envíes desde un pedido cancelado o en la papelera. Para el flujo normal, usa Activo o Preparado.</li>
                <li><strong>Línea ya enviada:</strong> no se puede volver a enviar ni borrar.</li>
            </ol>

            <div class="help-callout help-callout--warning">
                <strong>El envío crea un pedido real</strong>
                <p>No repitas el envío para “probar”. Si ya se creó el pedido en el mayorista, no lo vuelvas a mandar. La ayuda no puede cancelar ese pedido desde el panel.</p>
            </div>

            <h3>Mensajes que pueden aparecer</h3>
            <ul>
                <li>«Las tablas de pedidos del mayorista no están disponibles».</li>
                <li>«Esta tienda no tiene cuenta del mayorista».</li>
                <li>«No has seleccionado ninguna línea pendiente de enviar».</li>
                <li>«No se ha encontrado tarifa para…».</li>
                <li>Un producto propio no se puede enviar.</li>
            </ul>

            <h3>Qué datos reunir</h3>
            <p>Anota el número de pedido, la cuenta, la línea, el mensaje exacto y la hora. Revisa Logs del día y contacta por el canal externo que la plataforma te haya indicado. Este panel no incorpora un formulario de soporte.</p>
        HTML,
        'panel_path' => 'panel/pedidos',
        'panel_label' => 'Ir a Pedidos',
        'related' => ['pedidos/enviar-idirecto', 'configuracion/cuenta-idirecto', 'pedidos/estados-y-cobro'],
    ],

    [
        'slug'    => 'errores-imagenes',
        'title'   => 'Errores al subir imágenes',
        'summary' => 'Formatos, tamaños y pasos para resolver los avisos más habituales al subir el logotipo, el favicon, un banner o una imagen de producto.',
        'keywords' => ['imagen', 'subir imagen', 'error imagen', 'tamaño', 'mb', 'favicon', 'logo', 'banner', 'webp', 'archivo'],
        'location' => 'El selector de archivo aparece dentro del formulario de Diseño, Banners, Avisos o Productos propios.',
        'body' => <<<'HTML'
            <h3>Límites que debes conocer</h3>
            <ul>
                <li><strong>Banners:</strong> hasta 8 MB.</li>
                <li><strong>Favicon:</strong> hasta 512 KB y un lado máximo recomendado de 512 píxeles.</li>
                <li><strong>Logotipo, productos y avisos:</strong> normalmente hasta 5 MB.</li>
                <li>La plataforma puede convertir JPG, PNG, WebP u otros formatos a WebP para reducir peso. Los SVG y los GIF animados se tratan de forma especial.</li>
            </ul>

            <h3>Si el archivo no se sube</h3>
            <ol class="help-steps">
                <li>Comprueba el tamaño del archivo.</li>
                <li>Comprueba que es una imagen y no un documento renombrado.</li>
                <li>Prueba con una imagen más ligera o con un formato habitual.</li>
                <li>Recarga la página si el formulario lleva mucho tiempo abierto.</li>
                <li>Vuelve a seleccionarla y espera a que termine el proceso.</li>
                <li>Guarda el formulario completo después de subirla.</li>
            </ol>

            <h3>Mensajes frecuentes</h3>
            <ul>
                <li><strong>La imagen supera el límite de subida del servidor:</strong> el archivo es mayor de lo que acepta PHP o el plan.</li>
                <li><strong>El archivo no es una imagen válida:</strong> el contenido no coincide con un formato admitido.</li>
                <li><strong>La imagen debe subirse desde el formulario:</strong> no se admite una URL externa arbitraria.</li>
                <li><strong>La imagen seleccionada no pertenece a esta tienda:</strong> el archivo está asociado a otra tienda.</li>
            </ul>

            <div class="help-callout help-callout--info">
                <strong>Subir y guardar son pasos distintos</strong>
                <p>Una subida correcta no aplica el cambio por sí sola. Debes guardar el formulario de Diseño, Banners, Avisos o Productos para que el archivo quede asociado al contenido.</p>
                <p>No hay una biblioteca de medios en el panel para revisar o borrar imágenes sueltas. El logotipo solo se puede sustituir; el favicon se puede quitar con su botón.</p>
            </div>
        HTML,
        'panel_path' => 'panel/diseno',
        'panel_label' => 'Ir a Diseno',
        'related' => ['diseno/logo-favicon', 'diseno/banners', 'diseno/avisos'],
    ],

    [
        'slug'    => 'registro-actividad',
        'title'   => 'Cómo revisar el registro de actividad (Logs)',
        'summary' => 'Dónde consultar errores y actividad, cómo filtrar y qué datos reunir antes de pedir ayuda.',
        'keywords' => ['logs', 'registro', 'actividad', 'error', 'incidencia', 'pedido no aparece', 'filtros', 'nivel'],
        'location' => 'En Logs. Los filtros están en la parte superior; la tabla muestra hora, nivel, canal, mensaje y detalle.',
        'body' => <<<'HTML'
            <p>La sección <strong>Logs</strong> es el registro de actividad de la tienda. Sirve para comprobar qué ocurrió en una compra, una entrada al panel o un error del sistema.</p>

            <h3>Qué puedes filtrar</h3>
            <ul>
                <li><strong>Fecha:</strong> hoy o un día disponible.</li>
                <li><strong>Canal:</strong> compras, pedidos, accesos, seguridad, panel, catálogo o sistema, entre otros.</li>
                <li><strong>Nivel mínimo:</strong> desde información hasta crítico.</li>
                <li><strong>Texto libre:</strong> número de pedido, email, mensaje.</li>
            </ul>

            <h3>Qué significan los niveles</h3>
            <ul>
                <li><strong>Depuración e información:</strong> detalle de actividad normal.</li>
                <li><strong>Aviso:</strong> algo puede necesitar revisión.</li>
                <li><strong>Error:</strong> una operación ha fallado.</li>
                <li><strong>Crítico:</strong> el fallo es grave o ha detenido una acción.</li>
            </ul>

            <h3>Qué anotar antes de consultar</h3>
            <ol class="help-steps">
                <li>La fecha y la hora aproximada.</li>
                <li>La pantalla o la acción que estabas haciendo.</li>
                <li>El número de pedido o el email implicado.</li>
                <li>El mensaje exacto, sin resumirlo.</li>
                <li>El identificador de la petición si aparece en el detalle.</li>
            </ol>

            <div class="help-callout help-callout--info">
                <strong>Privacidad y alcance</strong>
                <p>Cada tienda ve solo sus propios logs. Los usuarios con rol de plataforma pueden ver todas las tiendas. No compartas fragmentos con datos personales sin necesidad. Los ficheros se conservan durante el plazo configurado por la plataforma.</p>
            </div>

            <h3>Qué no resuelve Logs</h3>
            <ul>
                <li>No repara el problema por sí solo.</li>
                <li>No envía la incidencia a soporte.</li>
                <li>No contiene un canal de contacto.</li>
                <li>No garantiza que todos los errores de terceros aparezcan con el mismo detalle.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/logs',
        'panel_label' => 'Ir a Logs',
        'related' => ['pedidos/guia-pedidos', 'problemas/no-guardar', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'funciones-no-disponibles',
        'title'   => 'Qué funciones no están disponibles todavía',
        'summary' => 'Lista transparente de acciones que el panel no permite hacer hoy, para no confundirlas con fallos de uso.',
        'keywords' => ['no disponible', 'pendiente', 'no funciona', 'factura', 'legal', 'emails', 'permisos', 'transporte', 'plan', 'límites'],
        'location' => 'Esta lista no corresponde a una sola pantalla: afecta a varias secciones del panel y de la web.',
        'body' => <<<'HTML'
            <p>La ayuda documenta lo que existe hoy. Estas funciones <strong>no están implementadas o están incompletas</strong>. No debes asumir que se pueden usar aunque aparezca un campo relacionado.</p>

            <h3>Tienda y publicación</h3>
            <ul>
                <li>No hay un botón para activar o suspender la tienda desde el panel.</li>
                <li>El modo global de pruebas no se puede desactivar desde la tienda.</li>
                <li>No hay un asistente de creación guiado; el alta y la configuración están repartidos en varias pantallas.</li>
                <li>Las plantillas Moderno y Minimal no tienen un conjunto completo de vistas propias.</li>
            </ul>

            <h3>Contenido y legal</h3>
            <ul>
                <li>No hay editor de páginas de contenido.</li>
                <li>No hay editor de aviso legal, privacidad, cookies, condiciones o devoluciones.</li>
                <li>No hay editor del pie de página.</li>
                <li>«Sobre nosotros» se guarda, pero no se publica en una página visible.</li>
                <li>«Meta título» se guarda, pero no sustituye al título real de la página.</li>
            </ul>

            <h3>Pagos, facturas y correos</h3>
            <ul>
                <li>No hay pasarela de pago ni cobro automático.</li>
                <li>«Facturado» es una etiqueta; no genera factura ni PDF.</li>
                <li>No hay emisión de facturas rectificativas ni documentos de devolución.</li>
                <li>No se envían emails de confirmación, avisos de pedido ni invitaciones.</li>
                <li>No hay recuperación automática de contraseña para clientes ni para usuarios del panel.</li>
            </ul>

            <h3>Pedidos y clientes</h3>
            <ul>
                <li>No se puede editar cliente, dirección o gastos de envío de un pedido ya creado.</li>
                <li>El bloque «Anadir lineas» de un pedido existente puede duplicar líneas al guardar.</li>
                <li>La papelera no cancela el pedido creado en el mayorista.</li>
                <li>No hay transportista, etiqueta de envío ni número de seguimiento.</li>
                <li>No hay un flujo de devoluciones, reembolsos o incidencias con estados propios.</li>
                <li>La ficha de cliente es de solo lectura y no permite restablecer su contraseña.</li>
            </ul>

            <h3>Productos, stock y archivos</h3>
            <ul>
                <li>No se puede editar el catálogo central del mayorista ni su stock.</li>
                <li>No hay ficha pública propia para los productos propios.</li>
                <li>El stock 0 de un producto propio se muestra como «Sin stock», pero el carrito permite comprarlo.</li>
                <li>No hay biblioteca de medios para administrar las imágenes subidas.</li>
            </ul>

            <h3>Planes, permisos y soporte</h3>
            <ul>
                <li>No hay una pantalla para cambiar de plan; «Ampliar plan» no completa esa gestión.</li>
                <li>Los roles de usuario no limitan el acceso a las secciones de la tienda.</li>
                <li>No se pueden editar, borrar o desactivar usuarios del panel.</li>
                <li>No hay un formulario de soporte dentro del panel ni una dirección de ayuda confirmada.</li>
                <li>El formulario de contacto de la web está desactivado.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>No inventes una solución que el panel no ofrece</strong>
                <p>Si una tarea depende de una de estas funciones, no la presentes como resuelta. Reúne la información del caso y consulta con quien administra la plataforma o con el responsable del área correspondiente.</p>
            </div>

            <h3>Qué sí puedes hacer</h3>
            <p>Puedes consultar pedidos, cambiar etiquetas, enviar líneas al mayorista, crear productos propios si el plan lo permite, configurar menú, diseño, banners, avisos, zonas de envío, dominios y revisar Logs. Las limitaciones anteriores no impiden esas tareas, pero cambian su alcance.</p>
        HTML,
        'panel_path' => 'panel/ayuda/categoria/problemas',
        'panel_label' => 'Volver a Solución de problemas',
        'related' => ['pedidos/incidencias-stock-direccion', 'problemas/registro-actividad', 'primeros-pasos/crear-tienda'],
    ],
];
