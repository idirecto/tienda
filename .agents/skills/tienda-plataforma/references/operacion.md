# Operación — comandos y recetas

## Entorno local

```bash
# Configurar/actualizar el dominio de pruebas (necesita sudo)
cd /var/www/html/tienda
sudo bash deploy/setup-local-domain.sh

# Alternativa sin Apache: servidor embebido (solo sirve /public como estático)
php -S 127.0.0.1:8099 index.php
```

| URL | Qué es |
|---|---|
| http://local.tienda/ | Portada de la tienda demo |
| http://local.tienda/catalogo | Catálogo (39.437 productos con stock) |
| http://local.tienda/panel | Panel — `admin@demo.test` / `demo1234` |
| http://idirecto-demo.local.tienda/ | La misma tienda por subdominio |
| http://local.tienda/?__store=<slug> | Cambiar de tienda **solo en desarrollo** |

## Apache

```bash
sudo apache2ctl configtest          # validar configuración antes de recargar
sudo systemctl reload apache2       # aplicar cambios
sudo tail -30 /var/log/apache2/local.tienda-error.log
sudo tail -30 /var/log/apache2/local.tienda-access.log
```

## nginx

```bash
# Instalar/actualizar el sitio (idempotente; dominio por defecto valduran.com)
sudo bash deploy/setup-nginx-domain.sh valduran.com
sudo FPM_SOCK=/run/php/php8.4-fpm.sock bash deploy/setup-nginx-domain.sh otro-dominio.com

# Ver que haria sin tocar nada (vale sin sudo)
bash deploy/setup-nginx-domain.sh valduran.com --dry-run

# Otras rutas / usuarios: el script toma ROOT de su propia ubicacion
sudo ROOT=/var/www/vhosts/valduran/tienda WEB_USER=nginx bash deploy/setup-nginx-domain.sh valduran.com

sudo nginx -t                    # validar antes de recargar
sudo systemctl reload nginx      # aplicar cambios
sudo tail -30 /var/log/nginx/valduran.com-error.log
sudo tail -30 /var/log/nginx/valduran.com-access.log
```

**Plesk / cPanel:** el panel gestiona nginx (y sobrescribe `/etc/nginx/plesk.conf.d`),
asi que el script avisa y se detiene. La via correcta ahi es: PHP Settings → 8.2+;
Hosting Settings → *Document root* = `tienda`; nginx como proxy de Apache (asi
`.htaccess` sigue enrutando y protegiendo) y pegar el bloque `[PLESK]` que imprime
`setup-nginx-domain.sh --dry-run` en *Additional nginx directives*.

Comprobar el bloqueo de rutas internas y el enrutado (nginx no lee `.htaccess`):

```bash
for p in /.env /app/bootstrap.php /config/database.php /storage/logs/php-error.log \
         /.agents/PROJECT.md /public/assets/css/shop.css; do
    printf '%s  %s\n' "$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: valduran.com' "http://127.0.0.1$p")" "$p"
done
# Esperado: 404 en todo menos /public/assets/... (200)
```

Al cambiar de dominio: repite el script con el nuevo nombre y ajusta
`APP_URL`, `BASE_DOMAINS` y `PLATFORM_CNAME` en `.env`.

## Servidor detectado por la aplicación

```bash
php -r 'require "app/bootstrap.php"; Tienda\Core\View::setBasePath("");
echo Tienda\Core\Server::label(), " · ", Tienda\Core\Server::scheme(), " · ",
     Tienda\Core\Server::host(), " · /public -> ", Tienda\Core\Server::publicPath(), "\n";'
```

## Base de datos

```bash
# Migraciones y semillas (idempotente)
php database/migrate.php
php database/migrate.php --seed

# Conexión de desarrollo
mysql -uphpmyadmin -ppass idirecto_db
```

Consultas útiles:

