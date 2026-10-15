#!/bin/bash
# Devolve o .git ao clone projeto/ de um ou mais cenários, a partir do
# solucao.bundle (completo: main do público + branch fvp/<cenario>).
# Funciona offline. Se projeto/ não existir, cria do zero.
#
# Uso:
#   ./recriar.sh a1-centralizar-redirect-middlewares   # um cenário
#   ./recriar.sh --todos                               # todos
#
# Depois: cd <cenario>/projeto && cp .env.example .env && \
#   ./run composer install && ./run up -d && ./run db:reset && \
#   ./run db:populate

set -o errexit
set -o pipefail

URL="https://github.com/VitorThorvi/tsi34d-backend.git"
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

recriar() {
    local cenario="$1"
    local dir="$AQUI/$cenario"
    local branch="fvp/$cenario"
    local tmp

    if [ ! -f "$dir/solucao.bundle" ]; then
        echo "sem solucao.bundle em $dir" >&2
        return 1
    fi

    if [ -d "$dir/projeto/.git" ]; then
        echo "$cenario: projeto/.git já existe, nada a fazer"
        return 0
    fi

    # git clone valida o bundle; git bundle verify exigiria estar num repo.
    tmp="$(mktemp -d)"
    if ! git clone --quiet --branch "$branch" "$dir/solucao.bundle" "$tmp"; then
        rm -rf "$tmp"
        echo "bundle inválido ou corrompido: $dir/solucao.bundle" >&2
        return 1
    fi
    mkdir -p "$dir/projeto"
    mv "$tmp/.git" "$dir/projeto/.git"
    rm -rf "$tmp"

    cd "$dir/projeto"
    git remote set-url origin "$URL"
    git checkout --quiet --force "$branch"
    git rev-parse -q --verify main >/dev/null || git branch --quiet main origin/main
    cp -n .env.example .env 2>/dev/null || true

    echo "$cenario: $(git rev-list --count main.."$branch") commit(s) em $branch;" \
         "árvore: $(git status --porcelain | wc -l | tr -d ' ') diferença(s)"
}

if [ "$#" -eq 0 ]; then
    sed -n '2,12p' "$0"
    exit 1
fi

if [ "$1" = "--todos" ]; then
    for d in "$AQUI"/*/; do
        d="${d%/}"
        [ -f "$d/solucao.bundle" ] || continue
        recriar "$(basename "$d")"
    done
else
    for c in "$@"; do
        recriar "$c"
    done
fi
