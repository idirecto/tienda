<?php

declare(strict_types=1);

/**
 * Articulos de Productos y precios.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'productos-propios',
        'title'   => 'Crear y editar productos propios',
        'summary' => 'Añade productos ajenos al catálogo central, entiende sus estados y decide dónde se muestran.',
        'keywords' => ['producto propio', 'productos propios', 'crear producto', 'sku', 'proveedor', 'categoría', 'publicado', 'borrador', 'oculto', 'imagen'],
        'location' => 'En Productos propios. El botón «Nuevo producto» está arriba a la derecha; cada fila tiene Editar y Borrar.',
        'body' => <<<'HTML'
            <p>Los <strong>productos propios</strong> son artículos que añades tú. No forman parte del catálogo central del mayorista y no se envían a sus pedidos.</p>

            <h3>Antes de crear uno</h3>
            <ul>
                <li>El plan debe permitir productos propios. El Básico configurado por defecto no permite ninguno.</li>
                <li>El nombre es obligatorio.</li>
                <li>La imagen es opcional, pero mejora la presentación.</li>
            </ul>

            <h3>Crear el producto</h3>
            <ol class="help-steps">
                <li>Entra en Productos propios y pulsa <strong>Nuevo producto</strong>.</li>
                <li>Rellena nombre, SKU o referencia, proveedor y categoría. La categoría es un texto libre, no una categoría del catálogo.</li>
                <li>Indica el precio y, si lo hay, el precio de oferta. El precio de oferta se usa cuando tiene valor.</li>
                <li>Escribe el stock.</li>
                <li>Elige el estado: Publicado, Borrador u Oculto.</li>
                <li>Añade una descripción y una imagen si quieres.</li>
                <li>Pulsa <strong>Crear producto</strong>.</li>
            </ol>

            <h3>Dónde aparece</h3>
            <p>Los productos publicados pueden aparecer en la portada y en el listado de productos propios si has creado una entrada de menú que lleve a <code>/propios</code>. Los borradores y los ocultos no se muestran en la web.</p>

            <div class="help-callout help-callout--warning">
                <strong>No tienen ficha propia</strong>
                <p>En esta versión, la tarjeta de un producto propio enlaza al catálogo general. El enlace «Ver ficha» no abre una ficha independiente del producto propio. No prometas a tus clientes una página de detalle que el sistema todavía no genera.</p>
            </div>

            <h3>Imagen y cuota</h3>
            <ul>
                <li>La imagen de producto admite hasta 5 MB y se puede optimizar a WebP.</li>
                <li>El número de productos propios depende del plan.</li>
                <li>El cupo se muestra en la cabecera de Productos propios.</li>
            </ul>

            <h3>Editar o borrar</h3>
            <p>Puedes editar los campos en cualquier momento. Borrar un producto elimina su ficha; no existe una papelera de productos propios.</p>

            <p>Si el plan no te deja crear productos, consulta <a href="{{base}}/panel/ayuda/productos/cuotas-productos-no-aparecen">Cuotas y productos que no aparecen</a>.</p>
        HTML,
        'panel_path' => 'panel/productos',
        'panel_label' => 'Ir a Productos propios',
        'related' => ['productos/precios-iva-stock', 'productos/cuotas-productos-no-aparecen', 'menu/categorias-visibles-propias'],
    ],

    [
        'slug'    => 'precios-iva-stock',
        'title'   => 'Precios, IVA, beneficio y stock',
        'summary' => 'Cómo se calcula el precio del catálogo, qué cambia en un producto propio y qué limitaciones tiene el stock.',
        'keywords' => ['precio', 'iva', 'beneficio', 'tarifa', 'stock', 'stock 0', 'precio oferta', 'precio final'],
        'location' => 'El beneficio y el IVA por defecto están en Ajustes. El precio y el stock de los productos propios se editan en Productos propios.',
        'body' => <<<'HTML'
            <h3>Precio del catálogo central</h3>
            <p>El precio de la web se calcula con la <strong>tarifa de la tienda en el mayorista</strong> más el <strong>beneficio</strong> configurado en Ajustes. La web muestra ese precio con IVA incluido. El pedido guarda la base sin IVA y suma el impuesto al total.</p>
            <div class="help-callout help-callout--info">
                <strong>Dos porcentajes distintos</strong>
                <p>El IVA no es un beneficio. El beneficio es lo que añades sobre el coste de la tarifa; el IVA es el impuesto que se suma al precio final. Cambiar uno no cambia automáticamente el otro.</p>
            </div>

            <h3>Productos propios</h3>
            <ul>
                <li>Tienen un <strong>precio</strong> y, opcionalmente, un <strong>precio de oferta</strong>.</li>
                <li>El formulario no incluye un campo para elegir el IVA. El sistema usa el tipo guardado en la ficha del producto, que por defecto es el 21 %.</li>
                <li>No hay precios distintos por nivel de cliente para los compradores de la tienda.</li>
            </ul>

            <h3>Stock</h3>
            <p>El stock del catálogo central lo gestiona el mayorista y la tienda no lo puede editar. Para los productos propios sí existe un campo de stock, pero su comportamiento actual es confuso:</p>
            <div class="help-callout help-callout--warning">
                <strong>Incidencia conocida con el stock 0</strong>
                <p>La tarjeta muestra <strong>0 como «Sin stock»</strong>, pero el carrito interpreta que la tienda no lleva control de existencias y permite añadir el producto. Hasta que esta incoherencia se corrija, no uses el stock 0 como criterio fiable para bloquear la compra. Revisa el estado y confirma el stock por otra vía.</p>
            </div>

            <h3>Qué revisar antes de cambiar precios</h3>
            <ol class="help-steps">
                <li>Comprueba la tarifa y el beneficio en Ajustes.</li>
                <li>Revisa una categoría y una ficha en la web pública.</li>
                <li>Comprueba que el precio final incluye el IVA esperado.</li>
                <li>Recuerda que los pedidos ya creados conservan los importes con los que nacieron.</li>
            </ol>

            <p>No hay una pantalla de tarifas por cliente ni de reglas de precio por provincia. La zona de envío no cambia el IVA ni el precio del producto.</p>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Venta y cobro',
        'related' => ['configuracion/venta-cobro-pagos', 'productos/productos-propios', 'productos/cuotas-productos-no-aparecen'],
    ],

    [
        'slug'    => 'cuotas-productos-no-aparecen',
        'title'   => 'Cuotas y productos que no aparecen',
        'summary' => 'Qué comprobar cuando un producto no se puede crear o no se ve en la web.',
        'keywords' => ['cuota', 'plan', 'productos propios', 'no aparece', 'oculto', 'borrador', 'sin stock', 'menú', 'buscador'],
        'location' => 'La cuota aparece en la cabecera de Productos propios y en la tarjeta del Panel.',
        'body' => <<<'HTML'
            <h3>Primero identifica el origen del producto</h3>
            <ul>
                <li><strong>Catálogo central:</strong> lo aporta el mayorista y no se edita desde Productos propios.</li>
                <li><strong>Producto propio:</strong> lo crea la tienda desde la sección Productos propios.</li>
            </ul>

            <h3>Si es un producto propio</h3>
            <ol class="help-steps">
                <li>Comprueba que su estado sea <strong>Publicado</strong>, no Borrador ni Oculto.</li>
                <li>Comprueba que el plan permite productos propios y que queda cuota.</li>
                <li>Revisa que no hayas borrado el producto.</li>
                <li>Comprueba que existe una entrada de menú hacia Productos propios.</li>
                <li>Publica el menú si has hecho cambios.</li>
            </ol>

            <h3>Si es del catálogo central</h3>
            <ol class="help-steps">
                <li>Comprueba que no esté agotado: los listados ocultan los productos sin stock.</li>
                <li>Comprueba que su categoría siga visible en el menú.</li>
                <li>Recuerda que el buscador en vivo usa las categorías visibles del menú.</li>
                <li>Prueba a abrir su ficha directa si tienes la dirección.</li>
            </ol>

            <div class="help-callout help-callout--warning">
                <strong>No hay una pantalla para ampliar el plan</strong>
                <p>Si el plan no permite productos propios, el botón «Ampliar plan» lleva a Ajustes, pero allí no existe un cambio de plan. La gestión del plan debe hacerla quien administra la plataforma.</p>
            </div>

            <h3>Casos que no se resuelven desde esta ayuda</h3>
            <ul>
                <li>Un producto central con datos incorrectos del mayorista.</li>
                <li>Un cambio de stock en el catálogo central.</li>
                <li>Una cuota de plan que necesita ampliarse.</li>
                <li>Un problema técnico repetido: revisa Logs y pide ayuda por el canal que la plataforma haya indicado fuera del panel.</li>
            </ul>

            <p>La ayuda no muestra un correo de soporte porque el panel no contiene un canal de soporte configurado.</p>
        HTML,
        'panel_path' => 'panel/productos',
        'panel_label' => 'Ir a Productos propios',
        'related' => ['productos/productos-propios', 'menu/catalogo-central', 'problemas/no-veo-cambios'],
    ],
];
