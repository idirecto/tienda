# PROMPT — Petición de trabajo

> **Este fichero lo escribe el dueño del proyecto.**
> El agente lo lee **al empezar** y lo actualiza **al terminar**.
>
> Cómo usarlo: escribe debajo de «Petición actual» lo que quieras, en lenguaje
> normal. Cuando el agente lo termine, moverá la petición al historial marcada
> como hecha y dejará esta zona limpia para la siguiente.

---

## Petición actual

<!-- ESCRIBE AQUÍ. Si está vacío, no hay nada pendiente. -->

_(vacío — sin petición pendiente)_

---

## Historial de peticiones

Cada petición terminada se anota aquí con la fecha, qué se pidió y qué se hizo.

### 2026-10-01 · Pedidos en el panel y envío por líneas a la tabla `pedidos` de idirecto (petición por chat)

**Pedido:** «en el panel de la tienda, crea un listado de pedidos, tantos activos,
ya facturados, borrados, todos los estados y la posibilidad de verlos; y que se
envíe a la tabla `pedidos` de idirecto eligiendo por línea de los pedidos, por
ejemplo si un pedido tiene 4 líneas de productos, se puede hacer un pedido hacia
idirecto (tabla `pedidos`) con los datos de la tienda que maneja la web y elegir
productos del pedido hecho por el cliente, por ejemplo elegir dos de los productos
para enviar a idirecto».

**Decisiones confirmadas antes de empezar:** (1) los pedidos se guardan en tablas
propias (`mt_orders`/`mt_order_items`), (2) cada tienda se enlaza con su cuenta del
mayorista desde Ajustes, y (3) el envío **escribe de verdad** en
`pedidos`/`pedidos_det`.

**Hecho:** módulo de pedidos completo en el panel (listado con pestañas
Todos/Activos/Borradores/Facturados/Cancelados/Borrados, buscador, paginación y
papelera restaurable; ficha con cliente, entrega, líneas y cambio de estado; alta
manual y añadir/quitar líneas con buscador de productos). El envío al mayorista va
**por línea** (`OrderGateway`): crea `pedidos_addr` + `pedidos` + `pedidos_det`
replicando el flujo web de idirecto (`web = 1`, `estado = NULL`,
`referencia = TIENDA-<slug>-<código>`), con la tarifa de la tienda
(`mt_stores.id_margen`), vista previa de importes antes de confirmar y reserva de
stock opcional. Cada línea enviada queda marcada, así que un pedido de 4 líneas se
puede mandar en dos veces (2+2) sin repetir nada; los productos propios no se
envían. Migración `003`, `config/idirecto.php` y claves `IDIRECTO_*` en el
`.env.example`. `verify.php` 62 → **78**. Detalle en `CHANGELOG.md`.

**Nota:** queda un pedido de ejemplo (`P26-00001`, cliente «Cliente de prueba») en
la tienda demo para ver la pantalla con datos, y la tienda demo **no** tiene cuenta
de idirecto configurada a propósito (hay que poner el id de cuenta en Ajustes).

### 2026-10-01 · Ver 20 productos por página en los listados (petición por chat)

**Pedido:** «en los listados, que aparezcan más productos; actualmente veo 12 y
quiero ver 20».

**Hecho:** `CATALOG_PER_PAGE` 12 → 20 (`.env`, `.env.example` y el valor por
defecto). Los destacados de la portada se separan en su propia clave
(`CATALOG_HOME_FEATURED=12`) para que aumentar los listados no alargue la
portada. Comprobado con HTTP real en `/`, `/catalogo`, categoría, subcategoría,
búsqueda y página 2 (20 tarjetas por página). `verify.php` 60 → 62.

### 2026-10-01 · Interfaz moderna white-label: design tokens, portada, tarjetas y filtros (petición por chat)

**Pedido:** refactorizar la plantilla como Desarrollador Frontend Senior /
Arquitecto UI-UX (referencias: elegancia y minimalismo de Scan.co.uk + potencia
visual y toque gaming de Caseking) siendo **totalmente agnóstica** a colores y
marca, porque es una plantilla blanca multi-tienda:

1. **Sistema de diseño dinámico**: todos los colores de marca (primario, CTAs,
   secundario, fondos, modo claro y **modo oscuro nativo**, texto y bordes) como
   variables CSS, inyectables desde el backend o un fichero de configuración
   global, para que cada tienda cambie su identidad en segundos sin tocar código.
