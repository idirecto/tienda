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

### 2026-10-08 · Fuera el botón «Mi panel» y las credenciales demo del login (petición por chat)

**Pedido:** «Retira el botón que lleva a /panel en la web y los datos de usuario por
defecto que aparece en el login con este mensaje: Demo: `admin@demo.test` / `demo1234`
y por supuesto habría que borrar esos datos de la base de datos si es que están en la
base de datos».

**Decisión confirmada:** borrar **solo el usuario demo**, no la tienda demo (la tienda
`idirecto-demo` sigue como escaparate y su contenido de ejemplo).

**Hecho:** se quita el botón «Mi panel» de `layouts/shop.php` y sus reglas `.btn-panel`
de `shop.css`; el login (`panel/login.php`) deja de traer los campos rellenos y
desaparece el aviso «Demo: …» (queda el enlace a `/registro`); se borra la fila
`admin@demo.test` de `mt_store_users` (se conserva `plataforma@local.test`); la semilla
`001_seed.sql` ya no crea ese usuario, para que una instalación nueva no recupere
credenciales por defecto; y `README.md`, `PROJECT.md`, `deploy/setup-local-domain.sh` y
la skill dejan de anunciarlas. `verify.php` 250 → **255** (TODO OK) con 5 comprobaciones
de regresión; probado en HTTP real (la portada y el login no muestran credenciales y
entrar con `admin@demo.test / demo1234` responde «Credenciales incorrectas»). Detalle en
`CHANGELOG.md` y `STATE.md`.

### 2026-10-05 · Catálogo: 40 productos por página y tarjeta entera enlazada (petición por chat)

**Pedido:** como desarrollador senior full-stack de e-commerce, rendimiento web, catálogos,
paginación, UX/UI responsive, accesibilidad y SEO técnico: (1) aumentar y optimizar el
número de productos de las páginas de categorías y subcategorías (objetivo: ~40 en
escritorio, 24 en tablet, 12-16 en móvil) sin cargar todo de golpe y con paginación real y
rastreable; y (2) que **toda la zona visual y superior de cada tarjeta** (imagen, marca,
nombre, características, «Ver ficha») lleve a la ficha, sin enlaces anidados y sin que
«Agregar al carrito» u otras acciones naveguen. Página de ejemplo:
`https://valduran.com/telefonia/moviles-smartphone` (448 productos). Sin tocar carrito,
checkout, menú, usuarios ni pedidos. Auditoría antes de tocar nada y desarrollo por fases.

**Auditoría (solo lectura):** la paginación ya existía (tradicional, `LIMIT/OFFSET`, rutas
`/page/N` con 301 desde query); el `12` venía del **`.env` de producción** (en desarrollo
eran 20), el fallback del modelo era 12 y el buscador en vivo usaba una clave inexistente;
la tarjeta ya tenía enlace estirado pero la imagen (`z-index: 1`) se comía el clic —
confirmado con `elementFromPoint` y clic real por CDP—; los productos propios no tienen
ficha. Índices existentes suficientes (tablas del mayorista de solo lectura).

**Decisión confirmada:** 40 productos en el **servidor para todas las pantallas** (las
columnas sí son responsive, para no duplicar URLs) y dejar las tarjetas de productos
propios como estaban (no tienen ficha).

**Hecho:** `CATALOG_PER_PAGE=40` (+ `CATALOG_SEARCH_PER_PAGE=16`) en `.env`, `.env.example`
y `config/catalog.php`, tope 120 en `Catalog::paginate()`; partial de paginación común
(`_pagination.php`) con Anterior/Siguiente, `…`, `aria-current`, `rel=prev|next` y «Página X
de Y · N productos», conservando filtros/orden/búsqueda, y 404 para páginas fuera de rango;
tarjeta entera clicable con un solo `<a>` (imagen, marca, specs y precio) manteniendo el
botón de carrito independiente; primera fila de imágenes inmediata y el resto en diferido.
`verify.php` 244 → **250** (TODO OK), probado con clics reales por CDP en móvil y escritorio
y responsive 320→1920. **Pendiente de despliegue:** poner `CATALOG_PER_PAGE=40` en el `.env`
de producción. Detalle en `CHANGELOG.md` y `STATE.md`.

### 2026-10-05 · Carrito: botón «Agregar al carrito» y mini-carrito lateral (petición por chat)

