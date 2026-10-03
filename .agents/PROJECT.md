# PROJECT — Cómo funciona la plataforma

Documento de referencia técnica. Si solo vas a leer un fichero, que sea este.

- **Ruta:** `/var/www/html/tienda`
- **Repositorio:** `git@github.com:PaliSick/tienda.git` (rama `main`)
- **Dominio de pruebas:** http://local.tienda
- **Idioma:** español (código, comentarios, commit, UI)

---

## 1. Qué es

Una plataforma **multi-tienda** (estilo Tiendanube) construida **encima del
catálogo del mayorista idirecto**. Cada tienda cliente obtiene:

- una **web pública** (storefront) con el catálogo central, filtrado a productos
  con stock, más sus propios productos;
- un **panel** para personalizar diseño, banners, avisos, productos, dominios y
  ajustes, sin tocar código;
- **dominio propio** con verificación por DNS, o subdominio
  `<slug>.idirecto.es`.

La tienda **no gestiona el stock del catálogo central**: lo lee. Su stock propio
solo aplica a sus productos propios.

---

## 2. Stack y principios

| | |
|---|---|
| Lenguaje | PHP 8.4 (sin framework) |
| Patrón | MVC propio (front controller único) |
| BD | MySQL 8 (`idirecto_db`), PDO con *prepared statements* reales |
| Plantillas | PHP plano en `app/Views/` con `extract()` |
| JS/CSS | Vanilla, sin dependencias ni build (solo `Public Sans` vía CDN opcional) |
| Dependencias | `aws/aws-sdk-php`, **opcional** y solo para S3 |

Principios no negociables:

1. **Solo aditivo.** Las tablas del mayorista (sin prefijo) son de **solo
   lectura**. Todo lo propio usa el prefijo `mt_`.
2. **Sin dependencias innecesarias.** Nada de Composer salvo el SDK de AWS.
3. **Aislamiento por tienda.** El `store_id` sale **siempre** de la sesión o del
   hostname, nunca de un parámetro que envíe el usuario.
4. **Defensivo.** Si el catálogo central no existe o está desactivado, el
   storefront no debe romper: devuelve listados vacíos.

---

## 3. Mapa de directorios

```
index.php                  Front controller y TODAS las rutas
.htaccess                  Enrutado + bloqueo de rutas internas
.agents/                   Documentación interna (NUNCA servida por web)
app/
  bootstrap.php            Autoload PSR-4 (Tienda\ -> app/), .env, helpers
  Core/                    Infraestructura (ver §4)
  Core/Idirecto/           Puente con el mayorista: Account, Pricing y OrderGateway
  Core/Registration.php    Alta de una tienda a partir de una cuenta de `tiendas`
  Core/Cart.php            Carrito del cliente final (en sesión, por tienda)
  Core/Checkout.php        Cierre del pedido: direcciones, pago y comentario
  Core/Shipping.php        Gastos de envío (tarifa plana + gratis desde X €)
  Core/CustomerAuth.php    Sesión del CLIENTE de la tienda (va aparte del panel)
  Controllers/             Storefront, Cart, Checkout, Customer, Registration + Admin/* (panel)
  Models/                  Acceso a datos (una clase por concepto)
  Views/
    layouts/               shop.php (web), panel.php, panel_blank.php
    register.php           Registro público de tienda (/registro)
    panel/                 Pantallas del panel (orders.php, order.php, order_form.php,
                           _order_lines.php = editor de líneas, customers.php, customer.php,
                           design_preview.php)
    themes/idirecto/       Tema público (home, _hero, catalog, product, page, _card, cart,
                           checkout, thanks, account/*)
config/                    app, appearance, database, storage, tenant, catalog, idirecto
database/
  migrations/001_schema.sql      13 tablas mt_ (idempotente)
  migrations/002_design_tokens.sql  Columnas de identidad visual en mt_stores
  migrations/003_orders.sql      Cuenta del mayorista en mt_stores + mt_orders/mt_order_items
  migrations/004_checkout.sql    mt_customers/mt_customer_addresses + venta y cobro
  seeds/001_seed.sql             Planes, temas y tienda demo
  migrate.php                    Ejecutor de migraciones + semillas
deploy/                    Vhosts de Apache, plantilla de nginx y scripts
public/                    ÚNICO directorio servido como estático
  assets/css|js            shop.css, panel.css, shop.js, panel.js
  uploads/                 Archivos locales (si STORAGE_DRIVER=local)
storage/                   cache/ y logs/ (escritura de la app)
tools/verify.php           118 comprobaciones automáticas
```

---

## 4. Infraestructura (`app/Core`)

