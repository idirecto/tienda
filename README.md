# Tienda — Plataforma multi-tienda (idirecto)

Proyecto **nuevo e independiente** que permite a cada tienda cliente tener su
propia web (storefront + panel de administración), alimentada por el catálogo
central del mayorista, con productos propios según su plan, imágenes en Amazon S3
y dominio propio con verificación DNS.

> No modifica nada de la web actual de idirecto: se conecta a la misma base de
> datos y **solo crea sus propias tablas** (prefijo `mt_`).

> **¿Vas a trabajar en el código (persona o agente)?** Empieza por
> [`.agents/PROMPT.md`](.agents/PROMPT.md) (la petición en curso) y sigue con
> [`.agents/PROJECT.md`](.agents/PROJECT.md) (arquitectura y trampas conocidas).
> Ese directorio **no se sirve por web**: solo es accesible por git o con acceso
> al servidor. Compruébalo con `bash .agents/scripts/check-privacidad.sh`.

---

## 1. Requisitos

- PHP **8.2+** (probado en 8.4)
- MySQL 8 / MariaDB 10.5+
- **Apache** con `mod_rewrite` **o nginx + PHP-FPM** (cualquiera de los dos;
  la aplicación detecta el servidor y sirve los assets desde la ruta correcta).
  Para desarrollo también vale el servidor embebido de PHP.
- Opcional: `aws/aws-sdk-php` para subir imágenes a S3

Sin dependencias obligatorias: el proyecto funciona sin Composer (driver local).

---

## 2. Instalación

```bash
cd /var/www/html/tienda

# 1) Configuracion
cp .env.example .env
#   edita .env (base de datos, almacenamiento, dominios...)
php -r 'echo bin2hex(random_bytes(16));'   # genera un APP_KEY

# 2) Esquema + datos de ejemplo (crea la tienda demo)
php database/migrate.php --seed

# 3) Comprobacion
php tools/verify.php
```

### Servidor de desarrollo

```bash
php -S 127.0.0.1:8099 index.php
# Portada:      http://127.0.0.1:8099/
# Panel:        http://127.0.0.1:8099/panel
# Demo:         admin@demo.test / demo1234
```

### Dominio de pruebas con Apache: `http://local.tienda`

Configura Apache, `/etc/hosts` y los permisos necesarios (requiere `sudo`):

```bash
cd /var/www/html/tienda
sudo bash deploy/setup-local-domain.sh
```

Es idempotente y hace 5 cosas:

1. **Permisos**: `.env` en `640` con grupo `www-data` (Apache debe poder leerlo) y
   escritura para `www-data` en `storage/logs`, `storage/cache` y `public/uploads`.
2. Añade a `/etc/hosts`: `127.0.0.1 local.tienda www.local.tienda idirecto-demo.local.tienda`
   (con copia de seguridad previa de `/etc/hosts`).
3. Instala el VirtualHost `deploy/apache-vhost-local.conf` en
   `/etc/apache2/sites-available/local.tienda.conf`.
4. Ejecuta `a2enmod rewrite` y `a2ensite local.tienda`.
5. `apache2ctl configtest` + `systemctl reload apache2`.

URLs resultantes:

| URL | Contenido |
|---|---|
| http://local.tienda/ | Tienda demo (portada) |
| http://local.tienda/panel | Panel — `admin@demo.test` / `demo1234` |
| http://local.tienda/catalogo | Catálogo central |
| http://idirecto-demo.local.tienda/ | Acceso por subdominio |

> **PHP 8.4 + mod_php (importante):** añade `;HttpOnly;Secure;SameSite=None` a las
> cookies. En HTTP el navegador descarta una cookie `Secure` y **la sesión del panel
> no persiste** (el login parece no funcionar). El `.htaccess` del proyecto elimina
> ese sufijo con `Header edit "Set-Cookie"`; no lo quites si pruebas sin HTTPS.

### Dominio con nginx + PHP-FPM

Funciona igual que con Apache, pero nginx **no lee `.htaccess`**: el enrutado al
front controller y el bloqueo de rutas internas se definen en el `server` block.

```bash
cd /var/www/vhosts/valduran/tienda        # la raiz del proyecto
sudo bash deploy/setup-nginx-domain.sh valduran.com

# Ver que haria, sin tocar nada (no necesita sudo):
bash deploy/setup-nginx-domain.sh valduran.com --dry-run
```

