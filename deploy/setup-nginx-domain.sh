#!/usr/bin/env bash
# =============================================================================
#  Instala (o actualiza) el sitio nginx de la plataforma multi-tienda.
#
#  Version del script: 2026-10-01b  (si en el servidor no coincide, el repo
#  esta desactualizado:  git pull)
#
#  Es IDEMPOTENTE: se puede ejecutar tantas veces como haga falta.
#
#  Uso:
#      sudo bash deploy/setup-nginx-domain.sh [dominio] [--dry-run]
#
#  - Dominio por defecto: valduran.com.
#  - --dry-run (o DRY_RUN=1): no toca nada; muestra lo que haria y el
#    fichero de configuracion que generaria. Se puede usar sin sudo.
#
#  Variables opcionales (por si el script no cuelga del proyecto):
#      ROOT=/var/www/vhosts/valduran/tienda   raiz del proyecto
#      WEB_USER=www-data                      usuario del servidor web
#      FPM_SOCK=/run/php/php8.4-fpm.sock      socket de PHP-FPM
#      FORZAR=1                               continuar aunque haya Plesk
#
#  Hace:
#    1. Ajusta permisos: .env legible por el servidor web y escritura en
#       storage/logs, storage/cache y public/uploads.
#    2. Detecta el socket de PHP-FPM.
#    3. Renderiza deploy/nginx-site.conf.tpl en
#       /etc/nginx/sites-available/<dominio>.conf y lo enlaza en sites-enabled.
#    4. Comprueba la configuracion (nginx -t) y recarga nginx.
#
#  AVISO PARA PLESK / CPANEL: si el panel gestiona nginx, NO uses este script
#  (sobrescribiria su configuracion). Usa las "directivas nginx adicionales"
#  del dominio; ver el final de este fichero.
#
#  Requiere: nginx y PHP-FPM instalados, y ejecutarse con sudo.
# =============================================================================
set -euo pipefail

DOMAIN=""
DRY_RUN="${DRY_RUN:-0}"
FORZAR="${FORZAR:-0}"

for arg in "$@"; do
    case "$arg" in
        --dry-run|--simular) DRY_RUN=1 ;;
        -h|--help) sed -n '2,30p' "$0"; exit 0 ;;
        -*) echo "ERROR: opcion desconocida: $arg" >&2; exit 2 ;;
        *) DOMAIN="$arg" ;;
    esac
done
DOMAIN="${DOMAIN:-valduran.com}"

ROOT="${ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
WEB_USER="${WEB_USER:-www-data}"
FPM_SOCK="${FPM_SOCK:-}"
TPL="${ROOT}/deploy/nginx-site.conf.tpl"
DEST="/etc/nginx/sites-available/${DOMAIN}.conf"
LINK="/etc/nginx/sites-enabled/${DOMAIN}.conf"

resumen() {
    echo
    echo "  dominio : ${DOMAIN}"
    echo "  raiz    : ${ROOT}"
    echo "  usuario : ${WEB_USER}"
    echo "  destino : ${DEST}"
    if [[ "$DRY_RUN" == "1" ]]; then
        echo "  modo    : dry-run (no se toca nada)"
    fi
    echo
}

if [[ $EUID -ne 0 && "$DRY_RUN" != "1" ]]; then
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
    echo "       (se esperaba $ROOT/index.php; usa ROOT=/ruta/al/proyecto)" >&2
    exit 1
fi

if [[ ! -f "$TPL" ]]; then
    echo "ERROR: falta la plantilla $TPL" >&2
    exit 1
fi

echo "==> Configuracion"
resumen