| Clase | Responsabilidad |
|---|---|
| `Env` | Lee `.env` a `$_ENV` (sin dependencias) |
| `Config` | Carga `config/*.php` con acceso por punto: `Config::get('catalog.markup')` |
| `Appearance` | **Sistema de diseño**: resuelve los tokens (config + tienda + `theme_tokens`), deriva hover/suave/contraste y emite el CSS de variables `--c-*` |
| `Database` | PDO singleton; `select/first/scalar/execute/insert/update/delete/transaction/tableExists` |
| `Router` | Compila `{param}`, detecta el subdirectorio base y despacha |
| `TenantResolver` | **Decide qué tienda se sirve** según el hostname |
| `Tenant` | Contexto inmutable de la tienda (nombre, tema, colores, radios, cuotas…) |
| `Auth` / `Session` / `Csrf` | Sesión, login y tokens CSRF |
| `Controller` / `View` / `Model` | Base MVC; `View` usa `extract()` |
| `Storage/*` | `StorageInterface` + `LocalStorage` + `S3Storage` + `StorageManager` + `StorageKey` |
| `Media/*` | `MediaUploader` (subida) e `ImageOptimizer` (WebP, tamaño, EXIF) |
| `Specs` | Parsea características y especificaciones; `quickHighlights()` alimenta los chips de la tarjeta |
| `Str` | `slugify()` (mismo criterio que idirecto) y `excerpt()` |
| `Dns` | Verificación de dominios por A/CNAME/TXT (con *lookup* inyectable) |
| `Core/Idirecto/Account` | Cuenta del mayorista de la tienda: tarifa, sucursal, comercial, forma de pago, país/provincia |
| `Core/Idirecto/Pricing` | Tarifa, coste, almacén, IVA, sujeto y canon de un producto del catálogo |
| `Core/Idirecto/OrderGateway` | Vista previa y envío de líneas a `pedidos_addr`/`pedidos`/`pedidos_det` |
| `Registration` | Alta de una tienda desde una cuenta del mayorista (alta pública `/registro`) |
| `Cart` | Carrito del cliente final: en sesión por tienda, con precios y stock en vivo |
| `Shipping` | Gastos de envío de la tienda (tarifa plana + gratis desde X €) |
| `Checkout` | Cierra el pedido: valida direcciones/pago y crea `mt_orders` + líneas |
| `CustomerAuth` | Sesión del cliente de la tienda (registro, entrada, invitado) |
| `Validation`/`Storage` `Exception` | Errores de dominio |

> `Models/Order` es el pedido que recibe la tienda de **su** cliente (estados,
> pestañas, papelera) y `Models/OrderItem` sus líneas; `OrderGateway` es lo único
> que escribe en las tablas del mayorista. `Models/Customer` y
> `Models/CustomerAddress` son los clientes finales de cada tienda y su libreta de
> direcciones (nunca se confunden con `Models/StoreUser`, que es del panel).

### Ciclo de una petición

```
Apache (.htaccess) ─┐
                    ├──> index.php
nginx (server block)┘
   │  (cli-server: solo /public se sirve estático)
   ├─> app/bootstrap.php    autoload, .env, config, sesión, helpers
   ├─> Router::dispatch()
   │      ├─ TenantResolver::resolve()  ──> Tenant
   │      └─ Controller::action($params)
   │             ├─ Model  ──> Database (PDO)
   │             └─ View   ──> layouts/*.php  (+ tema activo)
   └─> response
```

**Apache y nginx valen igual.** El enrutado al front controller y el bloqueo de
las rutas internas los pone el servidor: `.htaccess` en Apache y
`deploy/nginx-site.conf.tpl` (via `deploy/setup-nginx-domain.sh`) en nginx.
`app/Core/Server.php` detecta el servidor y centraliza lo único que cambia:

| Necesidad | Dónde se resuelve |
|---|---|
| Ruta pública de `/public` (assets) | `Server::publicPath()` (mira `DOCUMENT_ROOT`) |
| Esquema real http/https | `Server::isSecure()` (`HTTPS`, `REQUEST_SCHEME`, `X-Forwarded-Proto`) |
| Host de las URLs canónicas | `Server::host()` (`HTTP_HOST` o `X-Forwarded-Host`) |
| Cookie de sesión `Secure` | `Session::start()` → `Server::isSecure()` |

**Orden de resolución de tienda** (`TenantResolver`), por hostname, nunca cookies:

1. `?__store=<slug>` — solo en desarrollo, para probar sin DNS.
2. Subdominio: `<slug>.` + alguno de `BASE_DOMAINS`.
3. Dominio propio **verificado** en `mt_domains`.
4. `DEMO_STORE` del `.env`.
5. Primera tienda activa.

---

## 5. Modelo de datos

### Tablas propias (prefijo `mt_`) — 15

| Tabla | Contenido |
|---|---|
| `mt_plans` | Planes y cuota de productos propios (`-1` = ilimitado) |
| `mt_themes` | Temas disponibles (catálogo cerrado) |
| `mt_stores` | Tiendas: slug, plan, tema, colores, hosts, ajustes y **cuenta del mayorista** |
| `mt_store_users` | Usuarios del panel (`password_hash`) |
| `mt_media` | Archivos subidos: `key`, `url`, `driver`, dimensiones |
| `mt_banners` | Banners de portada |
| `mt_notices` | Avisos (barra superior / modal) |
| `mt_own_products` | Productos propios de la tienda |
| `mt_orders` | **Pedidos** que la tienda recibe de sus clientes (estados + papelera) |
| `mt_order_items` | **Líneas** del pedido, con el envío a idirecto por línea |
| `mt_domains` | Dominios propios + estado de verificación |
| `mt_dns_log` | Historial de comprobaciones DNS |
| `mt_content_blocks` | Páginas de contenido (`/pagina/{slug}`) |
| `mt_settings` | Ajustes clave/valor por tienda |
| `mt_migrations` | Control de migraciones aplicadas |

Todas con `utf8mb4_unicode_ci` y FK a `mt_stores(id) ON DELETE CASCADE`.