La raíz y el dominio se detectan/reciben por argumento, así que el script sirve
para cualquier ruta (`ROOT=/otra/ruta`), dominio y usuario web
(`WEB_USER=nginx`, `FPM_SOCK=/run/php/php8.4-fpm.sock`).

El script (idempotente, requiere nginx y PHP-FPM instalados) hace:

1. **Permisos**: `.env` en `640` con grupo `www-data` y escritura para `www-data`
   en `storage/logs`, `storage/cache` y `public/uploads`.
2. Detecta el socket de PHP-FPM (`/run/php/phpX.Y-fpm.sock`; se puede forzar con
   `FPM_SOCK=...`) y lo añade con el prefijo `unix:`.
3. Renderiza [`deploy/nginx-site.conf.tpl`](deploy/nginx-site.conf.tpl) en
   `/etc/nginx/sites-available/<dominio>.conf` y lo enlaza en `sites-enabled`.
4. `nginx -t` + `systemctl reload nginx`.

La plantilla bloquea `.env`, `.git`, `app/`, `config/`, `database/`, `deploy/`,
`storage/`, `tools/` y `vendor/`, sirve solo `index.php` como PHP y cachea los
estáticos 30 días. La raíz del sitio es la **del proyecto** (no `public/`), igual
que en Apache.

Para cambiar de dominio más adelante, vuelve a ejecutarlo con el nuevo nombre y
actualiza `APP_URL`, `BASE_DOMAINS` y `PLATFORM_CNAME` en `.env`. Con HTTPS:

```bash
sudo certbot --nginx -d valduran.com -d www.valduran.com
```

> **¿Servidor con panel (Plesk, cPanel)?** `/var/www/vhosts/...` es la ruta típica
> de Plesk: el panel gestiona nginx y sobrescribe `/etc/nginx/plesk.conf.d`, así
> que **no** uses este script (te avisará y se detendrá). En ese caso:
> PHP Settings → 8.2+; Hosting Settings → **Document root** = `tienda`; deja
> nginx como proxy de Apache (así `.htaccess` sigue funcionando) y pega en
> *Additional nginx directives* el bloque `[PLESK]` que imprime el script.

> **¿El dominio ya tenía web en ese servidor?** No añadas un vhost nuevo: **edita
> el que ya existe** y cámbiale el `root` a la carpeta del proyecto. Si el vhost
> viejo escucha en **IPs concretas** (`listen 51.68.7.177:80`) o sirve el
> **443/HTTPS** con su certificado, el fichero que genera el script (que escucha
> en `0.0.0.0:80`) **nunca ganará** y seguirás viendo la web antigua. En ese
> caso, el bloque nuevo debe copiar el `listen`, el `ssl_certificate` y el
> `server_name` del vhost viejo, con las reglas de `deploy/nginx-site.conf.tpl`
> para el bloqueo de rutas internas y el front controller.

> La aplicación detecta el servidor (`app/Core/Server.php`): calcula la ruta
> pública de `/public`, el esquema real (también detrás de un proxy/CDN con
> `X-Forwarded-Proto`) y el host de las URLs canónicas. El mismo código vale en
> Apache y en nginx sin cambios.

### Permisos: si ves «Error interno» en el navegador

El síntoma clásico de permisos mal puestos es un **`500` con el texto «Error
interno»** (sin más detalle). Suele significar que el usuario del servidor web
(`www-data`) **no puede leer `.env`**: entonces la app se queda sin credenciales
y sin `APP_DEBUG`, y el error real solo aparece en el log.

El arreglo recomendado (idempotente, con `sudo`):

```bash
sudo bash deploy/setup-local-domain.sh      # Apache
```

Si no tienes `sudo` a mano, basta con dar acceso al usuario web por ACL, sin
abrir `.env` a todo el mundo (sustituye `www-data` por el usuario de tu
servidor: `www-data` en Debian/Ubuntu, `nginx` en otras distribuciones):

```bash
setfacl -m  u:www-data:r-- .env
setfacl -R -m u:www-data:rwX storage/logs storage/cache public/uploads
find storage/logs storage/cache public/uploads -type d -exec setfacl -m d:u:www-data:rwX {} +
```

Desde el 2026-10-01, si `.env` no es legible la línea del log
(`storage/logs/php-error.log`) incluye una pista explícita, y si `storage/logs`
no es escribible el error va al log del servidor en lugar de perderse.

---

## 3. Configuración (`.env`)

