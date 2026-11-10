<?php

declare(strict_types=1);

/**
 * Articulos de uso de la plataforma (solo rol platform).
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'menu-plataforma',
        'title'   => 'Menú de plataforma: árbol compartido, visibilidad y publicación',
        'summary' => 'Cómo administra la plataforma el menú común y qué efectos tiene publicar o compartir un árbol.',
        'keywords' => ['plataforma', 'menú compartido', 'visibilidad', 'publicar todas', 'compartir árbol', 'niveles de cliente', 'rol platform'],
        'location' => 'Solo aparece en el menú del panel con el rol «platform», en «Menu plataforma».',
        'body' => <<<'HTML'
            <div class="help-callout help-callout--warning">
                <strong>Solo para administradores de la plataforma</strong>
                <p>Esta sección no está disponible para el propietario ni para los empleados de una tienda. Las rutas del controlador exigen el rol <code>platform</code>; el resto de usuarios recibe un acceso denegado.</p>
            </div>

            <h3>Qué se administra aquí</h3>
            <ul>
                <li>El árbol de menú compartido por todas las tiendas.</li>
                <li>Los nodos propios de una tienda concreta.</li>
                <li>Qué tiendas ven cada nodo compartido.</li>
                <li>El nivel mínimo de cliente de cada nodo.</li>
                <li>La publicación de una tienda o de todas.</li>
            </ul>

            <h3>Propiedad y visibilidad</h3>
            <ul>
                <li><strong>Compartido:</strong> el nodo no tiene tienda asignada y puede verse en todas, según su visibilidad.</li>
                <li><strong>Solo una tienda:</strong> el nodo pertenece a esa tienda.</li>
                <li><strong>Todas las tiendas:</strong> no se excluye ninguna.</li>
                <li><strong>Solo las marcadas:</strong> se muestra únicamente en las tiendas seleccionadas.</li>
                <li><strong>Todas menos las marcadas:</strong> se oculta en las tiendas seleccionadas.</li>
            </ul>

            <h3>Nivel de cliente</h3>
            <p>El nivel mínimo procede de las categorías de cliente del mayorista. Un nodo con nivel mínimo solo se muestra a tiendas cuyo nivel sea igual o superior. Si una tienda no tiene nivel asignado, puede dejar de ver esos nodos.</p>

            <h3>Publicar</h3>
            <ol class="help-steps">
                <li>Elige la tienda en el selector superior.</li>
                <li>Revisa la vista previa y los avisos de esa tienda.</li>
                <li>Pulsa <strong>Publicar en esta tienda</strong> para aplicar solo a esa tienda.</li>
                <li>Usa <strong>Publicar en todas las tiendas</strong> solo si estás seguro del alcance global.</li>
            </ol>

            <h3>Compartir el árbol de una tienda</h3>
            <p>La acción «Compartir el árbol de esta tienda con todas» convierte el árbol propio de esa tienda en compartido. Es una acción de alcance global y debe revisarse con cuidado antes de confirmar.</p>

            <div class="help-callout help-callout--warning">
                <strong>Alcance global</strong>
                <p>Una publicación o un cambio de visibilidad puede afectar a varias tiendas. Comprueba la tienda seleccionada, la propiedad de los nodos y los niveles antes de guardar o publicar. No uses esta pantalla para corregir una tienda concreta si la acción afecta a todas.</p>
            </div>

            <h3>Qué no hace esta sección</h3>
            <ul>
                <li>No modifica productos ni stock.</li>
                <li>No cambia la configuración de una tienda.</li>
                <li>No elimina tiendas.</li>
                <li>No sustituye a los permisos de una tienda: las decisiones de la tienda sobre categorías visibles se aplican después.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/plataforma/menu',
        'panel_label' => 'Ir a Menu plataforma',
        'related' => ['menu/estilos-y-publicacion', 'menu/editar-arbol', 'configuracion/cuenta-idirecto'],
    ],
];
