#!/usr/bin/env bash
# =============================================================================
#  Instala (o actualiza) el sitio nginx de la plataforma multi-tienda.
#
#  Es IDEMPOTENTE: se puede ejecutar tantas veces como haga falta.
#
#  Uso:
#      sudo bash deploy/setup-nginx-domain.sh [dominio]
#
#  Dominio por defecto: valduran.com. Para cambiar de dominio mas adelante,
#  vuelve a ejecutarlo con el nuevo nombre (y ajusta BASE_DOMAINS en .env).
#
#  Hace:
#    1. Ajusta permisos: .env legible por el servidor web y escritura en
#       storage/logs, storage/cache y public/uploads.
#    2. Detecta el socket de PHP-FPM (o usa la variable FPM_SOCK).
#    3. Renderiza deploy/nginx-site.conf.tpl en
#       /etc/nginx/sites-available/<dominio>.conf y lo enlaza en sites-enabled.
#    4. Comprueba la configuracion (nginx -t) y recarga nginx.
#
#  Requiere: nginx y PHP-FPM instalados, y ejecutarse con sudo.
# =============================================================================
set -euo pipefail

DOMAIN="${1:-valduran.com}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_USER="${WEB_USER:-www-data}"
FPM_SOCK="${FPM_SOCK:-}"
TPL="${ROOT}/deploy/nginx-site.conf.tpl"
DEST="/etc/nginx/sites-available/${DOMAIN}.conf"
LINK="/etc/nginx/sites-enabled/${DOMAIN}.conf"

if [[ $EUID -ne 0 ]]; then
    echo "ERROR: ejecuta este script con sudo." >&2
    echo "       sudo bash $0 ${DOMAIN}" >&2
    exit 1
fi

if ! command -v nginx >/dev/null 2>&1; then
    echo "ERROR: no encuentro nginx. Instalalo primero:" >&2
    echo "       sudo apt-get update && sudo apt-get install -y nginx php8.4-fpm" >&2
    exit 1
fi

if [[ ! -d "$ROOT" || ! -f "$ROOT/index.php" ]]; then
    echo "ERROR: no encuentro el proyecto en $ROOT" >&2
    exit 1
fi

if [[ ! -f "$TPL" ]]; then
    echo "ERROR: falta la plantilla $TPL" >&2
    exit 1
fi

echo "==> 1/5  permisos (usuario del servidor web: ${WEB_USER})"
# El fichero .env solo debe ser legible por su dueno y por el grupo del servidor.
if [[ -f "$ROOT/.env" ]]; then
    chgrp "$WEB_USER" "$ROOT/.env" 2>/dev/null || true
    chmod 640 "$ROOT/.env"
    echo "    .env  -> 640 $(stat -c '%U:%G' "$ROOT/.env")"
fi
# Directorios donde la aplicacion escribe en tiempo de ejecucion.
for d in storage/logs storage/cache public/uploads; do
    if [[ -d "$ROOT/$d" ]]; then
        chgrp -R "$WEB_USER" "$ROOT/$d" 2>/dev/null || true
        find "$ROOT/$d" -type d -exec chmod 2775 {} \; 2>/dev/null || true
        find "$ROOT/$d" -type f -exec chmod 664  {} \; 2>/dev/null || true
        echo "    $d  -> escritura para ${WEB_USER}"
    fi
done

echo "==> 2/5  PHP-FPM"
if [[ -z "$FPM_SOCK" ]]; then
    # Se elige la version mas alta disponible (php8.4 > php8.3 ...).
    FPM_SOCK="$(ls -1 /run/php/php*-fpm.sock 2>/dev/null | sort -V | tail -1 || true)"
fi
if [[ -z "$FPM_SOCK" || ! -S "$FPM_SOCK" ]]; then
    echo "ERROR: no encuentro el socket de PHP-FPM en /run/php/." >&2
    echo "       Instala PHP-FPM (sudo apt-get install -y php8.4-fpm) o indica el socket:" >&2
    echo "       sudo FPM_SOCK=/run/php/php8.4-fpm.sock bash $0 ${DOMAIN}" >&2
    exit 1
fi
# fastcgi_pass exige el prefijo "unix:" cuando es un socket; si algun dia se usa
# php-fpm escuchando en TCP, se pasa tal cual (127.0.0.1:9000).
if [[ "$FPM_SOCK" == /* ]]; then
    FPM_ADDR="unix:${FPM_SOCK}"
else
    FPM_ADDR="$FPM_SOCK"
fi
echo "    php-fpm: ${FPM_ADDR}"

echo "==> 3/5  sitio -> ${DEST}"
sed -e "s|__DOMINIO__|${DOMAIN}|g" \
    -e "s|__RAIZ__|${ROOT}|g" \
    -e "s|__FPM_ADDR__|${FPM_ADDR}|g" \
    "$TPL" > "$DEST"
chmod 0644 "$DEST"
ln -sfn "$DEST" "$LINK"
echo "    renderizado y enlazado en sites-enabled"

if [[ -e /etc/nginx/sites-enabled/default ]]; then
    echo "    AVISO: existe el sitio 'default' de nginx; si responde antes que este,"
    echo "           desactivalo con:  sudo rm /etc/nginx/sites-enabled/default"
fi

echo "==> 4/5  comprobacion"
if systemctl is-active --quiet apache2 2>/dev/null; then
    echo "    AVISO: Apache esta activo; no puede compartir el puerto 80 con nginx."
    echo "           Para desactivarlo:  sudo systemctl disable --now apache2"
fi
nginx -t
echo "    configuracion correcta"

echo "==> 5/5  recarga"
systemctl reload nginx
echo "    nginx recargado"

echo
echo "LISTO. Antes de abrir la web, revisa el .env de produccion:"
echo
echo "    APP_ENV=production"
echo "    APP_DEBUG=false"
echo "    APP_URL=http://${DOMAIN}"
echo "    BASE_DOMAINS=${DOMAIN}"
echo "    DEMO_STORE=idirecto-demo"
echo
echo "Y que el DNS de ${DOMAIN} y *.${DOMAIN} apunte a este servidor."
echo "Para HTTPS:  sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
echo
echo "    http://${DOMAIN}/              (tienda)"
echo "    http://${DOMAIN}/panel         (panel)"
echo
echo "Logs: /var/log/nginx/${DOMAIN}.error.log"
