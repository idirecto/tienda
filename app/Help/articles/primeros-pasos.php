<?php

declare(strict_types=1);

/**
 * Articulos de Primeros pasos.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'crear-tienda',
        'title'   => 'Crear y configurar mi tienda paso a paso',
        'summary' => 'Qué necesitas antes de empezar, qué hace el registro y qué debes revisar después para dejar la tienda preparada.',
        'keywords' => ['crear tienda', 'registro', 'alta', 'idirecto', 'subdominio', 'puesta en marcha', 'configurar tienda'],
        'location' => 'El alta no está dentro del panel: se realiza en la página pública de registro. Después de crearla, entrarás automáticamente en el panel.',
        'body' => <<<'HTML'
            <div class="help-callout help-callout--info">
                <strong>Antes de empezar</strong>
                <p>Para crear una tienda necesitas ser cliente del mayorista con el que trabaja la plataforma (idirecto) y tener una cuenta activa. Una misma cuenta solo puede tener una tienda en esta plataforma.</p>
            </div>

            <h3>1. Reúne estos datos</h3>
            <ul>
                <li>El <strong>email y la contraseña de tu cuenta de idirecto</strong>. Se usan para comprobar que la cuenta es tuya; la contraseña del mayorista no se guarda.</li>
                <li>Una <strong>dirección para la tienda</strong> si quieres elegirla tú. Por ejemplo, <code>mi-tienda</code> generará una dirección del tipo <code>mi-tienda.dominio-de-la-plataforma</code>.</li>
                <li>Una <strong>contraseña nueva para entrar en este panel</strong>, de al menos 8 caracteres.</li>
            </ul>

            <h3>2. Crea la tienda</h3>
            <ol class="help-steps">
                <li>Abre la página de registro de tiendas.</li>
                <li>Escribe el email y la contraseña de tu cuenta de idirecto.</li>
                <li>Si quieres, indica la dirección o subdominio de tu tienda. La plataforma comprueba que esté libre y que sea válido.</li>
                <li>Escribe dos veces la contraseña que usarás en este panel.</li>
                <li>Pulsa <strong>Crear mi tienda</strong>.</li>
            </ol>
            <p>Si la cuenta es correcta y no tiene otra tienda, el sistema crea la tienda, crea tu usuario de panel y te deja dentro.</p>

            <h3>3. Qué se configura automáticamente</h3>
            <ul>
                <li>La vinculación con tu cuenta del mayorista, que se gestiona en idirecto.</li>
                <li>El nombre comercial, la razón social y los datos de contacto que constan en esa cuenta.</li>
                <li>La dirección, ciudad, provincia, código postal y país, cuando están disponibles.</li>
                <li>Un IVA por defecto del 21 %. Puedes revisarlo en Ajustes.</li>
                <li>Las cuatro zonas de envío activadas y la transferencia bancaria como forma de pago inicial.</li>
                <li>La tienda queda técnicamente activa desde el primer momento.</li>
            </ul>

            <h3>4. Qué debes revisar después</h3>
            <ol class="help-steps">
                <li><strong>Ajustes:</strong> datos de contacto, IVA, beneficio, envío y formas de pago.</li>
                <li><strong>Diseño:</strong> nombre visible, eslogan, logotipo, favicon, colores y textos.</li>
                <li><strong>Menú:</strong> crear el menú o copiar el de referencia y publicarlo.</li>
                <li><strong>Banners y avisos:</strong> si quieres destacar algo en la portada.</li>
                <li><strong>Productos propios:</strong> solo si tu plan lo permite.</li>
                <li><strong>Dominios:</strong> cambiar el subdominio o conectar un dominio propio.</li>
            </ol>

            <div class="help-callout help-callout--warning">
                <strong>La venta real no se activa desde el panel</strong>
                <p>La plataforma puede estar en modo de pruebas global. Mientras esté activo, no se pueden comprar artículos aunque la casilla «Permitir pedidos online» esté marcada. Ese ajuste no se cambia desde el panel de la tienda; debe revisarlo quien administra la plataforma.</p>
            </div>

            <h3>5. La tienda no nace con todo</h3>
            <ul>
                <li>No hay un menú creado. Hasta que lo configures, la web solo muestra los accesos básicos.</li>
                <li>No hay banners, avisos ni productos propios.</li>
                <li>No hay páginas legales editables desde este panel.</li>
                <li>No hay una pantalla para cambiar de plan ni para activar o suspender la tienda.</li>
            </ul>

            <p>El proceso no está unificado en un asistente: se completa entre varias pantallas del panel. La <a href="{{base}}/panel/ayuda/categoria/primeros-pasos">lista de esta categoría</a> enlaza con cada paso.</p>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Ajustes',
        'related' => ['primeros-pasos/recorrido-panel', 'configuracion/datos-contacto-iva', 'menu/estilos-y-publicacion', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'recorrido-panel',
        'title'   => 'Recorrido por el panel',
        'summary' => 'Qué encontrarás en cada sección del menú lateral y dónde están las tareas principales.',
        'keywords' => ['panel', 'menú lateral', 'secciones', 'navegación', 'dónde está', 'mi tienda'],
        'location' => 'El menú lateral aparece a la izquierda en escritorio y en la parte superior en móvil. El nombre de la tienda y el botón «Ver mi tienda» están en la zona de marca.',
        'body' => <<<'HTML'
            <p>El panel tiene una barra lateral con las secciones de trabajo. La parte superior muestra el nombre de la pantalla, el usuario conectado y el botón <strong>Salir</strong>.</p>

            <h3>Secciones principales</h3>
            <dl class="help-definition">
                <dt>Panel</dt><dd>Resumen de pedidos activos, productos, banners, avisos, dominios e incidencias.</dd>
                <dt>Pedidos</dt><dd>Listado, alta manual, detalle, estados, cobro y envío al mayorista.</dd>
                <dt>Clientes</dt><dd>Compradores que han creado una cuenta o han comprado en la web, con sus pedidos y direcciones.</dd>
                <dt>Menu</dt><dd>Estilo del menú, categorías visibles, árbol de navegación y publicación.</dd>
                <dt>Diseno</dt><dd>Plantilla, colores, modo, tipografía, logotipo, favicon, textos y opciones de la tienda.</dd>
                <dt>Banners</dt><dd>Imágenes destacadas de la portada.</dd>
                <dt>Avisos</dt><dd>Mensajes breves para la portada.</dd>
                <dt>Productos propios</dt><dd>Productos que añades tú y que no salen del catálogo central.</dd>
                <dt>Dominios</dt><dd>Subdominio de la plataforma y dominios propios con verificación DNS.</dd>
                <dt>Ajustes</dt><dd>Datos de la tienda, venta, cobro, usuarios y zonas de envío.</dd>
                <dt>Logs</dt><dd>Registro de actividad para revisar avisos, compras y errores.</dd>
                <dt>Ayuda</dt><dd>Este centro de ayuda.</dd>
            </dl>

            <p>En la versión actual, algunos rótulos del menú aparecen sin tilde: verás <strong>Menu</strong> y <strong>Diseno</strong>. Son las mismas secciones que se explican en esta ayuda.</p>

            <h3>Otros elementos</h3>
            <ul>
                <li><strong>Ver mi tienda:</strong> abre la web pública de la tienda en otra pestaña.</li>
                <li><strong>Ayuda de esta pantalla:</strong> cuando está disponible, abre directamente el artículo relacionado con lo que estás viendo.</li>
                <li><strong>Salir:</strong> cierra la sesión del panel. No cierra la sesión de tus clientes.</li>
            </ul>

            <div class="help-callout help-callout--info">
                <strong>Una tienda, una sesión</strong>
                <p>Las personas que compran en tu web tienen su propia cuenta. No entran al panel ni ven estas secciones. Los usuarios del panel se gestionan en Ajustes.</p>
            </div>
        HTML,
        'panel_path' => 'panel',
        'panel_label' => 'Ir al Panel',
        'related' => ['primeros-pasos/crear-tienda', 'configuracion/usuarios-panel', 'problemas/registro-actividad'],
    ],

    [
        'slug'    => 'acceso-contrasena',
        'title'   => 'Entrar, salir y qué hacer si no recuerdo la contraseña',
        'summary' => 'Cómo se accede al panel, qué diferencia hay entre tus usuarios y tus clientes y qué alternativas existen hoy si pierdes el acceso.',
        'keywords' => ['entrar', 'login', 'contraseña', 'clave', 'salir', 'acceso', 'olvidé contraseña'],
        'location' => 'La entrada al panel está en /panel/login. El botón para cerrar sesión aparece en la esquina superior derecha.',
        'body' => <<<'HTML'
            <h3>Entrar</h3>
            <ol class="help-steps">
                <li>Abre la dirección del panel.</li>
                <li>Escribe el email y la contraseña de tu usuario del panel.</li>
                <li>Pulsa <strong>Entrar</strong>.</li>
            </ol>
            <p>El usuario del panel es distinto de tu cuenta de idirecto. Al crear la tienda se genera con tu email de idirecto, pero con la contraseña nueva que elegiste para este panel.</p>

            <h3>Salir</h3>
            <p>Pulsa <strong>Salir</strong> en la parte superior. La sesión del panel se cierra, pero no se borra la cuenta de tus clientes ni los datos de la tienda.</p>

            <h3>Si no recuerdo la contraseña</h3>
            <div class="help-callout help-callout--warning">
                <strong>No hay recuperación automática en esta versión</strong>
                <p>El panel no tiene enlace de «olvidé mi contraseña» ni envía correos de recuperación. Tampoco existe una pantalla para editar la contraseña de un usuario ya creado.</p>
                <p>Si no puedes entrar, pide a la persona que administra la plataforma que revise tu usuario. La ayuda no puede inventar un correo de soporte ni un procedimiento que el sistema no ofrece.</p>
            </div>

            <h3>Crear otro usuario del panel</h3>
            <p>Si necesitas que otra persona entre, puedes crear un usuario nuevo en Ajustes. Todos los usuarios del panel pueden acceder a las mismas secciones en la versión actual; los roles todavía no limitan el acceso.</p>

            <p>Para crear un usuario necesitas un nombre, un email que no esté usado en la plataforma y una contraseña de al menos 8 caracteres.</p>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a usuarios del panel',
        'related' => ['configuracion/usuarios-panel', 'primeros-pasos/recorrido-panel', 'problemas/funciones-no-disponibles'],
    ],
];
