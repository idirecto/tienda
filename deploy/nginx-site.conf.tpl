# =============================================================================
#  nginx + PHP-FPM para la plataforma multi-tienda
#
#  Plantilla: los marcadores de dominio, raiz del proyecto y socket de PHP-FPM
#  los sustituye deploy/setup-nginx-domain.sh, que es la forma recomendada:
#
#      sudo bash deploy/setup-nginx-domain.sh valduran.com
#
#  Tambien puedes copiarla a /etc/nginx/sites-available/<dominio>.conf,
#  reemplazar los marcadores a mano y enlazarla desde sites-enabled.
#
#  DIFERENCIAS CON APACHE
#   - nginx NO lee .htaccess: el enrutado al front controller y el bloqueo de
#     las rutas internas se definen aqui. Sin estas reglas, .env, app/,
#     config/... quedarian accesibles.
#   - La raiz del sitio es la carpeta del proyecto (la que contiene index.php),
#     igual que en el VirtualHost de Apache; no es el directorio public/.
#   - PHP se ejecuta con PHP-FPM (fastcgi_pass), no con mod_php.
#
#  TLS: cuando el DNS apunte al servidor, ejecuta
#      sudo certbot --nginx -d valduran.com -d www.valduran.com
#  certbot anade el bloque 443 y la redireccion desde HTTP.
# =============================================================================

server {
    listen      80;
    listen      [::]:80;

    # Dominio principal + subdominios de tienda (<slug>.valduran.com).
    # Los dominios propios de cada tienda entran por un "default server"
    # (ver el final de este fichero) o anadiendolos a esta lista.
    server_name __DOMINIO__ www.__DOMINIO__ *.__DOMINIO__;

    root __RAIZ__;
    index index.php;

    charset utf-8;

    # Subidas de imagenes desde el panel: los banners admiten hasta 8 MB, asi
    # que el cuerpo de la peticion tiene que pasar de ahi. OJO: ademas hay que
    # subir en PHP  upload_max_filesize = 12M  y  post_max_size = 13M  (en el
    # pool de PHP-FPM: /etc/php/*/fpm/php.ini).
    client_max_body_size 16M;

    access_log /var/log/nginx/__DOMINIO__.access.log;
    error_log  /var/log/nginx/__DOMINIO__.error.log;

    # Cabeceras de seguridad equivalentes a las del .htaccess de Apache.
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Sin listado de directorios en ninguna circunstancia.
    autoindex off;

    # -------------------------------------------------------------------------
    #  1) Rutas internas: NUNCA se sirven por web.
    #     (En Apache lo hace .htaccess; nginx no lo lee.)
    # -------------------------------------------------------------------------
    location ~ ^/(\.agents|\.git|app|config|database|deploy|storage|tools|vendor)(/|$) {
        return 404;
    }

    # Credenciales y ficheros de configuracion o documentacion.
    location ~* \.(env|sql|md|log|json|lock|yml|yaml|sh|ini|bak|dist|example)$ {
        return 404;
    }

    # Retos de Let's Encrypt: van ANTES del bloqueo de ficheros ocultos.
    location ^~ /.well-known/acme-challenge/ {
        allow all;
        try_files $uri =404;
    }

    # Cualquier otro fichero oculto (.env, .git, .htaccess...).
    location ~ /\. {
        return 404;
    }

    # -------------------------------------------------------------------------
    #  2) Front controller: ficheros reales o index.php con su query string.
    # -------------------------------------------------------------------------
    location / {
        try_files $uri /index.php$is_args$args;
    }

    # El unico PHP que se ejecuta es el front controller.
    location = /index.php {
        include fastcgi_params;

        fastcgi_param SCRIPT_NAME     /index.php;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        fastcgi_param DOCUMENT_ROOT   $document_root;
        fastcgi_param HTTPS           $https if_not_empty;

        # Socket de PHP-FPM ("unix:/run/php/php8.4-fpm.sock") o host:puerto
        # ("127.0.0.1:9000"). Lo rellena el script de instalacion.
        fastcgi_pass __FPM_ADDR__;
        fastcgi_read_timeout 60s;
    }

    # Cualquier otro .php se ignora (no se ejecuta ni se entrega).
    location ~ \.php$ {
        return 404;
    }

    # -------------------------------------------------------------------------
    #  3) Estaticos (assets y subidas) con cache larga.
    #     `expires` ya envia Cache-Control: max-age, sin romper la herencia
    #     de las cabeceras de seguridad del bloque server.
    # -------------------------------------------------------------------------
    location ~* \.(css|js|jpg|jpeg|png|webp|avif|gif|svg|ico|woff|woff2|ttf|eot|mp4|pdf)$ {
        try_files $uri =404;
        expires 30d;
        access_log off;
    }
}

# =============================================================================
#  Dominios propios de las tiendas (opcional)
#
#  Si cada tienda usa su propio dominio (p. ej. miotratienda.com) hay que
#  atender tambien ese Host. Tres opciones:
#
#   A. Anadir el dominio al server_name de arriba (lo mas simple si son pocos):
#          server_name valduran.com www.valduran.com *.<otro-dominio> ...;
#
#   B. Crear un "default server" que recoja cualquier Host no reconocido
#      (el resolver de la aplicacion decide que tienda mostrar). Descomenta:
#
#          server {
#              listen 80 default_server;
#              listen [::]:80 default_server;
#              server_name _;
#              root __RAIZ__;
#              index index.php;
#              include fastcgi_params;
#              # ... repetir los location de bloqueo y el front controller ...
#          }
#
#   C. Proxy inverso con TLS on-demand (Caddy/nginx) por delante.
#
#  Con HTTPS, emite un certificado por dominio:
#      sudo certbot --nginx -d miotratienda.com -d www.miotratienda.com
# =============================================================================