2. **Portada y slider principal**: banner full-width responsive con tipografía
   contundente, subtítulo y CTAs con hover suave; secciones de acceso rápido en
   grid para Tarjetas Gráficas, Portátiles Ultraligeros, Componentes PC,
   Periféricos y Setup Gaming.
3. **Componentes y UX**: fichas de producto minimalistas con transiciones,
   etiquetas de stock dinámicas y visualización rápida de especificaciones clave
   (tipo Scan); filtros avanzados por chipset, memoria, socket y precio;
   microinteracciones cuidadas en botones, tarjetas e iconos.
4. **Rendimiento y accesibilidad**: código modular, semántico (HTML5), con carga
   diferida, mobile-first y fluido en móvil, tablet y ultra-ancho.

**Hecho:** sistema de diseño con tokens (`config/appearance.php` +
`Core/Appearance` + migración `002`), modo claro/oscuro/auto con conmutador,
5 presets y **vista previa en vivo** en el panel; `shop.css` reescrito sin un solo
color literal; portada con slider accesible, franja de garantías y grid de accesos
rápidos; tarjetas con chips de especificaciones y etiqueta de stock dinámica;
filtros avanzados (socket, gráfica, memoria, formato, almacenamiento, marca y
precio) como enlaces con URL propia, chips activos, orden y panel lateral en móvil.
De paso, el catálogo pasó de ~3 s a ~20 ms (semijoin de stock + marca por lote) y
`verify.php` de 33 a **60** comprobaciones. Detalle en `CHANGELOG.md`.

**Nota para el dueño:** dos decisiones quedan pendientes por su parte y están
documentadas en `STATE.md` (pendientes 1 y 5): la tarifa de precios por tienda y
materializar el stock válido en una tabla `mt_` para eliminar el pico periódico
del contador y permitir orden por precio en todo el catálogo.

### 2026-10-01 · Banners de hasta 8 MB con buena calidad (petición por chat)

**Pedido:** permitir banners de hasta 8 MB, pero que se compriman en buena
calidad.

**Hecho:** límites **por tipo** en `MediaRules` (banners 8 MB y calidad 86; el
resto 5 MB y 82, configurable en `storage.types`), aviso en el panel antes de
subir, y los límites de PHP ajustados en `.htaccess` (venía con 2 MB, así que
ningún fichero grande llegaba) con aviso equivalente para PHP-FPM en el script
de nginx. De paso: los ficheros que superan `post_max_size` ya no confunden con
«token inválido» (la comprobación va antes del CSRF) y el CSRF responde 403 en
vez de 419 (que Apache convertía en 500). `verify.php` 32 → 33.

### 2026-10-01 · Imágenes de cada tienda: carpetas `tienda_<tipo>`, id en el nombre y WebP (petición por chat)

**Pedido:** que las imágenes que suben las tiendas (banners, productos y demás)
se guarden en carpetas separadas con el prefijo de la tienda (tipo
`tienda_banner`), con el **id de la tienda en el nombre** del fichero para poder
identificarlas/borrarlas, y que se suban con la mejor calidad pero comprimidas
(un JPG, a WebP o al formato más ligero).

**Hecho:** clave única para S3 y local
`tenants/tienda_<tipo>/<id>_<tipo>_<fecha>-<aleatorio>.webp` (`StorageKey`);
optimización a WebP con GD (`ImageOptimizer`, calidad 82, límite 2560 px,
orientación EXIF corregida, EXIF eliminado; SVG y GIF animado se respetan);
subida centralizada en `MediaUploader`; el panel informa del ahorro. De paso se
declararon los MIME `.webp`/`.avif` en `.htaccess` porque este Apache servía el
WebP sin `Content-Type`. `verify.php` 29 → 32 comprobaciones.

### 2026-10-01 · «Error interno» en la portada (petición por chat)

**Pedido:** revisar por qué `http://local.tienda/` solo mostraba «Error interno».

**Hecho:** no era el código sino los permisos: el proceso de Apache
(`www-data`) no podía leer `.env` (estaba en `pablo:pablo 640`), así que la app
se quedaba sin credenciales y sin `APP_DEBUG`, y tampoco podía escribir el log.
Arreglado con ACL (`setfacl`) sin `sudo`; además el log propio solo se activa si
`storage/logs` es escribible y ahora la línea del log avisa cuando `.env` no es
legible. La base de datos ya estaba completa: la web responde 200 y
`php tools/verify.php` → **TODO OK (29)**.