```sql
-- Productos del catálogo con stock (deben salir ~39.437)
SELECT COUNT(*) FROM productos p WHERE p.estado <> 4 AND p.id_subcategoria <> 178
AND EXISTS (SELECT 1 FROM stock s INNER JOIN almacenes a ON a.id=s.id_almacen
            WHERE s.part_number=p.part_number AND s.stock>0 AND s.activo=1
              AND s.costo>0 AND a.tipo<>2);

-- Tiendas y su plan
SELECT s.id, s.slug, s.status, p.code FROM mt_stores s
LEFT JOIN mt_plans p ON p.id = s.id_plan;

-- Dominios pendientes de verificar
SELECT id, store_id, domain, status, last_result FROM mt_domains;
```

## Verificación

```bash
php tools/verify.php                        # 32 comprobaciones del proyecto
bash .agents/scripts/check-privacidad.sh    # la documentación NO debe ser web
```

Comprobar JavaScript ya ejecutado (Chrome headless está instalado):

```bash
google-chrome --headless=new --disable-gpu --no-sandbox \
  --virtual-time-budget=8000 --dump-dom "http://local.tienda/producto/x/254299" | less

google-chrome --headless=new --disable-gpu --no-sandbox --hide-scrollbars \
  --window-size=1440,1600 --screenshot=/tmp/ficha.png "http://local.tienda/"
```

## Git

```bash
git status --short
git add -A
git commit -m "Area: que hace y por que"     # explica el motivo, no solo el cambio
git push origin main
```

`.env` está en `.gitignore`: **nunca** debe entrar en el repositorio.

## Problemas frecuentes

| Síntoma | Comprobación |
|---|---|
| Página en blanco o 500 | `sudo tail /var/log/apache2/local.tienda-error.log` (o `/var/log/nginx/<dominio>.error.log`) |
| «**Error interno**» sin más datos | Casi siempre: `www-data` **no puede leer `.env`** (sin credenciales ni `APP_DEBUG`). Mira `storage/logs/php-error.log` (lleva pista) y arregla con `sudo bash deploy/setup-local-domain.sh` o `setfacl -m u:www-data:r-- .env` |
| `storage/` o `public/uploads` sin escritura | `setfacl -R -m u:www-data:rwX storage/logs storage/cache public/uploads` |
| 502 Bad Gateway en nginx | PHP-FPM caído o socket equivocado: `systemctl status php8.4-fpm` y `fastcgi_pass unix:...` |
| `.env` accesible en nginx | Falta el `server` block: nginx **no** lee `.htaccess`; instala `deploy/nginx-site.conf.tpl` |
| Canónicas con `http://` detrás de proxy | Debe llegar `X-Forwarded-Proto`; lo resuelve `Server::isSecure()` |
| "No se pudo conectar a la base de datos" | Que el servidor web pueda leer `.env` (`640` y grupo `www-data`) |
| El login del panel no persiste | Cookie de sesión; ver `Header edit` en `.htaccess` (PROJECT.md §11) |
| No se ven los cambios de CSS/JS | `asset()` añade `?v=`; fuerza recarga con `Ctrl+Shift+R` |
| Imágenes rotas | No todas las fotos del mayorista existen; el JS las retira solo |
| 403 al pedir una ruta del proyecto | Es correcto: `.htaccess` bloquea rutas internas |
| `Access denied` al ejecutar `mysql` | Usa `-uphpmyadmin -ppass`; las credenciales están en `.env` |

## Añadir un ajuste de configuración

1. Añade la clave a `config/<fichero>.php` con `Env::get('MI_CLAVE', $defecto)`.
2. Añádela a `.env` **y** a `.env.example` (este último sí va al repositorio).
3. Documéntala en `README.md` si afecta al uso normal.

## Añadir una ruta

Todas las rutas están en `index.php`, agrupadas por storefront y panel:

```php
$router->get('/mi-ruta', [StorefrontController::class, 'miAccion']);
$router->post('/panel/mi-ruta', [MiController::class, 'guardar']);
```

Los `{param}` se convierten en grupos de captura. Los `POST` del panel deben
validar el token CSRF (`Csrf::check()`) y filtrar siempre por el `store_id` de
la sesión.
