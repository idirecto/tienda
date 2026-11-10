<?php

declare(strict_types=1);

/**
 * Articulos de Envios y zonas de venta.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'zonas-tarifas',
        'title'   => 'Configurar zonas, tarifas, plazos y condiciones',
        'summary' => 'Decide dónde vendes, cuánto cuesta el envío en cada zona y desde qué importe es gratis.',
        'keywords' => ['zonas', 'envío', 'envios', 'tarifa', 'coste', 'envío gratis', 'plazo', 'península', 'canarias', 'ceuta', 'melilla'],
        'location' => 'En Ajustes, tarjeta «Zonas de venta y envio», debajo de Venta y cobro.',
        'body' => <<<'HTML'
            <p>La tienda trabaja con <strong>cuatro zonas fijas</strong>: Península, Ceuta, Melilla y Canarias. No se pueden crear zonas nuevas ni elegir provincias una a una desde esta pantalla.</p>

            <h3>Qué significa cada campo</h3>
            <ul>
                <li><strong>Activar zona:</strong> si está desmarcada, no se aceptan pedidos de esa zona.</li>
                <li><strong>Coste (EUR):</strong> gastos de envío de esa zona. Si se deja vacío, se usa la tarifa general de Venta y cobro.</li>
                <li><strong>Envío gratis desde (EUR):</strong> importe mínimo para no cobrar envío. Si se deja vacío, hereda el umbral general. Un <strong>0</strong> desactiva el envío gratis de esa zona.</li>
                <li><strong>Plazo mínimo y máximo (días):</strong> información orientativa del tiempo de entrega. El máximo no puede ser menor que el mínimo.</li>
                <li><strong>Condiciones de envío:</strong> texto libre para explicar incidencias, reparto o recogidas de esa zona.</li>
            </ul>

            <h3>Cómo guardar</h3>
            <ol class="help-steps">
                <li>Activa al menos una zona.</li>
                <li>Revisa los costes y umbrales de cada zona. Si los dejas vacíos, se aplica la tarifa general.</li>
                <li>Escribe el mensaje que verá el cliente cuando su dirección no pertenezca a una zona activa.</li>
                <li>Pulsa <strong>Guardar zonas y envío</strong>.</li>
            </ol>

            <div class="help-callout help-callout--warning">
                <strong>Revisa el coste antes de abrir la venta</strong>
                <p>Una tienda nueva puede tener las cuatro zonas activas y una tarifa general de 0 €. Si no cambias esos valores, el envío puede aparecer como gratuito. Comprueba la tarifa general y cada zona antes de recibir pedidos reales.</p>
            </div>

            <h3>Qué no configura esta pantalla</h3>
            <ul>
                <li>No elige una empresa de transporte.</li>
                <li>No genera etiquetas de envío.</li>
                <li>No calcula por peso.</li>
                <li>No crea un número de seguimiento.</li>
                <li>No cambia impuestos.</li>
            </ul>

            <h3>Errores frecuentes</h3>
            <ul>
                <li>Dejar todas las zonas desactivadas.</li>
                <li>No escribir el mensaje de zona no disponible.</li>
                <li>Usar importes negativos o con más de dos decimales.</li>
                <li>Poner un plazo máximo menor que el mínimo.</li>
                <li>Escribir condiciones de más de 1000 caracteres.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Zonas de venta',
        'related' => ['envios/calculo-envio', 'configuracion/venta-cobro-pagos', 'problemas/no-recibo-pedidos'],
    ],

    [
        'slug'    => 'calculo-envio',
        'title'   => 'Cómo se calcula el envío y por qué se bloquea una dirección',
        'summary' => 'Entiende qué zona se aplica, cómo se suma el coste y qué revisar cuando el checkout rechaza una dirección.',
        'keywords' => ['envío', 'cálculo', 'dirección', 'provincia', 'código postal', 'zona bloqueada', 'transporte', 'seguimiento', 'peso'],
        'location' => 'La configuración está en Ajustes. El mensaje de dirección bloqueada aparece en el checkout y la zona visible en la ficha del pedido.',
        'body' => <<<'HTML'
            <h3>Cómo se elige la zona</h3>
            <p>La tienda solo admite direcciones de España. Con la provincia y el código postal se decide si la dirección pertenece a Península, Ceuta, Melilla o Canarias. Si esos datos no se pueden interpretar o no coinciden, la dirección no se asigna a ninguna zona.</p>

            <h3>Cómo se calcula el coste</h3>
            <ol class="help-steps">
                <li>Se comprueba que la zona esté activa.</li>
                <li>Se usa el coste propio de la zona o, si está vacío, la tarifa general.</li>
                <li>Se comprueba el umbral de envío gratis del pedido.</li>
                <li>El coste se suma al total del pedido.</li>
            </ol>
            <p>El precio de los productos no cambia por la zona. La zona solo decide si se puede entregar y qué gastos de envío se aplican.</p>

            <h3>Por qué se bloquea una dirección</h3>
            <ul>
                <li>La provincia o el código postal no se reconocen.</li>
                <li>La dirección no es de España.</li>
                <li>La provincia y el código postal apuntan a zonas distintas.</li>
                <li>La zona está desactivada.</li>
                <li>No hay ninguna zona activa configurada.</li>
            </ul>
            <p>El checkout muestra el mensaje que hayas escrito en Ajustes. Si no hay mensaje, se usa uno genérico del sistema.</p>

            <div class="help-callout help-callout--warning">
                <strong>No hay transportista ni seguimiento</strong>
                <p>El panel no permite elegir una empresa de transporte, imprimir etiquetas, añadir un número de seguimiento ni avisar automáticamente al cliente. El pedido muestra la zona de entrega, pero no gestiona el envío físico. Tampoco calcula portes por peso.</p>
            </div>

            <h3>Qué revisar si un cliente no puede comprar</h3>
            <ol class="help-steps">
                <li>Comprueba que la zona está activa en Ajustes.</li>
                <li>Revisa que la provincia y el código postal estén bien escritos.</li>
                <li>Comprueba que la dirección es de España.</li>
                <li>Mira el mensaje que aparece en el checkout.</li>
                <li>Si el problema continúa, revisa Logs y pide ayuda por el canal que la plataforma te haya facilitado.</li>
            </ol>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Zonas de venta',
        'related' => ['envios/zonas-tarifas', 'pedidos/incidencias-stock-direccion', 'problemas/no-recibo-pedidos'],
    ],
];