**Pedido:** actuar como desarrollador senior full-stack de e-commerce y mejorar el botón
«Agregar al carrito» y crear un **mini-carrito lateral derecho** con una experiencia tipo
PcComponentes pero con el diseño de Valduran, **reutilizando el carrito real** (sin carrito
paralelo), con confirmación visual, validación de stock, cierre con X/Escape/clic fuera,
contenido con imagen/nombre/SKU/precio/cantidad/subtotal/quitar, responsive de escritorio a
móvil (100 %) y sin tocar menú, categorías, productos ni checkout.

**Decisiones confirmadas:** (1) arreglar el stock real del catálogo en el carrito,
manteniendo que un producto propio con `stock = 0` signifique «la tienda no lleva stock»;
(2) el icono del carrito de la cabecera abre el mini-carrito con JS; (3) sin JS sigue
siendo un enlace a `/carrito`; (4) responsive completo con `100dvh` y safe-areas;
(5) CTA «Ver artículos del carrito» en escritorio y «Ver carrito» en móvil.

**Auditoría previa (entregada antes de tocar nada):** carrito en `Core/Cart`
(`$_SESSION['cart'][$storeId]`, claves `c<id>`/`o<id>`), sin tabla ni localStorage; alta por
`POST /carrito/anadir` (redirect + flash); contador en el layout; ruta real **`/carrito`**;
sin sistema de variantes; y el hueco de que `Catalog::find()` no traía `stock_total`.

**Qué se hizo:** `Catalog::stockTotal()` + validación de stock en `Cart` (añadir y
actualizar, con avisos); botón con el texto exacto **«Agregar al carrito»** y estados de
carga/confirmación en ficha y tarjetas; los endpoints del carrito detectan AJAX y responden
JSON con el partial `_mini_cart.php` (sin JS, POST y redirección de siempre); ruta de solo
lectura `GET /carrito/mini`; mini-carrito lateral accesible y responsive con líneas
+/-/cantidad editable/quitar sincronizadas con el backend, contador sin recargar, aviso
flotante y enlace de respaldo a `/carrito`. `verify.php` 232 → **244** (TODO OK), probado en
Chrome real por CDP. Detalle en `CHANGELOG.md` y `STATE.md`.

### 2026-10-04 · Un solo botón en el buscador (petición por chat)

**Pedido:** «sobre el buscador se dibujan dos botones para cerrar,
`shop-search-clear` y `shop-search-close`; deja uno solo y que al pulsarlo se cierre
toda la parte del buscador».

**Qué pasaba:** eran dos aspas iguales pegadas: una borraba el texto (y solo aparecía
al escribir) y la otra cerraba, así que parecían dos botones de cerrar.

**Qué se hizo:** se quitó `shop-search-clear` (layout, JS y CSS) y queda **un único
botón** que cierra todo el panel; para vaciar el texto está la X nativa del campo. Se
conserva la búsqueda al cerrar y `verify.php` añade una comprobación para que no vuelva
el segundo botón (231 → **232**, TODO OK). Probado en Chrome real por CDP a 1600 y
390 px: un solo botón de 40×40 y el clic oculta el overlay, quita `is-open` y el bloqueo
del body, sin errores de JavaScript.

### 2026-10-04 · Visibilidad del buscador: rejilla adaptativa en monitores grandes (petición por chat)

**Pedido:** «Actúa como arquitecto senior full-stack especializado en comercio
electrónico, menús jerárquicos, sistemas multi-tienda y aplicaciones B2B. Mejora la
visibilidad del buscador: en monitores grandes solo aparece un producto por línea,
cuando en `/var/www/html/puntobyze` se va adaptando de acuerdo al tamaño de la
pantalla; si es un teléfono sí muestra un resultado por línea, pero en un monitor
grande muestra más».

**Diagnóstico (Chrome real por CDP):** no era el CSS ni el JS, sino que
`StorefrontController::searchLive()` devolvía las tarjetas sueltas, sin el contenedor
`.grid-products` que el CSS ya esperaba; al ser bloques flex, caían **una por línea en
cualquier ancho** (12 resultados → 12 filas medidas a 1600, 1280, 900 y 390 px).