`mt_stores` guarda la **identidad visual** (migración `002`):
`color_primary`, `color_secondary`, `color_accent`, `color_bg`, `color_surface`,
`color_text`, `color_border`, `color_scheme` (`light|dark|auto`),
`radius_scale` (`compact|standard|rounded`), `font`, `theme_tokens` (JSON) y
`custom_css`. Los colores opcionales en `NULL` significan «usa el valor del tema».

`mt_stores` guarda además el **enlace con el mayorista** (migración `003`):
`id_tienda_idirecto` (cuenta en `tiendas.id`) e `id_margen` (`precios.id_margen`).
Se editan en Ajustes; la tarifa vacía cae a la de la cuenta y, si no hay, a
`IDIRECTO_DEFAULT_ID_MARGEN`.

`mt_orders` / `mt_order_items` (migración `003`): el pedido (cliente, entrega,
importes que paga el cliente, estado, papelera) y sus líneas (`source` =
`catalog|own`, cantidades, precio de cliente, **tarifa y coste del mayorista** y el
envío por línea: `sent_at` + `idirecto_pedido_id`). Ver §7bis.

### Tablas del mayorista — solo lectura (con una excepción)

`productos`, `productos_ext`, `precios`, `stock`, `almacenes`, `marcas`,
`categorias`, `subcategorias`, `tiendas`, `paises`, `provincias`, `canon`,
`tarifas`, `formas_pago`, `comerciales`, `productos_resenas`.

**Excepción (decisión del dueño):** al enviar líneas de un pedido al mayorista se
escriben filas en `pedidos_addr`, `pedidos` y `pedidos_det`, y se descuenta
`stock`/`reserva` en los almacenes tipo 0 y 4. Todo eso ocurre **solo** en
`Core/Idirecto/OrderGateway` (con vista previa y transacción única) y se puede
desactivar con `IDIRECTO_ENABLED=false` / `IDIRECTO_RESERVE_STOCK=false`.

---

## 6. Catálogo central

### Qué productos se muestran

Condiciones idénticas a idirecto (en `app/Models/Catalog.php`):

```sql
productos.estado <> 4
AND productos.id_subcategoria <> 178
AND EXISTS (stock.stock > 0 AND stock.activo = 1 AND stock.costo > 0
            AND almacenes.tipo <> 2)   -- EXISTS evita duplicar filas por almacén
```

Resultado en la BD de desarrollo: **39.437 de 233.773** productos.

