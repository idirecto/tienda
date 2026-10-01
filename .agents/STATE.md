# STATE — Estado del proyecto

> **El agente actualiza este fichero al terminar cada sesión.**
> Última actualización: **2026-10-01**

---

## Resumen

La plataforma está **funcionando** en http://local.tienda: storefront con
catálogo real, ficha de producto y panel completo. Falta el ciclo de compra
(carrito y pago) para poder vender.

> ✅ **Entorno (2026-10-01).** `idirecto_db` está **completa** (239 tablas:
> catálogo con 41.289 productos con stock, 33 categorías con stock, y las `mt_`),
> la web responde 200 y `php tools/verify.php` da **TODO OK (62)**.
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
| Catálogo central (stock, búsqueda, paginación) | ✅ Completo (**listados en ~20 ms**, ver notas) |
| Ficha de producto (galería + especificaciones) | ✅ Completo (colores ya tokenizados) |
| **Sistema de diseño white-label (tokens, claro/oscuro, presets, previa en vivo)** | ✅ Completo |
| **Portada (slider + accesos rápidos) y tarjetas con specs y stock** | ✅ Completo |
| **Filtros avanzados (socket, gráfica, memoria, formato, almacenamiento, marca, precio)** | ✅ Completo |
| Panel de la tienda (diseño, banners, avisos, productos, dominios, ajustes) | ✅ Completo |
| Dominios propios + verificación DNS | ✅ Completo |
| Almacenamiento (local / S3) | ✅ Completo (S3 sin probar contra bucket real) |
| Subida de imágenes (carpetas `tienda_<tipo>`, id en el nombre, WebP) | ✅ Completo |
| Entorno local (`http://local.tienda`) | ✅ Completo |
| **Despliegue Apache y nginx** | ✅ Completo (nginx verificado con binario real) |
| **Checkout: carrito, pago y envío** | ❌ **No existe** |
| **Precio según tarifa de la tienda** | ⚠️ Provisional (`MIN(precios.precio)`) |
| Temas `moderno` y `minimal` | ⚠️ Sembrados, sin maquetar (los tokens valen para cualquiera) |
| Panel maestro del mayorista | ❌ No existe |
| Tests automatizados / CI | ❌ No existe |

---

## Hecho

### Infraestructura
- MVC propio con autoload PSR-4 (`Tienda\` → `app/`), sin framework.
- 13 tablas `mt_` idempotentes + semillas (3 planes, 3 temas, tienda demo).
- Multi-tenant por hostname (subdominio → dominio propio verificado → demo).
- Sesión, CSRF y `password_hash` en el panel.
- Almacenamiento conmutable local/S3 (en BD solo claves y URLs).
- Verificación DNS (A/CNAME) con *lookup* inyectable para poder testear sin red.

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
- `tools/verify.php`: 62 comprobaciones automáticas (diseño, facetas, catálogo,
  almacenamiento, DNS, servidor).
- Documentación interna en `.agents/`, blindada frente a la web.
- Repositorio publicado en GitHub (`main`).

---

## Pendiente (por orden sugerido)

### 1. Precio según la tarifa de la tienda ⚠️
Hoy `Catalog::decorate()` usa `MIN(precios.precio)` sobre todas las tarifas, así
que puede mostrar un precio que no corresponde a esa tienda.

**Decisión pendiente del dueño:** ¿la tarifa la asigna el mayorista a cada
tienda, la elige la tienda, o se deriva del plan contratado? Sin esa decisión no
se puede implementar.

### 2. Checkout
Carrito (sesión), pasarelas de pago **de la tienda** (no del mayorista), cálculo
de envío y registro del pedido. Requiere tablas nuevas (`mt_orders`,
`mt_order_items`) y, si se quiere cumplir la regla de negocio original, dejar
constancia de las ventas para la política de precios por volumen.

### 3. Panel maestro del mayorista
Supervisión de tiendas, asignación de tarifas, auditoría de ventas.

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

---

## Riesgos conocidos

- `CATALOG_IMAGE_URL` apunta a `https://idirecto.es/img_products`. Si el
  mayorista bloquea el *hotlinking* o cambia las rutas, las imágenes dejan de
  verse (el *fallback* de marcador funciona, no rompe la página).
- El catálogo depende de tablas del mayorista que pueden cambiar sin aviso.
  `Catalog` es defensivo, pero conviene revisar `tools/verify.php` tras cambios.
- Las credenciales de `.env` están en texto plano en el servidor (fuera del
  repositorio). Es lo habitual, pero conviene revisar permisos (`640`).