**Qué se hizo:** `/buscar/live` envuelve sus tarjetas en `.grid.grid-products` (una
rejilla por grupo: «Productos de la tienda» y «Catalogo»); el panel usa
`repeat(auto-fill, minmax(200px, 1fr))` con **una columna en teléfono** (≤639 px) y
pasa al ancho `container-ultra` desde 1800 px. Además se corrigió un fallo **ya
existente** que apareció al verificar: Escape y la X cerraban y el panel se reabría al
instante porque el `focus` de vuelta al campo de la cabecera se confundía con el del
usuario (nuevo `state.closing`). Medido: 390 px → 1 tarjeta por línea, 1280 → 4,
1600 → 6, 2560 → 7, sin desbordes. `verify.php` 227 → **231** (TODO OK) + `CHANGELOG.md`
y `STATE.md`.

### 2026-10-04 · Auditoría de caché y caché moderna que actualice los precios (petición por chat)

**Pedido:** «Actúa como arquitecto senior full-stack especializado en comercio
electrónico […]; revisa todo el sistema en `/var/www/html/tienda` y dime si tenemos
un sistema de caché y en caso de no contar sugiere alguno moderno que mejore la
carga de la web pero que actualice los precios en caso de haber cambios».

**Auditoría:** sí había caché, pero casera y con trampas: dos `cached()` privados
(`Catalog` y `Menu`) sobre fichero con TTL por `filemtime`; la portada cacheaba las
fichas **con el precio dentro** (hasta 10 min de precio viejo); sin protección
contra la estampida (4,4 s por petición y combinación al caducar el `COUNT`); sin
limpieza (711 ficheros, 576 eran `count_page_*`); sin caché HTTP (las páginas salen
`no-store`). Medido en HTTP real.

**Decisión confirmada:** empezar por **Fase 1a + APCu** (cachear ids y resolver
precios en vivo + driver APCu con fallback a fichero, locks antiestampida y GC).

**Qué se hizo:** `Tienda\Core\Cache` con drivers `FileCache`/`ApcuCache`
(`config/cache.php`, claves `CACHE_*`), memo por petición y bloqueo por clave;
`Catalog::featured()` cachea solo los ids y relee en vivo (`findMany()`);
`Catalog::cached()` y `Menu::cached()` pasan por la capa nueva; `Menu::invalidate()`
invalida por patrón; `tools/cache-clear.php`; 12 comprobaciones nuevas en
`verify.php` (215 → **227**, TODO OK). Documentado en `CHANGELOG.md` y `STATE.md`.

### 2026-10-03 · Buscador en vivo como PuntoByZE, con el menú y los propios (petición por chat)

**Pedido:** «revisa el buscador de `/var/www/html/puntobyze`; haz que el buscador en este
proyecto funcione de manera similar, solo habría que agregar el caso de que tenga
productos agregados por la tienda, pero solo los productos de la tienda que está viendo
el cliente y por supuesto los productos en general, teniendo en cuenta las categorías y
subcategorías que tenga la tienda visible, que eso se selecciona en la configuración del
menú a mostrar».

**Decisión confirmada:** el menú manda siempre (el buscador solo ve el catálogo que la
tienda muestra en su menú, en modo completo o elegido, y sus propios productos).

**Qué se hizo:** `Menu::searchScope()` (árbol visible → categorías/subcategorías del
catálogo), alcance en `Catalog` (listados y facetas de la búsqueda), `searchFacets()`
(subcategorías y marcas), `OwnProduct::searchPublished()` (aislado por tienda), endpoint
`GET /buscar/live` con las tarjetas del tema y panel en vivo en el storefront (JS + CSS
por tokens), y 13 comprobaciones nuevas en `verify.php` (200 → **213**, TODO OK). Detalle
en `.agents/CHANGELOG.md` y `STATE.md`.

### 2026-10-03 · Elegir qué categorías se ven y crear categorías propias (petición por chat)

**Pedido:** «en el panel de la tienda se puede mostrar el menú completo o dar a elegir
qué categorías se mostrarán […] si la tienda decide no mostrar una subcategoría o
categoría eso se debe guardar en algún lado; además, dar la posibilidad de que agreguen
una categoría y subcategoría, por ejemplo “productos propios” o “Servicio”, que ellos
quieran agregar, y que solo esos valores se muestren en su web».

