# STATE — Estado del proyecto

> **El agente actualiza este fichero al terminar cada sesión.**
> Última actualización: **2026-10-01**

---

## Resumen

La plataforma está **funcionando** en http://local.tienda: storefront con
catálogo real, ficha de producto, panel completo y **pedidos con envío por líneas
al mayorista**. Falta el ciclo de compra en la tienda (carrito y pago) para que
los pedidos entren solos: hoy se dan de alta a mano o los meterá el futuro
checkout por el mismo modelo (`mt_orders`/`mt_order_items`).

> ✅ **Entorno (2026-10-01).** `idirecto_db` está **completa** (239 tablas:
> catálogo con 41.289 productos con stock, 33 categorías con stock, y las `mt_`),
> la web responde 200 y `php tools/verify.php` da **TODO OK (90)**.
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
| Catálogo central (stock, búsqueda, paginación) | ✅ Completo (**listados en ~20 ms**, ver notas) |
| Ficha de producto (galería + especificaciones) | ✅ Completo (colores ya tokenizados) |
| **Sistema de diseño white-label (tokens, claro/oscuro, presets, previa en vivo)** | ✅ Completo |
| **Portada (slider + accesos rápidos) y tarjetas con specs y stock** | ✅ Completo |
| **Filtros avanzados (socket, gráfica, memoria, formato, almacenamiento, marca, precio)** | ✅ Completo |
| Panel de la tienda (diseño, banners, avisos, productos, dominios, ajustes) | ✅ Completo |
| **Pedidos: listado por estados, ficha y envío por líneas a idirecto** | ✅ Completo |
| Dominios propios + verificación DNS | ✅ Completo |
| Almacenamiento (local / S3) | ✅ Completo (S3 sin probar contra bucket real) |
| Subida de imágenes (carpetas `tienda_<tipo>`, id en el nombre, WebP) | ✅ Completo |
| Entorno local (`http://local.tienda`) | ✅ Completo |
| **Despliegue Apache y nginx** | ✅ Completo (nginx verificado con binario real) |
| **Checkout: carrito, pago y envío** | ❌ **No existe** (modelo de pedidos y envío al mayorista, sí) |
| **Precio según tarifa de la tienda** | ⚠️ Provisional en el storefront (`MIN(precios.precio)`); los **pedidos** ya usan la tarifa de la tienda (`mt_stores.id_margen`) |
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

### Storefront
- Catálogo filtrado a **productos con stock**, con las mismas condiciones que
  idirecto: 39.437 de 233.773 productos; contador cacheado 10 min.
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
  tiene URL propia.
- Buscador, filtro por categoría y **subcategoría** (`?subcat`) y paginación,
  con **20 productos por página** (`CATALOG_PER_PAGE`; tope duro 60 en
  `Catalog::paginate`). Los **destacados de la portada** van aparte
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

### Entorno y operación
- `deploy/setup-local-domain.sh`: dominio de pruebas `http://local.tienda` con
  permisos correctos para `www-data` (Apache).
- `deploy/setup-nginx-domain.sh` + `deploy/nginx-site.conf.tpl`: el mismo sitio
  en **nginx + PHP-FPM** (`sudo bash deploy/setup-nginx-domain.sh valduran.com`).
- La app detecta el servidor (`app/Core/Server.php`): ruta pública de `/public`,
  esquema real (incluido proxy) y host de las URLs canónicas.
- `tools/verify.php`: 90 comprobaciones automáticas (diseño, facetas, catálogo,
  almacenamiento, DNS, servidor, registro de tiendas, pedidos y envío al mayorista).
- Documentación interna en `.agents/`, blindada frente a la web.
- Repositorio publicado en GitHub (`main`).

---

## Pendiente (por orden sugerido)

### 1. Precio según la tarifa de la tienda ⚠️
En el **storefront** `Catalog::decorate()` sigue usando `MIN(precios.precio)` sobre
todas las tarifas, así que la web puede mostrar un precio que no corresponde a esa
tienda. Los **pedidos** ya usan la tarifa de la tienda (`mt_stores.id_margen`, con
respaldo en `tiendas.id_margen`) porque el envío al mayorista la necesita. Lo que
falta es aplicar esa misma tarifa a los precios del catálogo público.

**Decisión pendiente del dueño:** ¿la tarifa la asigna el mayorista a cada tienda, la
elige la tienda, o se deriva del plan contratado? Ahora mismo se puede fijar a mano
en Ajustes.

### 2. Checkout
Carrito (sesión), pasarelas de pago **de la tienda** (no del mayorista), cálculo
de envío y registro del pedido. El modelo de pedidos **ya existe**
(`mt_orders`/`mt_order_items`) y el envío al mayorista también: el checkout solo
tiene que crear el pedido con `Order::createWithItems()` (mismo camino que el alta
manual del panel), de modo que las líneas del catálogo se puedan enviar luego a
idirecto como ya hace la ficha del pedido.

### 3. Panel maestro del mayorista
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
- `CATALOG_IMAGE_URL` apunta a `https://idirecto.es/img_products`. Si el
  mayorista bloquea el *hotlinking* o cambia las rutas, las imágenes dejan de
  verse (el *fallback* de marcador funciona, no rompe la página).
- El catálogo depende de tablas del mayorista que pueden cambiar sin aviso.
  `Catalog` es defensivo, pero conviene revisar `tools/verify.php` tras cambios.
- Las credenciales de `.env` están en texto plano en el servidor (fuera del
  repositorio). Es lo habitual, pero conviene revisar permisos (`640`).
