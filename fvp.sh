#!/usr/bin/env bash
# fvp.sh: acha no catálogo FVP os cenários que falam de um termo.
#
#   fvp.sh <termo> [termo2 ...]   cenários que citam qualquer termo
#                                 (sem distinguir maiúsculas), mais
#                                 relevantes primeiro (título vale 10),
#                                 e o exemplo pronto em exemplos-fvp/
#   fvp.sh -s <ID>                mostra o cenário inteiro (ex.: -s B10)
#   fvp.sh -l                     lista os títulos de todos os cenários
#   fvp.sh -c                     lista por chance (alta primeiro), com
#                                 o tempo estimado; filtre com grep
#
# Procura o catálogo ao lado do script; FVP_CATALOGO=/caminho sobrepõe.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CAT="${FVP_CATALOGO:-$DIR/fvp-possiveis-modificacoes.md}"
EX="$(dirname "$CAT")/exemplos-fvp"

[ -r "$CAT" ] || { echo "catálogo não encontrado: $CAT" >&2; exit 2; }

list() { grep -n '^### ' "$CAT"; }

# Ordena pela primeira palavra da linha "Chance: ..." de cada cenário.
by_chance() {
    awk '
        /^### / { h = $0; order[++k] = h; next }
        /^## /  { h = "" }
        h != "" && chance[h] == "" && /^Chance: / {
            v = $0; sub(/^Chance: /, "", v); sub(/[ .,(].*$/, "", v)
            chance[h] = v
            if (match($0, /Tempo: [0-9]+/))
                tempo[h] = substr($0, RSTART + 7, RLENGTH - 7) "m"
        }
        END {
            for (i = 1; i <= k; i++) {
                h = order[i]; v = chance[h]
                r = (v == "alta") ? 5 : (v == "média-alta") ? 4 : \
                    (v == "média") ? 3 : (v == "baixa") ? 1 : \
                    (v == "média-baixa" || v == "baixa-média") ? 2 : 0
                printf "%d\t%s\t%s\t%s\n", r, (v == "" ? "?" : v), tempo[h], h
            }
        }
    ' "$CAT" | sort -t "$(printf '\t')" -k1,1nr -s | cut -f2-
}

show() {
    local id="$1" out
    out="$(awk -v id="$id" '
        $0 ~ "^### " id "\\." { on = 1; print; next }
        on && /^(###|##) / { exit }
        on { print }
    ' "$CAT")"
    if [ -z "$out" ]; then
        echo "cenário $id não encontrado (use -l)" >&2
        return 1
    fi
    printf '%s\n' "$out"
}

# Tira acentos para casar com os slugs de exemplos-fvp/ (sem iconv).
plain() {
    local t="$1" pair
    for pair in á:a à:a ã:a â:a é:e ê:e í:i ó:o ô:o õ:o ú:u ü:u ç:c
    do
        t="${t//${pair%:*}/${pair#*:}}"
    done
    printf '%s' "$t"
}

search() {
    local terms
    terms="$(printf '%s' "$*" | tr 'A-Z' 'a-z' | tr ' ' '|')"
    awk -v terms="$terms" '
        BEGIN { n = split(terms, T, "|") }
        /^### / { h = $0; order[++k] = h; w = 10; title = 1 }
        /^## /  { if ($0 !~ /^### /) h = "" }
        h != "" {
            l = tolower($0)
            for (i = 1; i <= n; i++) if (index(l, T[i])) c[h] += w
            w = 1
        }
        END {
            for (i = 1; i <= k; i++)
                if (c[order[i]]) printf "%3d  %s\n", c[order[i]], order[i]
        }
    ' "$CAT" | sort -rn
    if [ -d "$EX" ]; then
        echo "--- exemplos-fvp/"
        ls "$EX" | grep -i -E "$(plain "$terms" | tr -d '/')" || true
    fi
}

case "${1:-}" in
    "")   sed -n '2,12p' "$0" | sed 's/^# \{0,1\}//'; exit 1 ;;
    -l)   list ;;
    -c)   by_chance ;;
    -s)   show "${2:?informe o ID, ex.: -s B10}" ;;
    *)    search "$@" ;;
esac
