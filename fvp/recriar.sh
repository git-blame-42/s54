#!/bin/bash
# Devolve o .git ao clone projeto/ de um cenário do FVP, a partir do
# solucao.bundle (completo: main + branch fvp/<cenario>).
# Funciona offline. Se projeto/ não existir, cria do zero.
#
# Uso (de qualquer diretório; caminho relativo ou absoluto do cenário):
#   fvp/recriar.sh fvp/4.1/exemplos/a1-centralizar-redirect-middlewares
#   fvp/recriar.sh fvp/4.2/exemplos/p1-slug fvp/4.1/exemplos/a2-slug
#   fvp/recriar.sh --todos      # todos os cenários de fvp/*/exemplos/
#
# O clone fica sem remote. Para apontar origin para o repositório da
# equipe: FVP_REPO_URL=<url> fvp/recriar.sh <caminho-do-cenario>
#
# Depois: cd <caminho-do-cenario>/projeto && cp .env.example .env && \
#   ./run composer install && ./run up -d && ./run db:reset && \
#   ./run db:populate

set -o errexit
set -o pipefail

URL="${FVP_REPO_URL:-}"
AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

recriar() {
    local dir cenario branch tmp

    if ! dir="$(cd "$1" 2>/dev/null && pwd)"; then
        echo "diretório não existe: $1" >&2
        return 1
    fi
    cenario="$(basename "$dir")"
    branch="fvp/$cenario"

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
    if ! git clone --quiet --branch "$branch" "$dir/solucao.bundle" "$tmp"
    then
        rm -rf "$tmp"
        echo "bundle inválido ou corrompido: $dir/solucao.bundle" >&2
        return 1
    fi
    mkdir -p "$dir/projeto"
    mv "$tmp/.git" "$dir/projeto/.git"
    rm -rf "$tmp"

    git -C "$dir/projeto" checkout --quiet --force "$branch"
    git -C "$dir/projeto" rev-parse -q --verify main >/dev/null \
        || git -C "$dir/projeto" branch --quiet main origin/main
    if [ -n "$URL" ]; then
        git -C "$dir/projeto" remote set-url origin "$URL"
    else
        git -C "$dir/projeto" remote remove origin
    fi
    cp -n "$dir/projeto/.env.example" "$dir/projeto/.env" 2>/dev/null || true

    echo "$cenario: $(git -C "$dir/projeto" rev-list --count main.."$branch")" \
         "commit(s) em $branch; árvore:" \
         "$(git -C "$dir/projeto" status --porcelain | wc -l | tr -d ' ')" \
         "diferença(s)"
}

if [ "$#" -eq 0 ]; then
    sed -n '2,16p' "$0"
    exit 1
fi

if [ "$1" = "--todos" ]; then
    for d in "$AQUI"/*/exemplos/*/; do
        d="${d%/}"
        [ -f "$d/solucao.bundle" ] || continue
        recriar "$d"
    done
else
    for c in "$@"; do
        recriar "$c"
    done
fi