**Qué había:** el catálogo de categorías y subcategorías es el compartido con idirecto y
PuntoByZE (solo lectura) y ya existía el árbol editable con borrador/publicación, los
nodos propios y el activo/desactivado. Faltaba lo importante: la tienda no podía ocultar
ni renombrar un nodo que no fuera suyo, una categoría propia con destino propio acababa
en `/catalogo`, no existía `/propios` y no se podía colgar un nodo propio dentro de una
categoría del catálogo.

**Decisiones confirmadas:** modo «completo»/«elegido» con interruptor por categoría;
las categorías propias pueden colgar también dentro de categorías del catálogo; destinos
«productos propios» (`/propios`), página de la tienda y enlace libre; de los nodos
compartidos se puede ocultar/mostrar y renombrar (no reordenar).

**Qué se hizo:** migración `009` (`mt_stores.menu_scope` + `mt_menu_item_overrides`, la
anulación por tienda sin tocar el nodo), el modo y las anulaciones en `Menu::visibleRows`
(rama y camino incluidos), destinos `propios`/`pagina` en los tres niveles, `/propios`
con los productos propios, la posibilidad de colgar nodos propios de categorías
compartidas, y en el panel la tarjeta «Qué categorías se ven» con Mostrar/Ocultar,
renombrar y «Volver al árbol», más «+ Productos propios» y «+ Página de la tienda».
Además se arregló que el editor perdía el destino al editar y que los enlaces absolutos
del menú se rompían. `verify.php` 186 → **200** (TODO OK); probado en navegador real y
en HTTP real. Detalle en `.agents/MENU-TIENDA-2026-10-03.md` y `CHANGELOG.md`.

### 2026-10-03 · Los filtros, por subcategoría y con contexto (petición por chat)

**Pedido:** «va mal; revisa cómo se filtran los filtros en puntobyze, en los listados los
filtros son por subcategorías; revisa bien la base de datos de dónde los obtiene y cuáles
mostrar por categoría o subcategoría y marca para poder ir filtrando de forma correcta».

**Qué era:** las facetas de configuración con `when` se escapaban de su subcategoría
(«Socket» salía en Tarjetas Gráficas o Memoria PC por coincidir la categoría) y en el
listado de categoría aparecían filtros que no tocaban; además, al elegir una marca o un
filtro las opciones y los contadores no se recalculaban, así que se podía pinchar una
opción y quedarse en 0 productos.

**Qué se hizo:** en PuntoByZE los filtros salen de `rel_filtros_subcat` (filtro ↦
subcategoría) y las marcas de `rel_marcas`, siempre dentro de una subcategoría; idirecto
recalcula las opciones con la marca/filtros ya elegidos. Se corrigió `facetVisible()` para
que `subcats` mande y sin subcategoría solo queden marca y precio, y
`structuredFilters()` ahora recibe el contexto (marca, filtros, precio, búsqueda, etiqueta)
y recalcula contadores en una consulta cacheada: oculta las opciones que darían 0 y
conserva las de los grupos ya elegidos. `verify.php` 183 → **186** (TODO OK). Detalle en
`CHANGELOG.md`.

### 2026-10-02 · Filtros de los listados por categoría y subcategoría (petición por chat)

**Pedido:** «revisa los filtros en los listados, que funcione correctamente; puedes tomar
de ejemplo los filtros que se usan en /var/www/html/idirecto o /var/www/html/puntobyze
para las categorías y subcategorías».

**Qué era:** los facetas no filtraban (el WHERE leía mal la selección y la ignoraba); la
faceta de marca fuera de una categoría generaba un `marca=` que nadie lee; la etiqueta de
`/ofertas`, `/novedades` y `/destacados` se perdía en todos los enlaces; los filtros
reales del mayorista (`filtros`/`subfiltros` por subcategoría) no existían, la ruta
`/f/…` daba 404 y los 14 destinos de filtro del menú seguían desactivados; además
`Specs` se caía con claves de especificación numéricas.

**Qué se hizo:** filtros estructurados del mayorista en `Catalog` (contadores con stock,
OR en grupo y AND entre grupos), ruta SEO `/f/{filtro}-{subfiltro}`, bloque de filtros en
la vista, corrección del WHERE de facetas y de los contadores de marca, etiqueta y marca
conservadas en los enlaces, destinos `filtro` del menú resueltos + migración `008` y menú
republicado, y arreglo de `Specs`. `verify.php` 176 → **183** (TODO OK). Detalle en
`CHANGELOG.md`.