### Base de datos (configurable)

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=idirecto_db
DB_USER=usuario
DB_PASS=clave
DB_CHARSET=utf8mb4
```

Es la **base central del mayorista**: de ahí se lee el catálogo
(`productos`, `precios`, `stock`, `marcas`, `categorias`) y ahí se crean las
tablas propias del proyecto (`mt_*`).

### Almacenamiento (configurable)

```ini
# "local" para desarrollo, "s3" para el bucket del mayorista
STORAGE_DRIVER=local

# Carpeta de cada tipo dentro del bucket: {STORAGE_FOLDER_PREFIX}_{tipo}
STORAGE_PREFIX=tenants/
STORAGE_FOLDER_PREFIX=tienda
STORAGE_MAX_BYTES=5242880      # 5 MB por fichero

S3_REGION=eu-west-1
S3_BUCKET=mi-bucket
S3_KEY=...
S3_SECRET=...
S3_PUBLIC_URL=              # opcional (CDN o dominio propio del bucket)

# Si el SDK de AWS no esta en ./vendor, apunta a un autoload existente:
AWS_SDK_AUTOLOAD=
```

Para activar S3:

```bash
composer require aws/aws-sdk-php
# y pon STORAGE_DRIVER=s3 en .env
```

Los ficheros se guardan con **la misma clave en S3 y en local**, así cambiar de
driver no invalida lo que ya hay en `mt_media`:

```
tenants/tienda_banners/7_banners_20261001-174530-9f3c1a2b.webp
└ prefijo └ tienda_tipo  └ id de la tienda   fecha    aleatorio
```

Cada tipo de imagen (`banners`, `productos`, `logo`, `general`) tiene su carpeta
`tienda_<tipo>` y el nombre empieza por el **id de la tienda**: se puede listar o
borrar por familias y saber de quién es cada fichero. En base de datos **solo** se
persisten key y URL (`mt_media`), nunca binarios.

### Optimización de las imágenes al subirlas

```ini
IMAGE_OPTIMIZE=true            # false = guardar el original tal cual
IMAGE_QUALITY=82               # calidad WebP (1-100)
IMAGE_QUALITY_BANNERS=86       # los banners, con algo más de calidad
IMAGE_MAX_WIDTH=2560           # si es mayor se reduce; nunca se amplía
IMAGE_MAX_HEIGHT=2560
IMAGE_KEEP_ANIMATED_GIF=true
```

| Lo que se sube | Qué se guarda |
|---|---|
| JPG, PNG, AVIF, BMP, WebP | **WebP** comprimido (en fotos se ahorra un 80-95 %) |
| PNG que ya pesa menos que su WebP | El PNG original (mejor calidad y menos peso) |
| GIF **animado** | GIF (a WebP perdería el movimiento) |
| SVG | SVG tal cual (es vectorial) |

Además se corrige la orientación EXIF (fotos de móvil), se limita el tamaño
máximo y se eliminan los metadatos EXIF (peso y privacidad). El panel muestra el
ahorro al subir cada imagen (`JPG 2,4 MB → WEBP 380 KB, -84%`).

**Límite de peso por tipo** (`app/Core/Media/MediaRules.php`): los **banners
admiten 8 MB** y el resto 5 MB, configurable por tipo:

```ini
STORAGE_MAX_BYTES=5242880            # general: 5 MB
STORAGE_MAX_BYTES_BANNERS=8388608    # banners: 8 MB
```

> ⚠️ Para que un banner de 8 MB llegue a la aplicación, **PHP** tiene que
> permitirlo. En Apache queda ajustado en `.htaccess`
> (`upload_max_filesize 12M`, `post_max_size 13M`); con **PHP-FPM** hay que
> ponerlo en el pool (`/etc/php/*/fpm/php.ini`) y en nginx
> `client_max_body_size 16M;` (ya viene en `deploy/nginx-site.conf.tpl`).
> Si el fichero no llega, el panel lo dice: *«La imagen supera el límite de
> subida del servidor (12M)…»*.

### Multi-tenant

```ini
BASE_DOMAINS=idirecto.es,tienda.local
DEMO_STORE=idirecto-demo          # tienda mostrada si el host no resuelve
PLATFORM_IP=203.0.113.10          # IP para el registro A
PLATFORM_CNAME=stores.idirecto.es # destino del CNAME
```

### Catálogo central

```ini
CATALOG_ENABLED=true
CATALOG_IMAGE_URL=https://idirecto.es/img_products
CATALOG_IMAGE_PATH=               # ruta en disco (opcional, solo desarrollo local)
CATALOG_CARD_IMAGE_SIZE=l         # talla de imagen en los listados: c | l | f
CATALOG_MARKUP=30                 # % aplicado si no hay precio de tarifa
CATALOG_PER_PAGE=20               # productos por pagina en los listados
CATALOG_HOME_FEATURED=12          # destacados de la portada (seleccion editorial)
```

### Catálogo: qué productos se muestran

Se muestran **los mismos productos que en idirecto, y solo si tienen stock**.
Condiciones aplicadas en `app/Models/Catalog.php` (idénticas a idirecto):

- `productos.estado <> 4`
- existe stock válido: `stock.stock > 0`, `stock.activo = 1`, `stock.costo > 0`
  y el almacén no es de tipo 2
- se excluye la subcategoría `178`

La ficha de un producto **sigue existiendo aunque se agote** (muestra "Sin stock"),
pero los agotados **no aparecen** en los listados ni en los destacados.

El contador total del listado se cachea 5 minutos en `storage/cache/` para no
repetir un `COUNT` pesado en cada petición.

### URLs de producto (SEO)

Igual que idirecto: **el nombre del producto va en la URL**.

```
/producto/{nombre-slug}/{id}      ->  /producto/fusor-oki-42931703/8
```

- El `{slug}` se genera con `Tienda\Core\Str::slugify()`, el mismo criterio que
  el `ListingUrl::slugify()` de idirecto (minúsculas, sin acentos, guiones).
- El `{id}` es la clave real: aunque cambie el nombre, la URL antigua con otro
  slug **redirige 301** a la canónica (evita contenido duplicado).
- `/producto/{id}` (sin slug) también **redirige 301** a la canónica, así no se
  rompen enlaces antiguos.
- La ficha incluye `<link rel="canonical">` absoluto y `<title>`/`meta description`
  con el nombre del producto.

### Ficha de producto (estructura tipo idirecto)

La ficha reproduce la estructura de idirecto en `app/Views/themes/idirecto/product.php`:

```
Breadcrumb:  Inicio > Categoría > Subcategoría > Producto
┌──────────────┬──────────────────────────┬────────────────────┐
│ 1. Galería   │ 2. Información           │ 3. Compra          │
│ carrusel +   │ H1 + marca + referencia  │ precio grande      │
│ miniaturas   │ ★ valoración             │ stock + envío      │
│ + flechas    │ Especificaciones clave   │ cantidad + COMPRAR │
│ + contador   │ (6 visibles, "Ver más")  │ medios de pago     │
│ + zoom       │ Descripción corta        │                    │
└──────────────┴──────────────────────────┴────────────────────┘
De un vistazo · Descripción · Especificaciones (por grupos) · Opiniones
```

Origen de cada dato (todo del catálogo central, sin duplicar información):

| Elemento | Tabla / campo |
|---|---|
| Breadcrumb | `categorias.categoria`, `subcategorias.subcategoria` |
| Marca | `marcas.marca` |
| Referencia / Modelo | `productos.part_number` |
| Galería | `productos.img_name` (plantilla `%d`/`%s`) + `CATALOG_IMAGE_URL` |
| Especificaciones (grupos) | `productos_ext.especificaciones` (HTML, parseado) |
| Especificaciones clave / Descripción corta | resumen por prioridades del anterior; `productos_ext.caracteristicas` |
| Descripción larga | `productos_ext.razones`, `contenido_enriquecido` |
| Historia de marca | `marcas.brand_story` |
| Stock | `stock` + `almacenes` (solo almacenes válidos) |
| Opiniones | `productos_resenas` (`activa = 1`) |

#### Imágenes: `img_name` es una plantilla, no un fichero

`productos.img_name` vale p. ej. `<slug>-%d-%s`; se sustituye `%d` por el id y
`%s` por el tamaño, y se le añade `_N.jpg` (`_1` … `_5`):

| Tamaño | Uso | Peso real medido |
|---|---|---|
| `c` | miniaturas de la ficha | ~3 KB |
| `l` | **listados** (catálogo y destacados) | ~7 KB |
| `f` | **imagen grande del carrusel** | ~38 KB |
| `g` | zoom del visor ampliado (bajo demanda) | ~4 MB |
| `o` | original | ~12 MB |

La talla de los listados se puede cambiar con `CATALOG_CARD_IMAGE_SIZE` (`c`,
`l` o `f`); por defecto `l`.

La ficha replica la **presentación de idirecto**, tomando los valores de su CSS
real (`ficha-puntobyze.css`, `ficha-tecnica.css` y sus estilos en línea) para que
ambas páginas se vean igual:

- **Galería**: carrusel que se desliza lateralmente y se navega **solo con las
  miniaturas** (idirecto no usa flechas: `carousel-control` no aparece en su
  HTML). Las miniaturas usan el mismo reparto de anchos y la activa se marca con
  el color principal del tema.
- **Especificaciones**: tarjetas con filas separadas por una línea fina y el
  **valor alineado a la derecha en negrita**.
- **De un vistazo**: cuadros centrados con icono en caja destacada.
- **Descripción**: puntos con viñeta circular usando el color de acento.

La estructura visual sigue siendo la de idirecto, pero **los colores salen del
sistema de diseño** (ver §3.bis): antes estaban fijados a la paleta de idirecto y
ahora la ficha se adapta a la marca de cada tienda y al modo oscuro.

Aun así el JS (`public/assets/js/shop.js`) añade dos mejoras sobre el original:

- si una foto no existe (404) **se retira del carrusel y de las miniaturas**
  (probado: un producto con `_1`…`_4` disponibles y `_5` inexistente queda con
  4 diapositivas y 4 miniaturas, sin huecos ni iconos rotos); si lo que falta es
  solo la miniatura, se reutiliza la imagen grande;
- teclado (←/→), gesto de deslizar y visor ampliado con la versión `g`.

#### Especificaciones agrupadas

`Tienda\Core\Specs::groupsFromHtml()` parsea el HTML de `productos_ext.especificaciones`:
los `<h3>` son los **nombres de grupo** (Procesador, Memoria…) y las celdas
`<td>Clave: valor</td>` posteriores son sus filas, hasta el siguiente `<h3>`.
Se ignoran las celdas contenedoras para no duplicar filas.

`Specs::summary()` construye el resumen corto (Marca, Modelo y los atributos
más relevantes para el comprador, con coincidencia exacta antes que parcial) y
`Specs::highlights()` el bloque «De un vistazo», que respeta un orden fijo de
ranuras para no repetir el mismo dato dos veces.

`Specs::pairs()` sigue convirtiendo `caracteristicas` en pares clave/valor,
respetando las comas dentro de un valor (p. ej. `Red de datos: 3G, EDGE`).


---

## 3.bis Sistema de diseño, portada y filtros

### Design tokens (white-label de verdad)

El storefront **no tiene ni un color escrito a mano**: `public/assets/css/shop.css`
solo consume variables CSS (`--c-*`). Quien las genera y de dónde salen:

| Pieza | Papel |
|---|---|
| `config/appearance.php` | Valores por defecto de la plataforma (paletas clara y oscura, presets, radios, tipografías, anchos). Se puede ajustar por `.env` (`THEME_*`). |
| `mt_stores` | Lo que elige cada tienda en el panel: `color_primary`, `color_secondary`, `color_accent`, `color_bg`, `color_surface`, `color_text`, `color_border`, `color_scheme`, `radius_scale`, `font`, `theme_tokens`, `custom_css`. |
| `Tienda\Core\Appearance` | Resuelve el mapa de tokens (marca, superficies, texto, bordes, estados, stock, sombras, radios, tipografías), **deriva** los tonos que faltan (hover, suave, contraste legible) y emite el CSS. |

Prioridad: configuración → columnas de la tienda → `theme_tokens` (JSON que
pisa cualquier token, sin migrar la base de datos). El `<head>` del storefront
inyecta el resultado en línea, así que no hay fichero extra ni parpadeo.

**Modo oscuro nativo.** `color_scheme` admite `light`, `dark` y `auto` (sigue al
sistema). El CSS emite `:root[data-color-scheme="dark"]` y
`@media (prefers-color-scheme: dark) { :root[data-color-scheme="auto"] }`, de modo
que los mismos componentes cambian de piel sin duplicar reglas. El cliente puede
conmutar claro/oscuro desde la cabecera (se guarda en `localStorage` y se aplica
antes de pintar, sin destello).

**Presets.** El panel trae cinco identidades completas (retail limpio,
tecnológico oscuro tipo Caseking, gaming, profesional y minimal): un clic y la
tienda cambia de colores, modo, forma y tipografía. Se aplican en el servidor
(`apply_preset`), no solo en el navegador.

**Vista previa fiel.** El panel abre su propia tienda en un `<iframe>` y le
inyecta, por `postMessage`, el CSS que genera **el mismo** código que el
storefront (`/panel/diseno/tokens`). Lo que se ve en la previa es exactamente lo
que se publica, sin reimplementar el cálculo de colores en JavaScript.

### Portada

`app/Views/themes/idirecto/home.php` monta, de arriba abajo: slider a ancho
completo (`_hero.php`), franja de garantías, **accesos rápidos** a las categorías
principales, destacados del catálogo central y productos propios.

- El slider es un carrusel accesible: las diapositivas inactivas llevan `hidden`
  (no reciben foco), las flechas y los puntos son botones reales, la rotación se
  pausa al pasar el ratón o al enfocar y **se desactiva** con
  `prefers-reduced-motion`. Admite teclado y gesto de deslizar.
- La primera imagen lleva `fetchpriority="high"` (es el LCP) y el `<head>` hace
  `preconnect` al host de las imágenes del catálogo.
- Los accesos rápidos se resuelven contra el árbol real de categorías
  (`Catalog::quickCategories()`): si una categoría se queda sin stock, su tarjeta
  desaparece sola. Se configuran en `config/catalog.php` (`quick_links`), con
  `cat`, `subcat` y/u `q` (para nichos sin subcategoría propia, como
  «ultraligeros»).

### Tarjetas de producto

Miniatura grande, marca, nombre, **chips con las especificaciones que se
comparan** (socket, gráfica, memoria, capacidad…), etiqueta de stock dinámica
(`En stock` / `Últimas N ud.` / `Sin stock`) y CTA que aparece al pasar el ratón o
al enfocar con el teclado.

Los chips salen de `productos_ext.caracteristicas` (barato) y, si el producto no
trae datos, se **extraen del nombre** (`Specs::quickHighlights()`), que en
informática suele llevarlos (`RTX 5080`, `DDR5`, `LGA 1851`, `16 GB`…). Todo se
resuelve con tres consultas fijas por listado (marca, stock y especificaciones),
no una por tarjeta.

### Filtros avanzados (facets)

Se declaran en `config/catalog.php` (`facets`) y se pintan como **enlaces**, no
como formulario: cada combinación tiene URL propia (compartible e indexable) y
funciona sin JavaScript. En móvil se convierten en un panel lateral que activa el
JS (sin JS no hay botón muerto).

| Facet | Ámbito | Ejemplo |
|---|---|---|
| Socket | nombre + características | AM5, LGA 1851, LGA 1700 |
| Gráfica | nombre + características | RTX serie 50, RX 9000, Arc |
| Memoria | nombre + características | DDR5, DDR4, DDR3 |
| Factor de forma | nombre + características | E-ATX, ATX, Micro-ATX, Mini-ITX |
| Almacenamiento | nombre + características | NVMe, SSD, HDD |
| Marca | dinámico del catálogo | top 12 marcas con stock del contexto (cacheado) |
| Precio | rango | mínimo y máximo reales (cacheados) |

Los filtros se limitan por contexto (`when`: categoría o subcategoría), así que
«Socket» solo aparece donde tiene sentido. Los chips de filtros activos conservan
el resto al quitarse.

**Rendimiento.** El orden por precio obliga a MySQL a calcular el precio de cada
candidato: dentro de una categoría es instantáneo, pero sobre las ~41.000
referencias del catálogo entero cuesta segundos. Por eso solo se ofrece cuando el
listado está acotado (categoría, búsqueda, facet o rango); en el catálogo
completo se mantiene relevancia y nombre.

---

## 4. Estructura

```
tienda/
├── index.php                Front controller (rutas)
├── config/                  app.php, appearance.php, database.php, storage.php, tenant.php, catalog.php, idirecto.php
├── app/
│   ├── bootstrap.php        Autoload, entorno, sesion, helpers
│   ├── Core/                Env, Config, Appearance, Database, Router, View, Controller, Model,
│   │   │                    Auth, Csrf, Session, Tenant, TenantResolver, Dns, Specs, Str
│   │   ├── Idirecto/        Account, Pricing, OrderGateway (envio de pedidos al mayorista)
│   │   └── Storage/         StorageInterface, LocalStorage, S3Storage, StorageManager
│   ├── Controllers/         StorefrontController + Admin/* (incl. OrderController)
│   ├── Models/              Store, StoreUser, Plan, Theme, Banner, Notice, Order, OrderItem,
│   │                        OwnProduct, Media, Domain, DnsLog, ContentBlock, Setting, Catalog
│   └── Views/               layouts/, panel/ (pedidos, diseno... y design_preview.php),
│                            themes/idirecto|moderno|minimal/ (home, _hero, _card, …), errors/
├── public/assets/           css/ y js/ del storefront y del panel
├── public/uploads/          destino del driver local (no versionado)
├── database/
│   ├── migrations/          001_schema.sql · 002_design_tokens.sql · 003_orders.sql (mt_)
│   ├── seeds/               001_seed.sql (planes, temas, tienda demo)
│   └── migrate.php          Ejecutor de migraciones y semillas
├── tools/verify.php         Comprobacion automatica (78)
└── deploy/                  Vhosts/plantillas de Apache y nginx + scripts
```

---

## 5. Rutas

**Storefront** (tienda resuelta por hostname)

| Ruta | Descripción |
|---|---|
| `/` | Portada: banner, avisos, destacados y productos propios |
| `/catalogo` | Catálogo con buscador, filtro por categoría y paginación |
| `/producto/{slug}/{id}` | Ficha de producto central (URL SEO, ver más abajo) |
| `/producto/{id}` | Redirige 301 a la URL canónica con slug |
| `/contacto` | Datos de contacto de la tienda |
| `/pagina/{slug}` | Bloques de contenido (sobre nosotros, envíos...) |

**Panel de la tienda**

| Ruta | Módulo |
|---|---|
| `/panel/login` · `/panel/logout` | Acceso |
| `/panel` | Resumen (cuotas, actividad, primeros pasos) |
| `/panel/diseno` | Plantilla, identidad visual (tokens), logo, textos y SEO |
| `/panel/diseno/tokens` | Vista previa: CSS de tokens que generan los valores del formulario |
| `/panel/diseno/previa` | Página de muestra que se carga en el `<iframe>` de la vista previa |
| `/panel/banners` | Banners con imagen (límite por plan) |
| `/panel/avisos` | Avisos/anuncios |
| `/panel/productos` | Productos propios (alta/edición/borrado, cuota por plan) |
| `/panel/pedidos` | Pedidos de tus clientes: estados (activos, facturados, borrados…), ficha y envío por líneas a idirecto |
| `/panel/pedidos/nuevo` | Alta manual de un pedido (el checkout aún no existe) |
| `/panel/pedidos/{id}` | Ficha del pedido: líneas, estado, papelera y envío al mayorista |
| `/panel/dominios` | Dominio propio, instrucciones DNS y verificación |
| `/panel/ajustes` | Datos fiscales y usuarios del panel |
| `/panel/media/subir` | Endpoint AJAX de subida de imágenes |

---

## 6. Resolución de tenant

El storefront se decide **por el hostname** (nunca por cookies):

1. `?tienda=slug` — solo fuera de producción (desarrollo).
2. `<slug>.<dominio_base>` → `mt_stores.slug`.
3. Dominio propio **verificado** (`mt_domains.status = 1`).
4. `DEMO_STORE` del `.env`.
5. Primera tienda activa.

---

## 7. Dominios propios (DNS)

Igual que Tiendanube: el cliente compra su dominio en su registrador y lo apunta
a la plataforma. El panel muestra las instrucciones y el estado.

| Tipo | Nombre | Valor |
|---|---|---|
| A | `@` | `PLATFORM_IP` |
| CNAME | `www` | `PLATFORM_CNAME` |

Al pulsar **Verificar DNS** se consultan los registros reales, se actualiza el
estado y se registra el intento en `mt_dns_log`.

Vhost de Apache de ejemplo: [`deploy/apache-vhost.conf`](deploy/apache-vhost.conf).
Para nginx: [`deploy/nginx-site.conf.tpl`](deploy/nginx-site.conf.tpl) (lo instala
`deploy/setup-nginx-domain.sh`).

---

## 8. Planes y productos propios

`mt_plans.own_products_quota`: `-1` ilimitado, `0` no permitido, `N` límite.

| Plan | Propios | Banners | Avisos | Dominio propio |
|---|---|---|---|---|
| Basico | 0 | 2 | 3 | No |
| Medio | 50 | 5 | 10 | Si |
| Premium | ilimitado | 20 | 50 | Si |

La cuota se aplica en el servidor (no solo en la interfaz): al crear un producto
se comprueba el plan y se rechaza si se ha superado.

---

## 8.bis Pedidos y envío al mayorista

El panel tiene un módulo de **pedidos**: lo que un cliente de la tienda ha
comprado, con todos sus estados (borrador, activo, preparado, enviado, facturado,
cancelado y papelera). Como el checkout todavía no existe, los pedidos se pueden
dar de alta a mano; el futuro carrito creará el pedido con
`Order::createWithItems()`, el mismo camino.

**Envío por líneas a idirecto.** En la ficha del pedido se marcan las líneas que
se quieren mandar (por ejemplo, 2 de 4 productos) y se crea un pedido **real** en
el mayorista:

- `pedidos` (`web = 1`, `estado = NULL` = activo, `referencia = TIENDA-<slug>-<código>`)
- `pedidos_det` (una fila por línea, con coste, ganancia, IVA, sujeto, canon y almacén)
- `pedidos_addr` (facturación = datos de la tienda; envío = el del pedido o el de la tienda)
- y descuenta `stock`/`reserva` en los almacenes tipo 0 y 4, igual que idirecto.

Cada línea enviada queda marcada (`sent_at` + `idirecto_pedido_id`), así que se
puede enviar el resto más tarde **sin repetir nada**. Los productos propios no se
pueden enviar (no existen en el catálogo del mayorista).

Para poder enviar, cada tienda indica en **Ajustes** su cuenta del mayorista:

```bash
IDIRECTO_ENABLED=true              # interruptor general del puente
IDIRECTO_DEFAULT_ID_MARGEN=12      # tarifa usada si la tienda no tiene una
IDIRECTO_RESERVE_STOCK=true        # descontar stock/reserva al enviar (false = solo crear el pedido)
```

Y en la tienda (`mt_stores`, editable en el panel): `id_tienda_idirecto`
(`tiendas.id`) e `id_margen` (`precios.id_margen`). Antes de confirmar, la ficha
muestra la **vista previa** con la base, el IVA y el total que tendrá el pedido en
el mayorista.

---

## 9. Base de datos

Tablas propias (prefijo `mt_`, aditivas sobre la BD central):

| Tabla | Uso |
|---|---|
| `mt_stores` | Tiendas (tenant) + configuración de diseño y datos públicos |
| `mt_store_users` | Usuarios del panel |
| `mt_plans` / `mt_themes` | Planes y plantillas |
| `mt_banners` / `mt_notices` / `mt_content_blocks` | Contenido |
| `mt_own_products` | Productos propios (cuota por plan) |
| `mt_orders` / `mt_order_items` | Pedidos de los clientes de la tienda y sus líneas (con el envío a idirecto por línea) |
| `mt_media` | Ficheros en S3/local (solo key + URL) |
| `mt_domains` / `mt_dns_log` | Dominios y verificaciones |
| `mt_settings` / `mt_migrations` | Config clave/valor y control de migraciones |

```bash
php database/migrate.php --status   # ver migraciones
php database/migrate.php            # aplicar pendientes
php database/migrate.php --seed     # + semillas
```

---

## 10. Estado y siguientes pasos

**Hecho y verificado:** núcleo MVC, configuración BD/S3, migraciones y semillas,
multi-tenant por hostname, storefront con **sistema de diseño tokenizado**
(claro/oscuro/auto, presets y vista previa en vivo), portada con slider y accesos
rápidos, tarjetas de producto con especificaciones clave y etiqueta de stock,
filtros avanzados por socket/gráfica/memoria/formato/marca/precio, panel completo
(diseño, banners, avisos, productos, dominios, ajustes), **pedidos con estados y
envío por líneas al mayorista**, subida de imágenes con validación y cuota de plan
aplicada en servidor.

**Pendiente (siguientes iteraciones):**

- Checkout de la tienda (carrito, pasarelas propias, envíos) creando los pedidos
  con `Order::createWithItems()`.
- Aplicar la tarifa de la tienda (`mt_stores.id_margen`) a los precios del
  catálogo público (los pedidos ya la usan).
- Temas `moderno` y `minimal` (ahora hay base + variables de tema).
- Panel maestro del mayorista (supervisión, tarifas, auditoría de ventas).
- Recuperación de contraseña y 2FA en el panel.
- Tests automatizados (PHPUnit) y CI.

---

## 11. Seguridad

- `.env` fuera de control de versiones; `.htaccess` bloquea `.env`, `.sql`, `.log`.
- Todas las consultas usan sentencias preparadas.
- Token CSRF en todos los formularios y en el endpoint de subida.
- Contraseñas con `password_hash` (bcrypt/argon).
- Validación de MIME real y tamaño en las subidas; nombre de objeto saneado.
- Aislamiento por tienda: cada consulta del panel filtra por `store_id` de la sesión.
