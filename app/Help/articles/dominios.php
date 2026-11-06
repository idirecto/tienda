<?php

declare(strict_types=1);

/**
 * Articulos de Dominios.
 *
 * @return array<int, array<string, mixed>>
 */
return [
    [
        'slug'    => 'subdominio',
        'title'   => 'Cambiar el subdominio de la tienda',
        'summary' => 'Cómo elegir la dirección gratuita de la plataforma y comprobar que carga correctamente.',
        'keywords' => ['subdominio', 'dirección', 'url', 'dominio plataforma', 'cambiar dirección', 'comprobar que carga'],
        'location' => 'En Dominios, tarjeta superior «Tu direccion web (subdominio de la plataforma)».',
        'body' => <<<'HTML'
            <p>Toda tienda tiene una dirección gratuita formada por un <strong>subdominio</strong> y el dominio de la plataforma. Por ejemplo: <code>mi-tienda.dominio-de-la-plataforma</code>.</p>
            <p>El <strong>subdominio</strong> es la primera parte, <code>mi-tienda</code>. No es necesario comprar un dominio para empezar.</p>

            <h3>Cambiar la dirección</h3>
            <ol class="help-steps">
                <li>Entra en Dominios.</li>
                <li>Escribe el nuevo subdominio en el campo correspondiente.</li>
                <li>Pulsa <strong>Guardar subdominio</strong>.</li>
                <li>Pulsa <strong>Comprobar que carga</strong>.</li>
                <li>Espera el resultado de DNS, HTTPS y certificado.</li>
            </ol>

            <h3>Reglas del subdominio</h3>
            <ul>
                <li>Debe tener entre 3 y 40 caracteres.</li>
                <li>Solo admite letras, números y guiones.</li>
                <li>No puede empezar ni terminar por guion.</li>
                <li>No puede estar reservado por la plataforma ni usarlo otra tienda.</li>
            </ul>

            <div class="help-callout help-callout--warning">
                <strong>Cambiar la dirección tiene consecuencias</strong>
                <p>La tienda pasará a publicarse en la nueva dirección. La dirección anterior deja de ser el subdominio de la tienda. Si has compartido enlaces o impresos con la dirección antigua, tenlo en cuenta antes de cambiarla.</p>
            </div>

            <h3>Qué comprueba «Comprobar que carga»</h3>
            <ul>
                <li>Si el DNS del subdominio apunta a la plataforma.</li>
                <li>Si la web responde por HTTPS.</li>
                <li>Si el certificado de seguridad cubre ese subdominio.</li>
            </ul>
            <p>El DNS comodín y el certificado de la plataforma se configuran en el servidor, no en tu registrador. Si la comprobación detecta un problema de certificado, no es algo que puedas arreglar desde este campo: debe revisarlo quien administra la plataforma.</p>

            <h3>Errores frecuentes</h3>
            <ul>
                <li>El subdominio ya está ocupado.</li>
                <li>Se ha escrito el nombre completo con puntos en lugar de la etiqueta.</li>
                <li>Se ha usado un guion al principio o al final.</li>
                <li>El DNS todavía no ha propagado.</li>
                <li>El certificado no cubre el subdominio.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/dominios',
        'panel_label' => 'Ir a Dominios',
        'related' => ['dominios/dominio-propio', 'problemas/no-veo-cambios', 'problemas/funciones-no-disponibles'],
    ],

    [
        'slug'    => 'dominio-propio',
        'title'   => 'Conectar y verificar un dominio propio',
        'summary' => 'Cómo enlazar un dominio comprado fuera de la plataforma y qué significa verificar los registros DNS.',
        'keywords' => ['dominio propio', 'dns', 'registro a', 'cname', 'verificar dominio', 'propagación', 'registrador', 'https'],
        'location' => 'En Dominios, tarjeta «Tus dominios propios» y formulario «Conectar un dominio propio».',
        'body' => <<<'HTML'
            <p>Un <strong>dominio propio</strong> es una dirección como <code>mitienda.com</code> que compras y gestionas en un proveedor externo. El panel no compra dominios ni cambia los registros DNS: solo comprueba si apuntan a la plataforma.</p>

            <h3>Antes de empezar</h3>
            <ul>
                <li>Debes tener el dominio comprado en un registrador.</li>
                <li>Debes poder editar sus registros DNS.</li>
                <li>No uses el dominio base de la plataforma ni uno de sus subdominios en este formulario.</li>
            </ul>

            <h3>Añadir el dominio</h3>
            <ol class="help-steps">
                <li>Escribe el dominio sin <code>https://</code>.</li>
                <li>Elige el método de verificación: registro A, CNAME o ambos.</li>
                <li>Pulsa <strong>Guardar dominio</strong>.</li>
                <li>Anota los valores que muestra la ficha del dominio.</li>
                <li>Crea esos registros en el panel de tu registrador.</li>
                <li>Espera la propagación.</li>
                <li>Vuelve a esta pantalla y pulsa <strong>Verificar DNS</strong>.</li>
            </ol>

            <h3>Qué es el DNS</h3>
            <p>El DNS es el sistema que indica a Internet qué servidor corresponde a un nombre. Si los registros A o CNAME no coinciden con los valores esperados, el dominio todavía no puede cargar la tienda.</p>

            <h3>Estados del dominio</h3>
            <ul>
                <li><strong>Pendiente:</strong> añadido, pero todavía no verificado.</li>
                <li><strong>Verificado:</strong> los registros DNS apuntan al destino esperado.</li>
                <li><strong>Error:</strong> los registros no existen o no coinciden.</li>
            </ul>
            <p>La propagación puede tardar desde unos minutos hasta 24-48 horas. No cambies los servidores DNS (NS) si las instrucciones del panel solo piden un registro A o CNAME.</p>

            <div class="help-callout help-callout--warning">
                <strong>Este panel no configura el certificado del dominio propio</strong>
                <p>La verificación disponible en esta pantalla es de DNS. No promete un certificado HTTPS automático para dominios propios. Si el navegador muestra un aviso de seguridad, debe revisarlo quien administra el servidor de la plataforma.</p>
            </div>

            <h3>Errores frecuentes</h3>
            <ul>
                <li>El dominio no tiene un formato válido.</li>
                <li>Ya está registrado en la plataforma.</li>
                <li>Los registros están creados, pero apuntan a otro destino.</li>
                <li>El cambio DNS todavía no se ha propagado.</li>
                <li>Se ha eliminado el dominio correcto o se ha modificado en el registrador.</li>
            </ul>
        HTML,
        'panel_path' => 'panel/dominios',
        'panel_label' => 'Ir a Dominios',
        'related' => ['dominios/subdominio', 'problemas/no-veo-cambios', 'problemas/funciones-no-disponibles'],
    ],
];
