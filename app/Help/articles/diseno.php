<?php

declare(strict_types=1);

/**
 * Articulos de Diseno y comunicacion.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'identidad-visual',
        'title'   => 'Colores, tipografía, cabecera y plantilla',
        'summary' => 'Cómo cambiar la identidad visual de la tienda y comprobar el resultado antes de guardar.',
        'keywords' => ['diseño', 'colores', 'tipografía', 'fuente', 'plantilla', 'tema', 'cabecera', 'preset', 'modo oscuro', 'vista previa'],
        'location' => 'En Diseno, columna izquierda. La vista previa se actualiza en la columna derecha.',
        'body' => <<<'HTML'
            <h3>Qué puedes ajustar</h3>
            <ul>
                <li><strong>Presets:</strong> combinaciones preparadas de colores, modo, forma y tipografía. Al pulsar uno se guarda la identidad completa.</li>
                <li><strong>Colores de marca:</strong> principal, secundario, acento y colores avanzados de fondo, tarjetas, texto y bordes.</li>
                <li><strong>Modo de color:</strong> claro, oscuro o automático.</li>
                <li><strong>Forma:</strong> bordes compactos, estándar o redondeados.</li>
                <li><strong>Tipografía:</strong> la fuente general de la tienda.</li>
                <li><strong>Estilo de cabecera:</strong> clásica, compacta o centrada.</li>
                <li><strong>Plantilla:</strong> las opciones activas que ofrece la plataforma.</li>
            </ul>

            <h3>Cómo aplicarlo</h3>
            <ol class="help-steps">
                <li>Haz los cambios y observa la vista previa de la derecha.</li>
                <li>Comprueba cómo se leen los botones y los textos con los colores elegidos.</li>
                <li>Pulsa <strong>Guardar cambios</strong>.</li>
                <li>Abre <strong>Ver tienda</strong> para revisar la portada, una categoría y una ficha de producto.</li>
            </ol>

            <div class="help-callout help-callout--info">
                <strong>La vista previa usa el mismo estilo que la web</strong>
                <p>No es una maqueta independiente: el panel genera los mismos colores y formas que se publican en la tienda. Si algo se ve mal en la previa, se verá igual en la web.</p>
            </div>

            <h3>Plantillas disponibles</h3>
            <p>El selector muestra las plantillas activas de la plataforma. En esta versión, la plantilla I-Directo es la que tiene el conjunto completo de vistas. Las opciones Moderno y Minimal pueden reutilizar la base visual con pequeños ajustes; no deben presentarse como diseños completamente distintos.</p>

            <h3>Opciones avanzadas</h3>
            <p>Dentro de «Personalización avanzada» hay dos campos para personas con conocimientos técnicos: tokens JSON y CSS propio. Si no sabes qué significan, no los uses: un JSON inválido no se guarda y un CSS incorrecto puede estropear la presentación.</p>

            <h3>Errores frecuentes</h3>
            <ul>
                <li>Un color escrito a mano no es válido.</li>
                <li>El JSON de tokens está mal formado.</li>
                <li>Se ha cambiado la vista previa, pero no se ha pulsado Guardar.</li>
                <li>La plantilla elegida no cambia todas las vistas porque no está desarrollada por completo.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/diseno',
        'panel_label' => 'Ir a Diseno',
        'related' => ['diseno/logo-favicon', 'diseno/textos-seo', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'logo-favicon',
        'title'   => 'Logotipo y favicon',
        'summary' => 'Cómo subir la imagen de marca y el icono que aparece en la pestaña del navegador.',
        'keywords' => ['logo', 'logotipo', 'favicon', 'icono', 'subir imagen', 'marca', 'cabecera'],
        'location' => 'En Diseno. El logotipo está en la tarjeta «Plantilla»; el favicon tiene una tarjeta propia llamada «Favicon».',
        'body' => <<<'HTML'
            <h3>Logotipo</h3>
            <ol class="help-steps">
                <li>Entra en Diseno y baja hasta la tarjeta «Plantilla».</li>
                <li>Selecciona un archivo de imagen.</li>
                <li>Espera a que termine la subida y revisa la vista previa.</li>
                <li>Pulsa <strong>Guardar cambios</strong> al final del formulario.</li>
            </ol>
            <p>El logotipo aparece en la cabecera de la tienda y sustituye al nombre textual cuando está disponible. Se aceptan imágenes como PNG, JPG, WebP o SVG; la plataforma puede convertirlas a WebP para ahorrar peso.</p>

            <h3>Favicon</h3>
            <ol class="help-steps">
                <li>Busca la tarjeta «Favicon».</li>
                <li>Sube una imagen cuadrada, preferiblemente de 512 × 512 píxeles.</li>
                <li>Comprueba la vista previa y pulsa <strong>Guardar cambios</strong>.</li>
            </ol>
            <p>Si no subes uno, la tienda usa el favicon predeterminado de la plataforma. El botón <strong>Quitar mi favicon y usar el predeterminado</strong> elimina el propio y vuelve al global.</p>

            <h3>Límites prácticos</h3>
            <ul>
                <li>El favicon admite hasta 512 KB. Usa una imagen nítida y sencilla.</li>
                <li>Las imágenes generales, incluidas el logotipo y las de productos, admiten hasta 5 MB.</li>
                <li>El favicon no puede cambiarse desde la ficha de otra tienda ni afecta al favicon global de la plataforma.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>Subir no es guardar</strong>
                <p>La subida deja la imagen preparada, pero el cambio definitivo se aplica cuando guardas el formulario de Diseño. Si sales sin guardar, el archivo puede quedar subido pero no se usará.</p>
                <p>El logotipo no tiene un botón visible para eliminarlo: solo se puede sustituir. Tampoco existe una biblioteca de medios en este panel para administrar los archivos subidos.</p>
            </div>

            <h3>Si la imagen no se ve</h3>
            <ul>
                <li>Comprueba que el archivo no supera el límite.</li>
                <li>Usa un formato de imagen habitual; renombrar un archivo no cambia su formato real.</li>
                <li>Fuerza la recarga de la web o espera unos minutos: el navegador puede conservar el favicon anterior.</li>
                <li>Vuelve a guardar el formulario después de la subida.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/diseno',
        'panel_label' => 'Ir a Logotipo y favicon',
        'related' => ['diseno/identidad-visual', 'problemas/errores-imagenes', 'diseno/banners'],
    ],

    [
        'slug'    => 'textos-seo',
        'title'   => 'Textos y SEO: qué puedes editar y qué no',
        'summary' => 'Qué campos de texto afectan a la tienda, cuáles todavía no se muestran y por qué no hay editor de textos legales.',
        'keywords' => ['textos', 'eslogan', 'sobre nosotros', 'meta título', 'meta descripción', 'seo', 'aviso legal', 'privacidad', 'cookies', 'pie de página'],
        'location' => 'En Diseno, tarjetas «Textos y datos» y «Opciones y SEO». Algunos datos también están en Ajustes.',
        'body' => <<<'HTML'
            <h3>Qué se puede editar</h3>
            <ul>
                <li><strong>Nombre de la tienda:</strong> también aparece en Ajustes.</li>
                <li><strong>Eslogan:</strong> se usa en la portada y en algunos textos de la web.</li>
                <li><strong>Teléfono, WhatsApp, email y dirección:</strong> se muestran en el pie y en la página de contacto cuando existen.</li>
                <li><strong>Meta descripción:</strong> texto que pueden usar los buscadores para describir la tienda.</li>
                <li><strong>Mostrar precios</strong> y <strong>Permitir pedidos online:</strong> opciones generales de la tienda.</li>
            </ul>

            <h3>Qué se guarda pero no se muestra</h3>
            <ul>
                <li><strong>Sobre nosotros:</strong> el campo existe y guarda texto, pero no hay una página pública que lo pinte en esta versión.</li>
                <li><strong>Meta título:</strong> se guarda, pero el título de la pestaña se genera con el nombre de la tienda o de la página; no sustituye a ese título.</li>
                <li><strong>Mostrar precios:</strong> la opción existe, pero su efecto no está aplicado de forma uniforme en todas las pantallas. Comprueba la web antes de confiar en ella como control de visibilidad.</li>
                <li><strong>Permitir pedidos online:</strong> puede cerrar los pedidos de la tienda, pero no puede abrirlos si el modo global de pruebas está activo.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>No hay editor de páginas legales</strong>
                <p>El panel no permite crear ni editar páginas de aviso legal, privacidad, cookies, condiciones de venta o devoluciones. Tampoco existe un editor del pie de página. No documentamos esos textos como si estuvieran disponibles.</p>
                <p>Si necesitas publicarlos, la decisión y el contenido deben resolverse fuera de esta pantalla con quien corresponda. Esta ayuda no redacta textos legales ni fiscales.</p>
            </div>

            <h3>Contacto y páginas</h3>
            <p>La página pública de contacto muestra los datos que hayas rellenado, pero el formulario de contacto está desactivado con el aviso «próximamente». No prometas a tus clientes una respuesta por ese formulario.</p>

            <h3>Revisión recomendada</h3>
            <ol class="help-steps">
                <li>Cambia los textos y guarda.</li>
                <li>Abre la portada, el pie y la página de contacto.</li>
                <li>Comprueba que el nombre visible, el teléfono y el email son correctos.</li>
                <li>Si un campo no aparece, revisa si está entre los que todavía no se pintan.</li>
            </ol>
        HTML,
        'panel_path' => 'panel/diseno',
        'panel_label' => 'Ir a Textos y SEO',
        'related' => ['configuracion/datos-contacto-iva', 'problemas/funciones-no-disponibles', 'primeros-pasos/crear-tienda'],
    ],

    [
        'slug'    => 'banners',
        'title'   => 'Configurar banners',
        'summary' => 'Crea una imagen destacada para la portada, decide cuándo se muestra y revisa el recorte antes de guardar.',
        'keywords' => ['banner', 'portada', 'hero', 'imagen', 'slider', 'programar', 'fecha', 'enlace', 'aviso'],
        'location' => 'En Banners, con «Nuevo banner». Los banners se editan en una pantalla con contenido, imagen, publicación, velo y vista previa.',
        'body' => <<<'HTML'
            <h3>Crear un banner</h3>
            <ol class="help-steps">
                <li>Entra en Banners y pulsa <strong>Nuevo banner</strong>.</li>
                <li>Escribe un nombre interno para reconocerlo en el panel.</li>
                <li>Rellena el título y el subtítulo que quieres mostrar encima de la imagen.</li>
                <li>Escribe un texto alternativo que describa la imagen si aporta información.</li>
                <li>Añade un enlace de botón si quieres que el banner lleve a otra pantalla.</li>
                <li>Sube la imagen.</li>
                <li>Elige estado, fecha, orden e intensidad del velo.</li>
                <li>Revisa la previsualización de escritorio y móvil y pulsa <strong>Crear banner</strong>.</li>
            </ol>

            <h3>Estados</h3>
            <ul>
                <li><strong>Borrador:</strong> se guarda, pero no se publica.</li>
                <li><strong>Programado:</strong> necesita fecha de inicio; se mostrará cuando llegue.</li>
                <li><strong>Activo:</strong> intenta mostrarse dentro de las fechas indicadas.</li>
                <li><strong>Inactivo:</strong> no se muestra.</li>
                <li><strong>Caducado:</strong> es un estado calculado cuando la fecha de fin ya pasó.</li>
            </ul>

            <h3>Imagen y recorte</h3>
            <ul>
                <li>El límite de subida para banners es de 8 MB.</li>
                <li>La medida recomendada para la portada es 1920 × 600 píxeles (proporción 16:5).</li>
                <li>La imagen se recorta para cubrir el espacio. Mantén el texto o el producto importante dentro de la zona central.</li>
                <li>La previsualización muestra el recorte real en escritorio y móvil.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>Solo se muestra la posición Portada (hero)</strong>
                <p>En esta versión no se pintan banners en el pie, en el medio de la página ni en otras posiciones. Si un banner antiguo tiene una posición no disponible, el panel te pedirá confirmar el cambio a Portada.</p>
            </div>

            <h3>Borrar o sustituir</h3>
            <p>Al borrar un banner se elimina su ficha, pero la imagen se conserva en el almacenamiento. No hay una biblioteca de medios visible en el panel para gestionar esas imágenes.</p>

            <h3>Errores frecuentes</h3>
            <ul>
                <li>Has alcanzado el límite de banners del plan.</li>
                <li>La fecha de fin es anterior o igual a la de inicio.</li>
                <li>El estado Programado no tiene fecha de inicio.</li>
                <li>El enlace no empieza por / ni es una dirección http/https.</li>
                <li>La imagen supera el límite o no es una imagen válida.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/banners',
        'panel_label' => 'Ir a Banners',
        'related' => ['diseno/avisos', 'problemas/errores-imagenes', 'menu/editar-arbol'],
    ],

    [
        'slug'    => 'avisos',
        'title'   => 'Configurar avisos',
        'summary' => 'Publica un mensaje breve en la portada, con estilo, icono, badge, imagen y fechas opcionales.',
        'keywords' => ['aviso', 'promoción', 'mensaje', 'barra', 'badge', 'icono', 'programar', 'destacar', 'portada'],
        'location' => 'En Avisos, con «Nuevo aviso». La previsualización está al final del formulario.',
        'body' => <<<'HTML'
            <h3>Crear un aviso</h3>
            <ol class="help-steps">
                <li>Entra en Avisos y pulsa <strong>Nuevo aviso</strong>.</li>
                <li>Elige el tipo: Información, Promoción o Aviso importante.</li>
                <li>Elige la variante visual, el tamaño del texto y, si quieres, un icono.</li>
                <li>Escribe el badge y el mensaje. El mensaje admite hasta 500 caracteres.</li>
                <li>Si lo necesitas, sube una imagen opcional.</li>
                <li>Decide si el aviso está visible y, si quieres, indica fecha de inicio y fin.</li>
                <li>Revisa la previsualización y pulsa <strong>Crear aviso</strong>.</li>
            </ol>

            <h3>Qué significan las fechas</h3>
            <ul>
                <li>Sin fecha de inicio, el aviso se muestra al guardarlo si está visible.</li>
                <li>Con una fecha futura, aparece como programado.</li>
                <li>Sin fecha de fin, permanece visible hasta que lo ocultes.</li>
                <li>Al pasar la fecha de fin, queda caducado.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>Un aviso no crea descuentos</strong>
                <p>El sistema no genera cupones, rebajas ni condiciones comerciales a partir del texto. El mensaje se publica tal como lo escribes. Si anuncias una promoción, debes gestionar sus condiciones y su precio por otra vía.</p>
                <p>Tampoco envía el aviso por email a los clientes.</p>
            </div>

            <h3>Dónde se ve</h3>
            <p>Los avisos se muestran en la portada de la tienda. Si hay varios activos, pueden aparecer juntos. No se ha implementado un aviso global en todas las páginas ni un modal independiente en esta versión.</p>

            <h3>Cuota y errores</h3>
            <ul>
                <li>Cada plan tiene un número máximo de avisos; el panel lo muestra en la cabecera.</li>
                <li>El mensaje es obligatorio.</li>
                <li>La fecha de fin debe ser posterior a la de inicio.</li>
                <li>La imagen debe ser válida y no superar el límite.</li>
                <li>Si el aviso está oculto, no se muestra aunque las fechas sean correctas.</li>
            </ul>

            <p>Al borrar un aviso se conserva su imagen. No existe una biblioteca de medios en el panel para eliminarla.</p>
        HTML,
        'panel_path' => 'panel/avisos',
        'panel_label' => 'Ir a Avisos',
        'related' => ['diseno/banners', 'problemas/errores-imagenes', 'problemas/funciones-no-disponibles'],
    ],
];
