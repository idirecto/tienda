<?php

declare(strict_types=1);

/**
 * Articulos de Configuracion de la tienda.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'datos-contacto-iva',
        'title'   => 'Datos, contacto e IVA de la tienda',
        'summary' => 'Dónde se cambian el nombre comercial, la razón social, el contacto y el IVA por defecto.',
        'keywords' => ['datos tienda', 'razón social', 'nif', 'email', 'teléfono', 'dirección', 'iva', 'impuesto', 'contacto'],
        'location' => 'En Ajustes, dentro de la tarjeta «Datos de la tienda». La dirección postal también aparece en Diseño, dentro de «Textos y datos».',
        'body' => <<<'HTML'
            <h3>Qué puedes cambiar en Ajustes</h3>
            <ul>
                <li><strong>Nombre comercial:</strong> el nombre que identifica la tienda.</li>
                <li><strong>Razón social:</strong> el nombre legal o de la sociedad.</li>
                <li><strong>Email y teléfono</strong> de contacto.</li>
                <li><strong>Código postal, ciudad y provincia.</strong></li>
                <li><strong>IVA por defecto (%):</strong> el porcentaje general de referencia.</li>
            </ul>

            <h3>Cómo guardar</h3>
            <ol class="help-steps">
                <li>Corrige los campos necesarios.</li>
                <li>Pulsa <strong>Guardar datos</strong> dentro de esa tarjeta.</li>
                <li>Comprueba el resultado en la web pública con «Ver mi tienda».</li>
            </ol>

            <div class="help-callout help-callout--warning">
                <strong>Hay datos repartidos en dos pantallas</strong>
                <p>El nombre, el email, el teléfono, el código postal, la ciudad y la provincia se pueden editar tanto en Ajustes como en Diseño. La razón social y el IVA solo están en Ajustes; la dirección completa y el WhatsApp están en Diseño.</p>
                <p>Si cambias un dato compartido, revisa también la otra pantalla antes de guardar para no dejar el valor anterior.</p>
            </div>

            <h3>Sobre el IVA</h3>
            <p>El IVA es el impuesto que se aplica al precio de venta. En esta versión solo hay un <strong>IVA general</strong>; no hay una pantalla para configurar impuestos distintos por producto ni por cliente. El catálogo central puede traer su propio tipo de impuesto.</p>
            <p>Esta ayuda no decide qué tipo de IVA corresponde a tu actividad. Si tienes dudas fiscales, consúltalas con una persona o asesoría especializada.</p>

            <h3>Qué no se edita aquí</h3>
            <ul>
                <li>El plan de la tienda.</li>
                <li>El estado técnico de la tienda.</li>
                <li>Las páginas legales: no tienen editor en el panel.</li>
                <li>El NIF/CIF de la cuenta del mayorista: se muestra, pero se gestiona en idirecto.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Ajustes',
        'related' => ['diseno/textos-seo', 'configuracion/cuenta-idirecto', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'venta-cobro-pagos',
        'title'   => 'Venta, cobro y formas de pago',
        'summary' => 'Configura el beneficio, el envío general y las formas de pago. Entiende qué se cobra automáticamente y qué se marca a mano.',
        'keywords' => ['beneficio', 'margen', 'markup', 'precio venta', 'pago', 'transferencia', 'contra reembolso', 'recogida', 'cobro', 'datos bancarios'],
        'location' => 'En Ajustes, tarjeta «Venta y cobro». Las zonas y sus costes de envío están en la tarjeta inferior «Zonas de venta y envío».',
        'body' => <<<'HTML'
            <h3>Beneficio sobre la tarifa</h3>
            <p>El precio que ve el cliente se construye con la <strong>tarifa que el mayorista aplica a tu cuenta</strong> más el <strong>beneficio</strong> que indiques. Ese beneficio se escribe como porcentaje y se limita a un máximo del 200 %.</p>
            <div class="help-callout help-callout--info">
                <strong>Precio con IVA en la web</strong>
                <p>La tienda muestra el precio final con IVA. El pedido guarda la base sin IVA y añade el impuesto al total. No tienes que calcular esa conversión a mano.</p>
            </div>

            <h3>Envío general</h3>
            <ul>
                <li><strong>Tarifa de envío general:</strong> coste que se usa en las zonas que no tengan uno propio.</li>
                <li><strong>Envío gratis general desde:</strong> importe mínimo del pedido a partir del cual el envío no se cobra. Déjalo vacío si no quieres activarlo.</li>
            </ul>
            <p>Las zonas pueden sustituir estos valores por sus propios costes y umbrales.</p>

            <h3>Formas de pago que aceptas</h3>
            <p>Marca las casillas que quieras ofrecer:</p>
            <ul>
                <li><strong>Transferencia bancaria.</strong> Conviene rellenar los datos bancarios para que el cliente sepa cómo pagar.</li>
                <li><strong>Contra reembolso.</strong> El cliente paga al recibir el pedido.</li>
                <li><strong>Recogida en tienda.</strong> El cliente recoge y paga en la tienda.</li>
            </ul>
            <p>El campo <strong>Datos para la transferencia</strong> es texto libre: titular, IBAN e instrucciones. Aparece en la página de gracias si el pedido se paga por transferencia y hay datos guardados.</p>

            <h3>Qué ocurre al confirmar un pedido</h3>
            <ol class="help-steps">
                <li>El pedido se registra como <strong>pendiente de pago</strong>.</li>
                <li>La tienda no cobra automáticamente: no hay pasarela de pago conectada.</li>
                <li>El tendero comprueba el ingreso y pulsa <strong>Marcar como pagado</strong> en la ficha del pedido.</li>
            </ol>

            <div class="help-callout help-callout--warning">
                <strong>El modo de pruebas manda sobre esta pantalla</strong>
                <p>Aunque marques «Permitir pedidos online», si la plataforma está en modo de pruebas global el carrito y el checkout seguirán bloqueados. Ese interruptor no está en este panel.</p>
                <p>Además, esta versión no envía correos de confirmación ni avisos al cliente. El pedido se ve en el panel y en la cuenta del cliente.</p>
            </div>

            <h3>Errores habituales</h3>
            <ul>
                <li>Escribir importes con letras o símbolos no admitidos.</li>
                <li>Dejar todas las zonas desactivadas.</li>
                <li>Marcar transferencia sin datos bancarios.</li>
                <li>Esperar un cobro automático que el sistema no realiza.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Venta y cobro',
        'related' => ['envios/zonas-tarifas', 'pedidos/estados-y-cobro', 'problemas/no-recibo-pedidos'],
    ],

    [
        'slug'    => 'cuenta-idirecto',
        'title'   => 'Cuenta de idirecto, tarifa y nivel de cliente',
        'summary' => 'Qué significan la cuenta del mayorista, la tarifa y el nivel de cliente, y por qué no se editan todos desde este panel.',
        'keywords' => ['idirecto', 'cuenta', 'mayorista', 'tarifa', 'margen', 'nivel cliente', 'categoría cliente', 'nif'],
        'location' => 'En Ajustes, tarjeta «Cuenta en idirecto (mayorista)», al final de la página.',
        'body' => <<<'HTML'
            <h3>Para qué sirve esta cuenta</h3>
            <p>El <strong>mayorista</strong> es quien proporciona el catálogo central y a quien se envían los pedidos. La cuenta de idirecto indica con qué cliente del mayorista compra tu tienda.</p>

            <h3>Los dos números</h3>
            <ul>
                <li><strong>Id de cuenta en idirecto:</strong> identifica tu cuenta en el mayorista. Si no existe, el panel lo rechaza.</li>
                <li><strong>Tarifa o margen:</strong> es la lista de precios que el mayorista aplica a esa cuenta. Si se deja vacía, se usa la tarifa de la propia cuenta.</li>
            </ul>
            <p>No confundas la tarifa del mayorista con el <strong>beneficio</strong> de tu tienda. La tarifa es el precio de compra; el beneficio es el porcentaje que añades para calcular el precio de venta.</p>

            <h3>Cómo revisar la cuenta</h3>
            <ol class="help-steps">
                <li>Comprueba el id de cuenta y la tarifa.</li>
                <li>Pulsa <strong>Guardar cuenta</strong>.</li>
                <li>Lee el recuadro de resumen: muestra la razón social, el NIF/CIF y la tarifa efectiva.</li>
                <li>Si vas a enviar pedidos al mayorista, confirma con Logs o con una prueba autorizada que el puente responde.</li>
            </ol>

            <h3>Nivel de cliente de la tienda</h3>
            <p>El nivel de cliente sale de la ficha de la cuenta en idirecto. Puede hacer que algunos enlaces del menú solo se muestren a determinados niveles. El panel no permite cambiar ese nivel: si aparece «sin asignar», quien gestiona la cuenta en idirecto debe revisar la categoría de cliente.</p>
            <div class="help-callout help-callout--info">
                <strong>No es un precio especial para tus clientes</strong>
                <p>El nivel de cliente de la tienda no crea tarifas distintas para las personas que compran en tu web. Solo condiciona la visibilidad de determinados nodos del menú.</p>
            </div>

            <h3>Errores frecuentes</h3>
            <ul>
                <li>La cuenta no existe: revisa el identificador con el mayorista.</li>
                <li>No aparece el nivel: la cuenta existe, pero no tiene categoría de cliente asignada.</li>
                <li>No se pueden enviar pedidos: falta la cuenta, la tarifa o el puente del mayorista.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a la cuenta del mayorista',
        'related' => ['pedidos/enviar-idirecto', 'menu/categorias-visibles-propias', 'problemas/no-envio-idirecto'],
    ],

    [
        'slug'    => 'usuarios-panel',
        'title'   => 'Usuarios del panel',
        'summary' => 'Cómo se crea otro acceso al panel y qué limitaciones tienen hoy los usuarios y los permisos.',
        'keywords' => ['usuarios', 'usuario', 'acceso', 'contraseña', 'rol', 'permisos', 'editor', 'crear usuario'],
        'location' => 'En Ajustes, tarjeta «Usuarios del panel», debajo de las opciones de venta y cobro.',
        'body' => <<<'HTML'
            <h3>Qué muestra la pantalla</h3>
            <p>Aparece una lista con el nombre, el email y el rol de cada usuario del panel. El formulario de alta está justo debajo.</p>

            <h3>Crear un usuario</h3>
            <ol class="help-steps">
                <li>Escribe el nombre de la persona.</li>
                <li>Indica un email que no esté ya usado por otro usuario de la plataforma.</li>
                <li>Escribe una contraseña de al menos 8 caracteres.</li>
                <li>Pulsa <strong>Crear usuario</strong>.</li>
            </ol>
            <p>No se envía una invitación por email. Debes comunicar el acceso de forma segura a la persona.</p>

            <div class="help-callout help-callout--warning">
                <strong>Los permisos todavía no están separados</strong>
                <p>En esta versión, cualquier usuario que pueda entrar al panel puede acceder a todas las secciones de su tienda: pedidos, ajustes, dominios, usuarios, etc. El rol que se muestra en la lista no limita esas acciones.</p>
                <p>No existe una pantalla para editar, desactivar o eliminar un usuario, ni para cambiar su contraseña.</p>
            </div>

            <h3>Qué hacer si un usuario pierde el acceso</h3>
            <p>No hay recuperación automática de contraseña. Pide a la persona que administra la plataforma que revise el usuario y, si es necesario, el acceso por el canal técnico que corresponda. La ayuda no inventa un correo de soporte.</p>

            <h3>Relación con otras cuentas</h3>
            <ul>
                <li>Los usuarios del panel son distintos de los clientes que compran en la web.</li>
                <li>También son distintos de la cuenta de idirecto del mayorista.</li>
                <li>El rol <strong>platform</strong> es especial: añade el Menú de plataforma y el mantenimiento de Logs.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Usuarios del panel',
        'related' => ['primeros-pasos/acceso-contrasena', 'problemas/funciones-no-disponibles', 'problemas/registro-actividad'],
    ],
];
