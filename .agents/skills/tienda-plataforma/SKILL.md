---
name: tienda-plataforma
description: Desarrolla y mantiene la plataforma multi-tienda de /var/www/html/tienda (PHP 8.4, MVC propio, catálogo central del mayorista idirecto). Úsalo cuando la tarea mencione tienda, multi-tienda, tenant, storefront, panel de tienda, catálogo central, productos propios, plan/cuota, tema/plantilla, banners, avisos, dominios, verificación DNS, S3, o cualquiera de las clases TenantResolver, Tenant, Catalog, Specs, StorageManager, Dns, Str; y siempre que se continúe el trabajo de este proyecto.
---

# Plataforma multi-tienda (`/var/www/html/tienda`)

Tienda online que se construye **encima del catálogo del mayorista idirecto**:
cada tienda cliente tiene su web pública y su panel de administración.

## Antes de tocar nada

1. **Lee `.agents/PROMPT.md`** — es la petición del dueño. Si está vacío, no hay
   nada pendiente: pregunta antes de improvisar.
2. **Lee `.agents/PROJECT.md`** — arquitectura completa, modelo de datos,
   convenciones y la lista de trampas ya pisadas.
3. **Lee `.agents/STATE.md`** — por dónde iba el trabajo y qué falta.

Los tres ficheros viven en el repositorio y **nunca** se sirven por web.

## Reglas que no se negocian

- Las tablas del mayorista (sin prefijo `mt_`) son de **solo lectura**.
- Todo lo propio usa prefijo `mt_` y se crea con migraciones **idempotentes**.
- **Sin frameworks ni dependencias**: PHP 8.4 y MVC propio. Solo se admite
  `aws/aws-sdk-php` (opcional, para S3).
- El aislamiento por tienda sale **siempre** del hostname o de la sesión; nunca
  de un parámetro que envíe el usuario.
- Todo en **español**: código, comentarios, commits, UI y documentación.
- Nunca commitees `.env` ni credenciales.

## Recetas frecuentes

| Quiero… | Dónde |
|---|---|
| Añadir una ruta | `index.php` (todas las rutas están ahí) |
| Cambiar el catálogo (filtros, precios) | `app/Models/Catalog.php` |
| Parsear especificaciones | `app/Core/Specs.php` |
| Cambiar la vista pública | `app/Views/themes/<tema>/` |
| Cambiar el layout público | `app/Views/layouts/shop.php` |
| Añadir una tabla | `database/migrations/` + ejecutar `php database/migrate.php` |
| Añadir un ajuste | `config/*.php` + clave en `.env` y `.env.example` |
| Subir/optimizar imágenes (WebP) | `app/Core/Media/` (`MediaUploader`, `ImageOptimizer`) |
| Cambiar carpetas/nombres en S3 | `app/Core/Storage/StorageKey.php` (+ `config/storage.php`) |
| Desplegar con **nginx** | `deploy/nginx-site.conf.tpl` + `deploy/setup-nginx-domain.sh <dominio>` |
| Saber servidor / esquema / host real | `app/Core/Server.php` |

Apache y nginx son intercambiables: la app detecta el servidor. En Apache manda
`.htaccess`; en nginx, el `server` block de `deploy/` (nginx **no** lee
`.htaccess`).

Comandos y operaciones concretas: [`references/operacion.md`](references/operacion.md).
Esquema de tablas `mt_` al detalle: [`references/esquema-bd.md`](references/esquema-bd.md).

## Verificación obligatoria antes de decir "hecho"

```bash
php tools/verify.php          # debe terminar en: TODO OK (33 comprobaciones)
php -l <fichero-modificado>   # sintaxis de cada PHP que toques
node --check public/assets/js/shop.js   # si tocas JS
curl -s -o /dev/null -w '%{http_code}\n' http://local.tienda/catalogo
bash .agents/scripts/check-privacidad.sh    # la documentación no debe ser web
```

Prueba siempre con **HTTP real** (`http://local.tienda/...`), no solo con el
intérprete de PHP: varios fallos de este proyecto solo aparecían en Apache.

## Al terminar

1. Actualiza `.agents/STATE.md` (estado) y `.agents/CHANGELOG.md` (qué y por qué).
2. Marca la petición como hecha en `.agents/PROMPT.md` y déjala limpia.
3. Commit explicando el **porqué**; `git push origin main`.

## Trampas conocidas (detalle en `PROJECT.md` §11)

- Con `PDO::ATTR_EMULATE_PREPARES = false` un parámetro nombrado **no puede
  repetirse** en la misma consulta.
- MySQL hace *commit* implícito en DDL: no envuelvas migraciones en transacción.
- `productos.img_name` es una **plantilla `sprintf`**, no un nombre de fichero.
- No todas las imágenes del mayorista existen: hay que tolerar el 404.
- `productos_atributos` está **vacía**: las specs salen de
  `productos_ext.especificaciones` (HTML).
- El servidor embebido de PHP solo debe servir `/public` (si no, expone `.env`).
- **nginx no lee `.htaccess`**: si despliegas en nginx sin el `server` block de
  `deploy/`, `.env`, `app/`, `config/`… quedan accesibles. Comprueba siempre con
  `curl` que `/.env` y `/app/bootstrap.php` devuelven 404.
- En `fastcgi_pass`, un socket necesita el prefijo `unix:`; un `host:puerto` no.
- Detrás de un proxy/CDN, `HTTPS` no llega: el esquema real está en
  `X-Forwarded-Proto` (lo resuelve `Server::isSecure()`).
