# CHANGELOG — Historial de sesiones

Registro de qué se cambió y **por qué**. La entrada más reciente va arriba.
El detalle línea a línea está en `git log`.

---

## 2026-10-05 · Carrito: botón «Agregar al carrito» y mini-carrito lateral

**Motivo (petición por chat):** mejorar el botón «Agregar al carrito» y crear un
mini-carrito lateral derecho con una experiencia tipo PcComponentes, pero con el diseño
de Valduran, **usando el carrito real** (sesión) y sin tocar menú, categorías, productos,
checkout ni nada ajeno.

**Auditoría previa:** el carrito es `Core/Cart`, vive en `$_SESSION['cart'][$storeId]`
(claves `c<id>`/`o<id>`); no hay tabla ni localStorage. El añadir era
`POST /carrito/anadir` (redirect + flash), el contador salía de `Cart::count()` en la
cabecera, la ruta del carrito es **`/carrito`** y no existe sistema de variantes. Se
detectó que `Cart::loadProduct()` usaba `Catalog::find()`, que **no traía `stock_total`**,
así que el catálogo no se recortaba por stock (solo el tope global 99).

**Qué se hizo**
- **Stock real:** `Catalog::stockTotal()` (suma de `stock` válido) y `Cart::loadProduct()`
  lo usa; `Cart::updateQuantities()` también recorta y **avisa**. `Catalog::MAX_QTY` (99)
  sigue como tope. Los productos propios con `stock = 0` mantienen el comportamiento
  actual (la tienda no lleva stock), como se acordó.