### 2026-09-30 · Funcionar en nginx (valduran.com) además de en Apache (petición por chat)

**Pedido:** que la web corra tanto en Apache como en nginx «según detecte el
servidor», para publicarla en `valduran.com` (y poder cambiar de dominio luego).

**Hecho:** `app/Core/Server.php` detecta el servidor y resuelve esquema, host y
ruta pública de `/public` (también detrás de proxy con `X-Forwarded-Proto`);
plantilla `deploy/nginx-site.conf.tpl` + instalador idempotente
`deploy/setup-nginx-domain.sh [dominio]` (por defecto `valduran.com`); el `.env`
de producción está documentado. Verificado con un nginx real en un puerto alto.

**Entorno:** la BD del `.env` perdió el catálogo y las tablas `mt_`; por
indicación del dueño **no se tocó**.

### 2026-09-29 · Menú de categorías del catálogo, basado en PuntoByZE (petición por chat)

**Pedido:** mejorar el diseño del menú de productos (el «catálogo») tomando como
base las categorías y subcategorías que usa PuntoByZE.

**Hecho:** megamenú en la cabecera (categorías + subcategorías con stock, árbol
cacheado) estilo PuntoByZE, con versión móvil a pantalla completa y botón atrás;
filtro `?subcat=` operativo y subcategorías en el filtro lateral del catálogo,
migas de pan y títulos coherentes. Se añadieron 2 comprobaciones a `verify.php`.

### 2026-09-29 · Layout ancho en monitores grandes (petición hecha por chat)

**Pedido:** ampliar el contenedor principal para aprovechar el ancho en monitores
grandes, inspirado en PcComponentes; adaptar grillas (listados, filtros laterales
y ficha de producto) y mantener la responsividad en tablet y móvil.

**Hecho:** `.container` fluido de 1180 a 1800 px; filtros del catálogo en columna
lateral sticky en escritorio; grillas y ficha con proporciones ajustadas; además
se corrigió un desbordamiento horizontal previo de la cabecera en móvil.

### 2026-09-29 · Documentación interna para agentes

**Pedido:** crear un sistema para que otro agente entienda el proyecto y, con un
prompt del dueño, pueda mejorarlo; que esa documentación no sea accesible por web.

**Hecho:** directorio `.agents/` con `PROMPT.md` (este fichero), `PROJECT.md`,
`STATE.md`, `CHANGELOG.md`, el Agent Skill `tienda-plataforma` y scripts de
instalación y comprobación de privacidad. Blindaje en tres capas (`.htaccess`,
`index.php`, VirtualHost) y verificación automática.

### 2026-09-29 · Listados con imagen de talla `l`

**Pedido:** en los listados usar la imagen con prefijo `l` en vez de `c`.

**Hecho:** `CATALOG_CARD_IMAGE_SIZE` (por defecto `l`); catálogo, búsquedas y
destacados de portada usan `l` (~7 KB) en lugar de `c` (~3 KB).

### 2026-09-29 · Ficha de producto igual que idirecto

**Pedido:** que la ficha se viera como la de idirecto, con su carrusel y sus
especificaciones.

**Hecho:** estilos tomados del CSS real de idirecto; carrusel horizontal
navegado por miniaturas (idirecto no usa flechas); especificaciones en tarjetas
agrupadas con el valor a la derecha.

### 2026-09-29 · URLs SEO, stock y dominio local

**Pedido:** URLs con el nombre del producto, mostrar solo productos con stock y
poder entrar desde el navegador en `http://local.tienda`.

**Hecho:** `/producto/{slug}/{id}` con redirección 301; catálogo filtrado por
stock (39.437 de 233.773 productos); VirtualHost de pruebas y arreglo de la
cookie de sesión de PHP 8.4.

---

## Plantilla para una petición nueva

Copiar y pegar, rellenando lo que sepas:

```
## Petición actual

Qué quiero:
...

Dónde se ve (URL de ejemplo, si aplica):
...

Cómo sabré que está bien:
...

Lo que NO quiero que cambie:
...
```