### 2026-10-02 · Parpadeo del Menú Compacto al pasar el ratón por una categoría (petición por chat)

**Pedido:** «cuando me posiciono sobre las clases `mn-bar-toggle` la pantalla empieza a
parpadear; revisa eso, solo debería mostrar las subcategorías de la categoría en el menú».

**Qué era:** no fallaba el HTML ni el árbol. Al abrir el panel, el JS añadía la clase
`mn-open` al `<body>`, pero `mn-open` ya era la clase del botón «Todas las categorías» y
sus reglas estaban sin acotar. El body pasaba a `display:inline-flex` con fondo de marca,
la página se descolocaba y la flecha de la categoría se iba a ~3700 px: el navegador
disparaba `mouseleave`, el panel se cerraba y volvía a abrirse **3-4 veces por segundo**.

**Qué se hizo:** la clase del body pasa a `mn-panel-open`; el botón del Menú Catálogo se
acota a `.mn-trigger .mn-open`; el bloqueo de scroll queda solo para el cajón móvil y el
modal de Catálogo (el desplegable de escritorio ya no bloquea la página); y
`aria-expanded` solo se marca en el disparador de la categoría activa, de modo que el
panel enseña **solo** las subcategorías de esa categoría. Reproducido y verificado con
Chrome real por CDP.

### 2026-10-02 · Administrar el menú desde el panel (petición por chat)

**Pedido:** «el sistema debe permitir administrar el menú desde el panel», revisando
antes las tablas equivalentes (categorías, subcategorías, productos, tiendas, clientes,
usuarios, configuración, banners, marcas, niveles de cliente) y proponiendo las
estructuras nuevas antes de crearlas. Como mínimo había que poder guardar tipo de menú,
categoría padre, nombre, slug, orden, activo, icono, badge (texto y color), banner y su
enlace, nivel mínimo de cliente, visibilidad por tienda, categorías vacías y fechas; y
el administrador debía poder elegir el estilo, reordenar con drag & drop, mover de
nivel, crear/editar/eliminar nodos, activar/desactivar, cambiar nombres y slugs, poner
iconos, badges y banners, decidir qué ve cada tienda y cada nivel de cliente,
previsualizar en escritorio y móvil, guardar borrador, publicar e invalidar la caché,
con **aislamiento total entre tiendas**.

**Auditoría y propuesta:** en `.agents/MENU-ADMIN-2026-10-02.md` (tabla de tablas
existentes que se reutilizan, estructuras nuevas propuestas con SQL y las 15
capacidades mapeadas). De los 17 campos pedidos, 8 ya existían.

**Decisiones confirmadas:** árbol global con visibilidad por tienda; rol `platform`
para el panel maestro; borrador + publicación; se mantienen `compacto|catalogo`.

**Qué se hizo:** migraciones `006` y `007` (campos del editor, `store_id` nulable,
`mt_menu_item_stores`, `mt_menu_published` y `mt_menu_revisions`), `Models/MenuAdmin`
(CRUD, mover, reordenar, visibilidad, validación), panel de tienda con editor +
borrador/publicación + vista previa, panel de plataforma (`/panel/plataforma/menu`)
con árbol compartido y reparto por tienda y nivel, `tools/platform-user.php` y 36
comprobaciones nuevas en `verify.php` (incluido el aislamiento con dos tiendas).

### 2026-10-02 · Los dos menús (Compacto y Catálogo) sobre el mismo árbol (petición por chat)

**Pedido:** «implementa los siguientes dos diseños de menú utilizando el mismo árbol
de categorías y la misma fuente de datos»: **Menú Compacto** (barra horizontal bajo
la cabecera, con «Más categorías» si no caben, megamenu con segundo y tercer nivel,
«Ver todo», accesos rápidos a Ofertas/Novedades/Marcas/Destacados, teclado, Escape y
clic fuera) y **Menú Catálogo** (botón «Todas las categorías», panel con fondo oscuro,
categorías a la izquierda, subcategorías y tercer nivel a la derecha, banners/marcas/
promociones y cierre). En **móvil (<1024 px)** los dos deben convertirse en el mismo
menú lateral por niveles, con botón de apertura, «Volver», cierre, áreas de 44 px,
teclado y ARIA. Y **que la tienda pueda elegir** cuál presenta.

