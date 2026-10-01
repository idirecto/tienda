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
