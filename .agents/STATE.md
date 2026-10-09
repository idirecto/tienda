# STATE — Estado del proyecto

> **El agente actualiza este fichero al terminar cada sesión.**
> Última actualización: **2026-10-09**

---

## Resumen

La plataforma está **funcionando** en http://local.tienda: storefront con
catálogo real, ficha de producto, panel completo y **pedidos con envío por líneas
al mayorista**. Falta el ciclo de compra en la tienda (carrito y pago) para que
los pedidos entren solos: hoy se dan de alta a mano o los meterá el futuro
checkout por el mismo modelo (`mt_orders`/`mt_order_items`).

> ✅ **Entorno (2026-10-03).** `idirecto_db` está **completa** (239 tablas:
> catálogo con 41.289 productos con stock, 33 categorías con stock, y las `mt_`),
> la web responde 200 y `php tools/verify.php` da **TODO OK (232)**. El menú se
> verificó además **en navegador real** (Chrome headless por CDP): 22 comprobaciones
> con el Menú Catálogo, 16 con el Menú Compacto y 20 del **editor del panel**
> (drag & drop incluido), sin errores de JavaScript. El **parpadeo** del Menú Compacto
> al pasar el ratón por una categoría se reprodujo y se corrigió (ver más abajo).
> El **buscador en vivo** también se probó en navegador real: teclear en la cabecera
> abre el panel, filtra por subcategoría/marca, cierra con Escape/clic fuera y no
> genera errores de JavaScript.
> El **HTML del storefront** ya no tiene elementos decorativos vacíos (punto de stock,
> caret y flecha del menú, progreso del slider) ni enlaces `tel:` mal formados; los
> avisos de atributos SVG/ARIA del validador de Chrome son falsos positivos.
>
> ⚠️ **Si vuelve a salir un 500 con «Error interno»**: casi siempre es que el
> usuario del servidor web (`www-data`) **no puede leer `.env`**, no un fallo de
> código. El log `storage/logs/php-error.log` ya lo dice con una pista. Arreglo:
> `sudo bash deploy/setup-local-domain.sh` o, sin sudo,
> `setfacl -m u:www-data:r-- .env` (más `rwX` para `storage/{logs,cache}` y
> `public/uploads`). En esta máquina está aplicado por **ACL** (el grupo del
> fichero es `pablo`, así que `getfacl .env` muestra `user:www-data:r--`).

| Área | Estado |
|---|---|
| Multi-tienda (resolución por hostname) | ✅ Completo |
| **Registro de tiendas (solo con cuenta activa del mayorista)** | ✅ Completo |
| **Compra del cliente final (carrito, cuenta, direcciones y pedido)** | ✅ Completo (**sin pasarela de pago**) |
| **Carrito: botón «Agregar al carrito» + mini-carrito lateral (AJAX, responsive, stock real)** | ✅ Completo |
| Catálogo central (stock, búsqueda, paginación) | ✅ Completo (**listados de 40 productos y tarjeta entera enlazada**, ~20-60 ms) |
| **Navegación: Menú Compacto y Menú Catalogo (un solo árbol de 3 niveles)** | ✅ Completo (14 categorías / 83 grupos / 346 destinos) |
| **Menú administrable: editor, borrador/publicación, visibilidad por tienda y nivel de cliente** | ✅ Completo (panel de tienda + panel de plataforma con rol `platform`) |
| **La tienda elige qué categorías se ven (modo completo/elegido), las oculta o renombra, y crea categorías propias (`/propios`, páginas, enlace libre)** | ✅ Completo |
| **Rutas SEO de catálogo (`/categoria/subcategoria/m/marca/orden/…`)** | ✅ Completo (301 desde las URLs con query string) |
| **Etiquetas de listado (Ofertas, Novedades, Destacados) y directorio de marcas** | ✅ Completo |
| Ficha de producto (galería + especificaciones) | ✅ Completo (colores ya tokenizados) |
| **Sistema de diseño white-label (tokens, claro/oscuro, presets, previa en vivo)** | ✅ Completo |
| **Portada (slider + accesos rápidos) y tarjetas con specs y stock** | ✅ Completo |
| **Filtros del mayorista por categoría/subcategoría (filtros/subfiltros)** | ✅ Completo (contadores con stock, OR en grupo y AND entre grupos, ruta SEO `/f/…`) |
| **Filtros avanzados (socket, gráfica, memoria, formato, almacenamiento, marca, precio)** | ✅ Completo (**ahora sí filtran**; el WHERE los ignoraba) |
| **Buscador del catálogo (en vivo, estilo PuntoByZE)** | ✅ Completo (solo el catálogo visible en el menú de la tienda + sus productos propios; `/buscar/live`; **rejilla adaptativa: 1 tarjeta por línea en el móvil y 5-7 en un monitor grande**) |
| Panel de la tienda (diseño, banners, avisos, productos, dominios, ajustes) | ✅ Completo (sin usuario demo: se crea en `/registro`) |
| **Pedidos: listado por estados, ficha y envío por líneas a idirecto** | ✅ Completo |
| Dominios propios + verificación DNS | ✅ Completo |
| Almacenamiento (local / S3) | ✅ Completo (S3 sin probar contra bucket real) |
| Subida de imágenes (carpetas `tienda_<tipo>`, id en el nombre, WebP) | ✅ Completo |
| **Favicon: el de cada tienda con el predeterminado de Valduran de respaldo** | ✅ Completo (configurable por `BRAND_FAVICON*`; la tienda solo cambia el suyo) |
| Entorno local (`http://local.tienda`) | ✅ Completo |
| **Despliegue Apache y nginx** | ✅ Completo (nginx verificado con binario real) |
| **Checkout: carrito, pago y envío** | ✅ Completo (cobro **sin pasarela**: transferencia, contra reembolso y recogida) |
| **Precio de venta de la tienda** | ✅ Completo: tarifa (`id_margen`) + beneficio (`mt_stores.markup`), con IVA incluido en la web y base sin IVA en el pedido |
| **Gastos de envío** | ⚠️ Tarifa plana + gratis desde X €; falta el cálculo por **peso y provincia** de puntobyze |
| **Emails al cliente y a la tienda** | ❌ No existe (no hay envío de correo: ni confirmación ni recuperar contraseña) |
| **Logs por día y por tienda (compras, accesos y errores)** | ✅ Completo (ficheros en `storage/logs/<canal>/<día>/<tienda>.log` + visor `/panel/logs` + `tools/logs.php`; retención 30 días) |
| Temas `moderno` y `minimal` | ⚠️ Sembrados, sin maquetar (los tokens valen para cualquiera) |
| Panel maestro del mayorista | ❌ No existe (el alta pública ya enlaza cada tienda con su cuenta) |
| Tests automatizados / CI | ❌ No existe |