**Qué se hizo:** un solo árbol editable (`mt_menu_items`, migración `005`), sembrado
una vez del menú de PuntoByZE (14 categorías / 83 grupos / 346 destinos), pintado por
una única vista (`_menu.php`) con dos presentaciones conmutadas por
`mt_stores.menu_style` (`compacto|catalogo`, sin tercera opción), el bloque
promocional cargado por AJAX y una sección **Panel > Menu** para elegir el estilo y
volver a copiar el árbol de referencia. Además, las URLs de navegación pasan a ser
**rutas SEO** (`/categoria/subcategoria/m/marca/…`) con **301** desde las antiguas.

### 2026-10-01 · Compra del cliente final, como en puntobyze (petición por chat)

**Pedido:** «haz el proceso para poder comprar como cliente: que pueda registrarse
como usuario de la tienda, ingresar direcciones, cambiar direcciones de envío,
agregar comentarios, similar a como cuenta la web de puntobyze para registrar un
pedido; puedes modificar las tablas de este sistema y recuerda que los pedidos luego
se pueden pasar algunos productos a las tablas que se usan tanto en idirecto como en
puntobyze».

**Qué había:** el pedido solo se podía crear a mano desde el panel. En el storefront
no existía carrito, ni cuentas de cliente, ni checkout; y el precio del catálogo era
el más barato de **todas** las tarifas (podía quedar por debajo del coste de la
tienda).

**Decisiones confirmadas:** compra **con cuenta o como invitado**; formas de pago
**transferencia, contra reembolso y recogida** (sin pasarela todavía: el pedido queda
pendiente de pago); envío **tarifa plana + gratis desde X €**; los clientes se guardan
**solo en nuestras tablas** (no se copian a `e_clientes` de puntobyze); y el precio de
venta es **tarifa de la tienda + beneficio configurable**, mostrado **con IVA
incluido** (el pedido guarda la base sin IVA).

**Hecho:** migración 004 (`mt_customers`, `mt_customer_addresses`, datos de cliente/
facturación/pago/comentario en `mt_orders` y ajustes de venta en `mt_stores`); carrito
en sesión con precios en vivo; cuenta del cliente con registro, entrada, pedidos y
**libreta de direcciones** (alta, edición y borrado); checkout con dirección, pago y
comentario que crea el pedido por `Order::createWithItems()`; el mayorista recibe la
**dirección real del cliente** y su comentario; sección **Clientes** en el panel y
botón de «marcar como pagado»; y `Catalog::forStore()` para que cada tienda venda a su
tarifa + beneficio con el IVA incluido. `verify.php` 90 → **118**. Detalle en
`CHANGELOG.md`.

### 2026-10-01 · Registro de tiendas: solo clientes del mayorista (petición por chat)

**Pedido:** «las tiendas que pueden usar esta web deben estar registradas en la
tabla `tienda` [del mayorista]; ¿eso está conectado en algún lado? Sin conexión, no
se puede registrar su tienda en la web».

**Qué había:** `tiendas` solo se usaba en el módulo de pedidos (cuenta y tarifa en
`mt_stores`, leídas por `Account`/`OrderGateway`) y en Ajustes para validar el id
de cuenta. **No existía ningún alta de tienda**: `mt_stores` nacía de la semilla o
a mano, y el enlace con `tiendas` se ponía manualmente.

**Decisión confirmada:** registro **público** con la cuenta de idirecto
(`/registro`) y, después, entrar al panel con un **usuario propio**.

**Hecho:** `/registro` (público) valida el email y la contraseña de la cuenta de
idirecto contra `tiendas` (mismo criterio que su login: `activo = 2`, ni cerrada ni
borrada, y su firma de contraseña `hash('sha256', md5(sha1($clave)))`) y crea en
una transacción la tienda de `mt_stores` **ya enlazada** (`id_tienda_idirecto` +
`id_margen`), activa, con slug único, los datos fiscales/de contacto de la cuenta y
su usuario de panel con contraseña propia. Una cuenta = una tienda; límite de
intentos por sesión para no servir de banco de pruebas de contraseñas; la
contraseña del mayorista no se guarda. `Controller::requireCsrf()` pasa a responder
403 (el 419 acababa en 500) y admite a qué ruta volver. Claves `IDIRECTO_REGISTER*`
en `.env.example`. `verify.php` 78 → **90**. Detalle en `CHANGELOG.md`.

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