# -----------------------------------------------------------------------------
#  Paneles que gestionan nginx (Plesk, cPanel): abortar salvo FORZAR=1
# -----------------------------------------------------------------------------
if [[ -d /etc/nginx/plesk.conf.d || -d /usr/local/psa ]]; then
    echo "==> AVISO: este servidor parece gestionado por Plesk."
    echo "    Plesk genera y sobrescribe /etc/nginx/plesk.conf.d/vhosts/*.conf,"
    echo "    asi que instalar aqui el sitio se perderia (o romperia el panel)."
    echo
    echo "    En Plesk, la via correcta es:"
    echo "      0. PHP Settings -> version 8.2 o superior (el proyecto exige PHP 8.2+)."
    echo "      1. Websites & Domains -> ${DOMAIN} -> Hosting Settings"
    echo "         -> Document root:  tienda     (deja /var/www/vhosts/<suscripcion>/tienda)"
    echo "      2. Activa 'Apache & nginx Settings' -> nginx como proxy (por defecto)."
    echo "         Asi .htaccess sigue funcionando y PHP corre bajo el usuario del dominio."
    echo "      3. En 'Additional nginx directives' pega las reglas que imprime este"
    echo "         script al final (o mira el final de este mismo fichero)."
    echo "      4. SSL/TLS Certificates -> Let's Encrypt (instalar/renovar)."
    echo "      5. Esquema y datos:  php database/migrate.php --seed"
    echo
    if [[ "$FORZAR" != "1" ]]; then
        echo "    Si aun asi quieres seguir con este script:  FORZAR=1 sudo bash $0 ${DOMAIN}"
        echo
        echo "    Directivas para Plesk (copiar en 'Additional nginx directives'):"
        echo "    ----------------------------------------------------------------"
        sed -n '/^#.*\[PLESK\]/,/^#.*\[\/PLESK\]/p' "$0" | sed 's/^# \{0,1\}//'
        exit 1
    fi
    echo "    FORZAR=1: se continua bajo tu responsabilidad."
    echo
fi

# -----------------------------------------------------------------------------
#  1) Permisos
# -----------------------------------------------------------------------------
echo "==> 1/5  permisos (usuario del servidor web: ${WEB_USER})"
if [[ "$DRY_RUN" == "1" ]]; then
    echo "    [dry-run] chgrp ${WEB_USER} .env && chmod 640 .env"
    echo "    [dry-run] escritura para ${WEB_USER} en storage/logs storage/cache public/uploads"
else
    # El fichero .env solo debe ser legible por su dueno y por el grupo del servidor.
    if [[ -f "$ROOT/.env" ]]; then
        chgrp "$WEB_USER" "$ROOT/.env" 2>/dev/null || true
        chmod 640 "$ROOT/.env"
        echo "    .env  -> 640 $(stat -c '%U:%G' "$ROOT/.env")"
    else
        echo "    AVISO: no existe $ROOT/.env (copia .env.example y ajustalo)"
    fi
    for d in storage/logs storage/cache public/uploads; do
        if [[ -d "$ROOT/$d" ]]; then
            chgrp -R "$WEB_USER" "$ROOT/$d" 2>/dev/null || true
            find "$ROOT/$d" -type d -exec chmod 2775 {} \; 2>/dev/null || true
            find "$ROOT/$d" -type f -exec chmod 664  {} \; 2>/dev/null || true
            echo "    $d  -> escritura para ${WEB_USER}"
        fi
    done
fi

# -----------------------------------------------------------------------------
#  2) PHP-FPM
# -----------------------------------------------------------------------------
echo "==> 2/5  PHP-FPM"
if [[ -z "$FPM_SOCK" ]]; then
    # Version mas alta del sistema, o el pool por dominio de Plesk.
    for candidato in \
        "$(ls -1 /run/php/php*-fpm.sock 2>/dev/null | sort -V | tail -1 || true)" \
        "/var/www/vhosts/system/${DOMAIN}/php-fpm.sock" \
        "/run/php-fpm/${DOMAIN}.sock"; do
        if [[ -n "$candidato" && -S "$candidato" ]]; then
            FPM_SOCK="$candidato"
            break
        fi
    done
