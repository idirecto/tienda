<?php

declare(strict_types=1);

/**
 * Articulos de Menu y catalogo.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'estilos-y-publicacion',
        'title'   => 'Elegir y publicar el menú',
        'summary' => 'Diferencia entre Menú Compacto y Menú Catálogo, cómo funciona el borrador y cuándo ven los visitantes los cambios.',
        'keywords' => ['menú', 'compacto', 'catálogo', 'publicar', 'borrador', 'vista previa', 'estilo'],
        'location' => 'En Menu. La tarjeta «Estilo de menu» está arriba a la izquierda y «Borrador y publicacion» arriba a la derecha.',
        'body' => <<<'HTML'
            <h3>Dos estilos, el mismo contenido</h3>
            <ul>
                <li><strong>Menú Compacto:</strong> barra horizontal con un desplegable y la opción «Más categorías».</li>
                <li><strong>Menú Catálogo:</strong> botón «Todas las categorías» que abre un panel con las categorías a la izquierda.</li>
            </ul>
            <p>Los dos muestran las mismas categorías y destinos. Solo cambia la forma de presentarlos.</p>

            <h3>Cambiar el estilo</h3>
            <ol class="help-steps">
                <li>Marca uno de los dos estilos.</li>
                <li>Pulsa <strong>Guardar estilo</strong>.</li>
                <li>Usa <strong>Vista previa</strong> para comprobarlo en escritorio y móvil.</li>
            </ol>
            <p>El estilo se aplica en cuanto lo guardas, aunque no pulses «Publicar cambios», porque es una opción de presentación de la tienda.</p>

            <h3>Borrador y publicación</h3>
            <p>Los cambios del árbol, los nombres, las categorías ocultas y las categorías propias se guardan como <strong>borrador</strong>. La tienda sigue mostrando la última versión publicada hasta que pulses <strong>Publicar cambios</strong>.</p>
            <div class="help-callout help-callout--info">
                <strong>Todavía no has publicado nunca</strong>
                <p>Si la tienda nunca ha publicado un menú, puede estar mostrando el árbol de trabajo actual. En cuanto publiques una versión, los cambios posteriores quedarán en borrador hasta que vuelvas a publicar.</p>
            </div>

            <h3>Qué revisar antes de publicar</h3>
            <ul>
                <li>Que haya al menos una categoría de primer nivel activa.</li>
                <li>Que las categorías tengan grupos y enlaces útiles.</li>
                <li>Que no queden destinos pendientes o desactivados por error.</li>
                <li>Que el nivel de cliente de la tienda no esté ocultando enlaces que esperabas ver.</li>
            </ul>

            <h3>Errores frecuentes</h3>
            <ul>
                <li><strong>He cambiado una categoría y no se ve:</strong> revisa que no haya cambios sin publicar.</li>
                <li><strong>No encuentro un enlace:</strong> consulta la tarjeta «Qué categorías se ven» y la lista de destinos pendientes.</li>
                <li><strong>La tienda no muestra menú:</strong> no hay ninguna categoría de primer nivel activa o el árbol está vacío.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/menu',
        'panel_label' => 'Ir a Menu',
        'related' => ['menu/editar-arbol', 'menu/categorias-visibles-propias', 'problemas/no-veo-cambios'],
    ],

    [
        'slug'    => 'editar-arbol',
        'title'   => 'Editar, ordenar y personalizar el árbol del menú',
        'summary' => 'Cómo crear grupos y enlaces, ordenar con el ratón y cambiar iconos, badges, banners y niveles mínimos.',
        'keywords' => ['árbol', 'menú', 'categoría', 'grupo', 'enlace', 'ordenar', 'icono', 'badge', 'banner', 'destino', 'arrastrar'],
        'location' => 'En Menu, tarjeta «Arbol de categorias», debajo de las tarjetas de estilo y publicación.',
        'body' => <<<'HTML'
            <div class="help-callout help-callout--warning">
                <strong>Los cambios no son definitivos hasta publicar</strong>
                <p>Editar el árbol modifica el borrador. La web seguirá mostrando la versión publicada hasta que pulses <strong>Publicar cambios</strong>.</p>
            </div>

            <h3>Añadir elementos</h3>
            <ul>
                <li><strong>+ Categoría</strong> crea un primer nivel.</li>
                <li><strong>+ Grupo</strong> crea un segundo nivel dentro de una categoría.</li>
                <li><strong>+ Enlace</strong> crea un destino dentro de un grupo.</li>
                <li><strong>+ Productos propios</strong> crea una entrada que lleva al listado de productos propios de la tienda.</li>
                <li><strong>+ Página de la tienda</strong> crea una entrada hacia una página existente de la tienda, si hay alguna disponible.</li>
            </ul>

            <h3>Opciones del editor</h3>
            <p>Al crear o editar un nodo puedes cambiar:</p>
            <ul>
                <li>El nombre y el slug que aparece en la dirección.</li>
                <li>El tipo de destino: categoría, subcategoría, marca, etiqueta, filtro, productos propios, página o enlace libre.</li>
                <li>El icono y un badge como «NUEVO», «OFERTA» o «TOP», con su color.</li>
                <li>Un banner de la tienda y su enlace.</li>
                <li>El nivel mínimo de cliente.</li>
                <li>Si se oculta cuando no tiene productos.</li>
                <li>Si está activo o inactivo.</li>
            </ul>

            <h3>Ordenar y mover</h3>
            <p>Arrastra un nodo para cambiar su posición. También puedes soltarlo sobre otro nodo para cambiar de nivel, siempre que no se superen los tres niveles del menú.</p>

            <div class="help-callout help-callout--warning">
                <strong>Los nodos compartidos no se editan aquí</strong>
                <p>Si un nodo lo gestiona la plataforma, verás «Compartida: la gestiona la plataforma». Puedes ocultarlo o cambiar el nombre que se muestra en tu web desde «Qué categorías se ven», pero no editarlo ni borrarlo desde esta pantalla.</p>
            </div>

            <h3>Copiar el menú de referencia</h3>
            <ol class="help-steps">
                <li>Revisa si quieres reemplazar todo el menú actual.</li>
                <li>Pulsa <strong>Copiar el menu de referencia</strong>.</li>
                <li>Revisa los avisos de destinos pendientes.</li>
                <li>Publica cuando el resultado sea correcto.</li>
            </ol>
            <p>Reemplazar el menú es una acción destructiva para el árbol propio y no tiene un botón de deshacer visible. Si no necesitas reemplazarlo, no marques esa casilla.</p>

            <h3>Si algo no aparece</h3>
            <ul>
                <li>Comprueba que el nodo está activo.</li>
                <li>Comprueba que su destino existe en el catálogo.</li>
                <li>Comprueba que tu nivel de cliente permite verlo.</li>
                <li>Publica los cambios.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/menu',
        'panel_label' => 'Ir al editor del menú',
        'related' => ['menu/estilos-y-publicacion', 'menu/categorias-visibles-propias', 'menu/catalogo-central'],
    ],

    [
        'slug'    => 'categorias-visibles-propias',
        'title'   => 'Mostrar, ocultar, renombrar y crear categorías',
        'summary' => 'Controla qué categorías aparecen en tu web y crea entradas propias sin modificar el catálogo compartido.',
        'keywords' => ['categorías visibles', 'ocultar categoría', 'renombrar', 'categoría propia', 'productos propios', 'página tienda', 'menú elegido', 'menú completo'],
        'location' => 'En Menu, tarjeta «Que categorias se ven», entre la publicación y el editor del árbol.',
        'body' => <<<'HTML'
            <h3>Elige el modo del menú</h3>
            <ul>
                <li><strong>Completo:</strong> se muestran todas las categorías salvo las que ocultes.</li>
                <li><strong>Elegido:</strong> solo se muestran las categorías que marques como visibles.</li>
            </ul>
            <p>Guarda el modo y después publica los cambios.</p>

            <h3>Mostrar, ocultar o renombrar</h3>
            <ol class="help-steps">
                <li>Busca la categoría o subcategoría en la lista.</li>
                <li>Pulsa <strong>Ocultar</strong> o <strong>Mostrar</strong>.</li>
                <li>Si quieres otro nombre para tu web, escríbelo en «Nombre en tu web» y pulsa <strong>Guardar nombre</strong>.</li>
                <li>Para recuperar el nombre original, usa <strong>Volver al arbol</strong>.</li>
            </ol>
            <p>Ocultar una categoría oculta también su rama. El cambio solo afecta a tu tienda: no cambia el catálogo compartido ni la web de las demás tiendas.</p>

            <h3>Categorías propias</h3>
            <p>Puedes crear una entrada propia para:</p>
            <ul>
                <li>Enlazar a tus productos propios.</li>
                <li>Enlazar a una página de tu tienda que ya exista.</li>
                <li>Abrir un enlace libre.</li>
            </ul>
            <p>También puedes colocar una entrada propia dentro de una categoría compartida, por ejemplo «Servicio» dentro de «Componentes».</p>

            <div class="help-callout help-callout--warning">
                <strong>La página de la tienda necesita existir antes</strong>
                <p>Esta versión no incluye un editor para crear páginas de contenido. Si quieres usar el destino «Página de la tienda», solo podrás elegir páginas que ya estén creadas fuera de esta pantalla. Si la lista está vacía, no inventes un contenido que el panel no permite guardar.</p>
            </div>

            <h3>Qué no se puede hacer desde aquí</h3>
            <ul>
                <li>Crear o borrar categorías del catálogo central.</li>
                <li>Modificar el nombre original para todas las tiendas.</li>
                <li>Cambiar el orden de los nodos compartidos.</li>
            </ul>

            <p>Después de los cambios, usa <strong>Publicar cambios</strong> y comprueba el resultado en la tienda pública.</p>
        HTML,
        'panel_path' => 'panel/menu',
        'panel_label' => 'Ir a categorías visibles',
        'related' => ['menu/editar-arbol', 'menu/catalogo-central', 'problemas/no-veo-cambios'],
    ],

    [
        'slug'    => 'catalogo-central',
        'title'   => 'Catálogo central: de dónde salen los productos',
        'summary' => 'Qué productos puedes mostrar, por qué no aparecen algunos y qué parte del catálogo no se gestiona desde el panel de la tienda.',
        'keywords' => ['catálogo', 'producto', 'stock', 'mayorista', 'idirecto', 'precio', 'ficha', 'marca', 'categorías'],
        'location' => 'No hay una pantalla para editar el catálogo central. Se ve en la web pública y se filtra a través de Menu y del buscador.',
        'body' => <<<'HTML'
            <p>La tienda muestra un <strong>catálogo central</strong> que pertenece al mayorista. No es una copia: la web lo lee en cada visita. La tienda no puede cambiar sus productos, sus marcas, sus categorías ni su stock.</p>

            <h3>Qué aparece</h3>
            <ul>
                <li>Productos que tienen stock disponible según la regla del mayorista.</li>
                <li>Sus categorías y subcategorías reales.</li>
                <li>Sus marcas, imágenes, características y variaciones disponibles.</li>
                <li>El precio se calcula con la tarifa que idirecto asigna a tu cuenta, más el <strong>beneficio fijo</strong> y después el <strong>beneficio porcentual</strong> configurados en Ajustes. La web lo muestra con IVA incluido.</li>
            </ul>

            <h3>Qué no aparece en los listados</h3>
            <p>Los productos sin stock válido no se muestran en las categorías ni en los resultados normales. La ficha directa puede seguir existiendo, pero el artículo puede indicar «Sin stock».</p>

            <h3>Qué controla la tienda</h3>
            <ul>
                <li>Qué categorías enseña en el menú y cuáles oculta.</li>
                <li>El nombre del enlace del menú, sin cambiar el nombre del catálogo compartido.</li>
                <li>Los productos propios que añade.</li>
                <li>Los beneficios fijo y porcentual que añade sobre la tarifa de su cuenta. La tarifa base se gestiona en idirecto.</li>
            </ul>

            <h3>Qué no controla la tienda</h3>
            <ul>
                <li>El stock del catálogo central.</li>
                <li>Las descripciones, fotos o especificaciones del mayorista.</li>
                <li>La creación o eliminación de categorías centrales.</li>
                <li>La ficha del producto más allá de los datos que recibe.</li>
            </ul>

            <div class="help-callout help-callout--info">
                <strong>El menú limita el buscador</strong>
                <p>El buscador en vivo trabaja con las categorías y subcategorías visibles en tu menú. Si ocultas una categoría, sus productos dejan de entrar en esa búsqueda aunque sigan existiendo en el catálogo central.</p>
            </div>

            <p>Si un producto no aparece, revisa el artículo <a href="{{base}}/panel/ayuda/productos/cuotas-productos-no-aparecen">Cuotas y productos que no aparecen</a>.</p>
        HTML,
        'panel_path' => 'panel/menu',
        'panel_label' => 'Ir a Menu',
        'related' => ['productos/cuotas-productos-no-aparecen', 'menu/categorias-visibles-propias', 'productos/precios-iva-stock'],
    ],
];
