#!/usr/bin/env bash
# =============================================================================
#  Comprueba que la documentacion interna NO es accesible por web.
#
#  Uso:
#     bash .agents/scripts/check-privacidad.sh [URL_BASE]
#     bash .agents/scripts/check-privacidad.sh --cli-server
#
#  Sin argumentos prueba http://local.tienda. Con --cli-server levanta el
#  servidor embebido de PHP en un puerto libre y prueba contra el.
#
#  Sale con codigo 0 solo si TODAS las rutas internas estan bloqueadas y el
#  control (un asset publico) responde 200.
# =============================================================================
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
MODE="${1:-http://local.tienda}"

# Rutas que JAMAS deben servirse
PATHS=(
    "/.agents/README.md"
    "/.agents/PROMPT.md"
    "/.agents/PROJECT.md"
    "/.agents/STATE.md"
    "/.agents/CHANGELOG.md"
    "/.agents/skills/tienda-plataforma/SKILL.md"
    "/.env"
    "/.env.example"
    "/README.md"
    "/.htaccess"
    "/composer.json"
    "/.git/config"
    "/config/database.php"
    "/database/migrations/001_schema.sql"
    "/deploy/setup-local-domain.sh"
    "/tools/verify.php"
    "/storage/logs/"
)

SERVER_PID=""

cleanup() {
    if [[ -n "$SERVER_PID" ]]; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi
}
trap cleanup EXIT

if [[ "$MODE" == "--cli-server" ]]; then
    PORT=8123
    while (exec 3<>/dev/tcp/127.0.0.1/$PORT) 2>/dev/null; do
        PORT=$((PORT + 1))
    done
    php -S "127.0.0.1:${PORT}" "$ROOT/index.php" >/dev/null 2>&1 &
    SERVER_PID=$!
    BASE="http://127.0.0.1:${PORT}"
    sleep 1.5
    echo "Servidor embebido de PHP en ${BASE} (pid ${SERVER_PID})"
else
    BASE="${MODE%/}"
    echo "Probando contra ${BASE}"
fi

echo "-------------------------------------------------------------"

fails=0

# Control: un asset publico DEBE responder 200 (si no, la prueba no vale)
control=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${BASE}/public/assets/css/shop.css")
if [[ "$control" == "200" ]]; then
    printf '  %-6s control: /public/assets/css/shop.css (accesible)\n' "OK"
else
    printf '  %-6s control: /public/assets/css/shop.css devolvio %s\n' "FALLO" "$control"
    fails=$((fails + 1))
fi

# Rutas internas: cualquiera que responda 200 es una fuga
for path in "${PATHS[@]}"; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "${BASE}${path}")
    if [[ "$code" == "200" ]]; then
        printf '  %-6s %-52s -> %s  <-- FUGA\n' "FALLO" "$path" "$code"
        fails=$((fails + 1))
    elif [[ "$code" == "000" ]]; then
        printf '  %-6s %-52s -> sin respuesta\n' "AVISO" "$path"
    else
        printf '  %-6s %-52s -> %s\n' "OK" "$path" "$code"
    fi
done

echo "-------------------------------------------------------------"
if [[ $fails -eq 0 ]]; then
    echo "RESULTADO: la documentacion interna NO es accesible por web (${#PATHS[@]} rutas + control)."
    exit 0
fi

echo "RESULTADO: ${fails} problema(s). Revisa .htaccess, index.php y el VirtualHost."
exit 1