- **Botón:** texto exacto **«Agregar al carrito»** en ficha y tarjetas
  (`_card.php`, `_card_own.php`, `product.php`) con estados normal/hover/activo/**carga**
  y confirmación; la cantidad de la ficha respeta `data-max` (stock).
- **AJAX sin carrito paralelo:** los mismos endpoints detectan la petición AJAX
  (`Controller::isAjax()`) y responden JSON con el **estado real** y el partial
  `_mini_cart.php` ya pintado; sin JS todo sigue siendo un POST con redirección. La
  petición AJAX sin CSRF válido responde **403 JSON** (no un 302 que rompería el fetch).
- **Mini-carrito:** ruta de **solo lectura** `GET /carrito/mini` + armazón en el layout
  (no cuesta consultas si no se abre). Se abre solo tras un alta correcta; cierre con X,
  Escape y clic en la capa exterior; foco al panel, `role="dialog"`/`aria-modal`,
  bloqueo de scroll que **se libera al cerrar**. Responsive: 380-460 px en escritorio,
  adaptado en tablet y **100 % en móvil**, `100dvh`, `safe-area-inset-top/bottom`, sin
  scroll horizontal, cabecera y pie fijos y **lista con scroll propio**. CTA
  «Ver artículos del carrito» en escritorio y «Ver carrito» en móvil.
- **Líneas:** imagen, nombre, SKU, precio unitario, +/−, cantidad editable (mínimo 1,
  máximo stock), subtotal, total y quitar, todo contra los endpoints del carrito, con
  estado de carga, sin peticiones duplicadas y con aviso flotante.
- **Anti doble alta:** el botón se bloquea mientras la petición está en vuelo (el
  servidor, además, suma en la línea existente: nunca crea líneas duplicadas).
- Se limita también la cantidad de la **página del carrito** al stock real.

**Verificado en Chrome real por CDP** (19/19 + 4 + 8 comprobaciones): alta desde tarjeta,
ficha, buscador en vivo y categoría; doble clic = una sola petición; error no abre el
panel y sale en español; +/−, cantidad manual recortada al stock, quitar y totales en
vivo; contador sin recargar; cierre con X/Escape/capa y scroll liberado; medidas reales
a 1440 (430 px), 768 (430 px), 390 (390 px) y 844×390 (alto completo) sin desbordes y sin
errores de JavaScript. `php tools/verify.php` 232 → **244** (TODO OK).

---

## 2026-10-04 · Buscador en vivo: un solo botón (el de cerrar)

**Motivo (petición por chat):** «sobre el buscador se dibujan dos botones para cerrar,
`shop-search-clear` y `shop-search-close`; deja uno solo y que al pulsarlo se cierre
toda la parte del buscador».

**Qué pasaba:** el panel tenía dos botones con la **misma aspa**: uno para borrar el
texto (`shop-search-clear`, que se mostraba al escribir) y otro para cerrar
(`shop-search-close`). Con texto escrito se veían los dos pegados, así que parecían dos
botones de cerrar.

**Qué se hizo**
- Se quita `shop-search-clear` del layout, del JS (`els.clear`, el toggle `hidden` de
  `sync()` y su listener) y del CSS. Queda **un único botón**, `shop-search-close`, que
  cierra **todo** el panel (oculta el overlay, quita `is-open`, libera el scroll del body
  y devuelve el foco al campo de la cabecera).
- Para vaciar el texto sigue estando la **X nativa** del `<input type="search">` (y el
  teclado). Al cerrar se conserva la búsqueda, por si el usuario vuelve a abrir.
- Se añade una comprobación a `verify.php` para que no reaparezca el segundo botón.

**Verificado en Chrome real por CDP** (1600 y 390 px): en la cabecera del panel hay
**un solo botón** (40×40, visible y clicable), los resultados siguen saliendo en rejilla
y el clic oculta el overlay (`hidden`), quita `is-open` y el bloqueo del body. Sin
errores de JavaScript (el único 404 es `/favicon.ico`, porque la tienda demo no tiene
favicon: es anterior y ajeno al buscador).

`verify.php` 231 → **232**.

---

## 2026-10-04 · Buscador en vivo: rejilla que se adapta (y Escape que cierra de verdad)

**Motivo (petición por chat):** «mejora la visibilidad del buscador; en monitores grandes
solo aparece un producto por línea, cuando en puntobyze se va adaptando de acuerdo al
tamaño de la pantalla».

**Causa raíz (reproducida en Chrome real por CDP):** `StorefrontController::searchLive()`
devolvía las tarjetas `<article class="product-card">` **sueltas**, sin el contenedor
`.grid-products` que el CSS ya esperaba (`#shop-search-results` es un bloque y
`.product-card` un bloque flex). Resultado: **una tarjeta por línea en cualquier
ancho**; medido a 1600, 1280, 900 y 390 px, 12 resultados → 12 filas.

**Qué se hizo**
- `/buscar/live` envuelve sus tarjetas en `.grid.grid-products`, una rejilla por grupo
  («Productos de la tienda» y «Catalogo»), de modo que el panel reutiliza la rejilla de
  tarjetas del catálogo en vez de apilarlas.
- CSS del panel: rejilla `repeat(auto-fill, minmax(200px, 1fr))` (mismo tamaño de
  tarjeta que el catálogo) y **una sola columna en teléfono** (`max-width: 639px`); el
  panel pasa a usar el ancho `container-ultra` desde 1800 px.
- **Fallo encontrado al verificar** (ya existía, no lo introdujo este cambio): al cerrar
  con **Escape** o con la **X**, `closePanel()` devolvía el foco al campo de la cabecera
  y su listener de `focus` reabría el panel: se cerraba y se reabría al instante. Ahora
  ese foco programático va marcado (`state.closing`) y no reabre; el clic real del
  usuario sigue abriendo.

**Medido (Chrome real por CDP, `local.tienda`):** 390/480 px → 1 tarjeta por línea;
640 → 2; 768 → 3; 1024 → 3; 1280 → 4; 1440 → 5; 1600 → 6; 1800 → 6; 2560 → 7. Sin
desbordes horizontales, sin tarjetas recortadas y con el CTA en todas. Escape cierra
(devolviendo el foco al campo) y el siguiente clic lo reabre.

`verify.php` 227 → **231** (rejilla del controlador, CSS adaptativo, teléfono y cierre).

---

## 2026-10-04 · Caché de datos con driver (APCu/fichero) y precios que nunca se quedan viejos

**Motivo:** al auditar el rendimiento se vio que el proyecto ya tenía caché, pero
**casera y con trampas**: la portada guardaba en caché las fichas **con el precio
dentro** (hasta 10 min de precio viejo), no había protección contra la estampida
(cuando caducaba el `COUNT`, 4,4 s por petición y por combinación), y la carpeta
`storage/cache` **crecía sin límite** (711 ficheros, 576 eran `count_page_*`).

**Qué se hizo**
- **`Tienda\Core\Cache`**: fachada única con `get/set/delete/remember/flush/
  forgetPattern/gc`. Añade tres cosas que no había: **driver intercambiable**
  (`CacheInterface` + `FileCache` + `ApcuCache`), **memo por petición** (L1) y
  **bloqueo por clave** antiestampida (el que llega segundo espera el valor en vez
  de repetir el cálculo). `config/cache.php` + claves `CACHE_*` en `.env.example`.
- **APCu cuando esté disponible** (`CACHE_DRIVER=auto`): memoria compartida del
  servidor, sin ficheros ni red. Si falta la extensión, cae solo al driver de
  fichero (es lo que pasa ahora en esta máquina, que no tiene `php8.4-apcu`).
- **Los precios dejan de cachearse**: `Catalog::featured()` ahora guarda **solo la
  lista de ids** (lo caro de elegir) y relee precio, stock, marca y specs **en
  vivo** con `findMany()`. Un cambio de tarifa del mayorista se ve en la siguiente
  petición, no cuando caduque un TTL.
- `FileCache` escribe **atómico** (temporal + `rename`), guarda la caducidad dentro
  del JSON (`e`) y **se limpia solo** (caducados + ficheros viejos + tope de
  ficheros). El formato antiguo se trata como no existente: se recalcula una vez y
  se reescribe nuevo.
- `Catalog::cached()` y `Menu::cached()` pasan por la capa nueva; `Menu::invalidate()`
  invalida por patrón (vale también con APCu, ya no depende de `glob()`).
- **`tools/cache-clear.php`**: estado, vaciar todo, borrar solo catálogo/menú,
  borrar las claves con importes o pasar la limpieza.
- `verify.php` 215 → **227**: driver válido, ida y vuelta, memo, borrado por patrón,
  limpieza, y —lo importante— que los destacados **no** cachean el precio y que el
  precio de la portada se recalcula al cambiar el beneficio.

**Medido** (HTTP real, `local.tienda`): `/catalogo` 4,4–6,9 s en frío → **24–60 ms**
en caliente, estable; `/buscar/live?q=rtx` en caliente ~35 ms; `/` ~25 ms. Sin
picos de 4 s repetidos al caducar el contador.

**Nota para el dueño:** para el modo APCu falta instalar la extensión en el
servidor (`sudo apt-get install php8.4-apcu` y reiniciar PHP-FPM/Apache). Mientras
no esté, funciona con el driver de fichero.

---

## 2026-10-03 · HTML del storefront: sin elementos decorativos vacíos y `tel:` válido

**Motivo:** el validador de HTML de Chrome marcaba en la portada elementos decorativos
vacíos («trimming empty span/i/button») y una URI mal formada en un enlace `<a>`.

**Qué se hizo**
- El **punto de stock** de las tarjetas (`_card`, `_card_own`, previa de diseño) se dibuja
  con `::before` de `.badge-stock`: fuera el `<i class="dot" aria-hidden="true"></i>`.
- El **caret** del menú (Compacto y «Más categorías») es un icono en línea
  (`icon_svg('chevron-d')`, nuevo en el juego de iconos): fuera el `<span>` vacío y los
  botones sin contenido; la rotación al abrir sigue en CSS (`.mn-caret`).
- La **flecha** `›` del menú lateral lleva su carácter dentro del `<span>` (antes iba por
  CSS `::after`, así que el elemento quedaba vacío).
- La **barra de progreso** del slider es un `::after` de `.hero-controls` (el JS escribe
  `--progress` ahí): fuera el `<span class="hero-progress">` vacío.
- Los **puntos del slider** (`hero-dot`) llevan un `<span class="sr-only">Banner N</span>`
  (son el nombre accesible del `role="tab"`): antes eran botones vacíos con `aria-label`.
- El **logo** del panel y de `/registro` (`.logo-dot`) pasa a `::before` del contenedor
  (`.sidebar-brand` y `.auth-head`), que era otro `<span>` vacío.
- El **punto de la actividad** del panel también pasa a `::before` de `.activity li`.
- **`Tenant::phoneHref()`**: el enlace `tel:` se construye solo con dígitos y el `+`
  inicial, de modo que un teléfono con espacios («+34 91 123 45 67») no genera una URI
  mal formada.

**Lo que NO se toca (falsos positivos del validador):** los avisos de atributos «proprietary»
en `<svg>` (`fill`, `stroke`, `stroke-width`, `stroke-linecap`, `stroke-linejoin`) y en
`aria-modal`, `aria-roledescription`, `loading`, `decoding`, `fetchpriority` o `integrity`
son atributos válidos de HTML5/ARIA/SVG; el validador usa un DTD antiguo y no los conoce.

`verify.php` 213 → **215** (iconos con contenido real y `tel:` válido). Verificado en Chrome
real por CDP: el menú y el slider siguen funcionando y sin errores de JavaScript.

---

## 2026-10-03 · Buscador en vivo como PuntoByZE, con el menú de la tienda y sus productos propios

**Motivo:** «revisa el buscador de `/var/www/html/puntobyze`; haz que el buscador en este
proyecto funcione de manera similar, solo habría que agregar el caso de que tenga
productos agregados por la tienda, pero solo los productos de la tienda que está viendo
el cliente y por supuesto los productos en general, teniendo en cuenta las categorías y
subcategorías que tenga la tienda visible, que eso se selecciona en la configuración del
menú a mostrar».

**Decisión del dueño:** **el menú manda siempre**. El buscador solo devuelve productos del
catálogo central cuya categoría/subcategoría forma parte del menú visible de la tienda
(en «completo» = todo el menú menos lo oculto; en «elegido» = solo lo marcado). Además
incluye los productos propios de esa tienda.

**Qué había:** el buscador era el formulario de la cabecera que hacía `GET /catalogo?q=`
(el listado de siempre): sin resultados en vivo, sin facetas propias de la búsqueda, sin
productos propios y **sin mirar el menú**, de modo que una tienda podía encontrar por
búsqueda productos de categorías que no mostraba.

**Qué se hizo**
- **`Menu::searchScope($storeId)`**: traduce el árbol ya resuelto (`visibleRows`: modo
  completo/elegido, anulaciones, visibilidad de plataforma y nivel de cliente) a los ids
  de categoría y subcategoría del catálogo que la tienda puede mostrar. Sin nodos en
  `mt_menu_items` no limita (una tienda sin menú no se queda sin buscador).
- **`Catalog`**: parámetro `$scope` en `buildFilters`/`paginate`/`facets`/`brandOptions`/
  `structuredFilters` (la subcategoría manda; si no hay ninguna, la categoría; si está
  restringido y no hay nada visible, `1 = 0`). Las claves de caché incluyen el alcance.
  Nuevo `searchFacets()` (subcategorías y marcas con contadores en contexto **sin** el
  rango de precio, que multiplicaba por 3-4 la latencia de la búsqueda en vivo).
- **`OwnProduct::searchPublished()`**: fila completa de los propios publicados que casan
  por nombre o referencia; `searchLive` los devuelve solo para el `store_id` de la tienda
  que se está viendo.
- **`StorefrontController::searchLive()`** + ruta **`GET /buscar/live`**: JSON con el HTML
  de las tarjetas (mismo tema, sin duplicar maquetación), las facetas y el enlace «Ver
  todos» (`/catalogo?q=…`). En las búsquedas, `catalog()` aplica el mismo alcance y filtra
  los productos propios por `q`; la navegación normal (`/catalogo` sin `q`) no cambia.
- **Interfaz**: panel a pantalla completa en `layouts/shop.php` (campo, filtros por
  subcategoría y marca, contador, cerrar/Escape/clic fuera) + módulo JS propio en
  `shop.js` (con `AbortController` para descartar respuestas viejas) + CSS en `shop.css`
  **solo con tokens**. Si el JS no carga, el formulario de la cabecera funciona igual.
- **`verify.php` 200 → 213**: alcance que sigue al menú (incluye ocultar una categoría),
  listados y búsqueda por texto dentro del alcance, facetas acotadas, acción/UI presentes
  y aislamiento de los productos propios entre dos tiendas (transacción que se deshace).

---

## 2026-10-03 · La tienda elige qué categorías se ven y puede crear las suyas

**Motivo:** «en el panel de la tienda se puede mostrar el menú completo o dar a elegir
qué categorías se mostrarán […] si la tienda decide no mostrar una subcategoría o
categoría eso se debe guardar en algún lado; además, dar la posibilidad de que agreguen
una categoría y subcategoría, por ejemplo “productos propios” o “Servicio”, que ellos
quieran agregar, y que solo esos valores se muestren en su web».

**Auditoría previa (reutilizar antes de crear).** El catálogo de categorías y
subcategorías es el **compartido** (`categorias`, `subcategorias`, `productos` de la
misma base que usan idirecto y PuntoByZE): solo lectura. Ya existían el árbol editable
(`mt_menu_items`), los nodos propios (`store_id` = tienda), el activo/desactivado, el
borrador + publicación y los productos propios (`mt_own_products`) y las páginas de
contenido (`mt_content_blocks`). Lo que faltaba, comprobado contra la base de datos:
la tienda no podía ocultar ni renombrar un nodo que no fuera suyo, una categoría propia
con destino propio acababa en `/catalogo` (el nivel 1 ignoraba su destino y se
descartaba sin grupos), no existía `/propios` y no se podía colgar un nodo propio dentro
de una categoría del catálogo. Propuesta en `.agents/MENU-TIENDA-2026-10-03.md`.

**Decisiones del dueño:** modo «completo» / «elegido» con interruptor por nodo;
las categorías propias pueden colgar también dentro de categorías del catálogo; destinos
«productos propios», página de la tienda y enlace libre; de los nodos compartidos se
puede **ocultar/mostrar y renombrar**, no reordenar.

**Estructuras nuevas (aditivas y reversibles)**
- `009_menu_tienda.sql`: `mt_stores.menu_scope` (`completo|elegido`, por defecto
  `completo`) y `mt_menu_item_overrides` (`store_id`, `item_id`, `state`
  `visible|oculto`, `label` NULL, único por tienda+nodo, claves foráneas en cascada).
  No se duplican nodos: se guarda solo lo que cambia, así el árbol puede ser compartido.

**Cómo funciona**
- `Menu::scopeForStore()` decide entre mostrar todo (menos lo oculto) o solo lo marcado;
  en «elegido» se incluyen además la rama y el camino del nodo marcado, y un `oculto`
  explícito se lleva su rama. `Menu::visibleRows()` aplica primero los filtros que ya
  existían (activo, visibilidad de plataforma, nivel de cliente) y después la decisión
  de la tienda; `publish()` usa lo mismo, así que el snapshot publicado sale filtrado.
- `MenuAdmin::setOverride()` / `clearOverride()` guardan la anulación **sin tocar la
  fila del nodo** (que puede ser compartida); el `store_id` sale siempre de la sesión.
  Un nodo creado por la tienda nace marcado como visible para que se vea en modo
  «elegido».
- Destinos nuevos `propios` (`/propios`, listado de los productos propios de la tienda,
  ruta y vista nuevas) y `pagina` (`/pagina/{slug}` de una página de contenido). El
  nivel 1 y el 2 ya respetan su destino: una categoría o un grupo propio se pinta aunque
  no tenga hijos, y las categorías del catálogo conservan su ruta y su panel promocional.
- La tienda puede colgar sus nodos dentro de una categoría compartida (se levantó la
  prohibición de `validate()` y `move()`), y `menu_href()` deja de romper los enlaces
  absolutos (`https://…` se convertía en `https://local.tiendahttps://…`).

**Panel de la tienda**
- Tarjeta **«Qué categorías se ven»**: modo, interruptor Mostrar/Ocultar y nombre propio
  por categoría y subcategoría, y «Volver al árbol». Botones «+ Productos propios» y
  «+ Página de la tienda». El editor rellena el destino al editar (antes se perdía al
  guardar) y solo muestra los campos del tipo elegido; los nodos compartidos ya no
  ofrecen botones que el servidor rechazaba.

**Verificación:** `php tools/verify.php` → **TODO OK (200)**, con 14 comprobaciones
nuevas (migración `009`, los dos modos, la anulación por tienda, la rama oculta, el
aislamiento con dos tiendas, los destinos propios y el nodo bajo categoría compartida).
En navegador real (Chrome por CDP): la tarjeta con 14 categorías y 84 subcategorías,
ocultar/mostrar y «Volver al árbol», el editor con el destino precargado y los presets,
sin errores de JavaScript; y en HTTP real la categoría «Productos propios» enlazando a
`/propios` (200) y el menú Compacto y Catálogo sin regresiones.

---

## 2026-10-03 · Filtros por subcategoría: alcance correcto y filtrado progresivo

**Motivo:** «va mal; revisa cómo se filtran los filtros en puntobyze, en los listados los
filtros son por subcategorías; revisa bien la base de datos de dónde los obtiene y cuáles
mostrar por categoría o subcategoría y marca para poder ir filtrando de forma correcta».

**Qué pasaba.**
1. **Los facetas de configuración se escapaban de su subcategoría.** `facetVisible()`
   devolvía `true` si coincidía la **categoría** *o* la subcategoría: como `socket`
   declara `cats=[9]`, aparecía en **todas** las subcategorías de COMPONENTES, incluidas
   Tarjetas Gráficas o Memoria PC. Y en el listado de categoría (sin subcategoría) salían
   socket/memoria/almacenamiento, que ahí no pintan nada.
2. **Los filtros no se adaptaban al contexto.** Al elegir una marca (o un filtro), los
   grupos del mayorista seguían mostrando todas sus opciones y con los contadores de la
   subcategoría completa: se podía pinchar una opción y quedarse en **0 productos**.

**Cómo lo hace la referencia (PuntoByZE), revisado en su código y su base de datos.**
Los filtros no son de la categoría ni globales: salen de `rel_filtros_subcat`
(**filtro ↦ subcategoría**) y sus valores de `subfiltros`, contando productos con stock
vía `rel_filtro_producto`. Las marcas también son por subcategoría (`rel_marcas`). La
subcategoría es, por tanto, el eje: en el listado de categoría no hay filtros laterales
(hay rejilla de subcategorías), y dentro de una subcategoría se muestran sus marcas y sus
filtros. Al aplicar: **OR dentro del mismo filtro, AND entre filtros**; idirecto, además,
recalcula las opciones en el contexto de la marca/filtros ya elegidos
(`getFiltrosNuevo`).

**Qué se hizo.**
- `Catalog::facetVisible()`: un faceta con `when` **solo** se muestra dentro de una
  subcategoría, y si declara `subcats` manda esa lista (la categoría solo se usa como
  respaldo). Sin subcategoría quedan solo las facetas globales (marca y precio), como en
  la referencia. Resultado: `/componentes/tarjetas-graficas` ya no ofrece «Socket»;
  `/componentes/placas-base` sí ofrece socket + memoria + factor.
- `Catalog::structuredFilters()` pasa a recibir la **selección completa** y el contexto
  (etiqueta, búsqueda, marca, facetas, precio) y recalcula los contadores con una sola
  consulta cacheada (600 s):
  - grupos **sin** selección: solo las opciones que dejan resultados, con su contador en
    contexto;
  - grupos **con** selección: conservan todas sus opciones para poder cambiarlas.
  Así se filtra «paso a paso» sin callejones sin salida.
- El controlador le pasa la selección completa, la etiqueta y `q`; la vista deja de pintar
  el contador «(0)» de una opción elegida sin resultados.
- `verify.php`: 3 comprobaciones nuevas (alcance de facetas por subcategoría y contadores
  progresivos cotejados con el listado). Total **186**.

**Verificación (HTTP real).**
- `/componentes` → solo marca + subcategorías (sin filtros laterales).
- `/componentes/tarjetas-graficas` → gpu, marca, subcategorías y los 6 filtros del
  mayorista (Conexión gráfica, Conexiones externas, Diseño Color, Memoria Gráfica,
  Modelo gráfica, Tipo gráfica); **ni socket ni memoria**.
- `/componentes/memoria-pc` → memoria (config) + los 6 filtros reales (incluido «Tipo
  memoria interna»: DDR5, DDR4…).
- Con marca **GIGABYTE** en Tarjetas Gráficas, «Tipo gráfica» queda en NVDIA (26), AMD (6)
  y GT para Multimedia (1): cuadra exactamente con el listado de cada combinación
  (probado también marca + filtro: 26 productos). Con **HP** solo quedan las opciones que
  HP tiene. Los enlaces conservan la marca al tocar un filtro y viceversa.
- Tiempos: subcategoría ~0,11-0,14 s; contexto con marca ~0,09-0,12 s en caliente.

---

## 2026-10-02 · Los filtros de los listados (categorías y subcategorías)

**Motivo:** «revisa los filtros en los listados, que funcione correctamente; puedes tomar
de ejemplo los filtros que se usan en /var/www/html/idirecto o /var/www/html/puntobyze
para las categorías y subcategorías».

**Lo que estaba mal (reproducido con HTTP real).**
1. **Los facetas no filtraban nada.** `Catalog::buildFilters()` llamaba a
   `normalizeSelection($facetSelection)`, pero el listado le pasa el **mapa de términos**
   (`['socket'=>['am5']]`), no la selección completa (`['terms'=>…]`). `normalizeSelection`
   leía `$selection['terms']`, que no existía, así que devolvía vacío: **socket, gráfica,
   memoria, formato, almacenamiento y marca se ignoraban**. Marcar una marca en `/ofertas`
   dejaba el catálogo entero.
2. **La faceta de marca fuera de una categoría generaba `/catalogo?marca=43&page=1`**: un
   parámetro que no lee nadie (el listado usa `f[marca][]`), así que no filtraba nada.
3. **La etiqueta comercial se perdía en todos los enlaces** (`/ofertas`, `/novedades`,
   `/destacados`): paginación, orden, facetas y subcategorías devolvían al catálogo
   completo porque `tag()` reescribía `$_GET` sin conservar `f[...]` y el constructor de
   URLs no incluía `etiqueta`.
4. **Los filtros reales del mayorista no existían en la tienda.** Las rutas
   `/f/{id_filtro}-{id_subfiltro}` respondían 404 y las **14 categorías de filtro** que la
   siembra del menú dejó desactivadas («Para un uso: GAMING», «Tipo gráfica: NVDIA»,
   «Tipo memoria interna: DDR5»…) no se podían pintar.
5. `Specs::highlightsFromPairs()` se caía con **TypeError** cuando una clave de
   especificación era numérica («1440»): PHP la guarda como entero en el array y
   `str_contains(int, string)` no lo admite. Rompía listados filtrados de monitores.

**Cómo lo hacen las referencias (idirecto y puntobyze).** El mayorista clasifica sus
productos en `rel_filtro_producto` (producto ↔ `subfiltros`) y declara en
`rel_filtros_subcat` qué `filtros` aplican a cada subcategoría. En un listado de
subcategoría se ofrecen esos grupos con el número de productos con stock y al aplicarlos
se combinan **OR dentro del mismo filtro y AND entre filtros**.

**Qué se hizo.**
- `Catalog`: `structuredFilters()` carga los grupos de la subcategoría (contadores con
  stock, cacheados 1 h y **cotejados contra el total real del listado**: 296 opciones
  probadas, 0 desajustes); `filterSubcategory()` resuelve a qué subcategoría conviene
  enlazar un filtro; `structuredChipLabel()` da la etiqueta del chip.
- El WHERE del listado aplica los filtros estructurados como semijoin
  (`p.id IN (SELECT … WHERE sf.id_filtro = N AND r.id_sub_filtro IN (…))`), con el id de
  filtro incrustado para que desde la URL no cuele un subfiltro de otro grupo.
- Se corrigió el envoltorio de `buildFilters()` (acepta el mapa de términos y la selección
  completa) y los contadores de marca se calculan ahora **en el contexto del listado**
  (etiqueta, búsqueda y resto de filtros), excluyendo la propia marca.
- `CatalogUrl`: `/f/{filtro}-{subfiltro}` se construye y se parsea (sin 404), la etiqueta
  sin jerarquía se emite como ruta propia (`/ofertas`) y la marca sin categoría viaja como
  `f[marca][]` en vez del `marca=` muerto.
- Vista del catálogo: bloque de filtros estructurados (con contador y «seleccionado
  primero»), conservación de `etiqueta` en el formulario y en todos los enlaces generados.
- `tag()` y `brand()` conservan facetas, precio y orden de la query original.
- Menú: los nodos de tipo `filtro` se resuelven a la subcategoría con stock y se pintan;
  `MenuAdmin` los valida (par filtro-subfiltro) y el editor permite crearlos con sus dos
  ids. Migración **008** que activa los 14 destinos sembrados + menú republicado (359
  destinos). El `Agenda` (filtro sin stock en todo el catálogo) se queda sin pintar en
  lugar de enlazar a una lista vacía.
- `Specs`: se convierte la clave a `string` antes de `str_contains`.

**Verificación.** `php tools/verify.php` → **TODO OK (183)** (5 comprobaciones nuevas de
filtros estructurados, facetas y URLs; 3 actualizadas porque ya no aplica el
comportamiento viejo). HTTP real: subcategoría 181 → 110 productos, `…/f/28-210` → 81,
`…/f/28-210/f/30-238` → 80 (AND), `/ofertas` + marca → filtrada, paginación de `/ofertas`
y del orden conservan la etiqueta, `/marca/43?orden=precio-asc` conserva la marca, los 11
enlaces de filtro del menú devuelven productos y `?pmin=500&pmax=900` acota a 24.
`storage/cache` limpio; caches de filtros ~0,3-0,5 s en frío y ~3 ms en caliente.

---

## 2026-10-02 · Parpadeo del Menú Compacto al pasar el ratón por una categoría

**Motivo:** «cuando me posiciono sobre las clases `mn-bar-toggle` la pantalla empieza a
parpadear; solo debería mostrar las subcategorías de la categoría en el menú».

**Causa raíz (reproducida en navegador real, Chrome por CDP).** El JS abría el panel
añadiendo la clase `mn-open` al `<body>`, pero **`mn-open` ya era el nombre de la clase
del botón «Todas las categorías» del Menú Catálogo**, y sus reglas estaban sin acotar:
`.mn-open { display:inline-flex; padding:.6rem .95rem; background: var(--c-primary); … }`.
Al abrirse el megamenú, el body pasaba a `display:inline-flex` con el fondo de marca: la
página se descolocaba entera y la flecha `.mn-bar-toggle` **saltaba de `y≈134` a `y≈3844`**.
El navegador disparaba entonces `mouseleave`, a los 260 ms se cerraba el panel, el body
volvía a `block`, la flecha regresaba bajo el cursor y `mouseenter` lo abría otra vez:
**entre 3 y 4 aperturas y cierres por segundo** (medido: 16 mutaciones en 3,5 s). Eso era
el parpadeo, no un problema de la animación ni del HTML del árbol.

**Arreglo**
- La clase de estado del body pasa a `mn-panel-open`: **única y sin choque** con el
  componente. Se actualizan el JS y la regla `body.mn-panel-open { overflow: hidden }`.
- Las reglas del botón del Menú Catálogo se acotan a `.mn-trigger .mn-open` (más
  `:hover` y `[aria-expanded="true"]`), de modo que ningún otro elemento con esa clase
  pueda heredar estilos de botón.
- El **bloqueo de scroll** de fondo se deja solo donde toca: cajón móvil y panel modal
  del Menú Catálogo. El megamenú desplegable del Menú Compacto en escritorio **no**
  bloquea la página (`body.mn-panel-open:has(.mn--compacto) { overflow: visible }`),
  porque al quitar la barra de scroll la página daba un salto lateral con cada apertura.
- `aria-expanded` pasa a marcarse **solo en el disparador de la categoría activa** (antes
  los siete botones decían «expandido» a la vez): en el panel solo se pinta el panel de
  esa categoría (`.mn-pane.is-active`), como se pidió.

**Verificación (Chrome headless por CDP, hover emulado).** Con el ratón quieto sobre
`.mn-bar-toggle`: **una sola mutación** del panel (antes 16 en 3,5 s), `body` en `block`,
la flecha se queda en `y≈134` y el panel muestra **solo** el panel de la categoría
apuntada (`mn-pane-3` → «Periféricos», `mn-pane-6` → «Software», `mn-pane-2` →
«Componentes»), con los `aria-expanded` correctos. Probado además: entrar en el panel no
lo cierra, salir sí; Menú Catálogo en escritorio (Escape y cambio de categoría) y el cajón
móvil a 390 px en **los dos** estilos (abrir, bajar de nivel, «Volver», Escape), todo sin
errores de JavaScript. `php tools/verify.php` → **TODO OK (176)**.

---

## 2026-10-02 · El menú se administra desde el panel (editor, publicación y visibilidad)

**Motivo:** «el sistema debe permitir administrar el menú desde el panel» con, como
mínimo, tipo de menú, categoría padre, nombre, slug, orden, activo, icono, badge (texto
y color), banner y su enlace, nivel mínimo de cliente, visibilidad por tienda,
configuración de categorías vacías y fechas; y 15 capacidades concretas (elegir estilo,
drag & drop, mover de nivel, CRUD, activar/desactivar, iconos, badges, banners,
visibilidad por tienda y por nivel, vista previa escritorio/móvil, borrador, publicar e
invalidar la caché), con **aislamiento total entre tiendas**.

**Auditoría previa (reutilizar antes de crear).** Se comprobó qué había ya:
`categorias`, `subcategorias`, `productos` y `marcas` (catálogo del mayorista, solo
lectura), `mt_stores` (**ya tiene `menu_style`**), `mt_settings` (clave/valor por
tienda), `mt_store_users` (**ya tiene `role`**), `mt_banners` (banner del nodo),
`categoria_cliente` (los niveles de cliente: 10/11/12 con `orden` 1/2/3) y
`tiendas.id_categ_cliente` (el nivel de cada tienda). De los 17 campos pedidos, 8 ya
existían. Propuesta escrita en `.agents/MENU-ADMIN-2026-10-02.md` antes de tocar nada.

**Decisiones del dueño:** árbol **global con visibilidad por tienda**; **rol
`platform`** en `mt_store_users` para el panel maestro; **borrador + publicación**;
se mantienen los valores `compacto|catalogo`.

**Estructuras nuevas (aditivas y reversibles)**
- `006_menu_admin.sql`: `icon`, `badge_color`, `banner_id`, `banner_url`,
  `min_customer_level`, `hide_empty`, `visibility`; `store_id` pasa a admitir NULL
  (nodo compartido de la plataforma) soltando y recreando su clave foránea; y las
  tablas `mt_menu_item_stores` (qué tiendas ven cada nodo), `mt_menu_published`
  (versión publicada) y `mt_menu_revisions` (histórico).
- `007_menu_badge_color.sql`: `badge_color` pasa a `varchar(32)` para que quepan los
  tokens de la identidad visual (`--c-primary-contrast` son 20 caracteres). Fallo
  encontrado al probar el editor: el `varchar(9)` inicial daba un 500.

**Cómo funciona**
- `Menu` lee **lo publicado** (una fila, sin JOIN) y cae al árbol de trabajo si la
  tienda nunca ha publicado: nada se rompe al actualizar. La caché lleva la versión
  publicada en la clave, y `invalidate()` la borra al publicar (tenía un fallo: los
  comodines se saneaban y el `glob()` no encontraba nada).
- `MenuAdmin` (`app/Models/MenuAdmin.php`, nuevo) concentra el editor: validación
  (nombre, slug único entre hermanos, destino real en el catálogo, hex o token en el
  color, nivel de cliente existente, **banner de la propia tienda**), CRUD, mover de
  nivel con recálculo de la rama, detección de ciclos, reordenado con `sort`
  normalizado, visibilidad y errores de base de datos convertidos en mensajes.
- `Auth::isPlatform()` + `PlatformMenuController`: el panel de plataforma exige el rol
  y responde 403 al resto. `tools/platform-user.php` crea o promueve ese usuario.
- Vista compartida `panel/_menu_tree.php` para los dos paneles (mismo árbol, mismas
  acciones), `panel/menu.php`, `panel/menu_plataforma.php` y la vista previa con dos
  marcos (`panel/menu_preview.php` + `themes/idirecto/menu_preview_frame.php`), donde
  el ancho del iframe activa las media queries reales (1280 escritorio, 390 móvil).
- `panel.js`: drag & drop que guarda el orden al soltar (una petición JSON) y el
  formulario de nodo que se rellena desde el DOM. `requireCsrf()` acepta ahora el token
  por cabecera `X-CSRF-Token` o en el cuerpo JSON: sin eso, el guardado del orden por
  AJAX no podía pasar el control de seguridad.

**Aislamiento (regla dura, probada)**
- El `store_id` **nunca** viene del formulario: sale de la sesión.
- Una tienda solo puede crear/editar/mover/borrar **sus** nodos; los compartidos son
  de solo lectura para ella, y los de otra tienda ni aparecen ni se pueden tocar
  aunque se conozca el `id`.
- `verify.php` lo comprueba con **dos tiendas** dentro de una transacción que se
  deshace (no deja datos de prueba).

**Verificación:** `php tools/verify.php` → **TODO OK (176)**, 36 comprobaciones nuevas.
Y con navegador real (CDP): **20 comprobaciones del editor** (tres niveles pintados,
formulario de editar/crear, drag & drop que guarda el orden, los dos marcos de la
vista previa a 1280 y 390 px, el 403 del panel de plataforma) más las 38 del menú del
escaparate, sin errores de JavaScript.

---

## 2026-10-02 · Navegación: Menú Compacto y Menú Catálogo sobre el mismo árbol

**Motivo:** el dueño pide los dos menús «utilizando el mismo árbol de categorías y la
misma fuente de datos», exactamente dos variantes (sin tercera), con el árbol tomado
de PuntoByZE y editable después, y que **la tienda elija** cuál presenta.

**Por qué así.** PuntoByZE no tiene un «menú»: tiene un árbol editorial de tres
niveles (`websites_owned` → `websites_menu` → `websites_list` → `websites_item`, la
web de referencia es `id_websites_owned = 2`) donde el tercer nivel apunta a
subcategoría, a marca (`m/{id}`), a filtro (`f/{id}-{id}`) o incluso a otra categoría.
Se copia **una vez** (solo nombres y orden) a una tabla propia y aditiva
(`mt_menu_items`, migración `005` idempotente) porque las tablas del mayorista son de
solo lectura. El estilo elegido vive en una columna nueva `mt_stores.menu_style`.

**Lo importante del diseño:** **una sola vista** (`themes/idirecto/_menu.php`) pinta los
dos diseños, conmutados por `data-menu-style`. Así es imposible que los dos menús
enseñen información distinta, que era el requisito explícito. Cambia solo la
presentación: barra horizontal con megamenu desplegable (Compacto) o botón «Todas las
categorías» con panel modal y fondo oscuro (Catálogo). El estilo se sanea siempre
contra `Menu::styles()`, que solo tiene esas dos claves: **no puede existir un tercer
menú** ni por configuración ni por base de datos.

**Cómo se hizo**
- `Core/CatalogUrl`: rutas SEO del catálogo como única fuente de slugs
  (`/categoria`, `/categoria/subcategoria`, `/m/marca`, `/etiqueta/…`, `/orden/…`,
  `/page/n`) con `parse()`, `fromQuery()` y `canonicalFromQuery()`. Las URLs antiguas
  con query string hacen **301** a su ruta; `f/…` (filtros estructurados) devuelve 404
  a propósito en vez de enseñar un listado equivocado.
- `Models/Menu`: árbol cacheado por tienda con **versión de forma** en la clave
  (`menu_tree_v2_<id>`), siembra desde la referencia, `stats()`/`pending()` para el
  panel, accesos rápidos y panel promocional. En la siembra el destino se resuelve por
  id (`/productos/s/128/…`), por nombre y por slug; **lo que no se reconoce se guarda
  inactivo con su aviso** (21 destinos: 14 filtros y 7 subcategorías), así el menú
  nunca enlaza a una página rota y la tienda ve qué falta.
- `Catalog`: etiquetas de listado (`ofertas` contra la tabla `ofertas` con fechas,
  `novedades` por `fecha_alta`, `destacados` por `etiqueta = 1`), `attachOffers()` para
  el aviso «Oferta» de las tarjetas y `brandList()`/`topBrands()` para `/marcas`.
- `StorefrontController`: 301 canónico, `seoListado()`, `tag()`, `brands()`, `brand()`
  y `menuPanel()` (devuelve **HTML ya renderizado**, no datos: así el JS no duplica
  tarjetas ni colores).
- Panel: `Admin/MenuController` + `panel/menu.php` con la elección de estilo
  (validada contra las dos claves), el resumen del árbol y la lista de pendientes.
- `_menu.php` + CSS/JS nuevos: teclado (flechas, Escape, trampa de foco en el modal),
  clic fuera, foco devuelto al disparador, `aria-expanded`/`aria-modal`/`role=tablist`
  y móvil por niveles con «Volver» y áreas de 44 px.

**Fallo encontrado y corregido en las pruebas de navegador** (Chrome headless por
CDP): el botón del menú lateral vive en la cabecera, **fuera** del contenedor del
menú, así que el manejador de «clic fuera» veía su propio clic y cerraba el cajón en
el mismo clic que lo abría. Se arregló con `stopPropagation()` y una guarda por
selector. También se corrigió que el árbol exponía el id del nodo de menú en lugar del
id de la categoría (el bloque promocional pedía una categoría inexistente) y una regla
CSS de escritorio (`.mn--compacto .mn-side { display: none }`) que dejaba el cajón
móvil sin la lista de categorías. Se retiró además el CSS muerto del antiguo
`.catmenu`/`.nav-catalog` y se movió la ruta comodín `/{ruta...}` al final del
fichero de rutas, porque declarada antes se tragaba el panel entero.

**Verificación:** `php tools/verify.php` → **TODO OK (140)**, con 22 comprobaciones
nuevas (dos estilos y saneo, tablas/columnas de la 005, árbol de tres niveles, que
ningún enlace del menú sea una ruta inválida, que los filtros pendientes no se pinten,
etiquetas de listado contra las tablas reales, rutas SEO y canonicalización) y
**prueba en navegador real**: 22 comprobaciones con el Menú Catálogo y 16 con el Menú
Compacto (apertura, Escape, clic fuera, carga diferida del bloque promocional, cajón
móvil a 390 px con «Volver» y 44 px), sin errores de JavaScript.

---

## 2026-10-01 · Compra como cliente final: carrito, cuenta, direcciones y pedido

**Motivo:** el dueño pide el proceso para que un cliente compre en la web —
**registrarse como usuario de la tienda, dar de alta direcciones y cambiarlas,
dejar comentarios**— «similar a lo que cuenta la web de puntobyze para registrar
un pedido», y recuerda que después los productos se pueden pasar a las tablas que
comparten idirecto y puntobyze. Antes solo existía el pedido manual del panel:
`mt_orders` se llenaba a mano y en el storefront no había ni carrito ni cuentas.

**Cómo funciona puntobyze (lo que se ha copiado):** cliente (`e_clientes`) →
direcciones (`e_direccion`: nombre, NIF, país/provincia/población, CP, dirección,
detalle, teléfono/móvil) → carrito → confirmar dirección → confirmar pedido →
dos filas en `pedidos_addr` (facturación y envío) → `pedidos` (`id_tienda`,
`forma`, `comentario`, `web = 1`) → `pedidos_det` por línea. Se comprobó además
que **idirecto y puntobyze comparten base de datos** (`idirecto_db`: `e_clientes`
3.094, `e_direccion` 5.815, `pedidos` 852.570, `poblaciones` 85.375...).

**Decisiones del dueño:** se puede comprar **registrándose o como invitado**;
formas de pago **transferencia, contra reembolso y recogida en tienda** (sin
pasarela todavía: el pedido nace pendiente de pago); **gastos de envío = tarifa
plana + gratis desde X €**; los clientes viven **solo en nuestras tablas** (no se
copian a `e_clientes`); y el precio de venta es **la tarifa de la tienda + un
beneficio configurable, con el IVA incluido** en la web.

**Cambios**

- **Migración 004**: `mt_customers` (clientes finales por tienda, con `is_guest`),
  `mt_customer_addresses` (libreta con los mismos datos que espera `pedidos_addr`
  + los ids de país/provincia/población), y en `mt_orders` el cliente, la
  dirección de facturación completa, el detalle/teléfonos/ids del envío, el
  comentario del cliente y el cobro (`payment_method`, `payment_status`,
  `paid_at`). En `mt_stores`: `markup`, `shipping_flat`, `free_shipping_from`,
  `pay_transfer`, `pay_cod`, `pay_pickup` y `bank_details`.
- **Precio de venta de la tienda** (`Catalog::forStore`): la tienda ya no muestra
  el precio más barato de todas las tarifas (que podía ser **menor que su
  coste**), sino `tarifa de la tienda (id_margen) + beneficio (mt_stores.markup)`,
  y el storefront lo muestra **con IVA incluido** mientras el pedido guarda la
  base sin IVA. Las cachés de destacados y rango de precios pasan a ser por
  tienda y se olvidan al guardar los ajustes de venta.
- **Carrito** (`Core/Cart`, en sesión y por tienda) sobre la ficha y las tarjetas
  del catálogo, sin depender de JavaScript. Precio, stock y nombre se leen en vivo
  del catálogo: lo único que se guarda es producto y cantidad.
- **Cuenta del cliente** (`CustomerAuth`, separada de la del panel): registro,
  entrada, salida, «mis datos», «mis pedidos», ficha de pedido y **libreta de
  direcciones** (alta, edición y borrado). Un invitado que se registra con el
  mismo email **reclama** su cuenta y conserva sus pedidos.
- **Checkout** (`Core/Checkout`): dirección de envío (de la libreta o nueva),
  facturación opcional distinta, forma de pago, comentario y confirmación. Crea
  `mt_orders` + `mt_order_items` con los importes recalculados en el servidor y
  guarda la dirección en la libreta **solo si el pedido se registra bien**.
- **Envío al mayorista** (`OrderGateway`): pasa a `pedidos_addr` la **dirección
  real del cliente** (con sus ids de país/provincia/población, celular y el email
  en `localidad`, como puntobyze) en lugar de reutilizar la de la tienda, usa la
  facturación del pedido si la trae, y manda el comentario del cliente en
  `pedidos.detalles`.
- **Panel**: nueva sección **Clientes** (listado con buscador y ficha con
  direcciones y pedidos), la ficha de pedido enseña cobro, comentario del cliente
  y facturación, con botón para **marcar como pagado**; y Ajustes estrena el
  bloque **Venta y cobro** (beneficio, envío, formas de pago y datos bancarios).
- `Controller`: `themeView()` y `bootPricing()` suben al controlador base (los
  usan storefront, carrito, cuenta y checkout).
- `tools/verify.php`: 90 → **118** comprobaciones.

**Verificación:** `php tools/verify.php` → TODO OK (118). Las pruebas de carrito,
cliente, direcciones y pedido van dentro de una transacción que se deshace, así
que no queda basura: solo se comprueban los números, no se guarda nada. Incluye
el mapeo de `shippingAddress()`/`billingAddress()` (invocadas por reflexión) para
comprobar que al mayorista le llega la dirección del cliente.

Con **HTTP real** se probó el circuito completo: añadir al carrito (con y sin
stock), totales con envío gratis desde 60 €, alta de un pedido **como invitado**
y otro **con cuenta y dirección guardada + facturación distinta** (ambos guardados
correctamente en `mt_orders`/`mt_order_items`, con base, IVA y envío que cuadran
al céntimo), la libreta de direcciones (crear, editar y borrar), el registro y la
entrada del cliente (con límite de intentos), la ficha del pedido en el panel, el
buscador de productos del panel (devuelve la base sin IVA), el botón de cobro y
los ajustes de venta (comprobando que el precio de la web cambia y la caché se
limpia). Los pedidos y clientes de prueba se borraron al terminar.

**Nota para el dueño:** en la tienda demo se han activado precios y compras
(`allow_orders`, `show_prices`, beneficio 15 %, envío 4,95 € y gratis desde 60 €)
para poder probarlo; se cambia en Panel > Ajustes. **Pendiente:** pasarela de pago
real, gastos por peso/provincia, emails de confirmación al cliente y a la tienda,
y recuperación de contraseña del cliente (no hay envío de correo todavía).

---

## 2026-10-01 · Registro de tiendas: solo clientes del mayorista

**Motivo:** el dueño pregunta si la tabla `tiendas` (la de clientes del
mayorista) está conectada en algún sitio y avisa de que **sin esa conexión no se
puede registrar una tienda en la web**. Hasta ahora no había ningún alta: las
tiendas (`mt_stores`) solo nacían de la semilla o a mano en la base de datos, y
el enlace con `tiendas` se ponía manualmente en Ajustes.

**Decisión confirmada:** registro **público** con la cuenta de idirecto
(`/registro`), y después se entra al panel con un **usuario propio** (no con las
credenciales del mayorista).

**Cambios**

- `app/Core/Registration.php` (nuevo): alta de una tienda a partir de una cuenta
  del mayorista. La tienda nace **activa y enlazada** (`id_tienda_idirecto` +
  `id_margen` de la cuenta) y con los datos fiscales/de contacto copiados
  (razón social, NIF, dirección, población, provincia y país resueltos por id),
  plan por defecto, slug único y su usuario de panel.
- `Account::login()` + `Account::signature()` (nuevos): comprueban la cuenta
  contra `tiendas` con el mismo criterio que el login de idirecto
  (`activo = 2`, sin cerrar ni borrar) y **su misma firma de contraseña**
  (`hash('sha256', md5(sha1($clave)))` — **no** es `password_hash`).
- `RegistrationController` + rutas `GET|POST /registro` (públicas) y vista
  `register.php` (layout de acceso): email y contraseña de idirecto, dirección de
  tienda opcional y contraseña nueva para este panel.
- Límite de intentos por sesión (`IDIRECTO_REGISTER_ATTEMPTS`, 5 por 15 min) para
  que el formulario no sirva para probar contraseñas del mayorista, más registro
  en el log de los rechazos. La contraseña de idirecto **no se guarda**.
- `config/idirecto.php` y `.env.example`: `IDIRECTO_REGISTER`,
  `IDIRECTO_REGISTER_PLAN`, `IDIRECTO_REGISTER_ATTEMPTS` y
  `IDIRECTO_REGISTER_WINDOW`.
- `Controller::requireCsrf()`: responde **403** (el 419 lo convertía Apache en
  500) y admite a qué ruta volver, para que los formularios públicos vuelvan a su
  página y no al panel. El login del panel enlaza con el registro.
- `tools/verify.php`: 78 → **90** comprobaciones.

**Cómo funciona el alta**

1. El tendero entra con el email y la contraseña de **su cuenta de idirecto**.
2. Se comprueba la cuenta (activa, no cerrada, no borrada) y que esa cuenta no
   tenga ya tienda en la web (si la tiene, se le manda al panel).
3. Se crea `mt_stores` (slug libre, plan por defecto, datos de la cuenta,
   `id_tienda_idirecto` + `id_margen`) y `mt_store_users` (contraseña propia con
   `password_hash`), y se le deja dentro del panel.
4. Su tienda funciona al momento en `https://<slug>.<dominio base>` y ya puede
   enviar pedidos al mayorista sin configurar nada.

**Verificación:** `php tools/verify.php` → TODO OK (90). La cadena completa
(comprobar cuenta → crear tienda → usuario de panel → entrar) se prueba en
`verify.php` dentro de una transacción que se deshace, así que no queda ninguna
tienda de prueba. Con HTTP real se probaron además el formulario (sin token,
contraseñas distintas, credenciales incorrectas y bloqueo por intentos) y el alta
completa: se insertó una **cuenta temporal** en `tiendas` (nombre "VERIFICACION
AGENTE"), se registró la tienda por HTTP (302 → `/panel`, aviso con la URL
pública, `id_tienda_idirecto` ya relleno en Ajustes y storefront respondiendo 200)
y acto seguido se borraron la tienda, el usuario y la cuenta: los contadores de
`tiendas` (9.539), `mt_stores` (1) y `mt_store_users` (1) volvieron a su valor
previo y no queda ningún rastro.

**Nota para el dueño:** el registro se puede cerrar con `IDIRECTO_REGISTER=false`
(la página informa y no da de alta a nadie). Las tiendas creadas a mano en la
base de datos siguen funcionando sin cuenta del mayorista, pero no pueden enviar
pedidos: para eso hay que rellenar el id de cuenta en Ajustes.

---

## 2026-10-01 · Pedidos en el panel y envío por líneas a idirecto

**Motivo:** el dueño pide, en el panel de la tienda, un listado de pedidos con todos
los estados (activos, los ya facturados, borrados…), poder verlos y **enviar a la
tabla `pedidos` de idirecto solo las líneas que elija** (por ejemplo, de un pedido con
4 líneas de productos, mandar 2). Decisiones confirmadas por el dueño antes de
empezar: (1) los pedidos se guardan en tablas propias `mt_` —el mayorista no tiene
pedidos de nuestros clientes—, (2) cada tienda se enlaza con su cuenta del mayorista
desde Ajustes, y (3) el envío **escribe de verdad** en `pedidos`/`pedidos_det`.

**Cambios**

- `database/migrations/003_orders.sql` (nueva, idempotente): columnas
  `mt_stores.id_tienda_idirecto` (cuenta en `tiendas.id`) y `mt_stores.id_margen`
  (tarifa en `precios.id_margen`); tablas `mt_orders` (pedido) y `mt_order_items`
  (líneas, con el envío **por línea**: `sent_at` + `idirecto_pedido_id`).
- `app/Models/Order.php` (nuevo): 7 estados (borrador, activo, preparado, enviado,
  facturado, cancelado, borrado), pestañas del listado, filtros con búsqueda,
  paginación, papelera restaurable y recálculo de totales.
- `app/Models/OrderItem.php` (nuevo): normaliza las líneas del formulario (precio con
  coma, cantidad mínima), resume enviables/enviadas/propias y marca el envío.
- `app/Core/Idirecto/` (nuevo):
  - `Account`: resuelve la cuenta del mayorista de una tienda (tarifa, sucursal,
    comercial, forma de pago, país/provincia y dirección de facturación).
  - `Pricing`: precio de tarifa, coste, almacén, IVA, sujeto y canon de un producto,
    con los mismos criterios que `getPrecio()`/`getCosto()` de idirecto.
  - `OrderGateway`: vista previa (mismos cálculos, sin escribir) y escritura en
    `pedidos_addr`, `pedidos` y `pedidos_det` replicando `guardaPedido()` de
    idirecto, con reserva de stock opcional.
- `app/Controllers/Admin/OrderController.php` (nuevo) y rutas `/panel/pedidos*`:
  listado con pestañas, ficha, alta manual, añadir/quitar líneas, cambio de estado,
  papelera/restaurar, envío al mayorista y buscador JSON de productos.
- Vistas `panel/orders.php`, `panel/order.php`, `panel/order_form.php` y
  `panel/_order_lines.php` (editor de líneas reutilizable); CSS y JS del panel
  (pestañas, editor con buscador y totales en vivo, selección de líneas con los
  totales del envío).
- Ajustes: campos de la cuenta del mayorista (id y tarifa) con validación de que la
  cuenta existe de verdad; menú del panel con **Pedidos** y contador de pedidos
  activos en el resumen.
- `config/idirecto.php` (nuevo) y claves `IDIRECTO_*` en `.env.example`.
- `Catalog::search()` y `OwnProduct::searchForStore()`: búsqueda ligera para el
  selector de productos del panel (el listado del storefront no cambia).
- `tools/verify.php`: 62 → **78** comprobaciones.

**Decisiones de negocio aplicadas**

- La dirección de **facturación** del pedido en el mayorista son los datos de la
  tienda (lo que maneja esta web); la de **envío** es la del pedido si el tendero la
  escribe y, si no, la de la tienda. Así funciona tanto la venta normal como el
  *dropshipping* al cliente final.
- `pedidos_det.costo` es el coste del mayorista y `ganancia = precio de tarifa −
  coste`, igual que en su propio flujo; el pedido va con `web = 1`, `estado = NULL`
  (activo), `referencia = TIENDA-<slug>-<código>` y `comentario` con la tienda, para
  que el mayorista pueda localizarlo.
- La reserva de stock (almacenes tipo 0 y 4) se replica, pero se puede desactivar con
  `IDIRECTO_RESERVE_STOCK=false` sin dejar de crear el pedido.
- **Los productos propios no se envían** al mayorista (no existen en su catálogo): el
  panel los marca y los deja fuera de la selección.

**Verificación:** `php tools/verify.php` → TODO OK (78). Con HTTP real (Apache,
`http://local.tienda`): alta de un pedido con 2 líneas (una de catálogo y una propia),
ficha con la previsión del envío (base 144,74 € + IVA 31,03 € = 178,77 €), pestañas de
estado, buscador, cambio de estado, papelera y restaurar, añadir y quitar líneas, y
**envío real de una sola línea** (creó el pedido #925415 en `pedidos`); después se
borró para no dejar rastro y los contadores de `pedidos` (852.570), `pedidos_det`
(1.407.955) y `pedidos_addr` (1.648.269) volvieron a su valor previo. El envío
completo (parcial, pedido completo, repetición rechazada y reserva de stock) se probó
en una transacción deshecha al final. Los totales del JS se comprobaron con Chrome
headless sobre un banco de pruebas (230,00 + 48,30 + 5,00 = 283,30 € en el editor y
144,74/31,03/178,77 € en el envío).

**Nota para el dueño:** queda en la base de datos un **pedido de ejemplo**
(`P26-00001`, cliente "Cliente de prueba") para poder ver la pantalla con datos; se
puede mover a la papelera o dejar como está. La tienda demo **no** tiene cuenta de
idirecto configurada a propósito: hay que poner el id de cuenta (y, si procede, la
tarifa) en Ajustes antes de enviar nada.

---

## 2026-10-01 · 20 productos por página en los listados

**Motivo:** el dueño ve 12 productos en los listados y quiere 20.

**Cambios**

- `CATALOG_PER_PAGE` 12 → **20** (`.env`, `.env.example` y el valor por defecto de
  `config/catalog.php`). Como la rejilla es fluida (`auto-fill`), 20 llenan 3-4
  filas en escritorio sin tocar el CSS.
- Los **destacados de la portada** pasan a tener su propia clave
  (`CATALOG_HOME_FEATURED`, por defecto 12). Antes reutilizaban
  `catalog.per_page`, así que subir los listados habría alargado la portada sin
  que nadie lo pidiera: es una selección editorial, no un listado paginado.
- `tools/verify.php`: 60 → **62** comprobaciones (productos por página en el
  listado y destacados de portada, para que un cambio de configuración no pase
  inadvertido).

**Verificación:** `php tools/verify.php` → TODO OK (62). Medido con HTTP real:
`/`, `/catalogo`, `?cat=9`, `?subcat=102`, `?q=rtx` y `page=2` → 20 tarjetas por
página (la última página muestra las que quedan); la portada sigue con 12.

---

## 2026-10-01 · Interfaz moderna, white-label y rendimiento del catálogo

**Motivo:** el dueño pide una plantilla de informática (multi-tienda) con estética
tipo Scan/Caseking: sistema de diseño agnóstico de marca y de color, portada con
slider a ancho completo, accesos rápidos a las categorías principales, tarjetas
con especificaciones clave y etiquetas de stock dinámicas, filtros avanzados de
entusiasta (socket, chipset, memoria, precio), microinteracciones, modo oscuro y
todo responsive/optimizado.

**Cambios — sistema de diseño**

- `config/appearance.php` (nuevo): valores por defecto de la plataforma (paletas
  clara y oscura, presets de identidad, escalas de radios, tipografías, anchos).
  Se puede ajustar por `.env` (`THEME_*`).
- `app/Core/Appearance.php` (nuevo): resuelve los tokens (config → `mt_stores` →
  `theme_tokens` JSON), **deriva** hover/activo/suave y el color de texto legible
  (contraste WCAG) y emite el CSS de variables `--c-*`.
- `database/migrations/002_design_tokens.sql` (nueva, idempotente): columnas
  `color_accent`, `color_bg`, `color_surface`, `color_text`, `color_border`,
  `color_scheme`, `radius_scale`, `theme_tokens` y `custom_css` en `mt_stores`.
- `Tenant`: accesores nuevos (`colorAccent`, `colorBackground`, `colorScheme`,
  `radiusScale`, `themeTokens`, `customCss`…).
- `public/assets/css/shop.css`: **reescrito** para consumir solo tokens. Ya no
  queda ni un color literal (antes la ficha estaba fijada a la paleta de
  idirecto). Modo oscuro nativo y microinteracciones en tarjetas, botones,
  filtros y focos.
- `layouts/shop.php`: inyecta los tokens en el `<head>`, `data-color-scheme`,
  conmutador claro/oscuro, `skip-link`, `preconnect` al CDN de imágenes y
  semántica HTML5 (`header`/`nav`/`main`/`footer`).

**Cambios — portada, tarjetas y filtros**

- `themes/idirecto/_hero.php` (nuevo): slider a ancho completo y accesible
  (`hidden` en las diapositivas inactivas, pausa al interactuar, teclado, gesto
  táctil, `prefers-reduced-motion`, primera imagen con `fetchpriority="high"`).
- `themes/idirecto/home.php` + `Catalog::quickCategories()`: franja de garantías y
  **accesos rápidos** a las 5 categorías, resueltos contra el árbol real con stock
  (`config/catalog.php` → `quick_links`).
- `themes/idirecto/_card.php` / `_card_own.php`: tarjeta minimalista con marca,
  nombre, **chips de especificaciones** (`Specs::quickHighlights()`: de
  `caracteristicas` y, si faltan, del nombre), **etiqueta de stock dinámica**
  (`in|low|out`) y CTA al pasar el ratón o enfocar.
- `themes/idirecto/catalog.php`: filtros avanzados como **enlaces** (funcionan sin
  JS y cada combinación tiene URL propia), chips de filtros activos, orden y panel
  lateral en móvil.
- `config/catalog.php`: facetas declarativas (`socket`, `gpu`, `memoria`,
  `factor`, `almacenamiento`), marca dinámica, rango de precio, órdenes y specs de
  tarjeta. `Catalog`: `facets()`, `selectionFromQuery()`, `priceBounds()`,
  `sorts()`, `sortKey()`.
- Panel: `DesignController` reescrito (presets, tokens, validación de JSON y
  limpieza del CSS propio), `panel/design.php` con vista previa en vivo,
  `panel/design_preview.php` (nueva) y `/panel/diseno/tokens` + `diseno/previa`.

**Cambios — rendimiento (lo más importante)**

- `Catalog::stockExistsSql()` pasa de `EXISTS` correlacionado a **semijoin**
  (`p.part_number IN (SELECT …)`). Misma regla y mismo resultado (41.289
  productos), pero el listado del catálogo baja de **~3 s a ~20 ms**.
- La marca se resuelve **por lote** (`attachBrands`) en lugar del
  `LEFT JOIN marcas`, que solo él costaba ~1 s y degradaba el plan.
- Las tarjetas completan stock y especificaciones con **3 consultas fijas** por
  listado (`hydrate`), nunca una por producto.
- El orden por precio se ofrece **solo si el listado está acotado** (sobre las
  41.000 referencias cuesta ~4 s). Se cachean en fichero el contador total (10
  min), los destacados de portada (10 min) y las facetas (15-30 min): el servidor
  de base de datos de esta máquina tiene la caché InnoDB fría a menudo y estos
  recorridos pesados se notaban en la portada.

**Verificación:** `php tools/verify.php` → **TODO OK (62)** (eran 33). Además:
HTTP real (portada ~30 ms, catálogo ~25 ms), navegación con Chrome headless a
360/768/1440 px sin desbordamiento horizontal, panel con sesión real (login,
guardado de preset, tokens JSON válidos e inválidos, vista previa) y comprobación
de que las páginas no emiten avisos de PHP.

---

## 2026-10-01 · Banners de hasta 8 MB, con buena calidad (límites por tipo)

**Motivo:** el dueño quiere admitir banners de hasta 8 MB sin perder calidad al
comprimirlos.

**Cambios**

- `app/Core/Media/MediaRules.php` (nuevo): resuelve peso, calidad y tamaño
  máximo **por tipo** (`config/storage.php` → `storage.types`, que pisa a los
  valores generales). Por defecto los **banners admiten 8 MB**
  (`STORAGE_MAX_BYTES_BANNERS`) y se recomprimen con **calidad 86**
  (`IMAGE_QUALITY_BANNERS`) porque son fotos grandes de portada; el resto sigue
  en 5 MB y 82.
- `MediaUploader` valida contra el límite del tipo y `ImageOptimizer` aplica su
  calidad y su tamaño máximo. El panel avisa **antes** de subir
  (`data-max-bytes` en el input, límite del fichero calculado en el servidor).
- **`.htaccess`**: `upload_max_filesize 12M` y `post_max_size 13M`. Este Apache
  venía con **2M/8M**, así que ningún fichero de más de 2 MB llegaba a la
  aplicación: el ajuste de 8 MB no habría servido de nada. Va dentro de
  `<IfModule>` para no romper si algún día se sirve con PHP-FPM.
  `deploy/nginx-site.conf.tpl` sube `client_max_body_size` a 16M y
  `deploy/setup-nginx-domain.sh` avisa si el `php.ini` de FPM sigue en 2M.
- `MediaUploader::excedePostMaxSize()` + `mensajeLimitePhp()`: mensajes claros
  cuando el fichero no llega (antes el panel decía «no se ha recibido ningún
  fichero», o incluso «Token de seguridad inválido», porque al superar
  `post_max_size` PHP vacía también `$_POST` con el token). La comprobación va
  **antes** del CSRF en `MediaController::upload()`.
- El fallo de CSRF del endpoint de subida responde **403** y no 419: Apache/PHP
  no saben responder 419 y lo convertían en un 500.
- `tools/verify.php`: 32 → **33** comprobaciones (límites por tipo).

**Verificación (HTTP real, sesión de panel + multipart):**

| Caso | Resultado |
|---|---|
| Banner de **6,0 MB** | `ok` · WebP 2560×1024 · 6.328.029 B → 17.144 B (**−99,7 %**) |
| El mismo fichero como `productos` | `422` «La imagen pesa 6,0 MB y el maximo para "productos" es 5,0 MB.» |
| Banner de **12,7 MB** (> `upload_max_filesize`) | `422` con el aviso del límite de PHP |
| Banner de **14 MB** (> `post_max_size`) | `413` con el mismo aviso |
| CSRF inválido / sin fichero | `403` / `422` |

`php tools/verify.php` → TODO OK (33). El fichero subido en la prueba se borró
desde el panel (desaparece del disco y de `mt_media`).

---

## 2026-10-01 · Imágenes de las tiendas: carpetas `tienda_<tipo>`, id en el nombre y WebP

**Motivo:** el dueño quiere que las imágenes que sube cada tienda (banners,
productos, logo…) se guarden separadas por tipo en carpetas con el prefijo de la
tienda, con el **id de la tienda en el nombre del fichero** (para poder
identificarlas y borrarlas después) y **comprimidas**: si sube un JPG, que se
convierta a un formato ligero.

**Cambios**

- `app/Core/Storage/StorageKey.php` (nuevo): una única clave para S3 y local:
  `tenants/tienda_banners/7_banners_20261001-174530-9f3c1a2b.webp`.
  - Carpeta `{STORAGE_FOLDER_PREFIX}_{tipo}` → `tienda_banners`,
    `tienda_productos`, `tienda_logo`…, para listar/borrar por familias.
  - El nombre empieza por el id de la tienda (0 = global) y lleva fecha +
    aleatorio, así que nunca se reescribe un fichero.
  - Sanea los segmentos: un tipo con `../` no puede escapar de la carpeta.
- `app/Core/Media/ImageOptimizer.php` (nuevo): recompone la imagen con GD.
  - **JPG, PNG, AVIF, BMP y WebP → WebP** (calidad 82 por defecto). En la prueba
    real: 203 KB → 15,7 KB (**−92 %**).
  - **SVG** se respeta (vectorial) y **GIF animado** se queda en GIF.
  - Si un PNG ya pesa menos que su WebP (logos, gráficos planos), se guarda el
    original: mejor calidad y menos peso.
  - Corrige la orientación EXIF, limita a 2560 px sin ampliar y **quita los
    metadatos EXIF** (peso y privacidad).
- `app/Core/Media/MediaUploader.php` (nuevo): punto único de subida
  (`upload()` para HTTP, `storePath()` para importaciones/tareas); el
  `MediaController` solo persiste los metadatos en `mt_media`.
- Los drivers (`StorageInterface`, `LocalStorage`, `S3Storage`) reciben ahora un
  **fichero ya preparado en disco**, comparten la clave y S3 sube con
  `Cache-Control: immutable` y metadatos de la tienda. Se pasan las dos
  comprobaciones de MIME/tamaño al servicio de subida (un solo sitio).
- `config/storage.php` + `.env.example`: `STORAGE_PREFIX`,
  `STORAGE_FOLDER_PREFIX`, `STORAGE_MAX_BYTES` y el bloque `IMAGE_*`
  (optimización, calidad, tamaño máximo, GIF animado).
- `panel.js`: al subir una imagen muestra el ahorro
  (`JPG 2,4 MB → WEBP 380 KB, −84 %`).
- **`.htaccess`**: se declaran los tipos MIME `.webp` y `.avif`. Probando con
  HTTP real se vio que este Apache no trae `webp` en `/etc/mime.types` y servía
  la imagen **sin `Content-Type`** (los buscadores y las previsualizaciones de
  WhatsApp/Telegram no la pintan).
- `tools/verify.php`: 29 → **32** comprobaciones (clave `tienda_tipo` con el id,
  clave saneada, el driver S3 usa el mismo esquema, JPG → WebP más ligero, SVG
  sin reconvertir).

**Verificación:** `php tools/verify.php` → TODO OK (32). Subida **real por HTTP**
con sesión en el panel (login + CSRF + multipart): la respuesta fue
`tenants/tienda_banners/1_banners_20261001-172238-b203220e.webp`,
`image/webp`, 203595 B → 15706 B, 2560×1024; el fichero se sirvió con
`Content-Type: image/webp` (200) y el borrado desde el panel lo eliminó de disco
y de `mt_media`. También se comprobó la transparencia de PNG (conservada), el SVG
intacto, el GIF estático convertido y el animado respetado.

---

## 2026-10-01 · Publicar en otro servidor: script de nginx más seguro y Plesk

**Motivo:** el dueño va a poner la web en `https://valduran.com` desde
`/var/www/vhosts/valduran/tienda` (ruta típica de Plesk), sobre un dominio que
antes servía otra web. Hacía falta poder instalarlo sin riesgo y saber qué
comando ejecutar en cada tipo de servidor.

**Cambios** (`deploy/setup-nginx-domain.sh`):

- `--dry-run` (funciona sin `sudo`): muestra lo que haría y el `server` block
  que generaría, sin tocar nada.
- **Detecta Plesk/cPanel y se detiene** (el panel sobrescribe
  `/etc/nginx/plesk.conf.d`), imprimiendo en su lugar las instrucciones del panel
  y las directivas equivalentes para pegar en *Additional nginx directives*
  (bloque `[PLESK]` al final del script).
- `ROOT`, `WEB_USER` y `FPM_SOCK` configurables; también detecta el socket de
  PHP-FPM por dominio de Plesk (`/var/www/vhosts/system/<dominio>/php-fpm.sock`).
- Avisa si **otro sitio ya declara el mismo `server_name`** (la web anterior que
  sigue activa), si existe el sitio `default` o si Apache ocupa el puerto 80.
- Al terminar imprime los pasos que faltan: `.env` de producción,
  `php database/migrate.php --seed`, `certbot` y las comprobaciones con `curl`.
- `deploy/nginx-site.conf.tpl`: el comentario de cabecera ya no contiene los
  marcadores (se sustituían por el dominio/ruta al renderizar).

**Verificación:** `bash -n`, `--dry-run` con la ruta y el dominio reales, y el
fichero generado pasa `nginx -t` con el binario real (raíz, dominio y socket
sustituidos correctamente).

---

## 2026-10-01 · «Error interno»: permisos de `.env` y diagnóstico en el log

**Motivo:** `http://local.tienda/` devolvía `500 «Error interno»`. No era el
código: el proceso de Apache (`www-data`) **no podía leer `.env`** (estaba en
`pablo:pablo 640`, con un grupo distinto y sin ACL). Sin `.env` la app se queda
sin credenciales de BD y sin `APP_DEBUG` (de ahí el mensaje genérico en vez del
detalle), y como tampoco podía escribir en `storage/logs`, el error real no
quedaba registrado en ninguna parte. La base de datos, mientras tanto, ya se
había completado por su lado (239 tablas, catálogo y `mt_`).

**Cambios**

- Permisos corregidos **sin `sudo`** con ACL (`setfacl`): lectura de `.env` para
  `www-data` sin abrirlo a todo el mundo, y escritura (con ACL por defecto) en
  `storage/logs`, `storage/cache` y `public/uploads`. El arreglo «canónico»
  sigue siendo `sudo bash deploy/setup-local-domain.sh`.
- `app/bootstrap.php`: el log propio solo se activa si `storage/logs` es
  escribible; si no, el error va al log del servidor en lugar de perderse.
- `index.php`: si el error salta con `APP_DEBUG` desactivado, la línea del log
  añade una pista cuando `.env` no es legible por el usuario del servidor web.
- Documentación: README («Permisos: si ves “Error interno”») y
  `operacion.md` (tabla de problemas frecuentes).

**Verificación:** reproducido y arreglado con HTTP real. Sin el ACL: `500
«Error interno»` y la pista en `storage/logs/php-error.log`. Con el ACL: `200`
en `/`, `/catalogo`, `/catalogo?cat=`, `/catalogo?subcat=`, `/panel/login` y
`/contacto`; `.env` y `app/` siguen dando `403`. `php tools/verify.php` →
**TODO OK (29 comprobaciones)** y `check-privacidad.sh` OK.

---

## 2026-09-30 · nginx + detección de servidor (dominio valduran.com)

**Motivo:** el dueño va a publicar la web en un servidor Ubuntu con **nginx** en
`valduran.com` (y más adelante quizá con otro dominio), manteniendo el Apache
actual. Hacía falta que el mismo código corriera en los dos sin tocar nada.

**Cambios**

- `app/Core/Server.php` (nuevo): detecta el servidor (Apache, nginx, servidor
  embebido o CLI) y centraliza lo único que cambia entre ellos:
  - la **ruta pública de `/public`** a partir de `DOCUMENT_ROOT` (raíz del
    proyecto o raíz en `public/`);
  - el **esquema real** (`HTTPS`, `REQUEST_SCHEME` de nginx o
    `X-Forwarded-Proto` si hay proxy/CDN);
  - el **host** de las URLs canónicas (`HTTP_HOST` o `X-Forwarded-Host`).
- `asset()` y `absolute_url()` (bootstrap) y la cookie `Secure` de `Session`
  pasan a usar `Server`, así que el storefront y el login funcionan igual en
  Apache, en nginx y detrás de un proxy.
- `deploy/nginx-site.conf.tpl`: `server` block equivalente a `.htaccess` +
  VirtualHost: `try_files` al front controller (`/index.php$is_args$args`),
  único PHP ejecutable (`location = /index.php`), bloqueo de `.env`, `.git`,
  `app`, `config`, `database`, `deploy`, `storage`, `tools` y `vendor`,
  excepción para `/.well-known/acme-challenge/` (certbot), cabeceras de
  seguridad y caché de estáticos 30 días.
- `deploy/setup-nginx-domain.sh`: instalador **idempotente** (dominio por
  defecto `valduran.com`): permisos, autodetección del socket de PHP-FPM
  (con el prefijo `unix:` que exige `fastcgi_pass`), render de la plantilla,
  `nginx -t` y `systemctl reload nginx`.
- `app/Models/Catalog.php`: `isAvailable()` comprueba **todas** las tablas que
  usa el catálogo (`productos`, `stock`, `almacenes`, `precios`, `marcas`,
  `categorias`, `subcategorias`), no solo `productos`. Con una base a medio
  restaurar el storefront devuelve listados vacíos en vez de un error 500.
- `tools/verify.php`: 3 comprobaciones nuevas (servidor detectado, ruta pública
  de `/public` y esquema según `X-Forwarded-Proto`) → **29**. Además resume
  cuántas tablas del catálogo faltan en lugar de morir con un error fatal.
- Documentación: README (§nginx y cómo cambiar de dominio), PROJECT (§ ciclo de
  petición y verificación), STATE, el skill, `.agents/.../operacion.md` y
  `.env.example` (bloque de producción con `valduran.com`).

**Verificación:** la plantilla se validó y se **ejecutó con un nginx real**
(binario 1.18 extraído en `/tmp`, sin instalarlo, escuchando en un puerto alto):

| Petición | Resultado |
|---|---|
| `/public/assets/css/shop.css` | 200 + `Cache-Control` 30 d + cabeceras de seguridad |
| `/.env`, `/app/...`, `/config/...`, `/storage/...`, `/.agents/...`, `/.git/...`, `/README.md` | 404 |
| `/` y `/catalogo` | 502 (socket PHP-FPM simulado: llegan al front controller) |

La detección se probó simulando Apache y nginx en HTTP/HTTPS, tras un proxy con
`X-Forwarded-Proto`/`Host` y con la raíz apuntando a `public/`.

**Nota de entorno:** en esta máquina la base de datos del `.env`
(`idirecto_db`) ya no contiene el catálogo ni las tablas `mt_`, así que la web
responde 500 en local. El dueño pidió **no tocar la base de datos**; el catálogo
está ahora en las bases `idirecto` (235.165 productos) y `dev_idirecto_db`.

---

## 2026-09-29 · Menú de categorías del catálogo (estilo PuntoByZE)

**Motivo:** el dueño pidió mejorar el menú de productos (lo que en la web
llamamos «catálogo») tomando como referencia las categorías y subcategorías de
PuntoByZE (`/var/www/html/puntobyze`), que usa el mismo catálogo central.

**Cambios**

- `app/Models/Catalog.php`:
  - `menuTree()` devuelve el árbol **categoría → subcategorías con stock**
    (33 y 304), con el mismo criterio de visibilidad que el listado, y lo cachea
    30 min en `storage/cache/catalog_menu.json` (la consulta recorre `stock`).
  - `menuLabel()` normaliza los nombres del mayorista, que vienen irregulares
    («ORDENADORES», « TELEFONÍA», «Ocio»): Mayúscula inicial, acrónimos cortos
    en mayúsculas (TPV, USB) y palabras cortas en minúscula («y», «de»...).
  - `subcategory()` y el nuevo parámetro `paginate(..., $subcategoryId)` hacen
    que el filtro `?subcat=` funcione de verdad. Antes la ficha de producto ya
    enlazaba a `?subcat=` pero el catálogo lo ignoraba (bug latente).
  - Se retira `categories()`, sin uso desde que existe `menuTree()`.
- `app/Views/layouts/shop.php`: megamenú en la cabecera (columna de categorías +
  panel de subcategorías en columnas), con disparador propio junto a «Catálogo»
  para no perder el enlace directo al listado completo.
- `public/assets/css/shop.css` y `shop.js`: bloque `catmenu`, con los colores del
  tema de cada tienda. En móvil/tablet pasa a pantalla completa con navegación
  por pasos (categorías → subcategorías) y botón atrás; cierre con Escape,
  backdrop o botón, y `prefers-reduced-motion` respetado.
- `app/Views/themes/idirecto/catalog.php`: el `h1`, las migas de pan y las
  subcategorías del filtro lateral reflejan la categoría/subcategoría activas;
  el selector de categorías sale del árbol (sin categorías vacías).
- `tools/verify.php`: 2 comprobaciones nuevas (árbol de categorías y filtro por
  subcategoría) → **26**.

**Verificación:** con Chrome headless (`/var/www/html/tienda`): el megamenú abre,
cambia de categoría, sincroniza `aria-expanded` y cierra en 1440/1920 px; en
390/768 px entra en modo pasos y vuelve con el botón atrás; 0 desbordamiento
horizontal. `php tools/verify.php` → TODO OK (26 comprobaciones).

---

## 2026-09-29 · Layout ancho en monitores grandes

**Motivo:** en monitores de alta resolución el contenedor se quedaba en 1180 px
y dejaba márgenes vacíos enormes a los lados (690 px por lado en una pantalla de
2560 px). El dueño pidió un ancho mayor, fluido y de inspiración PcComponentes,
sin romper tablet ni móvil.

**Cambios**

- `public/assets/css/shop.css`: `.container` pasa de `min(1180px, 94%)` a un
  ancho fluido (`min(1600px, 94%, max(1180px, 92%))`) que crece con la pantalla
  y en monitores ultra anchos sigue subiendo hasta 1800 px sin saltos. Los
  topes viven en las variables `--container-min/-max/-ultra`.
- Catálogo: los filtros dejan de ir en la cabecera y pasan a una **columna
  lateral sticky** de 260 px en escritorio (`catalog.php` + `.catalog-layout`,
  `.catalog-aside`, `.filters-aside`); el listado se queda con el ancho restante.
  En móvil y tablet se apilan como antes.
- `.grid-products`: en ≥1440 px sube el mínimo de tarjeta (200 → 210 → 220 px)
  para ganar elementos por fila sin que las cards queden diminutas.
- Ficha de producto: en ≥1280 px la galería y la información ganan proporción y
  la columna de compra se mantiene compacta
  (`minmax(0, 1.15fr) 1fr minmax(300px, .85fr)`); la imagen de la galería se
  limita a 520 px de alto.
- Banner y cabecera: el hero gana algo de alto en pantallas grandes; en móvil el
  buscador salta a una segunda línea. Esto último corrige un **desbordamiento
  horizontal preexistente** de la cabecera en pantallas estrechas (360 px).
- `contact-grid` se limita a 1100 px para que el formulario no se estire con el
  contenedor ancho; la prosa ya estaba limitada a 75ch.

**Verificación:** medido con Chrome headless de 360 a 2560 px: contenedor de
338/722/963/1180/1325/1600/1800 px, **0 desbordamiento horizontal** en portada,
catálogo, catálogo filtrado, contacto y ficha; móvil y tablet conservan el ancho
anterior. `php tools/verify.php` → TODO OK (24 comprobaciones).

---

## 2026-09-29 · Documentación interna para agentes

**Motivo:** el dueño pidió un sistema para que otro agente entienda el proyecto y,
con un prompt suyo, pueda continuarlo; y que esa documentación no fuera accesible
desde la web.

**Cambios**

- Nuevo directorio `.agents/` con `README.md` (índice y protocolo), `PROMPT.md`
  (la petición del dueño), `PROJECT.md` (arquitectura), `STATE.md`, este
  `CHANGELOG.md`, el Agent Skill `tienda-plataforma` y scripts de instalación y
  verificación.
- **Seguridad:** `.htaccess` bloquea ahora `.agents`, `.git`, `app`, `config`,
  `database`, `deploy`, `storage`, `tools` y `vendor`, amplía los tipos de
  fichero denegados y fuerza `-Indexes`.
- **Seguridad (fallo real):** `index.php` servía como estático **cualquier**
  fichero existente en el servidor embebido de PHP, incluido `.env` con las
  credenciales de la base de datos. Ahora solo se sirve `/public`.
- El VirtualHost de despliegue incorpora un `<DirectoryMatch>` de refuerzo.

**Nota:** el bloqueo en el VirtualHost ya aplicado requiere volver a ejecutar
`deploy/setup-local-domain.sh`; el de `.htaccess` está activo de inmediato.

---

## 2026-09-29 · Listados con la imagen de talla `l`

**Motivo:** las tarjetas usaban la variante `c` (~3 KB), demasiado pequeña al
ampliarse en la tarjeta.

**Cambios**

- `Catalog::decorate()` usa la talla de `CATALOG_CARD_IMAGE_SIZE` (por defecto
  `l`). Afecta al catálogo, a las búsquedas y a los destacados de portada.
- Verificado: `c` = 2.741 B · `l` = 7.093 B · `f` = 38.343 B para la misma foto.

---

## 2026-09-29 · Ficha de producto igual que idirecto

**Motivo:** el dueño comparó su ficha con la real y las imágenes "se veían feas",
no había un slider como en idirecto y las especificaciones no se parecían.

**Investigación:** se descargó el CSS real de idirecto (`ficha-puntobyze.css`,
`ficha-tecnica.css`) y sus bloques `<style>` en línea.

**Hallazgos**

- idirecto **no usa flechas** en la galería: se navega solo con las miniaturas.
- El carrusel usa la talla `f`, las miniaturas la `c` y el zoom la `g`.
- Las especificaciones son tarjetas blancas de radio 16 px **sin rayado**, con el
  valor alineado a la derecha en negrita.
- Su tabla `productos_atributos` está prácticamente vacía, así que el resumen
  cae a Marca/Modelo.

**Cambios**

- Estilos de la ficha reescritos con los valores reales de idirecto.
- Galería: carrusel horizontal (`translateX`) navegado por miniaturas; se
  eliminan flechas, contador y botón de zoom superpuestos.
- Especificaciones: tarjetas agrupadas `pb-spec-card`, "De un vistazo" con
  iconos, tarjetas con caja de icono y descripción corta con viñeta cian.
- El JS retira del carrusel y de las miniaturas las fotos que dan 404, y
  reutiliza la imagen grande si solo falta la miniatura.
- `asset()` añade `?v=<filemtime>` para evitar servir CSS/JS cacheados.

---

## 2026-09-29 · URLs SEO, filtro de stock y dominio local

**Motivo:** el dueño pidió URLs con el nombre del producto ("como idirecto"),
mostrar los mismos productos que idirecto **cuando tienen stock**, y poder
entrar desde el navegador en `http://local.tienda`.

**Cambios**

- `/producto/{slug}/{id}` con `Str::slugify()` (mismo criterio que idirecto),
  301 a la canónica y `<link rel="canonical">` absoluto.
- Catálogo filtrado por stock: de 233.773 a **39.437** productos.
- **Bug corregido:** el buscador devolvía 500 porque se repetía el parámetro
  `:q` cuatro veces y PDO (sin emulación) no lo permite.
- Entorno local: `/etc/hosts` + VirtualHost + permisos para `www-data`.
- **Bug corregido:** Apache no leía `.env` (estaba en `600`) y daba *Access
  denied* de MySQL.
- **Bug corregido:** el login del panel no persistía porque mod_php (PHP 8.4)
  añade `;HttpOnly;Secure;SameSite=None` y el navegador descarta la cookie
  `Secure` en HTTP. Se elimina ese sufijo con `Header edit` en `.htaccess`.

---

## 2026-09-29 · Reversión de idirecto y arranque del proyecto nuevo

**Motivo:** el planteamiento inicial (multi-tienda dentro de idirecto) se
sustituyó por un **proyecto nuevo e independiente**.

**Cambios**

- Se revirtieron todos los cambios en `/var/www/html/idirecto` (código y
  esquema: vuelta a las 226 tablas originales, repositorio limpio).
- Se creó `/var/www/html/tienda` con el stack actual, 13 tablas `mt_` y semillas.
- Commit inicial y publicación en GitHub.

**Decisión de diseño:** misma base de datos central con prefijo `mt_`, sin
framework, resolución de tienda por hostname.