fi
if [[ -z "$FPM_SOCK" ]]; then
    echo "ERROR: no encuentro el socket de PHP-FPM." >&2
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
if [[ "$FPM_SOCK" == /* && ! -S "$FPM_SOCK" ]]; then
    echo "    AVISO: $FPM_SOCK no existe todavia (¿PHP-FPM instalado y arrancado?)"
fi
echo "    php-fpm: ${FPM_ADDR}"

# -----------------------------------------------------------------------------
#  3) Sitio
# -----------------------------------------------------------------------------
echo "==> 3/5  sitio -> ${DEST}"
RENDER="$(mktemp)"
sed -e "s|__DOMINIO__|${DOMAIN}|g" \
    -e "s|__RAIZ__|${ROOT}|g" \
    -e "s|__FPM_ADDR__|${FPM_ADDR}|g" \
    "$TPL" > "$RENDER"

if [[ "$DRY_RUN" == "1" ]]; then
    echo "    [dry-run] se escribiria ${DEST} (${LINK}) con este contenido:"
    echo "    ----------------------------------------------------------------"
    sed 's/^/    /' "$RENDER"
    echo "    ----------------------------------------------------------------"
    rm -f "$RENDER"
    echo
    echo "==> dry-run terminado: no se ha tocado nada."
    exit 0
fi

install -m 0644 "$RENDER" "$DEST"
rm -f "$RENDER"
ln -sfn "$DEST" "$LINK"
echo "    renderizado y enlazado en sites-enabled"

# Otra web anterior en el mismo dominio: nginx se queda con la primera que
# coincida, asi que hay que desactivarla.
for otro in /etc/nginx/sites-enabled/*; do
    [[ -e "$otro" ]] || continue
    [[ "$(basename "$otro")" == "${DOMAIN}.conf" ]] && continue
    if grep -qsE "server_name[^;]*[[:space:]]${DOMAIN}([[:space:];]|$)" "$otro" 2>/dev/null; then
        echo "    AVISO: $(basename "$otro") tambien declara ${DOMAIN}."
        echo "           Desactivalo (sudo rm '$otro') para que no gane el sitio antiguo."
    fi
done

if [[ -e /etc/nginx/sites-enabled/default ]]; then
    echo "    AVISO: existe el sitio 'default' de nginx; si responde antes que este,"
    echo "           desactualo con:  sudo rm /etc/nginx/sites-enabled/default"
fi

# -----------------------------------------------------------------------------
#  4) y 5) Validar y recargar
# -----------------------------------------------------------------------------
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
echo "LISTO. Siguientes pasos en el servidor:"
echo
echo "  1) .env (copia .env.example si no existe):"
echo "        APP_ENV=production"
echo "        APP_DEBUG=false"
echo "        APP_URL=https://${DOMAIN}"
echo "        BASE_DOMAINS=${DOMAIN}"
echo "        DEMO_STORE=idirecto-demo"
echo "        + credenciales de la base de datos de ese servidor"
echo "  2) Esquema y datos:   php database/migrate.php --seed"
echo "  3) HTTPS:             sudo certbot --nginx -d ${DOMAIN} -d www.${DOMAIN}"
echo "     (si el dominio ya tenia certificado, certbot lo reutiliza)"
echo "  4) Comprobar protecciones:"
echo "        for p in /.env /app/bootstrap.php /config/database.php \\"
echo "                 /.agents/PROJECT.md /public/assets/css/shop.css; do"
echo "          printf '%s  %s\\n' \"\$(curl -s -o /dev/null -w '%{http_code}' https://${DOMAIN}\$p)\" \"\$p\""
echo "        done"
echo "        # esperado: 404 en todo menos /public/assets/... (200)"
echo
echo "  https://${DOMAIN}/           (tienda)"
echo "  http://${DOMAIN}/panel      (panel)"
echo "  Logs: /var/log/nginx/${DOMAIN}.error.log"
echo

# =============================================================================
#  [PLESK] Directivas para pegar en Plesk -> dominio -> Apache & nginx Settings
#  -> "Additional nginx directives" (cuando nginx esta como proxy delante de
#  Apache, que es lo habitual). Plesk ya enruta a index.php via .htaccess de
#  Apache, aqui solo hace falta impedir que nginx sirva ficheros internos.
# =============================================================================
#
#     # Rutas internas y ficheros sensibles: nunca por web
#     location ~ ^/(\.agents|\.git|app|config|database|deploy|storage|tools|vendor)(/|$) {
#         deny all;
#         return 404;
#     }
#     location ~* \.(env|sql|md|log|json|lock|yml|yaml|sh|ini|bak|dist|example)$ {
#         deny all;
#         return 404;
#     }
#     location ~ /\.(?!well-known) {
#         deny all;
#         return 404;
#     }
#
#  Y en Plesk: Websites & Domains -> <dominio> -> Hosting Settings ->
#  Document root = tienda   (apunta a /var/www/vhosts/<suscripcion>/tienda).
#
# =============================================================================
#  [/PLESK]
# =============================================================================
