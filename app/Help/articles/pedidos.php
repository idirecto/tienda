<?php

declare(strict_types=1);

/**
 * Articulos de Pedidos y clientes.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'guia-pedidos',
        'title'   => 'Guía completa de pedidos',
        'summary' => 'Qué es un pedido, de dónde viene, qué puedes ver y gestionar y qué queda fuera del panel.',
        'keywords' => ['pedido', 'pedidos', 'estado', 'cobro', 'cliente', 'dirección', 'envío', 'mayorista', 'idirecto', 'guía'],
        'location' => 'En Pedidos. La ficha completa de cada pedido se abre desde el número o desde el botón «Ver».',
        'body' => <<<'HTML'
            <p>Un <strong>pedido</strong> es una compra de un cliente de tu tienda. Puede llegar desde el checkout de la web o crearse manualmente desde el panel.</p>

            <h3>De dónde viene un pedido</h3>
            <ul>
                <li><strong>Pedido web:</strong> el cliente lo crea al finalizar la compra. Queda pendiente de pago y asociado a su cuenta o a un cliente invitado.</li>
                <li><strong>Pedido manual:</strong> lo crea el tendero desde «Nuevo pedido». No pasa por el checkout web y no crea automáticamente una ficha en Clientes.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>El modo de pruebas bloquea los pedidos web</strong>
                <p>Mientras la plataforma esté en modo de pruebas global, el carrito y el checkout rechazan la compra. Los pedidos manuales sí se pueden crear en el panel, pero no son compras reales de la web.</p>
            </div>

            <h3>El listado</h3>
            <ul>
                <li>Pestañas: Todos, Activos, Borradores, Facturados, Cancelados y Borrados.</li>
                <li>Buscador por número de pedido, nombre del cliente o email.</li>
                <li>Columnas de fecha, cliente, número de líneas, total, estado y líneas enviadas al mayorista.</li>
                <li>Paginación de 20 pedidos por página.</li>
            </ul>

            <h3>Estados del pedido</h3>
            <table class="help-table">
                <thead><tr><th>Estado</th><th>Qué significa</th></tr></thead>
                <tbody>
                    <tr><td>Borrador</td><td>Pedido que todavía no se considera real. Solo se ofrece al crear un pedido manual.</td></tr>
                    <tr><td>Activo</td><td>Pedido pendiente de preparar. Es el estado normal de un pedido nuevo.</td></tr>
                    <tr><td>Preparado</td><td>El pedido está listo para enviarse al mayorista.</td></tr>
                    <tr><td>Enviado</td><td>Se usa cuando ya se han enviado al mayorista todas las líneas que se podían enviar. También puede seleccionarse a mano.</td></tr>
                    <tr><td>Facturado</td><td>Etiqueta manual. No genera una factura ni un documento descargable.</td></tr>
                    <tr><td>Cancelado</td><td>Marca el pedido como cancelado. No revierte por sí solo un pedido ya enviado al mayorista.</td></tr>
                    <tr><td>Borrado</td><td>Papelera. El pedido se puede restaurar.</td></tr>
                </tbody>
            </table>
            <p>En esta versión no hay reglas que impidan pasar de cualquier estado a otro. El estado es una etiqueta de organización; cambiarlo no dispara emails, cobros, facturas ni devoluciones de stock.</p>

            <h3>Qué ves en la ficha</h3>
            <ul>
                <li>Nombre, email, teléfono y NIF/CIF del cliente cuando existen.</li>
                <li>Dirección de entrega y, si es distinta, dirección de facturación.</li>
                <li>Forma de pago y estado del cobro.</li>
                <li>Comentario del cliente y notas internas.</li>
                <li>Líneas con producto, unidades, precio de cliente, tarifa del mayorista, coste e importe.</li>
                <li>Estado de envío de cada línea: pendiente, enviada o producto propio.</li>
                <li>Cuenta del mayorista y tarifa utilizada.</li>
            </ul>

            <h3>Qué puedes hacer</h3>
            <ul>
                <li>Cambiar el estado y marcar el cobro como pagado o pendiente.</li>
                <li>Crear un pedido manual con sus líneas.</li>
                <li>Enviar al mayorista las líneas del catálogo central que selecciones.</li>
                <li>Quitar una línea que todavía no se ha enviado.</li>
                <li>Mover el pedido a la papelera y restaurarlo.</li>
            </ul>

            <h3>Qué no puedes hacer desde este panel</h3>
            <ul>
                <li>Editar el cliente o la dirección de un pedido ya creado.</li>
                <li>Cambiar los gastos de envío de un pedido ya creado.</li>
                <li>Cobrar automáticamente con tarjeta o pasarela.</li>
                <li>Emitir o descargar una factura.</li>
                <li>Elegir transportista, generar etiquetas o añadir seguimiento.</li>
                <li>Enviar emails de confirmación al cliente.</li>
                <li>Enviar al mayorista los productos propios.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>El envío al mayorista es real</strong>
                <p>Al confirmar el envío se crea un pedido en las tablas del mayorista. No hay un botón para deshacerlo desde este panel. Revisa la cuenta, la dirección, las líneas y los importes antes de confirmar.</p>
            </div>

            <h3>Si algo no cuadra</h3>
            <ol class="help-steps">
                <li>No envíes el pedido si la dirección, el stock o el importe no son correctos.</li>
                <li>Anota el número de pedido, la hora y el mensaje exacto.</li>
                <li>Revisa los Logs del día.</li>
                <li>Pide ayuda por el canal que la plataforma te haya indicado fuera del panel. No hay un formulario de soporte dentro de la aplicación.</li>
            </ol>
        HTML,
        'panel_path' => 'panel/pedidos',
        'panel_label' => 'Ir a Pedidos',
        'related' => ['pedidos/pedido-manual', 'pedidos/estados-y-cobro', 'pedidos/enviar-idirecto', 'pedidos/incidencias-stock-direccion'],
    ],

    [
        'slug'    => 'pedido-manual',
        'title'   => 'Crear un pedido manual y añadir líneas',
        'summary' => 'Cómo registrar una venta desde el panel, qué datos pide y qué limitaciones tiene la edición posterior.',
        'keywords' => ['nuevo pedido', 'pedido manual', 'añadir línea', 'buscar producto', 'gastos de envío', 'dirección', 'cliente'],
        'location' => 'En Pedidos, botón «Nuevo pedido». La edición de líneas de un pedido existente aparece en su ficha, en «Anadir lineas».',
        'body' => <<<'HTML'
            <h3>Crear el pedido</h3>
            <ol class="help-steps">
                <li>En Pedidos, pulsa <strong>Nuevo pedido</strong>.</li>
                <li>Rellena el nombre del cliente. Es obligatorio.</li>
                <li>Añade email, teléfono, NIF/CIF y notas internas si los tienes.</li>
                <li>Decide si el envío va a la dirección de la tienda o a la dirección escrita por ti.</li>
                <li>Indica los gastos de envío que se cobrarán al cliente.</li>
                <li>Elige el estado inicial: Borrador, Activo o Preparado.</li>
                <li>Busca productos del catálogo o propios, o añade una línea manual.</li>
                <li>Revisa cantidades, precio de cliente, IVA y subtotal.</li>
                <li>Pulsa <strong>Crear pedido</strong>.</li>
            </ol>

            <h3>Dirección de entrega</h3>
            <p>Si dejas marcado «Enviar a la dirección de la tienda», el pedido usará la dirección configurada en Ajustes cuando se envíe al mayorista. Si lo desmarcas, puedes escribir la dirección del cliente.</p>

            <h3>Líneas del pedido</h3>
            <ul>
                <li>El buscador devuelve productos del catálogo central y productos propios publicados.</li>
                <li>Los productos propios se pueden vender, pero no enviar al mayorista.</li>
                <li>Cada línea se puede editar mientras estás creando el pedido: nombre, cantidad, precio e IVA.</li>
                <li>También puedes crear una línea manual escribiendo el nombre del producto.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>Incidencia conocida al añadir líneas a un pedido existente</strong>
                <p>En la ficha de un pedido, el bloque «Anadir lineas» aparece precargado con las líneas que ya tiene el pedido. El botón «Guardar lineas» no actualiza esas líneas: el servidor añade nuevas. Esto puede duplicar el pedido.</p>
                <p>Hasta que la función se corrija, no uses «Guardar lineas» en un pedido existente. Para un pedido nuevo, el formulario de alta sí empieza vacío.</p>
            </div>

            <h3>Qué ocurre después</h3>
            <ul>
                <li>El pedido recibe un número del tipo P26-00005.</li>
                <li>Las líneas del catálogo central se pueden seleccionar para enviarlas al mayorista.</li>
                <li>El pedido manual no crea una ficha en Clientes.</li>
                <li>No se envía un correo automático al cliente.</li>
            </ul>

            <h3>Si te has equivocado al crear el pedido</h3>
            <p>No hay una pantalla para editar el cliente, la dirección o los gastos de envío de un pedido ya creado. Antes de guardar, revisa esos datos con especial cuidado.</p>
        HTML,
        'panel_path' => 'panel/pedidos/nuevo',
        'panel_label' => 'Crear un pedido',
        'related' => ['pedidos/guia-pedidos', 'pedidos/enviar-idirecto', 'pedidos/incidencias-stock-direccion'],
    ],

    [
        'slug'    => 'estados-y-cobro',
        'title'   => 'Estados y cobro de un pedido',
        'summary' => 'Qué significa cada estado, cómo cambiar el cobro y qué efectos no tiene cambiar una etiqueta.',
        'keywords' => ['estado', 'cambiar estado', 'borrador', 'activo', 'preparado', 'enviado', 'facturado', 'cancelado', 'pagado', 'pendiente de pago', 'cobro'],
        'location' => 'En la ficha de cada pedido. El selector de estado está arriba; el bloque «Cobro» está en la tarjeta del cliente.',
        'body' => <<<'HTML'
            <h3>Cambiar el estado</h3>
            <ol class="help-steps">
                <li>Abre el pedido desde Pedidos.</li>
                <li>En el selector superior, elige el nuevo estado.</li>
                <li>Pulsa <strong>Guardar estado</strong>.</li>
            </ol>
            <p>La pantalla permite elegir cualquier estado. No hay una secuencia obligatoria ni una validación de cambios. Usa el estado como una etiqueta de organización y no como una acción automática.</p>

            <table class="help-table">
                <thead><tr><th>Estado</th><th>Uso recomendado</th></tr></thead>
                <tbody>
                    <tr><td>Borrador</td><td>Pedido manual que todavía no quieres tratar como real.</td></tr>
                    <tr><td>Activo</td><td>Pedido recibido y pendiente de preparar.</td></tr>
                    <tr><td>Preparado</td><td>Pedido revisado y listo para mandar al mayorista.</td></tr>
                    <tr><td>Enviado</td><td>Se ha enviado al mayorista o se ha entregado. El panel no distingue ambos casos con suficiente claridad.</td></tr>
                    <tr><td>Facturado</td><td>Etiqueta de control. No emite factura.</td></tr>
                    <tr><td>Cancelado</td><td>Pedido anulado según el criterio de la tienda. No revierte automáticamente un envío al mayorista.</td></tr>
                    <tr><td>Borrado</td><td>Papelera restaurable.</td></tr>
                </tbody>
            </table>

            <div class="help-callout help-callout--warning">
                <strong>Recomendación operativa</strong>
                <p>Antes de enviar líneas al mayorista, deja el pedido en <strong>Activo</strong> o <strong>Preparado</strong>. La interfaz permite intentar el envío desde otros estados, pero el flujo previsto solo pasa automáticamente a Enviado desde esos dos.</p>
            </div>

            <h3>Cobro</h3>
            <p>El estado del cobro es independiente del estado del pedido:</p>
            <ul>
                <li><strong>Pendiente de pago:</strong> el pedido se ha registrado, pero no se ha confirmado el ingreso.</li>
                <li><strong>Pagado:</strong> el tendero confirma el cobro con el botón <strong>Marcar como pagado</strong>.</li>
            </ul>
            <p>Puedes volver a pendiente con <strong>Volver a pendiente</strong>. El panel guarda la fecha en la que se marcó como pagado.</p>

            <h3>Qué no hace el sistema</h3>
            <ul>
                <li>No cobra con tarjeta ni con una pasarela.</li>
                <li>No comprueba transferencias bancarias.</li>
                <li>No envía un email al cliente al cambiar el estado.</li>
                <li>No emite una factura al marcar «Facturado».</li>
                <li>No devuelve automáticamente el stock al cancelar.</li>
            </ul>

            <p>Los métodos visibles son los que la tienda haya configurado en Ajustes: transferencia, contra reembolso o recogida. La página de gracias del cliente puede mostrar los datos bancarios si los has guardado.</p>
        HTML,
        'panel_path' => 'panel/pedidos',
        'panel_label' => 'Ir a Pedidos',
        'related' => ['pedidos/guia-pedidos', 'configuracion/venta-cobro-pagos', 'pedidos/enviar-idirecto'],
    ],

    [
        'slug'    => 'enviar-idirecto',
        'title'   => 'Enviar líneas al mayorista',
        'summary' => 'Cómo se crea el pedido real en idirecto, qué líneas se envían y qué comprobar antes de confirmar.',
        'keywords' => ['enviar idirecto', 'mayorista', 'pedido real', 'línea enviada', 'tarifa', 'producto propio', 'stock', 'cuenta'],
        'location' => 'En la ficha del pedido, dentro de «Lineas del pedido», bloque inferior «Enviar lineas a idirecto».',
        'body' => <<<'HTML'
            <div class="help-callout help-callout--warning">
                <strong>Esta acción crea un pedido real</strong>
                <p>No es una simulación. Se escribe en las tablas de pedidos del mayorista y puede descontar o reservar stock según la configuración de la plataforma. No hay un botón para deshacerlo desde este panel.</p>
            </div>

            <h3>Antes de enviar</h3>
            <ol class="help-steps">
                <li>Abre la ficha del pedido y revisa el cliente y la dirección.</li>
                <li>Comprueba la cuenta del mayorista y la tarifa en Ajustes.</li>
                <li>Deja el pedido en estado Activo o Preparado.</li>
                <li>Revisa las líneas, las cantidades y los importes de la vista previa.</li>
            </ol>

            <h3>Enviar solo algunas líneas</h3>
            <ol class="help-steps">
                <li>Marca las casillas de las líneas que quieres enviar.</li>
                <li>Comprueba la base, el IVA, el total y el número de líneas seleccionadas.</li>
                <li>Pulsa <strong>Enviar lineas a idirecto</strong>.</li>
                <li>Confirma el aviso.</li>
            </ol>
            <p>Cada línea enviada queda marcada con el número de pedido creado en el mayorista. Puedes enviar las demás más tarde sin repetir las anteriores.</p>

            <h3>Qué se envía y qué no</h3>
            <ul>
                <li>Solo se envían líneas del catálogo central que tengan producto asociado y todavía no se hayan enviado.</li>
                <li>Los productos propios no se envían al mayorista; aparecen marcados como «propio» y no tienen casilla de envío.</li>
                <li>Las líneas ya enviadas no se pueden borrar ni volver a enviar.</li>
                <li>Si todas las líneas enviables se han enviado y el pedido estaba Activo o Preparado, pasa a Enviado.</li>
            </ul>

            <h3>Errores habituales</h3>
            <ul>
                <li>No hay cuenta de idirecto configurada.</li>
                <li>El puente con el mayorista no está disponible.</li>
                <li>No se ha seleccionado ninguna línea pendiente.</li>
                <li>Un producto no tiene tarifa para la cuenta.</li>
                <li>La línea pertenece a un producto propio.</li>
                <li>El pedido está cancelado o en la papelera.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>Limitación actual de estados</strong>
                <p>La ficha puede mostrar la opción de enviar aunque el pedido no esté Activo o Preparado. No la uses fuera del flujo recomendado: el envío puede crear un pedido en el mayorista sin que el estado local cambie como esperas.</p>
            </div>

            <p>Consulta también <a href="{{base}}/panel/ayuda/problemas/no-envio-idirecto">No puedo enviar líneas a idirecto</a>.</p>
        HTML,
        'panel_path' => 'panel/pedidos',
        'panel_label' => 'Ir a Pedidos',
        'related' => ['pedidos/guia-pedidos', 'problemas/no-envio-idirecto', 'configuracion/cuenta-idirecto'],
    ],

    [
        'slug'    => 'incidencias-stock-direccion',
        'title'   => 'Incidencias de stock, dirección y envío',
        'summary' => 'Qué hacer cuando un pedido tiene una línea sin stock, una dirección incorrecta o una entrega bloqueada.',
        'keywords' => ['stock', 'sin stock', 'dirección incorrecta', 'cambiar dirección', 'envío bloqueado', 'incidencia', 'cancelar línea'],
        'location' => 'En la ficha del pedido y en el checkout del cliente. La dirección y el stock no se editan en una pantalla independiente.',
        'body' => <<<'HTML'
            <h3>Problema de stock</h3>
            <ul>
                <li>El catálogo central solo muestra productos con stock en los listados.</li>
                <li>El stock central no se puede editar desde el panel de la tienda.</li>
                <li>El envío al mayorista no comprueba por sí mismo todas las existencias: usa la tarifa y los datos del producto.</li>
                <li>Un producto propio con stock 0 todavía puede añadirse al carrito, así que no es una señal fiable.</li>
            </ul>
            <p>Si una línea no se puede servir, no envíes el pedido al mayorista todavía. Revisa si la línea se puede quitar antes de enviarla. Los cambios de líneas recalculan los totales, pero no existe un flujo automático de sustitución, devolución o reembolso.</p>

            <h3>Problema de dirección</h3>
            <p>La ficha muestra la dirección de entrega que se enviará al mayorista. Si es incorrecta:</p>
            <div class="help-callout help-callout--warning">
                <strong>No hay edición de la dirección en el panel</strong>
                <p>Una vez creado el pedido, no existe una pantalla para corregir el cliente o la dirección. No envíes al mayorista un pedido con una dirección incorrecta. Anota el número de pedido y pide ayuda por el canal externo que la plataforma te haya indicado.</p>
                <p>Si ya se envió, cancelar la etiqueta local no cancela automáticamente el pedido del mayorista.</p>
            </div>

            <h3>La dirección queda bloqueada en el checkout</h3>
            <p>Puede deberse a que la zona no está activa, la provincia y el código postal no se reconocen o la dirección no es de España. Revisa la configuración de envíos y el mensaje de zona no disponible.</p>

            <h3>No hay seguimiento del transporte</h3>
            <p>El panel no elige transportista, no genera etiquetas y no guarda un número de seguimiento. El estado del pedido es una etiqueta interna; no demuestra por sí solo que el paquete esté en camino.</p>

            <h3>Qué datos reunir antes de pedir ayuda</h3>
            <ul>
                <li>Número de pedido y fecha.</li>
                <li>Producto y cantidad afectados.</li>
                <li>Mensaje exacto que aparece.</li>
                <li>Estado del pedido y estado del cobro.</li>
                <li>Si la línea ya se envió al mayorista.</li>
                <li>La entrada correspondiente en Logs, si está disponible.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/pedidos',
        'panel_label' => 'Ir a Pedidos',
        'related' => ['pedidos/guia-pedidos', 'envios/calculo-envio', 'problemas/registro-actividad'],
    ],

    [
        'slug'    => 'papelera',
        'title'   => 'Papelera de pedidos',
        'summary' => 'Cómo mover un pedido a la papelera, restaurarlo y qué no se deshace con esa acción.',
        'keywords' => ['papelera', 'borrar pedido', 'restaurar pedido', 'borrados', 'eliminar'],
        'location' => 'En la ficha del pedido, botón «Mover a la papelera»; en el listado, pestaña «Borrados».',
        'body' => <<<'HTML'
            <h3>Mover a la papelera</h3>
            <ol class="help-steps">
                <li>Abre el pedido.</li>
                <li>Pulsa <strong>Mover a la papelera</strong>.</li>
                <li>Confirma la acción.</li>
            </ol>
            <p>El pedido pasa a la pestaña Borrados y deja de aparecer en las pestañas normales.</p>

            <h3>Restaurar</h3>
            <ol class="help-steps">
                <li>Entra en Pedidos y abre la pestaña <strong>Borrados</strong>.</li>
                <li>Abre el pedido.</li>
                <li>Pulsa <strong>Restaurar pedido</strong>.</li>
            </ol>
            <p>El pedido vuelve al estado Activo. Las líneas y los datos se conservan.</p>

            <div class="help-callout help-callout--warning">
                <strong>La papelera no cancela el pedido del mayorista</strong>
                <p>Si las líneas ya se enviaron a idirecto, mover el pedido a la papelera no las elimina del mayorista ni recupera el stock. La papelera solo afecta a la ficha local.</p>
            </div>

            <h3>Qué no hace</h3>
            <ul>
                <li>No borra definitivamente el pedido desde la interfaz.</li>
                <li>No borra las líneas ya enviadas.</li>
                <li>No reembolsa pagos.</li>
                <li>No devuelve stock por sí sola.</li>
                <li>No elimina las imágenes o datos asociados fuera del pedido.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/pedidos',
        'panel_label' => 'Ir a Pedidos',
        'related' => ['pedidos/estados-y-cobro', 'pedidos/incidencias-stock-direccion', 'pedidos/guia-pedidos'],
    ],

    [
        'slug'    => 'clientes',
        'title'   => 'Clientes: quién compra y sus direcciones',
        'summary' => 'Qué clientes aparecen, qué información se puede consultar y por qué esta sección es de solo lectura.',
        'keywords' => ['clientes', 'cliente invitado', 'direcciones', 'teléfono', 'email', 'cuenta cliente', 'pedidos'],
        'location' => 'En Clientes. La ficha se abre desde «Ver ficha».',
        'body' => <<<'HTML'
            <h3>Qué clientes aparecen</h3>
            <p>Aparecen los compradores que han finalizado un pedido en la web, ya sea con una cuenta o como invitados. Cada cliente pertenece a una tienda; el mismo email puede existir en tiendas distintas como cuentas separadas.</p>
            <p>Los pedidos creados manualmente desde el panel no crean una ficha en Clientes, porque no están asociados a una cuenta de comprador.</p>

            <h3>Qué puedes consultar</h3>
            <ul>
                <li>Nombre y email.</li>
                <li>Teléfono o móvil cuando consta.</li>
                <li>NIF/CIF cuando consta.</li>
                <li>Número de pedidos e importe comprado.</li>
                <li>Direcciones guardadas y marca de envío por defecto.</li>
                <li>Lista de pedidos con estado, pago y total.</li>
                <li>Si es invitado y cuándo se creó.</li>
            </ul>

            <h3>Cómo buscar</h3>
            <p>Usa el buscador de Clientes por nombre, email o teléfono. La búsqueda se envía al pulsar «Buscar».</p>

            <div class="help-callout help-callout--info">
                <strong>Sección de solo lectura</strong>
                <p>La tienda no edita aquí los datos ni las direcciones del cliente. El comprador los mantiene desde su cuenta en la web. Tampoco hay un botón para restablecer su contraseña ni para enviarle un correo desde el panel.</p>
            </div>

            <h3>Qué no gestiona la tienda</h3>
            <ul>
                <li>La contraseña del cliente.</li>
                <li>La recuperación de su cuenta por email.</li>
                <li>La baja o el borrado de su cuenta desde el panel.</li>
                <li>Los datos de pago, que no se guardan en el panel.</li>
            </ul>

            <h3>Si un cliente no aparece</h3>
            <ul>
                <li>Comprueba que el pedido se creó desde la web y no manualmente.</li>
                <li>Recuerda que el modo de pruebas global puede impedir compras reales.</li>
                <li>Revisa el email exacto con el que se registró o compró.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/clientes',
        'panel_label' => 'Ir a Clientes',
        'related' => ['pedidos/guia-pedidos', 'configuracion/usuarios-panel', 'problemas/funciones-no-disponibles'],
    ],
];
