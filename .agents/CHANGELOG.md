# CHANGELOG — Historial de sesiones

Registro de qué se cambió y **por qué**. La entrada más reciente va arriba.
El detalle línea a línea está en `git log`.

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
