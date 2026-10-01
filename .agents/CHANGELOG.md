# CHANGELOG — Historial de sesiones

Registro de qué se cambió y **por qué**. La entrada más reciente va arriba.
El detalle línea a línea está en `git log`.

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
