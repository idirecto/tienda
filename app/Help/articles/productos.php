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
        'related' => ['productos/configurar-calcular-tarifa', 'productos/precios-iva-stock', 'productos/cuotas-productos-no-aparecen', 'menu/categorias-visibles-propias'],
    ],

    [
        'slug'    => 'configurar-calcular-tarifa',
        'title'   => 'Cómo se configura y calcula la tarifa de tu tienda',
        'summary' => 'Entiende la tarifa de idirecto, los dos beneficios, la fórmula exacta, el redondeo y qué hacer cuando un producto no muestra precio.',
        'keywords' => ['tarifa', 'tarifa tienda', 'beneficio', 'beneficio fijo', 'beneficio porcentual', 'margen', 'markup', 'precio', 'precio venta', 'coste', 'coste tarifa', 'configurar precio', 'configurar tarifa', 'calcular precio', 'iva', 'redondeo'],
        'location' => 'En Ajustes, dentro de la tarjeta «Venta y cobro». La tarifa base se gestiona en idirecto; aquí solo se cambian el beneficio fijo y el porcentual.',
        'body' => <<<'HTML'
            <h3>Qué significa el coste de tarifa</h3>
            <p>La <strong>tarifa del mayorista</strong>, también llamada <strong>tarifa de tu cuenta en idirecto</strong>, es el precio que corresponde a tu cuenta para cada producto. Es la base sobre la que se calcula el precio de venta de la tienda.</p>
            <p>No es el precio final que paga tu cliente ni el coste interno del almacén. Es un dato que se gestiona en idirecto y que el panel de la tienda no permite editar.</p>

            <div class="help-callout help-callout--info">
                <strong>La tarifa no es el precio de venta</strong>
                <p>El precio de venta se obtiene aplicando el beneficio que configures sobre esa tarifa. Si no tienes claro qué tarifa tiene asignada tu cuenta, consúltalo con idirecto o con quien administra la plataforma.</p>
            </div>

            <h3>Los dos beneficios que configuras tú</h3>
            <table class="help-table">
                <thead>
                    <tr><th>Concepto</th><th>Qué hace</th><th>Ejemplo sencillo</th></tr>
                </thead>
                <tbody>
                    <tr><td>Beneficio fijo (EUR)</td><td>Se suma a la tarifa antes de aplicar el porcentaje.</td><td>Tarifa de 10 € y beneficio fijo de 1 €: la base antes del porcentaje es 11 €.</td></tr>
                    <tr><td>Beneficio porcentual (%)</td><td>Se aplica sobre la tarifa más el beneficio fijo.</td><td>Si la base anterior es 11 € y el porcentaje es 12 %, el beneficio es 1,32 €.</td></tr>
                    <tr><td>IVA</td><td>Se suma al final. No es un beneficio de la tienda.</td><td>Un 21 % sobre el precio base da el precio final que ve el cliente.</td></tr>
                </tbody>
            </table>

            <h3>Qué opciones de tarifa puedes usar</h3>
            <p>No hay un selector de tipos de tarifa ni una opción llamada «automática» que puedas elegir en el panel. La tarifa base se obtiene automáticamente de tu cuenta de idirecto. Lo que decides tú es cómo añadir el beneficio:</p>
            <ul>
                <li><strong>Los dos beneficios:</strong> escribes un importe fijo y un porcentaje. Es la opción que combina las dos partes de la fórmula.</li>
                <li><strong>Solo beneficio fijo:</strong> dejas el porcentaje en <code>0</code>. La tarifa sube la misma cantidad en todos los productos.</li>
                <li><strong>Solo beneficio porcentual:</strong> dejas el beneficio fijo en <code>0</code>. El incremento depende del precio de cada producto.</li>
                <li><strong>Sin beneficio:</strong> dejas los dos valores en <code>0</code>. El precio base coincide con la tarifa.</li>
            </ul>
            <p>El sistema elige automáticamente la tarifa y el almacén que corresponden a tu cuenta entre los que tienen stock y una tarifa válida. Esa elección no se hace producto a producto desde este panel.</p>

            <h3>Dónde se configura</h3>
            <ol class="help-steps">
                <li>Entra en <strong>Ajustes</strong> y busca la tarjeta <strong>Venta y cobro</strong>.</li>
                <li>Revisa el campo <strong>Beneficio fijo (EUR)</strong>.</li>
                <li>Revisa el campo <strong>Beneficio porcentual (%)</strong>.</li>
                <li>El bloque «Ejemplo con tus valores guardados» te enseña cómo quedaría una tarifa de 1 € y otra de 500 €.</li>
                <li>Pulsa <strong>Guardar venta y cobro</strong>.</li>
            </ol>
            <p>La tarifa base no aparece como un campo editable: se lee de idirecto. Si intentas corregirla desde Ajustes, no encontrarás esa opción. El beneficio fijo admite hasta <strong>999.999,99 €</strong> y el porcentual hasta un <strong>200 %</strong>; ambos aceptan como máximo dos decimales. Si un valor no es válido, no se guarda ningún cambio de esa tarjeta y se muestra el motivo.</p>
            <p><a href="{{base}}/panel/ajustes#venta-y-cobro">Abrir Ajustes &gt; Venta y cobro</a></p>

            <h3>La fórmula exacta</h3>
            <p>Primero se suma el beneficio fijo a la tarifa. Después se aplica el porcentaje sobre ese resultado. Finalmente se añade el IVA para mostrar el precio de la web:</p>
            <p><code>precio base = (tarifa + beneficio fijo) × (1 + beneficio porcentual / 100)</code></p>
            <p><code>precio final = precio base × (1 + IVA / 100)</code></p>
            <p>El orden es importante. No es lo mismo aplicar el porcentaje y sumar el fijo después. En la fórmula implementada, el porcentaje también se calcula sobre el beneficio fijo.</p>

            <h3>Ejemplo 1: producto económico</h3>
            <p>Valores de ejemplo: tarifa de <strong>1,00 €</strong>, beneficio fijo de <strong>1,00 €</strong>, porcentaje del <strong>12 %</strong> e IVA del <strong>21 %</strong>.</p>
            <table class="help-table">
                <thead>
                    <tr><th>Paso</th><th>Cálculo</th><th>Resultado</th></tr>
                </thead>
                <tbody>
                    <tr><td>1. Tarifa</td><td>—</td><td>1,00 €</td></tr>
                    <tr><td>2. Sumar el fijo</td><td>1,00 + 1,00</td><td>2,00 €</td></tr>
                    <tr><td>3. Calcular el 12 %</td><td>2,00 × 0,12</td><td>0,24 €</td></tr>
                    <tr><td>4. Precio base</td><td>2,00 + 0,24</td><td><strong>2,24 €</strong></td></tr>
                    <tr><td>5. Añadir el IVA del 21 %</td><td>2,24 × 1,21 = 2,7104</td><td><strong>2,71 €</strong></td></tr>
                </tbody>
            </table>
            <p>El precio que vería el cliente en la web es <strong>2,71 €</strong>, con el IVA incluido. Sin IVA, el pedido guarda una base de <strong>2,24 €</strong>.</p>

            <h3>Ejemplo 2: producto de mayor precio</h3>
            <p>Se mantienen los mismos valores de ejemplo y se cambia solo la tarifa por <strong>500,00 €</strong>.</p>
            <table class="help-table">
                <thead>
                    <tr><th>Paso</th><th>Cálculo</th><th>Resultado</th></tr>
                </thead>
                <tbody>
                    <tr><td>1. Tarifa</td><td>—</td><td>500,00 €</td></tr>
                    <tr><td>2. Sumar el fijo</td><td>500,00 + 1,00</td><td>501,00 €</td></tr>
                    <tr><td>3. Calcular el 12 %</td><td>501,00 × 0,12</td><td>60,12 €</td></tr>
                    <tr><td>4. Precio base</td><td>501,00 + 60,12</td><td><strong>561,12 €</strong></td></tr>
                    <tr><td>5. Añadir el IVA del 21 %</td><td>561,12 × 1,21 = 678,9552</td><td><strong>678,96 €</strong></td></tr>
                </tbody>
            </table>
            <p>El precio que vería el cliente es <strong>678,96 €</strong>, con el IVA incluido. La base sin IVA del pedido es <strong>561,12 €</strong>.</p>

            <div class="help-callout help-callout--warning">
                <strong>Ejemplo ilustrativo</strong>
                <p>Los valores de 1 € de beneficio fijo, 12 % de porcentaje y 21 % de IVA se usan solo para explicar el cálculo. No son la configuración predeterminada de tu tienda. El precio real depende de tu tarifa de idirecto, de los beneficios que guardes y del IVA de cada producto.</p>
            </div>

            <h3>Cómo se redondea</h3>
            <ul>
                <li>El beneficio fijo y el porcentual se guardan con un máximo de dos decimales.</li>
                <li>El precio base se redondea a dos decimales antes de aplicar el IVA.</li>
                <li>El precio final con IVA se vuelve a redondear a dos decimales.</li>
                <li>La web muestra el precio con IVA incluido; el pedido conserva la base sin IVA y el tipo de IVA aplicado.</li>
                <li>Los pedidos ya creados conservan los importes con los que nacieron, aunque después cambies los beneficios.</li>
            </ul>
            <p>Si el producto no trae un IVA propio, se usa el IVA por defecto configurado en Ajustes.</p>

            <h3>Qué hacer si un producto no muestra precio</h3>
            <p>Cuando el sistema no encuentra una tarifa válida para la cuenta de la tienda, no inventa un precio. En los listados aparece <strong>«Consultar»</strong> y en la ficha se muestra un aviso de que el precio no está disponible. En ese estado no se puede añadir el producto al carrito.</p>
            <ol class="help-steps">
                <li>Comprueba que el producto tenga stock y que no esté agotado.</li>
                <li>Revisa que tu cuenta de idirecto esté activa y tenga una tarifa asignada.</li>
                <li>Comprueba que el producto no esté en una categoría oculta del menú.</li>
                <li>Si el aviso se repite en varios productos, contacta con idirecto o con quien administra la plataforma: la tarifa base no se corrige desde Ajustes.</li>
            </ol>
            <p>La fórmula de venta no usa el coste interno del almacén. Si el catálogo no encuentra una combinación válida de tarifa, almacén, stock y coste, el producto puede quedar sin precio o fuera de los listados; no se debe escribir un precio manual en un producto del catálogo desde este panel. Si tu cuenta no tiene una tarifa asignada, la plataforma puede aplicar una tarifa general, pero nunca inventa un precio por producto.</p>

            <p>Esta fórmula se aplica al catálogo central en listados, buscador, ficha, carrito y pedidos. Los <strong>productos propios</strong> no la usan: se venden al precio que escribes en su ficha.</p>
        HTML,
        'panel_path' => 'panel/ajustes#venta-y-cobro',
        'panel_label' => 'Ir a Ajustes > Venta y cobro',
        'related' => ['configuracion/venta-cobro-pagos', 'productos/precios-iva-stock', 'configuracion/cuenta-idirecto', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'precios-iva-stock',
        'title'   => 'Precios, IVA, beneficios y stock',
        'summary' => 'Cómo se calcula el precio del catálogo con beneficio fijo y porcentual, qué cambia en un producto propio y qué limitaciones tiene el stock.',
        'keywords' => ['precio', 'iva', 'beneficio', 'beneficio fijo', 'beneficio porcentual', 'margen', 'markup', 'tarifa', 'stock', 'stock 0', 'precio oferta', 'precio final'],
        'location' => 'El beneficio fijo, el porcentual y el IVA por defecto están en Ajustes. El precio y el stock de los productos propios se editan en Productos propios.',
        'body' => <<<'HTML'
            <h3>Precio del catálogo central</h3>
            <p>La guía completa, con la configuración, el redondeo y dos ejemplos paso a paso, está en <a href="{{base}}/panel/ayuda/productos/configurar-calcular-tarifa">Cómo se configura y calcula la tarifa de tu tienda</a>. Aquí tienes un resumen.</p>
            <p>Se parte de la <strong>tarifa que idirecto aplica a tu cuenta</strong>. Primero se suma el <strong>beneficio fijo en euros</strong> y después se aplica el <strong>beneficio porcentual</strong>:</p>
            <p><code>precio base = (tarifa + beneficio fijo) × (1 + beneficio porcentual / 100)</code></p>
            <p>La web muestra ese precio con el IVA incluido. El pedido guarda la base sin IVA y suma el impuesto al total. El orden importa: el porcentaje se aplica después de sumar el fijo.</p>

            <table class="help-table">
                <thead>
                    <tr><th>Concepto</th><th>Qué hace</th><th>Dónde se configura</th></tr>
                </thead>
                <tbody>
                    <tr><td>Beneficio fijo</td><td>Suma un importe en euros a la tarifa. Ayuda a que los artículos baratos dejen margen.</td><td>Ajustes &gt; Venta y cobro</td></tr>
                    <tr><td>Beneficio porcentual</td><td>Aplica un porcentaje al resultado anterior. Mantiene el margen en los artículos caros.</td><td>Ajustes &gt; Venta y cobro</td></tr>
                    <tr><td>IVA</td><td>Se suma al final. No es un beneficio.</td><td>Tipo del producto; IVA por defecto en Ajustes</td></tr>
                </tbody>
            </table>

            <div class="help-callout help-callout--info">
                <strong>Ejemplo</strong>
                <p>Con una tarifa de 1 €, 2 € de beneficio fijo y un 20 %: <code>(1 + 2) × 1,20 = 3,60 €</code> antes de IVA. Con una tarifa de 500 € y los mismos valores: <code>(500 + 2) × 1,20 = 602,40 €</code>.</p>
                <p>El bloque «Ejemplo con tus valores guardados» de Ajustes muestra estos cálculos con tu configuración real.</p>
            </div>

            <h3>Productos propios</h3>
            <ul>
                <li>Tienen un <strong>precio</strong> y, opcionalmente, un <strong>precio de oferta</strong>.</li>
                <li>El beneficio fijo y el porcentual <strong>no se aplican a los productos propios</strong>: se usa el precio que escribes en su ficha.</li>
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
                <li>Comprueba el beneficio fijo y el porcentual configurados en Ajustes.</li>
                <li>Revisa una categoría y una ficha en la web pública.</li>
                <li>Comprueba que el precio final incluye el IVA esperado.</li>
                <li>Recuerda que los pedidos ya creados conservan los importes con los que nacieron.</li>
            </ol>

            <p>No hay una pantalla de tarifas por cliente ni de reglas de precio por provincia o categoría. La zona de envío no cambia el IVA ni el precio del producto.</p>
        HTML,
        'panel_path' => 'panel/ajustes',
        'panel_label' => 'Ir a Configurar precios y cobro',
        'related' => ['productos/configurar-calcular-tarifa', 'configuracion/venta-cobro-pagos', 'productos/productos-propios', 'productos/cuotas-productos-no-aparecen'],
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