- La **ficha** de un producto sigue existiendo aunque se agote (muestra "Sin
  stock"), pero los agotados **no aparecen en listados**.
- El `COUNT` total se cachea **5 minutos** en `storage/cache/`.

### Imágenes: `img_name` es una plantilla

`productos.img_name` **no es un nombre de fichero**; es un `sprintf`:

```
sprintf(img_name, id, tamaño) . '_N.jpg'     N = 1..5
```

Tamaños reales medidos en el servidor del mayorista:

| Tamaño | Uso en este proyecto | Peso |
|---|---|---|
| `c` | miniaturas de la ficha | ~3 KB |
| `l` | **listados** (catálogo, destacados) | ~7 KB |
| `f` | **imagen grande del carrusel** | ~38 KB |
| `g` | zoom del visor ampliado (bajo demanda) | ~4 MB |
| `o` | original (no se usa) | ~12 MB |

Configuración: `CATALOG_IMAGE_URL` (base), `CATALOG_IMAGE_PATH` (ruta en disco
opcional para comprobar existencia en local) y `CATALOG_CARD_IMAGE_SIZE`.

**No todas las fotos existen.** El JS retira del carrusel y de las miniaturas
las que dan 404, y reutiliza la imagen grande si solo falta la miniatura.

### Especificaciones

- `productos_ext.caracteristicas` → cadena corta `"Clave: valor, Clave: valor"`.
- `productos_ext.especificaciones` → **HTML** con `<h3>` (nombre de grupo) y
  celdas `<td>Clave: valor</td>`.
- `Specs::groupsFromHtml()` lo convierte en grupos; `Specs::summary()` construye
  el resumen por prioridades (coincidencia exacta antes que parcial) y
  `Specs::highlights()` el bloque «De un vistazo».
- `productos_atributos` **está vacía** en esta base de datos: no usarla.

---

## 7. Storefront y panel

### Rutas públicas

| Ruta | Contenido |
|---|---|
| `/` | Portada: slider de banners, franja de garantías, accesos rápidos a categorías, destacados, productos propios |
| `/catalogo` | Catálogo con buscador (`?q`), categoría (`?cat`), subcategoría (`?subcat`), **facetas** (`?f[clave][]=valor`), **precio** (`?pmin`/`?pmax`), orden (`?orden`) y paginación |
| `/buscar/live` | **Buscador en vivo** (JSON): resultados del catálogo visible de la tienda + sus productos propios, con facetas de subcategoría y marca. Parámetros `q`, `s[]`, `m[]`, `pmin`, `pmax`. El panel lo pinta `shop.js` desde el campo de la cabecera |
| `/producto/{slug}/{id}` | Ficha (**URL SEO**); `/producto/{id}` redirige 301 |
| `/contacto`, `/pagina/{slug}` | Contacto y páginas de contenido |
| `/registro` | **Alta de una tienda nueva** con la cuenta de idirecto (ver más abajo) |
| `/carrito` | Carrito del cliente (añadir, cambiar cantidades, quitar, vaciar) |
| `/checkout` | Cierre del pedido: dirección, forma de pago y comentario |
| `/checkout/gracias/{code}` | Pedido registrado (resumen e instrucciones de pago) |
| `/cuenta` | Cuenta del cliente: pedidos, direcciones y datos (`/cuenta/login`, `/cuenta/registro`, `/cuenta/pedidos/{code}`, `/cuenta/direcciones`, `/cuenta/perfil`) |

### Compra del cliente final

El comprador **no es el tendero**: tiene su propia cuenta y su propia sesión
(`CustomerAuth`, con `mt_customers` y `mt_customer_addresses`), separadas del panel.

- **Carrito** (`Cart`): en la sesión y por tienda. Solo guarda producto y cantidad;
  el precio, el nombre y el stock se leen del catálogo en cada visita, así que nunca
  se compra a un precio viejo. Los productos que dejan de estar disponibles se
  quitan solos con un aviso.
- **Precio de venta**: `tarifa de la tienda (mt_stores.id_margen) + beneficio
  (mt_stores.markup)`, y en la web **con IVA incluido** (`Catalog::forStore()` fija
  ese contexto en cada petición; el panel usa la base sin IVA). El pedido guarda la
  base en `mt_order_items.price_customer` y el IVA se calcula al totalizar.
- **Envío**: tarifa plana y gratis desde X € (`Shipping`, configurable en Ajustes).
- **Cierre** (`Checkout`): dirección de envío (de la libreta o nueva), facturación
  opcional distinta, forma de pago (transferencia, contra reembolso o recogida) y
  **comentario**. Crea el pedido con `Order::createWithItems()` —el mismo camino que
  el alta manual del panel— y lo deja **pendiente de pago** (el tendero lo confirma
  en el panel). La dirección solo se guarda en la libreta si el pedido se registra.
- **Invitado o registrado**: se puede comprar sin cuenta; en ese caso se crea el
  cliente con su email y su dirección, y si después se registra con el mismo email
  **reclama la cuenta** y conserva sus pedidos.
- **Al mayorista** se le pasa la **dirección del cliente** (con sus ids de
  país/provincia/población, celular y el email en `localidad`) y su comentario en
  `pedidos.detalles`, en lugar de reutilizar la dirección de la tienda.

### Registro de tiendas (solo clientes del mayorista)

**En esta plataforma solo puede tener tienda quien ya es cliente del mayorista.**
No hay altas sueltas: el tendero entra en `/registro` con el email y la contraseña
de su cuenta de idirecto y `Account::login()` la comprueba contra `tiendas` con el
mismo criterio que el login de idirecto:

```sql
… WHERE LOWER(email) = :email AND password = :password
     AND activo = 2 AND COALESCE(cerrada,0) = 0 AND COALESCE(deleted,0) = 0
```

`password` es `hash('sha256', md5(sha1($clave)))` (**no** es `password_hash`, ver
§11). Con la cuenta verificada, `Registration::register()` crea en una transacción:

1. `mt_stores`: slug único (el pedido o derivado del nombre), plan por defecto
   (`IDIRECTO_REGISTER_PLAN`), `status = 1`, datos fiscales/de contacto copiados de
   la cuenta (razón social, NIF, dirección, población, provincia y país resueltos
   por id) y, sobre todo, **`id_tienda_idirecto` + `id_margen`**: la tienda nace
   enlazada y puede enviar pedidos sin configurar nada.
2. `mt_store_users`: usuario `owner` con el email de la cuenta y **contraseña
   propia** (`password_hash`). La contraseña del mayorista no se guarda.

Una cuenta = una tienda (si ya la tiene, se le manda al panel). El formulario
lleva límite de intentos por sesión (`IDIRECTO_REGISTER_ATTEMPTS`) porque
autentica contra las cuentas del mayorista. Con `IDIRECTO_REGISTER=false` queda
cerrado. Al terminar, la tienda responde ya en `https://<slug>.<dominio base>`.

### Sistema de diseño (white-label)

El storefront no escribe **ningún** color a mano: `public/assets/css/shop.css`
consume variables `--c-*` que genera `Tienda\Core\Appearance` en línea en el
`<head>`.

- **Fuentes de los tokens**, de menor a mayor prioridad: `config/appearance.php`
  (y sus `THEME_*` del `.env`) → columnas de diseño de `mt_stores`
  (`color_primary|secondary|accent|bg|surface|text|border`, `color_scheme`,
  `radius_scale`, `font`) → `mt_stores.theme_tokens` (JSON libre que pisa
  cualquier token sin migrar la base de datos).
- **Derivados**: hover, activo, tono suave y color de texto legible
  (`Appearance::contrast()`, contraste WCAG) se calculan en PHP, así que el CSS
  no necesita adivinar ni duplicar reglas.
- **Modo oscuro**: `color_scheme` = `light|dark|auto`. El CSS emite
  `:root[data-color-scheme="dark"]` y
  `@media (prefers-color-scheme:dark){:root[data-color-scheme="auto"]}`. El
  visitante puede conmutar desde la cabecera (se guarda en `localStorage` y un
  script mínimo lo aplica antes de pintar, sin destello).
- **Presets**: 5 identidades completas en `config/appearance.php` (`presets`);
  el panel las aplica en el servidor (`apply_preset`).
- **Vista previa fiel**: `/panel/diseno/previa` (página suelta con `shop.css`) se
  carga en un `<iframe>` del panel y `/panel/diseno/tokens` le devuelve, con los
  valores del formulario, el mismo CSS que emitiría el storefront. No hay lógica
  de color duplicada en JavaScript.
- **Tema claro/oscuro y el modo del visitante**: `data-color-scheme` en `<html>`.

### Portada

`themes/idirecto/home.php` compone: `_hero.php` (slider a ancho completo),
franja de garantías, **accesos rápidos** (`Catalog::quickCategories()`, declarados
en `config/catalog.php` → `quick_links`, resueltos contra el árbol real para no
enlazar a listados vacíos a excepción de los `q`, que caen a la categoría si no
hay resultados), destacados y productos propios.

El slider (`_hero.php` + bloque `slider` de `shop.js`) usa `hidden` en las
diapositivas inactivas, pausa la rotación con `mouseenter`/`focusin`/pestaña
oculta, respeta `prefers-reduced-motion` y admite teclado y gesto táctil. La
primera imagen es el LCP: `fetchpriority="high"` y `preconnect` al host del CDN.

### Tarjetas de producto y filtros

- `_card.php` pinta marca, nombre, **chips de especificaciones** (`Specs::quickHighlights()`:
  primero `productos_ext.caracteristicas`, y si no hay, extraídos del propio
  nombre), etiqueta de **stock dinámica** (`stock_band`: `in|low|out` según el
  stock real) y CTA que aparece al pasar el ratón o al enfocar.
- `Catalog::hydrate()` completa un listado con **3 consultas fijas** (marca,
  stock y características), nunca una por tarjeta.
- Los filtros se declaran en `config/catalog.php` → `facets` (`socket`, `gpu`,
  `memoria`, `factor`, `almacenamiento`, `marca` dinámica y `precio`) y se pintan
  como **enlaces**, de modo que funcionan sin JavaScript y cada combinación tiene
  URL propia. `Catalog::selectionFromQuery()` sanea la selección contra la
  configuración y `Catalog::facets()` limita cada filtro por contexto (`when`).
- **Orden por precio**: `Catalog::sorts($acotado)`. Ordenar por precio obliga a
  calcular la subconsulta de `precios` para cada candidato; sobre el catálogo
  entero son ~4 s, así que solo se ofrece cuando hay categoría, búsqueda, faceta o
  rango que acote el listado.

### Menú de categorías del catálogo

La cabecera monta un **megamenú** (estilo PcComponentes / PuntoByZE) a partir de
`Catalog::menuTree()`: categorías en columna lateral y subcategorías repartidas
en columnas; en móvil es pantalla completa con navegación por pasos y botón
atrás. Solo entran categorías **con stock** y el árbol se cachea 30 min en
`storage/cache/catalog_menu.json` (`Catalog::MENU_TTL`). Se pinta en
`layouts/shop.php` y su CSS/JS viven en los bloques `catmenu` de `shop.css` y
`shop.js`.

### Rutas del panel (`/panel/*`)

`login`, `logout`, `dashboard`, `diseno` (+ `diseno/tokens` y `diseno/previa`
para la vista previa), `banners`, `avisos`, `productos` (CRUD), `dominios` (alta,
verificar, borrar), `ajustes`, `media/subir` y `media/{id}/borrar`. Todas exigen
sesión + CSRF, y filtran por el `store_id` de la sesión.

Además, el módulo de pedidos (ver §7bis):

| Ruta | Contenido |
|---|---|
| `/panel/pedidos` | Listado con pestañas de estado, buscador y paginación |
| `/panel/pedidos/nuevo` · `POST /panel/pedidos` | Alta manual de un pedido |
| `/panel/pedidos/buscar` | JSON: buscador de productos (catálogo + propios) |
| `/panel/pedidos/{id}` | Ficha: cliente, entrega, líneas y envío al mayorista |
| `POST /panel/pedidos/{id}/lineas` · `.../lineas/{line}/borrar` | Añadir / quitar líneas |
| `POST /panel/pedidos/{id}/estado` | Cambiar de estado |
| `POST /panel/pedidos/{id}/idirecto` | Enviar **solo las líneas marcadas** a idirecto |
| `POST /panel/pedidos/{id}/borrar` · `.../restaurar` | Papelera y restaurar |

Las rutas `nuevo` y `buscar` van **antes** de `/panel/pedidos/{id}` (el router
resuelve en orden de declaración).

### 7bis. Pedidos y envío al mayorista

Un pedido es lo que **un cliente de la tienda** ha comprado: `mt_orders`
(cliente, entrega, notas, importes que paga el cliente, estado) + `mt_order_items`
(líneas). No hay checkout todavía: los pedidos se dan de alta a mano en el panel
(o los creará el checkout con `Order::createWithItems()`).

**Estados** (`mt_orders.status`): `0` borrador, `1` activo, `2` preparado,
`3` enviado, `4` facturado, `5` cancelado, `6` borrado (papelera restaurable). El
listado los agrupa en pestañas y admite además un estado concreto (`?estado=4`).

**Envío por líneas.** En la ficha se marcan las líneas que se quieren mandar (por
ejemplo 2 de 4) y `OrderGateway::send()` escribe en el mayorista:

1. `pedidos_addr` con la dirección de **facturación** (datos de la tienda) y, si el
   pedido trae dirección de entrega propia, otra para el **envío** (si no, se
   reutiliza la de facturación).
2. `pedidos` con `web = 1`, `estado = NULL` (activo), `fecha = NOW()`,
   `referencia = TIENDA-<slug>-<código>`, `id_tienda` = cuenta de la tienda,
   `forma`/`plazo`/`dias`/`sucursal_id` de la cuenta y los totales del mayorista.
3. `pedidos_det` por línea con `costo` (coste del mayorista), `ganancia`
   (`precio de tarifa − coste`), `impuestos` (IVA del producto o de la tienda),
   `sujeto`, `canon`, `id_almacen` y `precio_actual`.
4. Descuenta `stock` y suma `reserva` en los almacenes tipo 0/4 (igual que
   idirecto; `IDIRECTO_RESERVE_STOCK=false` lo desactiva).
5. Marca las líneas en `mt_order_items` (`sent_at`, `idirecto_pedido_id`,
   `price_idirecto`, `cost_idirecto`), actualiza `mt_orders` y, si ya no queda
   ninguna línea del catálogo pendiente, pasa el pedido a **enviado**.

La tarifa sale de `precios` con el `id_margen` de la tienda (respaldo:
`tiendas.id_margen` → `IDIRECTO_DEFAULT_ID_MARGEN`) y el coste de `stock` +
`tarifas`, con los mismos criterios que `getPrecio()`/`getCosto()` de idirecto
(`app/Core/Idirecto/Pricing.php`). Los **productos propios** no se pueden enviar
(no existen en el catálogo del mayorista): el panel los marca y quedan fuera.

`OrderGateway::preview()` hace exactamente los mismos cálculos **sin escribir**, y
es lo que la ficha muestra antes de confirmar (base, IVA y total).

### Temas

Catálogo cerrado en `mt_themes`. Implementado: **`idirecto`**.
`moderno` y `minimal` están sembrados pero **sin maquetar**.

La vista se resuelve con `View::render("themes/{tema}/{vista}")` y cae a la vista
base si el tema no la tiene, de modo que un tema incompleto no rompe.

---

## 8. Almacenamiento e imagenes

`StorageManager` elige driver según `STORAGE_DRIVER`:

- `local` → `public/uploads/` (solo desarrollo).
- `s3` → `S3Storage` (bucket del mayorista).

**En la base de datos solo se guardan claves y URLs**, nunca el binario. La
validación de MIME es real (`finfo`, por contenido), no por extensión.

### Clave de los ficheros (`StorageKey`)

Los dos drivers usan **la misma clave**, así que cambiar de driver no invalida lo
que ya hay en `mt_media`:

```
{STORAGE_PREFIX}/{STORAGE_FOLDER_PREFIX}_{tipo}/{id}_{tipo}_{fecha}-{aleatorio}.{ext}
tenants/tienda_banners/7_banners_20261001-174530-9f3c1a2b.webp
```

- La carpeta es `tienda_<tipo>` (banners, productos, logo, general…): permite
  listar o borrar familias enteras y ver de un vistazo qué es cada cosa.
- El nombre empieza por el **id de la tienda**, así un fichero se identifica (y
  se puede borrar) aunque se mueva de carpeta.

### Optimización al subir (`MediaUploader` + `ImageOptimizer`)

Todo pasa por `Tienda\Core\Media\MediaUploader::upload()` (subida HTTP) o
`storePath()` (ficheros en disco: importaciones, tareas):

- **JPG, PNG, AVIF, BMP y WebP → WebP** con calidad `IMAGE_QUALITY` (82 por
  defecto). Ahorro típico en fotos: 80-95 %.
- **GIF animado** se queda en GIF (a WebP perdería el movimiento) y **SVG** tal
  cual (es vectorial).
- Si un PNG ya pesa menos que su WebP (gráficos planos, logos), se respeta el
  original: mejor calidad y menos peso.
- Corrige la orientación EXIF (fotos de móvil), limita a `IMAGE_MAX_WIDTH` /
  `IMAGE_MAX_HEIGHT` sin ampliar nunca, y **quita los metadatos EXIF** (peso y
  privacidad: GPS, número de serie…).
- Interruptor de emergencia: `IMAGE_OPTIMIZE=false` guarda el original.

### Límites por tipo (`MediaRules`)

`Tienda\Core\Media\MediaRules` resuelve peso, calidad y tamaño máximo **por
tipo** (`config/storage.php` → `storage.types`, que pisa a los valores
generales). Por defecto los **banners admiten 8 MB** (`STORAGE_MAX_BYTES_BANNERS`)
y se comprimen con **calidad 86** (`IMAGE_QUALITY_BANNERS`) porque son fotos
grandes de portada donde se notan los degradados; el resto se queda en 5 MB y
calidad 82. El panel lee el límite del propio tipo (`data-max-bytes`) y avisa
antes de subir.

Dos cosas que hay que tener presentes al tocar estos límites:

- **PHP** corta antes que la aplicación: `upload_max_filesize` (2 MB por
  defecto) y `post_max_size`. En Apache se ajustan en `.htaccess`
  (`12M`/`13M`); con PHP-FPM, en el pool. `deploy/setup-nginx-domain.sh` avisa
  si el `php.ini` de FPM sigue corto.
- Si el fichero no llega, el panel lo explica: `413`/422 con
  «La imagen supera el límite de subida del servidor…». Ojo: por encima de
  `post_max_size` PHP vacía también `$_POST`, así que esa comprobación va **antes**
  del CSRF en `MediaController::upload()`.

Los tipos MIME de `.webp`/`.avif` se declaran en `.htaccess` porque algunos
servidores no los traen en `/etc/mime.types` y servirían la imagen sin
`Content-Type`.

---

## 9. Entorno local

```bash
sudo bash deploy/setup-local-domain.sh     # /etc/hosts + VirtualHost + permisos
php tools/verify.php                       # 118 comprobaciones
php -S 127.0.0.1:8099 index.php            # servidor embebido (alternativa)
```

Credenciales demo: `admin@demo.test` / `demo1234`.

El script también deja los permisos correctos: `.env` en `640` con grupo
`www-data`, y escritura para `www-data` en `storage/{cache,logs}` y
`public/uploads`.

### Producción con nginx (valduran.com)

```bash
sudo apt-get install -y nginx php8.4-fpm
sudo bash deploy/setup-nginx-domain.sh valduran.com
# .env: APP_URL, BASE_DOMAINS=valduran.com y PLATFORM_CNAME
sudo certbot --nginx -d valduran.com -d www.valduran.com
```

El mismo código sirve para Apache y para nginx; para cambiar de dominio basta
con repetir el script con el nuevo nombre y tocar esas tres claves del `.env`.

---

## 10. Verificación antes de dar algo por hecho

```bash
php tools/verify.php                 # debe decir: TODO OK (118 comprobaciones)
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/catalogo
curl -s -o /dev/null -w '%{http_code}\n' "http://local.tienda/catalogo?cat=9&subcat=102&f%5Bsocket%5D%5B0%5D=am5"
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/registro
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/panel/login
bash .agents/scripts/check-privacidad.sh    # la documentación no debe ser web
```

Para probar el envío a idirecto **sin dejar rastro** en las tablas del mayorista,
`OrderGateway::send()` se une a una transacción ya abierta (`Database::pdo()`), así
que se puede ejecutar dentro de un `beginTransaction()` y hacer `rollBack()` al
terminar: sirve para comprobar que todo se escribe bien sin crear pedidos reales.

Los listados del catálogo no deben tardar segundos: si vuelven a ir lentos, mirar
el plan de la consulta de stock (ver §11, «Semijoin de stock»).

Para comprobar JavaScript renderizado, hay Chrome headless disponible:

```bash
google-chrome --headless=new --disable-gpu --no-sandbox \
  --virtual-time-budget=8000 --dump-dom "http://local.tienda/producto/x/254299" | less
```

---

## 11. Trampas ya pisadas (no repetir)

| Síntoma | Causa | Solución |
|---|---|---|
| `Invalid parameter number` en un `LIKE` | Con `ATTR_EMULATE_PREPARES = false` un parámetro nombrado **no puede repetirse** | Usar `:q1`, `:q2`… |
| `There is no active transaction` en migraciones | MySQL hace *commit* implícito en DDL | No envolver DDL en transacciones |
| El login del panel "no hace nada" en HTTP | mod_php (PHP 8.4) añade `;HttpOnly;Secure;SameSite=None`; el navegador descarta la cookie `Secure` | `Header edit` en `.htaccess` (ya aplicado) |
| Credenciales visibles en el servidor embebido | `index.php` servía **cualquier** fichero existente, incluido `.env` | Solo se sirve estáticamente `/public` |
| Imágenes rotas en listados/ficha | `img_name` es una plantilla `sprintf`, no un nombre de fichero | `applyImagePattern()` |
| Cambios de CSS/JS que "no se ven" | Caché del navegador | `asset()` añade `?v=<filemtime>` |
| `500` al buscar en el catálogo | Parámetro `:q` repetido (ver primera fila) | Marcadores distintos |
| Colación `utf8mb3` vs `utf8mb4` en FK | Tablas antiguas del mayorista | Declarar `CHARACTER SET` explícito |
| **Catálogo y portada tardan ~3 s** | `EXISTS` **correlacionado** contra `stock` (845 k filas): el optimizador lo reevalúa fila a fila | Usar **semijoin**: `p.part_number IN (SELECT …)`. El listado pasa de ~3 s a ~20 ms (ver §11bis) |
| El listado se vuelve lento al añadir la marca | El `LEFT JOIN marcas` cambia el plan de ejecución (más de 1 s él solo) | Quitar el JOIN del listado y resolver la marca **por lote** (`attachBrands()`) |
| Ordenar por precio en el catálogo entero tarda ~4 s | `ORDER BY` con la subconsulta de `precios` se calcula para cada candidato | Ofrecer el orden por precio solo cuando el listado está acotado (`Catalog::sorts($acotado)`) |
| Un `Warning` de PHP aparece **dentro** del `href` de un enlace | En una vista, el closure no capturaba una variable (`use (…)`) y con `html_errors` el aviso se imprime como HTML | Los closures de las vistas deben capturar todo lo que usan; comprobar el HTML con `curl \| grep -i warning` |
| La previa del panel parece no aplicar el modo oscuro | `getComputedStyle` leído justo tras cambiar `data-color-scheme` devuelve el color **a mitad de transición** | Leer los tokens de `:root` con `getPropertyValue('--c-…')` o esperar un *tick* |
| No se puede insertar en `pedidos` del mayorista | `pedidos.id_facturacion` es `NOT NULL` (y sin valor por defecto) | Crear **antes** la fila de `pedidos_addr` y usar su `id`; `OrderGateway` lo hace en la misma transacción |
| "¿Y si se envía dos veces el mismo pedido a idirecto?" | El envío por pedido duplicaría líneas | El envío es **por línea** (`mt_order_items.sent_at` + `idirecto_pedido_id`) y `OrderGateway::loadItems()` solo carga líneas del catálogo pendientes: repetir el envío no duplica nada |
| Botones de borrar de cada línea dentro del formulario de envío | HTML **no permite anidar formularios**: el navegador desarma el marcado | Las casillas se asocian al formulario con el atributo `form="..."` de HTML5 y la tabla queda fuera de él |
| El buscador de productos del panel devuelve productos de otra marca | `productos.marca` es un dato del mayorista que a veces no coincide con el nombre | El buscador filtra por nombre, referencia y marca a propósito; la marca puede ser imprecisa |
| `/panel/pedidos/nuevo` mostraba la ficha de un pedido | El router resuelve **en orden de declaración**: `{id}` capturaba `nuevo` | Declarar `nuevo` y `buscar` **antes** de `/panel/pedidos/{id}` |
| `password_verify()` no valida ninguna cuenta de `tiendas` | La contraseña del mayorista **no** es `password_hash`: es `hash('sha256', md5(sha1($clave)))` | Usar `Account::signature()` / `Account::login()`; si cambian su login, ajustar ahí |
| Un cliente del mayorista no puede registrarse y su contraseña es correcta | `tiendas.activo` **no** vale `1`: idirecto marca las cuentas activas con `activo = 2` (y hay que descartar `cerrada` y `deleted`) | Mismo filtro que el login de idirecto: `activo = 2 AND COALESCE(cerrada,0)=0 AND COALESCE(deleted,0)=0` |
| `SQLSTATE[HY093]: Invalid parameter number` al buscar clientes | En `Customer::forStore()` el mismo `:q` se usaba en tres `LIKE` de la misma consulta (prohibido con `EMULATE_PREPARES = false`) | Un parámetro por repetición: `:q1`, `:q2`, `:q3` |
| La web muestra un precio y el pedido cobra otro | El storefront trabaja **con IVA incluido** (`Catalog::forStore(..., true)`) y el panel **sin IVA** (`false`); `price_final` es el precio de venta de cada contexto y `price_net` la base | Convertir con `Catalog::netFromSale()` / `saleFromNet()`; en el pedido se guarda **siempre** la base sin IVA |
| Los precios del catálogo salen «demasiado baratos» (por debajo del coste) | Nadie fijó el contexto de la tienda y `precioSql()` cae en `MIN(precios.precio)` de **todas** las tarifas | Llamar a `Catalog::forStore($id_margen, $markup, $tax, $withTax)` al empezar la petición: lo hace `Controller::bootPricing()` en el storefront y `OrderController::bootStorePricing()` en el panel |
| `Fatal error: Access level to ...::requireAuth() must be protected` | Ese método ya existe en `Core\Controller`: un controlador hijo no puede bajarlo a `private` | No redefinirlo (o hacerlo `protected`); en los `Admin/*` solo se redefine `storeId()` |
| Una compra fallida dejaba una dirección vacía en la libreta | Se guardaba la dirección **antes** de validar el pedido | Guardarla solo cuando el pedido ya está registrado (`CheckoutController::place()`) |

### 11bis. Semijoin de stock (rendimiento)

`Catalog::stockExistsSql()` expresa la visibilidad como
`p.part_number IN (SELECT s.part_number FROM stock s INNER JOIN almacenes a …)`.
Es **la misma regla** que el `EXISTS` original (verificado: 41.289 productos con
ambas formas) pero la subconsulta no depende de la fila exterior, así que MySQL
la resuelve una vez.

El contador total y los destacados de portada sí son caros (~1-3 s con la caché
InnoDB fría: recorren `productos` comprobando `stock`) y por eso se cachean en
fichero 10 minutos (`storage/cache/catalog_count_*.json`, `catalog_featured_*.json`). Si algún día
se quiere eliminar ese pico periódico, la vía limpia es materializar el stock
válido en una tabla propia `mt_` refrescada por tarea — es una decisión de
frescura de datos del dueño, no un cambio que deba hacer un agente por su cuenta.

---

## 12. Roadmap

Ver [`STATE.md`](STATE.md) para el estado detallado. Pendiente principal:

1. **Cobro real y emails** — el checkout registra el pedido y su forma de pago, pero
   no cobra: falta la pasarela (Redsys, con firma y notificación), el estado de la
   transacción y los emails de confirmación al cliente y aviso a la tienda. De ahí
   cuelga también **recuperar la contraseña** del cliente.
2. **Gastos de envío por peso y provincia** — ahora es tarifa plana + gratis desde X €;
   puntobyze los calcula por peso del pedido y provincia de destino.
3. **Panel maestro del mayorista** — supervisión, tarifas y auditoría de ventas.
   El alta ya existe (`/registro`, con la cuenta del mayorista) y los clientes finales
   están en `mt_customers`; falta el punto de vista del mayorista para gestionar todas
   las tiendas.
4. **Materializar el stock válido** (`mt_` refrescada por tarea) para eliminar el
   pico del contador y permitir orden por precio en todo el catálogo.
5. **Temas `moderno` y `minimal`**.
6. **Tests (PHPUnit) e integración continua.**
