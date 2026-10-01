#!/usr/bin/env bash
# =============================================================================
#  Instala el Agent Skill "tienda-plataforma" para que un agente lo descubra.
#
#  Uso:
#     bash .agents/scripts/install-skill.sh            # instala
#     bash .agents/scripts/install-skill.sh --uninstall
#
#  Crea un enlace simbolico en los directorios de skills del usuario que
#  existan (~/.claude/skills, ~/.codegpt/skills). El enlace apunta al skill del
#  repositorio, de modo que al hacer git pull el skill queda actualizado solo.
# =============================================================================
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
SKILL_SRC="$ROOT/.agents/skills/tienda-plataforma"
SKILL_NAME="tienda-plataforma"

if [[ ! -f "$SKILL_SRC/SKILL.md" ]]; then
    echo "ERROR: no se encuentra $SKILL_SRC/SKILL.md" >&2
    exit 1
fi

# Directorios de skills candidatos (solo se usan los que ya existen)
CANDIDATES=(
    "$HOME/.claude/skills"
    "$HOME/.codegpt/skills"
    "$HOME/.config/claude/skills"
)

if [[ "${1:-}" == "--uninstall" ]]; then
    removed=0
    for dir in "${CANDIDATES[@]}"; do
        target="$dir/$SKILL_NAME"
        if [[ -L "$target" ]]; then
            rm "$target"
            echo "  eliminado: $target"
            removed=$((removed + 1))
        fi
    done
    [[ $removed -eq 0 ]] && echo "  no habia nada que eliminar"
    exit 0
fi

echo "Instalando el skill '$SKILL_NAME'"
echo "  origen: $SKILL_SRC"

installed=0
for dir in "${CANDIDATES[@]}"; do
    if [[ ! -d "$dir" ]]; then
        continue
    fi

    target="$dir/$SKILL_NAME"

    if [[ -e "$target" && ! -L "$target" ]]; then
        echo "  AVISO: $target ya existe y no es un enlace; se deja como esta"
        continue
    fi

    ln -sfn "$SKILL_SRC" "$target"
    echo "  instalado: $target -> $SKILL_SRC"
    installed=$((installed + 1))
done

if [[ $installed -eq 0 ]]; then
    echo "  AVISO: no se encontro ningun directorio de skills."
    echo "  Crea uno (por ejemplo ~/.claude/skills) y vuelve a ejecutarlo."
    exit 1
fi

echo
echo "Listo. El agente descubrira el skill en la proxima sesion."
echo "Comprobacion: head -5 \"$SKILL_SRC/SKILL.md\""
