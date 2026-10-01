#!/usr/bin/env bash
# =============================================================================
#  Configura el dominio de pruebas  ->  http://local.tienda
#
#  Hace, de forma IDEMPOTENTE:
#    1. Ajusta permisos para que el servidor web pueda leer .env y escribir
#    2. Anade las entradas en /etc/hosts
#    3. Instala el VirtualHost en Apache
#    4. Habilita el sitio y los modulos necesarios
#    5. Valida la configuracion y recarga Apache
#
#  Uso:
#     sudo bash deploy/setup-local-domain.sh
# =============================================================================
set -euo pipefail

DOMAIN="local.tienda"
ROOT="/var/www/html/tienda"
WEB_USER="${WEB_USER:-www-data}"
VHOST_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/apache-vhost-local.conf"
VHOST_DST="/etc/apache2/sites-available/${DOMAIN}.conf"
HOSTS_ENTRY="127.0.0.1 ${DOMAIN} www.${DOMAIN} idirecto-demo.${DOMAIN} mi-tienda.${DOMAIN}"

if [[ $EUID -ne 0 ]]; then
    echo "ERROR: ejecuta este script con sudo." >&2
    echo "       sudo bash $0" >&2
    exit 1
fi

if [[ ! -d "$ROOT" ]]; then
    echo "ERROR: no existe $ROOT" >&2
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

echo "==> 2/5  /etc/hosts"
if grep -qE "(^|[[:space:]])${DOMAIN}([[:space:]]|$)" /etc/hosts; then
    echo "    ya existe la entrada para ${DOMAIN}, no se toca"
else
    cp -n /etc/hosts "/etc/hosts.bak.$(date +%Y%m%d%H%M%S)" 2>/dev/null || true
    printf '\n# Proyecto multi-tienda (entorno local)\n%s\n' "$HOSTS_ENTRY" >> /etc/hosts
    echo "    anadido: $HOSTS_ENTRY"
fi

echo "==> 3/5  VirtualHost -> ${VHOST_DST}"
install -m 0644 "$VHOST_SRC" "$VHOST_DST"
echo "    instalado"

echo "==> 4/5  modulos y sitio"
a2enmod rewrite >/dev/null 2>&1 || true
a2ensite "${DOMAIN}.conf" >/dev/null
echo "    sitio habilitado"

echo "==> 5/5  comprobacion y recarga"
apache2ctl configtest
systemctl reload apache2

echo
echo "LISTO. Abre en el navegador:"
echo "    http://${DOMAIN}/                    (tienda demo)"
echo "    http://${DOMAIN}/panel               (admin@demo.test / demo1234)"
echo "    http://idirecto-demo.${DOMAIN}/      (acceso por subdominio)"
echo
echo "Logs: /var/log/apache2/${DOMAIN}-error.log"
