# STATE — Estado del proyecto

> **El agente actualiza este fichero al terminar cada sesión.**
> Última actualización: **2026-09-29**

---

## Resumen

La plataforma está **funcionando** en http://local.tienda: storefront con
catálogo real, ficha de producto y panel completo. Falta el ciclo de compra
(carrito y pago) para poder vender.

> ⚠️ **Aviso de entorno (2026-09-30).** En esta máquina, la base de datos del
> `.env` (`idirecto_db`) ya **no** contiene el catálogo (`productos`, `stock`,
> `precios`…) ni las tablas `mt_` de la plataforma, así que la web responde
> **500** en local. El catálogo está ahora en las bases `idirecto` (235.165
> productos, 36 categorías) y `dev_idirecto_db`. El dueño pidió **no tocar la
> base de datos**: para volver a un entorno funcional hay que restaurarla o
> apuntar `DB_NAME` a la correcta y ejecutar
> `php database/migrate.php --seed` (crea/repuebla las `mt_`).

| Área | Estado |
|---|---|
| Multi-tienda (resolución por hostname) | ✅ Completo |
| Catálogo central (stock, búsqueda, paginación) | ✅ Completo |
| Ficha de producto (galería + especificaciones) | ✅ Completo |
| Panel de la tienda (diseño, banners, avisos, productos, dominios, ajustes) | ✅ Completo |
| Dominios propios + verificación DNS | ✅ Completo |
| Almacenamiento (local / S3) | ✅ Completo (S3 sin probar contra bucket real) |
| Entorno local (`http://local.tienda`) | ✅ Completo |
| **Despliegue Apache y nginx** | ✅ Completo (nginx verificado con binario real) |
| **Checkout: carrito, pago y envío** | ❌ **No existe** |
| **Precio según tarifa de la tienda** | ⚠️ Provisional (`MIN(precios.precio)`) |
| Temas `moderno` y `minimal` | ⚠️ Sembrados, sin maquetar |
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
  idirecto: 39.437 de 233.773 productos; contador cacheado 5 min.
- Buscador, filtro por categoría y **subcategoría** (`?subcat`) y paginación.
- **Menú de categorías (megamenú)** en la cabecera, estilo PuntoByZE: categorías
  + subcategorías con stock (33 y 304), cacheadas 30 min; en móvil pantalla
  completa con botón atrás. El catálogo lista las subcategorías de la categoría
  activa y las migas de pan ya funcionan.
- URLs SEO `/producto/{slug}/{id}` con 301 a la canónica y `<link rel="canonical">`.
- Ficha con la **presentación calcada de idirecto**: carrusel horizontal
  navegado por miniaturas, especificaciones agrupadas, "De un vistazo",
  descripción y opiniones.
- Productos propios de la tienda mezclados con el catálogo central.
- Banners, avisos y páginas de contenido.
- **Layout fluido en pantallas grandes**: el contenedor general crece de 1180 a
  1800 px según el monitor; el catálogo usa filtros laterales (sticky) y la
  ficha reparte mejor galería / información / compra. Responsive intacto de
  360 px en adelante.

### Panel
- Login, dashboard con contadores, diseño (colores, tipografía, cabecera).
- CRUD de banners, avisos y productos propios con **cuota por plan**.
- Subida de imágenes con validación de MIME real y vista previa.
- Alta de dominios propios con instrucciones DNS y verificación.

### Entorno y operación
- `deploy/setup-local-domain.sh`: dominio de pruebas `http://local.tienda` con
  permisos correctos para `www-data` (Apache).
- `deploy/setup-nginx-domain.sh` + `deploy/nginx-site.conf.tpl`: el mismo sitio
  en **nginx + PHP-FPM** (`sudo bash deploy/setup-nginx-domain.sh valduran.com`).
- La app detecta el servidor (`app/Core/Server.php`): ruta pública de `/public`,
  esquema real (incluido proxy) y host de las URLs canónicas.
- `tools/verify.php`: 29 comprobaciones automáticas.
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
si el tema no la implementa, así que se pueden añadir sin riesgo.

### 5. Tests
No hay ninguno. Prioridad: `Specs` (parseo de especificaciones), `Str::slugify`,
`TenantResolver` y `Dns::evaluate` (lógica pura, fácil de testear).

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

---

## Riesgos conocidos

- `CATALOG_IMAGE_URL` apunta a `https://idirecto.es/img_products`. Si el
  mayorista bloquea el *hotlinking* o cambia las rutas, las imágenes dejan de
  verse (el *fallback* de marcador funciona, no rompe la página).
- El catálogo depende de tablas del mayorista que pueden cambiar sin aviso.
  `Catalog` es defensivo, pero conviene revisar `tools/verify.php` tras cambios.
- Las credenciales de `.env` están en texto plano en el servidor (fuera del
  repositorio). Es lo habitual, pero conviene revisar permisos (`640`).
