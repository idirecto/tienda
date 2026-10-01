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
  Controllers/             StorefrontController + Admin/* (panel)
  Models/                  Acceso a datos (una clase por concepto)
  Views/
    layouts/               shop.php (web), panel.php, panel_blank.php
    panel/                 Pantallas del panel
    themes/idirecto/       Tema público (home, catalog, product, page, _card)
config/                    app, database, storage, tenant, catalog
database/
  migrations/001_schema.sql  13 tablas mt_ (idempotente)
  seeds/001_seed.sql         Planes, temas y tienda demo
  migrate.php                Ejecutor de migraciones + semillas
deploy/                    Vhosts de Apache, plantilla de nginx y scripts
public/                    ÚNICO directorio servido como estático
  assets/css|js            shop.css, panel.css, shop.js, panel.js
  uploads/                 Archivos locales (si STORAGE_DRIVER=local)
storage/                   cache/ y logs/ (escritura de la app)
tools/verify.php           33 comprobaciones automáticas
```

---

## 4. Infraestructura (`app/Core`)

| Clase | Responsabilidad |
|---|---|
| `Env` | Lee `.env` a `$_ENV` (sin dependencias) |
| `Config` | Carga `config/*.php` con acceso por punto: `Config::get('catalog.markup')` |
| `Database` | PDO singleton; `select/first/scalar/execute/insert/update/delete/transaction/tableExists` |
| `Router` | Compila `{param}`, detecta el subdirectorio base y despacha |
| `TenantResolver` | **Decide qué tienda se sirve** según el hostname |
| `Tenant` | Contexto inmutable de la tienda (nombre, tema, color, cuotas…) |
| `Auth` / `Session` / `Csrf` | Sesión, login y tokens CSRF |
| `Controller` / `View` / `Model` | Base MVC; `View` usa `extract()` |
| `Storage/*` | `StorageInterface` + `LocalStorage` + `S3Storage` + `StorageManager` + `StorageKey` |
| `Media/*` | `MediaUploader` (subida) e `ImageOptimizer` (WebP, tamaño, EXIF) |
| `Specs` | Parsea características y especificaciones del catálogo central |
| `Str` | `slugify()` (mismo criterio que idirecto) y `excerpt()` |
| `Dns` | Verificación de dominios por A/CNAME/TXT (con *lookup* inyectable) |
| `Validation`/`Storage` `Exception` | Errores de dominio |

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

### Tablas propias (prefijo `mt_`) — 13

| Tabla | Contenido |
|---|---|
| `mt_plans` | Planes y cuota de productos propios (`-1` = ilimitado) |
| `mt_themes` | Temas disponibles (catálogo cerrado) |
| `mt_stores` | Tiendas: slug, plan, tema, colores, hosts, ajustes |
| `mt_store_users` | Usuarios del panel (`password_hash`) |
| `mt_media` | Archivos subidos: `key`, `url`, `driver`, dimensiones |
| `mt_banners` | Banners de portada |
| `mt_notices` | Avisos (barra superior / modal) |
| `mt_own_products` | Productos propios de la tienda |
| `mt_domains` | Dominios propios + estado de verificación |
| `mt_dns_log` | Historial de comprobaciones DNS |
| `mt_content_blocks` | Páginas de contenido (`/pagina/{slug}`) |
| `mt_settings` | Ajustes clave/valor por tienda |
| `mt_migrations` | Control de migraciones aplicadas |

Todas con `utf8mb4_unicode_ci` y FK a `mt_stores(id) ON DELETE CASCADE`.

### Tablas del mayorista — **SOLO LECTURA**

`productos`, `productos_ext`, `precios`, `stock`, `almacenes`, `marcas`,
`categorias`, `subcategorias`, `productos_resenas`.

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
| `/` | Portada: banner, avisos, destacados, productos propios |
| `/catalogo` | Catálogo con buscador (`?q`), categoría (`?cat`), subcategoría (`?subcat`) y paginación |
| `/producto/{slug}/{id}` | Ficha (**URL SEO**); `/producto/{id}` redirige 301 |
| `/contacto`, `/pagina/{slug}` | Contacto y páginas de contenido |

### Menú de categorías del catálogo

La cabecera del storefront monta un **megamenú** (inspirado en PcComponentes /
PuntoByZE) a partir de `Catalog::menuTree()`: categorías en una columna lateral
y sus subcategorías repartidas en columnas. En móvil se convierte en pantalla
completa con navegación por pasos y botón atrás.

- Solo aparecen categorías y subcategorías **con stock** (mismo criterio que el
  listado), así que ningún enlace lleva a una página vacía.
- La consulta recorre `stock` y es costosa: el árbol se cachea 30 min en
  `storage/cache/catalog_menu.json` (`Catalog::MENU_TTL`).
- Los enlaces son `?cat={id}&subcat={id}`; el filtro por subcategoría también
  funciona desde el buscador de la ficha de producto y desde el catálogo, que
  lista las subcategorías de la categoría activa.
- El megamenú se pinta en `layouts/shop.php`; el CSS y el JS viven en
  `public/assets/css/shop.css` y `public/assets/js/shop.js` (bloque `catmenu`).

### Rutas del panel (`/panel/*`)

`login`, `logout`, `dashboard`, `diseno`, `banners`, `avisos`, `productos`
(CRUD), `dominios` (alta, verificar, borrar), `ajustes`, `media/subir` y
`media/{id}/borrar`. Todas exigen sesión + CSRF, y filtran por el `store_id` de
la sesión.

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
php tools/verify.php                       # 33 comprobaciones
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
php tools/verify.php                 # debe decir: TODO OK (33 comprobaciones)
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/catalogo
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/panel/login
bash .agents/scripts/check-privacidad.sh    # la documentación no debe ser web
```

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

---

## 12. Roadmap

Ver [`STATE.md`](STATE.md) para el estado detallado. Pendiente principal:

1. **Precio por tarifa de tienda** — hoy se usa `MIN(precios.precio)`; debería
   aplicar el margen/tarifa que corresponda a cada tienda.
2. **Checkout** — carrito, pasarelas de pago de la tienda y envío.
3. **Panel maestro del mayorista** — supervisión, tarifas y auditoría de ventas.
4. **Temas `moderno` y `minimal`**.
5. **Tests (PHPUnit) e integración continua.**