---

## Hecho

### Infraestructura
- MVC propio con autoload PSR-4 (`Tienda\` → `app/`), sin framework.
- 15 tablas `mt_` idempotentes + semillas (3 planes, 3 temas, tienda demo).
- Multi-tenant por hostname (subdominio → dominio propio verificado → demo).
- Sesión, CSRF y `password_hash` en el panel.
- Almacenamiento conmutable local/S3 (en BD solo claves y URLs).
- Verificación DNS (A/CNAME) con *lookup* inyectable para poder testear sin red.

### Favicon: el de la tienda con el predeterminado de Valduran de respaldo (2026-10-09)
- **`Core/Favicon` es la única fuente de verdad.** Resuelve el favicon **efectivo**: el propio de la
  tienda si existe y está disponible; si no (vacío, URL no válida, fichero borrado o remoto que no
  carga), el **predeterminado de Valduran**. Nunca devuelve `href` vacío ni roto: como último
  recurso usa un SVG en línea. Emite las etiquetas `<link>` y valida las URLs (solo rutas del sitio
  sin `..` y `http(s)`; nada de `javascript:`/`data:`).
- **Predeterminado configurable** en `config/brand.php` + `.env`: `BRAND_FAVICON`,
  `BRAND_FAVICON_EXTRA`, `BRAND_FAVICON_APPLE`, `BRAND_FAVICON_VERSION` (ruta relativa a `public/` o
  URL absoluta). Se acompaña de un recurso nuevo en el proyecto
  (`public/assets/img/favicon-valduran.{ico,svg}` + `-180.png`, ICO multi-tamaño).
- **Migración `010`**: `mt_stores.favicon_key` (para comprobar que el fichero sigue ahí y poder
  borrarlo) y `mt_stores.favicon_version` (sello de tiempo **monótono** que se añade como `?v=`).
- **La tienda solo cambia el suyo**: en **Panel > Diseño** hay una tarjeta con vista previa,
  subida (tipo `favicon`, 512 KB / 512 px / calidad 92) y «Quitar mi favicon y usar el
  predeterminado». Al sustituir se borra el fichero anterior (y su fila de `mt_media`). El
  predeterminado no aparece como campo editable y el panel avisa de que es global.
- **Layouts y red de seguridad**: el storefront, el panel y el login emiten el favicon resuelto; y
  `GET /favicon.ico` / `/favicon.svg` (302 con caché de 5 min) garantizan que ese sondeo del
  navegador **nunca** devuelve 404.
- **Caché razonable**: la versión en la URL invalida solo lo que cambia; `.htaccess` añade
  `ExpiresByType` de 30 días para imágenes, igual que la plantilla de nginx.
- **Pruebas**: `verify.php` 290 → **312** (TODO OK) y QA en **HTTP real** con dos tiendas y un
  usuario temporales (borrados): 27 comprobaciones del flujo completo (casos 1-4, sustitución, ver
  desaparecer el fichero, quitar el favicon y aislamiento con la otra tienda y el global).

### Registro de logs por día y por tienda (2026-10-08)
- **Todo en ficheros, sin tabla nueva**: una línea JSON por evento en
  `storage/logs/<canal>/<AAAA-MM-DD>/<id-tienda>_<slug>.log`. Así el log no depende de la base de
  datos que intenta diagnosticar y se puede leer con `tail`, `grep` o `jq`.
- **`Core/Logger`**: niveles PSR-3 (`debug`…`critical`), canales (`compras`, `pedidos`, `acceso`,
  `seguridad`, `panel`, `catalogo`, `sistema`), contexto (petición, tienda, IP, método, URL,
  usuario) y **redacción de secretos**. La tienda del evento manda; si no, la de la sesión del
  panel; si no, la del hostname. Nunca rompe la petición y captura también los errores **fatales**
  de PHP.
- **`Core/Log/LogReader`**: días, canales y tiendas con log; filtros por fecha, canal, nivel
  mínimo, tienda y texto libre; paginación y conteos. El panel y la consola leen de aquí.
- **Compras instrumentadas de punta a punta**: carrito (producto no disponible, recorte por
  stock), checkout (pedido registrado, compra rechazada y fallo inesperado con contexto), envío a
  idirecto y cambios de estado/cobro. Además: entradas al panel y a la cuenta del cliente,
  registros de tienda y CSRF inválido.
- **Visor `Panel > Logs`** (`/panel/logs`): resumen por nivel, filtros, detalle del contexto y
  ruta del fichero. **Aislamiento**: cada tienda ve solo la suya (el `store_id` sale de la sesión
  y el parámetro `tienda` se ignora); el rol `platform` ve todas y puede limpiar. Tarjeta
  «Incidencias hoy» en el panel.
- **`tools/logs.php`** para consola (`--tienda`, `--fecha`, `--desde/--hasta`, `--canal`,
  `--nivel`, `--q`, `--limit`, `--resumen`, `--dias`, `--tiendas`, `--gc`).
- **Configuración**: `config/log.php` y claves `LOG_*` (interruptor, nivel, carpeta, retención de
  30 días e intervalo de limpieza). `Logger::gc()` borra los días viejos.
- **Pruebas**: `verify.php` 261 → **290** (TODO OK), en carpeta temporal para no ensuciar los logs
  reales, y HTTP real (compra, CSRF, visor y aislamiento con una tienda y un usuario temporales).

### Registro de tiendas (solo clientes del mayorista)
- **`/registro`** (público): el tendero entra con el email y la contraseña de **su
  cuenta de idirecto** y se crea su tienda. Sin cuenta activa no hay tienda: es
  la conexión que faltaba con la tabla `tiendas`.
- Se comprueba con el criterio de idirecto (`activo = 2`, ni cerrada ni borrada) y
  su misma firma de contraseña (`hash('sha256', md5(sha1($clave)))`, **no** es
  `password_hash`); la contraseña del mayorista no se guarda.
- La tienda nace **activa y enlazada** (`id_tienda_idirecto` + `id_margen`), con
  los datos fiscales y de contacto de la cuenta (razón social, NIF, dirección,
  provincia y país resueltos por id), plan por defecto, slug único y su usuario de
  panel con contraseña propia. Al terminar queda dentro del panel y su storefront
  ya funciona.
- Una cuenta = una tienda: si esa cuenta ya tiene tienda, se le manda al panel.
- Límite de intentos por sesión (5 / 15 min) y registro de los rechazos en el log,
  para que el formulario no sirva para probar contraseñas del mayorista.

### Compra del cliente final
- **Carrito** en la sesión y por tienda (`Core/Cart`): solo guarda producto y
  cantidad; el precio, el nombre y el stock se leen **en vivo** del catálogo, así
  que nunca se compra a un precio viejo. Se añade desde la ficha y desde las
  tarjetas, y funciona sin JavaScript.
- **Cuenta del cliente** (`Core/CustomerAuth` + `mt_customers`), independiente de
  la del tendero: registro, entrada (con límite de intentos), salida, mis datos,
  **mis pedidos** y **libreta de direcciones** (alta, edición y borrado, con
  dirección de envío/facturación por defecto). Se puede comprar **como invitado**:
  en ese caso se crea el cliente con el email y su dirección, y si luego se
  registra con el mismo email **reclama la cuenta** y conserva sus pedidos.
- **Checkout** (`Core/Checkout`): dirección de envío de la libreta o nueva,
  facturación distinta si se quiere, forma de pago (transferencia, contra
  reembolso o recogida) y **comentario**. Crea `mt_orders` + `mt_order_items` con
  los importes recalculados en el servidor; el pedido nace **pendiente de pago** y
  el tendero lo confirma en el panel («marcar como pagado»).
- **Precio de venta**: `tarifa de la tienda (mt_stores.id_margen) + beneficio
  (mt_stores.markup)`, con el **IVA incluido** en la web y la **base sin IVA** en
  el pedido (que es lo que se manda al mayorista). Los gastos de envío son tarifa
  plana + gratis desde X € (en Ajustes). Todo se ve en el carrito antes de pagar.
- **Al mayorista le llega la dirección del cliente** (no la de la tienda), con sus
  ids de país/provincia/población, celular, el email en `localidad` y su comentario
  en `pedidos.detalles`, igual que hace puntobyze.
- **Panel**: sección **Clientes** (listado con buscador y ficha con direcciones y
  pedidos) y bloque **Venta y cobro** en Ajustes.

### Carrito: botón «Agregar al carrito» y mini-carrito lateral (2026-10-05)
- **Botón** con el texto exacto **«Agregar al carrito»** en la ficha, las tarjetas del
  catálogo y las de productos propios, con estados normal/hover/activo/**carga**,
  confirmación visual y sin dobles altas (se bloquea mientras la petición está en vuelo;
  el servidor, además, suma en la línea existente y nunca duplica).
- **Stock real en el carrito**: `Catalog::stockTotal()` y `Cart` lo aplican al añadir y al
  cambiar cantidades (con aviso en español). Antes el catálogo no se recortaba por stock.
  Los propios con `stock = 0` siguen significando «la tienda no lleva stock».
- **AJAX sin carrito paralelo**: los endpoints de siempre (`/carrito/anadir`,
  `/carrito/actualizar`, `/carrito/quitar`, `/carrito/vaciar`) responden JSON cuando la
  petición es AJAX (`Controller::isAjax()`) y siguen redirigiendo sin JavaScript; el CSRF
  inválido responde 403 JSON. `GET /carrito/mini` es solo lectura y devuelve el partial
  `_mini_cart.php` con el carrito de la sesión.
- **Mini-carrito lateral**: drawer fijo, 380-460 px en escritorio, adaptado en tablet y
  **100 % en móvil**, `100dvh`, safe-areas, sin scroll horizontal, cabecera y pie fijos con
  **lista de scroll propio**; abre tras un alta correcta y cierra con X, Escape y clic
  fuera, devolviendo el foco y **liberando el scroll**. Cada línea trae imagen, nombre,
  SKU, precio, +/−, cantidad editable (mín. 1, máx. stock), subtotal, total y quitar, con
  aviso flotante de éxito/error. CTA «Ver artículos del carrito» / «Ver carrito».
- **Enlace de respaldo**: el icono del carrito de la cabecera abre el mini-carrito con JS
  y sigue siendo un enlace a **`/carrito`** sin JS.
- `verify.php` 232 → **244** (TODO OK), probado en Chrome real por CDP (tarjetas, ficha,
  buscador en vivo y categorías; doble clic; error que no abre el panel; medidas a 1440,
  768, 390 y 844×390; sin errores de JavaScript).

### Catálogo: 40 productos por página y tarjeta entera enlazada (2026-10-05)
- **40 productos por página** en todos los listados (`CATALOG_PER_PAGE`, `.env`,
  `.env.example` y valor por defecto de `config/catalog.php`); tope de seguridad
  120 en `Catalog::paginate()`. El límite lo fija el **servidor para todas las
  pantallas** (una URL canónica por página, sin duplicados); el número de
  **columnas** sí es responsive (1 en móvil, 3-4 en tablet, 4-5 en escritorio).
  Medido: 40 productos = 7-9 filas y la consulta sigue en ~40-90 ms.
- **El buscador en vivo** se separa (`CATALOG_SEARCH_PER_PAGE`, 16) para que
  subir los listados no alargue el desplegable. Los destacados de portada siguen
  en `CATALOG_HOME_FEATURED` (12).
- **Paginación común** (`_pagination.php`) para catálogo y productos propios:
  Anterior/Siguiente siempre visibles (desactivados en los extremos), ventana de
  números con `…`, `aria-current="page"`, `rel="prev"`/`rel="next"` y
  «Página X de Y · N productos». Enlaces reales, sin JavaScript, que conservan
  categoría, subcategoría, filtros, orden, etiqueta y búsqueda. Las páginas
  fuera de rango responden **404** (antes devolvían una página vacía con 200).
- **Toda la tarjeta lleva a la ficha**: el enlace estirado del nombre (`z-index`
  por encima de la imagen y por debajo de las acciones) hace clicables imagen,
  marca, especificaciones y precio con **un solo `<a>`** (sin enlaces anidados);
  «Ver ficha» sigue siendo un enlace hermano y «Agregar al carrito» un botón
  independiente. Verificado con clics reales (CDP): imagen/marca/specs/precio/
  «Ver ficha» navegan, el carrito añade y abre el mini-carrito sin navegar, en
  móvil y en escritorio con hover.
- Rendimiento: la primera fila de imágenes carga de inmediato
  (`fetchpriority="high"` en la primera) y el resto en diferido. Página de
  categoría: 40 tarjetas, ~0,2 s y +13 % de HTML que con 20 (el peso lo domina
  el megamenú, no las tarjetas).
- `verify.php` 244 → **250** (TODO OK), con comprobaciones nuevas del límite, de
  la paginación común y del apilado de la tarjeta.

### Nivel de cliente: aviso accionable (2026-10-08)
- **El aviso del menú ya dice qué falta y dónde arreglarlo**: `Menu::storeLevelStatus()`
  devuelve el nivel, el id de cuenta y un **motivo** (`sin_cuenta`, `cuenta_inexistente`,
  `sin_categoria`, `categoria_invalida`); `Menu::levelWarning()` lo traduce a una frase. El
  bloque «Nivel de cliente de la tienda» del editor del menú enlaza a **Ajustes** cuando el
  arreglo está en nuestra mano (falta la cuenta o su id no existe).
- **Ajustes → Cuenta en idirecto** muestra el **nivel resuelto** (o «sin asignar») y, cuando
  falta, el aviso con el motivo; el panel de plataforma explica el caso concreto.
- El nivel **no se edita** en nuestro panel: sale de `tiendas.id_categ_cliente` de la cuenta
  del mayorista (tabla de **solo lectura**), que asigna idirecto en su ficha de cliente.
  `storeLevel()` pasa a ser un envoltorio de `storeLevelStatus()`, así que el menú se
  comporta igual que antes.
- `verify.php` 255 → **261** (TODO OK) con 6 comprobaciones; probado en **HTTP real** con una
  tienda y un usuario temporales (borrados después) en las dos ramas: cuenta sin categoría
  (mensaje con `#cuenta`, sin botón) y tienda sin cuenta (mensaje + botón «Ir a Ajustes»).

### Sin credenciales demo en el acceso (2026-10-08)
- **El escaparate ya no enlaza al panel**: se retira el botón «Mi panel» de la
  cabecera (`layouts/shop.php`) y sus reglas `.btn-panel` en `shop.css`. Al panel
  se entra escribiendo la URL `/panel`.
- **El login no publica credenciales**: `panel/login.php` va sin `value` en los
  campos y sin el aviso «Demo: `admin@demo.test` / `demo1234`». Quien no tenga
  tienda todavía puede registrarse en `/registro`.
- **Usuario demo borrado de la base de datos** (1 fila de `mt_store_users`):
  `admin@demo.test` ya no existe y esas credenciales responden «Credenciales
  incorrectas». Se conserva la tienda demo (escaparate) y el usuario de
  plataforma `plataforma@local.test` (rol `platform`).
- **La semilla ya no lo recrea**: se quitó el `INSERT` de `001_seed.sql`; una
  instalación nueva crea su usuario al registrar la tienda en `/registro`.
- `verify.php` 250 → **255** (TODO OK), con 5 comprobaciones de regresión
  (layout, CSS, login, semilla y base de datos). Probado en HTTP real.

### Storefront
- Catálogo filtrado a **productos con stock**, con las mismas condiciones que
  idirecto: 41.289 de 235.165 productos; contador cacheado 10 min.
- **Sistema de diseño tokenizado** (`config/appearance.php` + `Core/Appearance`):
  el CSS no tiene ni un color a mano; cada tienda define marca (`primary`,
  `secondary`, `accent`), superficies, texto y bordes, más modo
  (`light|dark|auto`), forma y tipografía desde el panel. Los tonos derivados
  (hover, suave, contraste legible) se calculan en PHP; `theme_tokens` (JSON)
  permite pisar cualquier variable sin tocar código.
- **Modo oscuro nativo** con conmutador en la cabecera (preferencia del visitante
  en `localStorage`, aplicada antes de pintar) y `auto` siguiendo al sistema.
- **Portada**: slider a ancho completo (accesible, con pausa al interactuar,
  teclado, gesto táctil y `prefers-reduced-motion`), franja de garantías,
  **accesos rápidos** a las 5 categorías principales (resueltos contra el árbol
  real con stock) y destacados.
- **Tarjetas de producto**: marca, nombre, **chips de especificaciones**
  (`Specs::quickHighlights`, de `caracteristicas` y, si faltan, del nombre),
  etiqueta de stock dinámica (`En stock` / `Últimas N ud.` / `Sin stock`) y CTA
  al pasar el ratón o enfocar. Tres consultas fijas por listado.
- **Filtros avanzados**: socket, gráfica, memoria, factor de forma,
  almacenamiento, marca (dinámica, cacheada) y precio, con chips de filtros
  activos, orden (incluido precio cuando el listado está acotado) y panel lateral
  en móvil. Los filtros son enlaces: funcionan sin JavaScript y cada combinación
  tiene URL propia. **Corregido (2026-10-02):** el WHERE del listado leía mal la
  selección y los ignoraba; ahora filtran de verdad y los contadores de marca se
  calculan en el contexto del listado (etiqueta, búsqueda y resto de filtros).
- **Filtros reales del mayorista en cada subcategoría** (`filtros`/`subfiltros` +
  `rel_filtros_subcat`/`rel_filtro_producto`, solo lectura): se pintan con el
  número de productos con stock, **OR dentro del mismo filtro y AND entre
  filtros**, ruta SEO `/categoria/subcategoria/f/{filtro}-{subfiltro}`, chips y
  caché de 1 h. El mismo modelo que usan idirecto y puntobyze. Ejemplo:
  `/componentes/tarjetas-graficas/f/28-210` (NVDIA) → 81 productos.
- **Alcance por subcategoría (corregido 2026-10-03)**: las facetas con `when`
  solo salen dentro de su subcategoría (`subcats` manda sobre `cats`); sin
  subcategoría quedan solo marca y precio (como puntobyze). Antes «Socket»
  aparecía en Tarjetas Gráficas o Memoria PC por coincidir la categoría.
- **Filtrado progresivo (corregido 2026-10-03)**: al elegir marca/filtro/precio,
  los grupos sin selección ocultan las opciones que darían 0 productos y
  recalculan sus contadores en contexto; los grupos ya elegidos conservan todas
  sus opciones. Una sola consulta cacheada 600 s.
- **La etiqueta y la marca ya no se pierden** en los enlaces: `/ofertas` pagina y
  ordena sin volver al catálogo completo, y la página de una marca conserva
  facetas, precio y orden.
- Buscador, filtro por categoría y **subcategoría** (`?subcat`) y paginación,
  con **40 productos por página** (`CATALOG_PER_PAGE`; tope duro 120 en
  `Catalog::paginate`). Las columnas de la rejilla sí son responsive, pero el
  número de productos lo fija el servidor para todas las pantallas (una sola
  URL canónica, sin duplicados). El **buscador en vivo** tiene su propio tamaño
  (`CATALOG_SEARCH_PER_PAGE`, 16) y los **destacados de la portada** van aparte
  (`CATALOG_HOME_FEATURED`, 12) porque son una selección editorial, no un
  listado.
- **Menú de categorías (megamenú)** en la cabecera, estilo PuntoByZE: categorías
  + subcategorías con stock (33 y 304), cacheadas 30 min; en móvil pantalla
  completa con botón atrás. El catálogo lista las subcategorías de la categoría
  activa y las migas de pan ya funcionan.
- URLs SEO `/producto/{slug}/{id}` con 301 a la canónica y `<link rel="canonical">`.
- Ficha con la **presentación calcada de idirecto** (carrusel por miniaturas,
  especificaciones agrupadas, «De un vistazo», descripción y opiniones) pero con
  los colores del tema.
- Productos propios de la tienda mezclados con el catálogo central.
- Banners, avisos y páginas de contenido.
- **Layout fluido en pantallas grandes**: el contenedor general crece de 1180 a
  1800 px según el monitor; el catálogo usa filtros laterales (sticky) y la
  ficha reparte mejor galería / información / compra. Responsive intacto de
  360 px en adelante (probado sin desbordamiento horizontal a 360/768/1440).

### Panel
- Login, dashboard con contadores, **editor de identidad visual** (presets,
  colores de marca y avanzados, modo claro/oscuro/auto, forma, tipografía,
  tokens JSON y CSS propio) con **vista previa en vivo** en un iframe que usa el
  mismo generador de tokens que la web pública.
- CRUD de banners, avisos y productos propios con **cuota por plan**.
- Subida de imágenes con validación de MIME real y vista previa.
- Alta de dominios propios con instrucciones DNS y verificación.

### Menú administrable desde el panel (2026-10-02)
- **Un solo árbol** (`mt_menu_items`) con **borrador y publicación**: el escaparate
  lee la última versión publicada (`mt_menu_published`, una fila por tienda) y el
  panel edita el borrador; hasta que no se pulsa «Publicar» la tienda no cambia.
  Al publicar se guarda el histórico (`mt_menu_revisions`) y **se invalida la caché**.
- **Todo editable desde el panel**: crear, editar, borrar (con su rama), activar y
  desactivar, ordenar con **drag & drop**, mover de nivel (con validación de ciclos y
  de los tres niveles), nombre, slug (único entre hermanos), icono, badge («NUEVO»,
  «OFERTA», «TOP»…) con su color, banner de la tienda o enlace de banner, y
  «ocultar si no tiene productos».
- **Menú global de la plataforma** (`store_id` NULL = compartido) con visibilidad
  por tienda (`todas` / `solo estas` / `todas menos estas`, en `mt_menu_item_stores`)
  y por **nivel de cliente** (`min_customer_level`, comparado con el `orden` de
  `categoria_cliente`: Informática 1, Telefonía 2, Papelería 3). Una tienda sin nivel
  asignado no ve los nodos con nivel mínimo.
- **Panel de plataforma** (`/panel/plataforma/menu`) para el rol `platform`
  (`tools/platform-user.php` lo crea): árbol compartido, propiedad de cada nodo
  (compartida o de una tienda), visibilidad, publicación por tienda o de todas, y
  un botón para convertir el árbol de una tienda en compartido.
- **Aislamiento entre tiendas**: el `store_id` sale siempre de la sesión y cada
  consulta lo comprueba; los nodos de otra tienda ni se ven ni se pueden modificar
  (probado con dos tiendas en `verify.php`, y a mano: «Ese nodo no es de tu tienda»).
- **Vista previa** del borrador en escritorio (marco de 1280 px) y móvil (390 px),
  cada uno con su media query real.

### Navegación: Menú Compacto y Menú Catalogo (2026-10-02)
- **Un solo árbol de categorías de 3 niveles** (`mt_menu_items`, migración `005`)
  que alimenta **los dos** estilos: primero se copia del menú de la web de
  referencia (PuntoByZE, solo nombres y orden, leyendo sus tablas sin tocarlas) y
  luego se edita desde el panel. Hoy: **14 categorías / 83 grupos / 359 destinos**;
  quedan **7 destinos pendientes** (subcategorías que no se reconocieron) guardados
  **desactivados** con su aviso, visibles en el panel. Los **14 destinos de filtro**
  («Para un uso: GAMING», «Tipo gráfica: NVDIA»…) ya se resuelven: migración `008`
  los activó y el menú se republicó.
- **Menú Catalogo** (por defecto): botón «Todas las categorías» → panel con fondo
  oscuro, categorías a la izquierda y grupos con el tercer nivel a la derecha.
- **Menú Compacto**: barra horizontal bajo la cabecera con las 7 primeras
  categorías (`MENU_COMPACT_MAX`), el resto en «Más categorías», megamenu al pulsar
  o al pasar el ratón y accesos rápidos (Ofertas, Novedades, Marcas, Destacados).
- Los dos se abren y se cierran igual: **Escape**, clic fuera, botón de cierre,
  `aria-expanded`/`aria-modal`, foco al abrir y vuelta del foco al cerrar.
- **Arreglado el parpadeo del Menú Compacto** (2026-10-02): el panel abría añadiendo
  `mn-open` al `<body>` y esa clase ya era la del botón «Todas las categorías», así
  que el body pasaba a `inline-flex` con fondo de marca, la flecha se desplazaba
  ~3700 px, saltaba `mouseleave` y el menú se abría/cerraba en bucle. El estado del
  body ahora es **`mn-panel-open`** (y el botón se acota a `.mn-trigger .mn-open`);
  el scroll de fondo solo se bloquea en el cajón móvil y en el modal de Catálogo, no
  en el desplegable de escritorio. `aria-expanded` solo en el disparador activo.
- **Móvil (<1024 px)**: los dos pasan al **mismo cajón lateral** por niveles
  (categorías → grupos → destinos), con «Volver», cierre, áreas de 44 px y teclado.
- El bloque promocional (banner, marcas y destacados) se carga **por AJAX**
  (`GET /menu/panel/{id}` devuelve HTML ya renderizado), así el primer pintado no
  paga la consulta de marcas (64–278 ms).
- **Rutas SEO** (`Core/CatalogUrl`): `/categoria`, `/categoria/subcategoria`,
  `/m/marca`, `/etiqueta/…`, `/orden/…`, `/page/n`, más `/marcas`, `/marca/{id}` y
  `/ofertas`, `/novedades`, `/destacados`. Las URLs con query string hacen **301** a
  su ruta; **`f/…` (filtros estructurados) ya se resuelve** hacia la misma
  selección que `f[filtro][]=subfiltro` (antes respondía 404). Las facetas y el
  rango de precio siguen en query.
- Etiquetas de listado resueltas en SQL contra `ofertas` (218 productos),
  `fecha_alta` (90 días, 196) y `etiqueta = 1` (26.000), y **aviso «Oferta»** en las
  tarjetas (`Catalog::attachOffers`).

### Elegir categorías y crear las propias (2026-10-03)
- **Migración `009`**: `mt_stores.menu_scope` (`completo|elegido`, por defecto
  `completo`) y `mt_menu_item_overrides` (por tienda y nodo: `state` `visible|oculto`
  y `label` para el nombre propio). No se duplica ningún nodo: el árbol puede ser
  compartido y la tienda solo guarda su decisión.
- **Modo «completo»**: se ve todo el menú del catálogo menos lo que la tienda oculte.
  **Modo «elegido»**: se ve solo lo marcado, más su rama y su camino. Un `oculto`
  explícito se lleva su rama. Los nodos creados por la tienda nacen marcados.
- **El catálogo es compartido y de solo lectura**: la tienda decide qué se ve y cómo se
  llama, pero no modifica `categorias` ni `subcategorias` (ni afecta a otras tiendas).
- **Categorías propias**: destinos `propios` (`/propios`, listado nuevo con los
  productos propios de la tienda), `pagina` (`/pagina/{slug}` de sus páginas de
  contenido) y enlace libre. El nivel 1 y 2 respetan su destino y se pintan aunque no
  tengan hijos. La tienda puede colgar además nodos suyos **dentro de una categoría
  compartida** (p. ej. «Servicio» dentro de «Componentes»).
- **Panel**: tarjeta «Qué categorías se ven» (modo, Mostrar/Ocultar, nombre propio y
  «Volver al árbol»), botones «+ Productos propios» y «+ Página de la tienda», y el
  editor rellena el destino al editar (antes se perdía al guardar).
- **`publish()` aplica lo mismo**, así que el snapshot publicado ya sale filtrado; la
  caché se invalida al publicar. Las instantáneas publicadas antes de este cambio
  siguen funcionando (sin `panel_id` se usa el id del nodo para el bloque promocional).

### Buscador del catálogo en vivo (2026-10-03)
- **`/buscar/live`** (JSON) imita el buscador de PuntoByZE: al enfocar o teclear en el
  campo de la cabecera se abre un panel con resultados (tarjetas del tema, pintadas en
  el servidor), **subcategorías y marcas** con contadores y «Ver todos los resultados».
  Sin JavaScript el formulario sigue navegando a `/catalogo?q=` como siempre.
- **El menú manda**: `Menu::searchScope($storeId)` traduce el árbol **visible** de la
  tienda (modo completo/elegido, anulaciones, visibilidad de plataforma, nivel de
  cliente) a las categorías y subcategorías del catálogo que se pueden devolver. El
  catálogo central se consulta siempre con ese alcance (subcategoría primero; si el
  menú no aporta ninguna, por categoría); sin nodos en `mt_menu_items` no se limita.
  La navegación normal de `/catalogo` (sin `q`) no cambia: el alcance solo se aplica a
  las **búsquedas**.
- **Productos propios**: `OwnProduct::searchPublished()` busca por nombre o referencia
  solo los publicados de **la tienda que se está viendo** (aislamiento por `store_id`).
  Salen los primeros («Productos de la tienda»), y con filtros de catálogo activos no
  entran (son ajenos a categorías, marcas y precios centrales).
- **Rendimiento**: las facetas del buscador (`Catalog::searchFacets`) no calculan el
  rango de precio — en un texto amplio obligaba a evaluar la tarifa producto a producto
  y la respuesta subía a 3-4 s. Ahora ~1-2 s en frío y ~50 ms en caliente; el listado
  completo ya ofrece sus campos de precio mínimo/máximo.
- **Rejilla adaptativa (corregido 2026-10-04)**: el endpoint devolvía las tarjetas
  sueltas, sin el `.grid-products` que el CSS esperaba, así que se apilaban **una por
  línea en cualquier monitor**. Ahora `/buscar/live` las envuelve en la rejilla (una por
  grupo) y el panel decide las columnas por ancho: **1 en el móvil** (≤639 px), 2 a 640,
  3 a 768-1024, 4 a 1280, 5 a 1440, **6 a 1600 y 7 a partir de 1800** (el panel usa el
  ancho `container-ultra` desde 1800 px), sin desbordes.
- **Cerrar el panel (corregido 2026-10-04)**: Escape y la X devolvían el foco al campo de
  la cabecera y su listener de `focus` reabría el panel al instante. El foco programático
  va marcado (`state.closing`); el clic del usuario sigue abriendo.
- **Un solo botón (corregido 2026-10-04)**: el panel tenía dos aspas iguales pegadas
  (`shop-search-clear`, que borraba, y `shop-search-close`, que cerraba) y parecían dos
  botones de cerrar. Se quitó el de borrar (layout, JS y CSS): queda solo el de cerrar,
  que oculta todo el buscador. Para vaciar el texto está la X nativa del campo.

### Pedidos y envío al mayorista (2026-10-01)
- Tablas propias `mt_orders` (pedido) y `mt_order_items` (líneas) con **envío por
  línea**: cada línea que va a idirecto queda marcada con su `sent_at` y el
  `pedido id` creado, así un pedido de 4 líneas se puede mandar en dos veces (2+2)
  sin repetir nada. Migración `003` (idempotente).
- **Listado** con pestañas: Todos, Activos, Borradores, Facturados, Cancelados y
  Borrados (papelera restaurable), con buscador por número, cliente o email y
  paginación. Se puede filtrar además por estado concreto (`?estado=4`).
- **Ficha del pedido**: cliente, entrega, notas, líneas con su estado de envío
  (pendiente / enviado #id / producto propio), cambio de estado y alta de líneas
  con buscador de productos (catálogo del mayorista + propios).
- **Envío al mayorista** (`Core/Idirecto`): replica el flujo web de idirecto
  (`pedidos_addr` + `pedidos` + `pedidos_det`, `web = 1`, `estado = NULL`,
  `referencia = TIENDA-<slug>-<código>`), con la **tarifa de la tienda**
  (`mt_stores.id_margen`) y los mismos criterios de coste/ganancia/IVA de
  idirecto. Antes de enviar, la ficha muestra la **vista previa** con los importes
  reales (subtotal, IVA y total) y los avisos.
- La dirección de facturación es la de la tienda; la de envío, la del pedido si la
  hay y, si no, la de la tienda.
- La **reserva de stock** de los almacenes exteriores (tipo 0 y 4) se replica, pero
  se puede desactivar con `IDIRECTO_RESERVE_STOCK=false`; todo el envío se puede
  apagar con `IDIRECTO_ENABLED=false`.
- Los **productos propios no se envían** al mayorista (no están en su catálogo): el
  panel los marca y los deja fuera de la selección.
- Alta manual de pedidos (el checkout aún no existe) y **pedido de ejemplo**
  `P26-00001` en la tienda demo para ver la pantalla con datos.

### Rendimiento (2026-10-01)
- La condición de stock pasó de `EXISTS` correlacionado a **semijoin**
  (`part_number IN (SELECT …)`): mismo resultado (41.289 productos), pero el
  listado del catálogo pasó de **~3 s a ~20 ms**.
- La marca se resuelve **por lote** (`attachBrands`) en vez de con un
  `LEFT JOIN marcas`, que degradaba el plan (~1 s extra por consulta).
- El contador total (caro, ~1 s) se cachea 10 min; los destacados de portada, 10
  min; y las facetas (marcas y rango de precios), 15-30 min.
- El orden por precio se ofrece **solo cuando el listado está acotado** (categoría,
  búsqueda, faceta o rango): sobre el catálogo entero cuesta ~4 s.
- Medido en HTTP real: portada ~30 ms, catálogo ~25 ms, catálogo con subcategoría
  ~60 ms, filtrado ~30 ms.

### Caché de datos y precios (2026-10-04)
- Una sola capa: **`Tienda\Core\Cache`** (`get/set/delete/remember/flush/
  forgetPattern/gc`) con drivers **`FileCache`** y **`ApcuCache`**
  (`config/cache.php`, claves `CACHE_*`). `Catalog` y `Menu` ya no tienen su
  propia caché en fichero.
- **APCu** se usa solo si la extensión está instalada (`CACHE_DRIVER=auto`);
  si no, fichero. **En esta máquina no está instalada**, así que ahora corre el
  driver de fichero. Para activarla:
  `sudo apt-get install php8.4-apcu` y reiniciar PHP-FPM/Apache.
- Lo que aporta la capa nueva: **memo por petición** (L1), **bloqueo por clave**
  (una clave caducada no la recalculan N peticiones) y **limpieza automática**
  (caducados, ficheros viejos y tope de ficheros; antes la carpeta crecía sin fin).
- **Regla de precios**: nunca se cachea un precio. `Catalog::featured()` cachea
  **solo los ids** de la selección y relee precio/stock/marca/specs en vivo
  (`findMany()`); `forgetPriceCache()` solo queda para el rango del filtro.
- Herramienta: **`tools/cache-clear.php`** (`estado|all|catalog|menu|precios|
  patron|gc`).
- Medido en HTTP real: `/catalogo` 4,4-6,9 s en frío → **24-60 ms** en caliente
  estable (sin los picos de 4 s que había con la caché casera).

### Entorno y operación
- `deploy/setup-local-domain.sh`: dominio de pruebas `http://local.tienda` con
  permisos correctos para `www-data` (Apache).
- `deploy/setup-nginx-domain.sh` + `deploy/nginx-site.conf.tpl`: el mismo sitio
  en **nginx + PHP-FPM** (`sudo bash deploy/setup-nginx-domain.sh valduran.com`).
- La app detecta el servidor (`app/Core/Server.php`): ruta pública de `/public`,
  esquema real (incluido proxy) y host de las URLs canónicas.
- `tools/verify.php`: 312 comprobaciones automáticas (diseño, facetas, filtros
  estructurados del mayorista, catálogo, paginación y enlaces de las tarjetas, caché y
  precios en vivo, carrito y mini-carrito, favicon de la tienda y predeterminado de
  Valduran, almacenamiento, DNS, servidor, registro de tiendas, pedidos, envío al
  mayorista, ausencia de credenciales por defecto y diagnóstico del nivel de cliente).
- Documentación interna en `.agents/`, blindada frente a la web.
- Repositorio publicado en GitHub (`main`).

---

## Pendiente (por orden sugerido)

### 1. Cobro real (pasarela) 💳
El checkout registra el pedido y su forma de pago (transferencia, contra reembolso o
recogida), pero **no cobra**: el pedido nace pendiente y el tendero lo marca como
pagado en el panel. Falta integrar una pasarela (Redsys tiene su API en
`/var/www/html/puntobyze/app/inc/redsysapi.class.php` como referencia) con su firma,
la notificación de pago y el estado de la transacción.

### 2. Emails al cliente y a la tienda
No hay envío de correo en el proyecto: al confirmar el pedido el cliente solo ve la
página de gracias y la tienda se entera por el panel. Faltan el email de confirmación
(y, con él, **recuperar la contraseña** del cliente, hoy imposible si se le olvida).

### 3. Gastos de envío por peso y provincia
Ahora es tarifa plana + gratis desde X €, suficiente para empezar. Puntobyze calcula
los portes por **peso del pedido y provincia** (`getPortes()` con tabla de tramos):
para igualarlo hacen falta pesos fiables por producto y mantener esa tabla.

### 4. Cuenta y direcciones en el panel maestro
Los clientes y sus direcciones viven en nuestras tablas (`mt_customers`,
`mt_customer_addresses`) y **no** se copian a `e_clientes` (tabla de puntobyze), que
es la decisión tomada. Si algún día el mayorista quiere ver el cliente final, habría
que decidirlo otra vez (implicaría crearle cuenta en puntobyze).

### 5. Panel maestro del mayorista
Supervisión de tiendas, asignación de tarifas y auditoría de ventas.

El **alta** ya está resuelta por el registro público (`/registro`, cada tienda nace
enlazada a su cuenta de `tiendas`), así que lo que falta aquí es el punto de vista
del mayorista: ver todas las tiendas, cambiarles plan/tarifa, suspenderlas y
auditar sus ventas. Requiere un acceso maestro propio (tabla + login), que no se
ha hecho por no inventar el modelo de permisos sin que lo pida el dueño.

### 4. Temas `moderno` y `minimal`
Están en `mt_themes` pero sin vistas. El sistema de temas ya cae a la vista base
si el tema no la implementa, así que se pueden añadir sin riesgo. Los tokens de
diseño son independientes del tema: valen para cualquiera.

### 5. Materializar el stock válido (rendimiento)
El contador del catálogo recorre `productos` comprobando `stock` (~1 s) y el
orden por precio sobre todo el catálogo cuesta ~4 s. La solución limpia es una
tabla propia (`mt_`) con el stock válido por `part_number`, refrescada por tarea
(cron o botón en el panel). **Decisión del dueño**: implica aceptar un desfase
de minutos entre el mayorista y la web, así que no se ha hecho por iniciativa
propia.

### 6. Tests
No hay ninguno. Prioridad: `Specs` (parseo de especificaciones),
`Appearance` (utilidades de color y tokens), `Str::slugify`, `TenantResolver` y
`Dns::evaluate` (lógica pura, fácil de testear).

---

## Decisiones tomadas (para no volver a discutirlas)

- **Misma base de datos central** (`idirecto_db`) con prefijo `mt_` para lo
  propio. Se descartó una base separada.
- **Sin framework.** MVC ligero propio.
- **Resolución de tienda por hostname**, nunca por cookie ni por parámetro
  (el `?__store=` es solo para desarrollo).
- **El prefiere 301 a la URL canónica** cuando el slug no coincide (idirecto
  ignora el slug; nosotros redirigimos, que es mejor para SEO).
- **La documentación va en el repo** (visible en git y por acceso al servidor)
  pero **bloqueada en la web**.
- **La identidad visual son tokens, no columnas sueltas**: el color se guarda como
  token de diseño y `Appearance` deriva lo que falte. El CSS del storefront no
  escribe colores: si aparece uno literal, es un error.
- **La vista previa del panel usa el generador real** (`/panel/diseno/tokens`) y
  no una reimplementación en JavaScript.
- **Los pedidos de la tienda son nuestros** (`mt_orders`/`mt_order_items`): el
  mayorista no guarda los pedidos de nuestros clientes. Se envían a idirecto por
  líneas, y solo los productos del catálogo central (los propios no existen allí).
- **El envío al mayorista escribe de verdad** en `pedidos_addr`, `pedidos` y
  `pedidos_det` (decisión expresa del dueño), replicando su flujo web; se puede
  apagar con `IDIRECTO_ENABLED=false` o dejar sin reserva de stock con
  `IDIRECTO_RESERVE_STOCK=false`.
- **Cada tienda dice con qué cuenta compra** (`mt_stores.id_tienda_idirecto` e
  `id_margen`) desde Ajustes, en vez de adivinarlo por CIF o email.
- **Solo los clientes del mayorista pueden tener tienda aquí**: el alta es pública
  (`/registro`) pero exige una cuenta activa en `tiendas`; la tienda nace enlazada
  a esa cuenta y con su tarifa. Se entra al panel con un **usuario propio** (no
  con las credenciales del mayorista, que no se guardan).
- **La tienda vende a tarifa + beneficio, con IVA incluido**: el precio de la web es
  `tarifa de la tienda (id_margen) + beneficio (mt_stores.markup)`, **con el IVA
  incluido** (es lo que paga el cliente). El pedido guarda la **base sin IVA** en
  `mt_order_items.price_customer` y el IVA se suma en los totales, de modo que la
  cuenta cuadra con el precio que vio el cliente y con lo que factura el mayorista.
  Decisión del dueño: nada de mostrar el precio más barato publicado, que en muchos
  productos era **menor que el coste** de la tienda (ej. 311,17 € frente a 315,67 €).
- **Se puede comprar como invitado o con cuenta**: en los dos casos el pedido queda
  ligado a un cliente de `mt_customers`, con su dirección guardada en la libreta. Un
  invitado que se registra con el mismo email **conserva sus pedidos**.
- **El cobro no se automatiza todavía**: el pedido nace pendiente de pago y el
  tendero lo marca como pagado en el panel. Cada tienda elige en Ajustes qué formas
  acepta (transferencia, contra reembolso, recogida) con sus datos bancarios.
- **Los clientes finales viven solo en nuestras tablas** (`mt_customers`,
  `mt_customer_addresses`): no se crean cuentas en `e_clientes` (la tabla de
  puntobyze), decisión expresa del dueño. Al mayorista le llega la dirección del
  cliente en `pedidos_addr`, pero `pedidos.id_e_cliente` va vacío.

---

## Riesgos conocidos

- **La contraseña de `tiendas` no es `password_hash`**: es
  `hash('sha256', md5(sha1($clave)))` (ver `Account::signature()`). Si el
  mayorista cambia su login, el registro deja de validar cuentas (y hay que
  ajustar `Account::login()`). La comprobación está limitada por sesión
  (`IDIRECTO_REGISTER_ATTEMPTS`) porque este formulario autentica contra las
  cuentas del mayorista; si algún día se abre mucho, conviene limitar también por
  IP y añadir captcha.
- **El envío a idirecto escribe en tablas de producción del mayorista**
  (`pedidos`, `pedidos_det`, `pedidos_addr`, y descuenta `stock`/`reserva`). Está
  aislado en `Core/Idirecto/OrderGateway`, con vista previa obligatoria y
  transacción única, pero una tienda mal configurada podría crear pedidos reales:
  conviene dejar `IDIRECTO_ENABLED=false` hasta tener la cuenta asignada.
- **El precio depende del beneficio de la tienda**: al cambiarlo (Ajustes) se
  limpian las cachés de precios, pero un pedido ya registrado conserva los importes
  con los que nació (es lo correcto: es lo que aceptó el cliente). Si se cambia la
  tarifa en idirecto, los pedidos nuevos usan la nueva y los viejos no.
- **Sin emails**: el cliente no recibe confirmación ni puede recuperar su
  contraseña; si la pierde, el tendero tendría que tocarla a mano en la base de
  datos. La entrada del cliente tiene límite de intentos por sesión (5 / 15 min),
  pero es por sesión, no por IP.
- `CATALOG_IMAGE_URL` apunta a `https://idirecto.es/img_products`. Si el
  mayorista bloquea el *hotlinking* o cambia las rutas, las imágenes dejan de
  verse (el *fallback* de marcador funciona, no rompe la página).
- El catálogo depende de tablas del mayorista que pueden cambiar sin aviso.
  `Catalog` es defensivo, pero conviene revisar `tools/verify.php` tras cambios.
- Las credenciales de `.env` están en texto plano en el servidor (fuera del
  repositorio). Es lo habitual, pero conviene revisar permisos (`640`).
