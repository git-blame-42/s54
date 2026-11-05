#!/usr/bin/env bash
# fvp.sh: acha nos catálogos FVP (fvp/<escopo>/catalogo.md) o cenário
# que fala de um termo, por escopo (4.1, 4.2...) ou em todos.
#
#   fvp.sh                   lista todos os cenários, por escopo e chance
#   fvp.sh 4.2               só os cenários do escopo 4.2
#   fvp.sh [4.2] <termo>...  cenários que citam qualquer termo (título vale
#                            10; frase entre aspas conta como um só termo)
#                            e o exemplo pronto em fvp/<escopo>/exemplos/
#   fvp.sh -s <ID>           mostra o cenário inteiro (ex.: -s P1), em
#                            qualquer escopo
#   fvp.sh -c [4.2]          lista por chance (alta primeiro), com tempo
#   fvp.sh -l [4.2]          lista só os títulos
#   fvp.sh -h                esta ajuda
#
# FVP_DIR=/caminho sobrepõe a pasta fvp/ ao lado do script.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="${FVP_DIR:-$DIR/fvp}"
TAB="$(printf '\t')"

help() { sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; }

catalog() { printf '%s/%s/catalogo.md' "$ROOT" "$1"; }

is_scope() { [ -n "$1" ] && [ -r "$(catalog "$1")" ]; }

# Escopos disponíveis (um por linha), ou só o informado.
scopes() {
    local c
    if [ -n "${1:-}" ]; then
        printf '%s\n' "$1"
        return
    fi
    for c in "$ROOT"/*/catalogo.md; do
        [ -r "$c" ] && basename "$(dirname "$c")"
    done
}

# Linhas "rank TAB escopo TAB chance TAB tempo TAB ### ID. título".
ranked() {
    local s
    for s in $(scopes "${1:-}"); do
        awk -v scope="$s" '
            /^### / { h = $0; order[++k] = h; next }
            /^## /  { h = "" }
            h != "" && chance[h] == "" && /^Chance: / {
                v = $0; sub(/^Chance: /, "", v); sub(/[ .,(].*$/, "", v)
                chance[h] = v
                if (match($0, /Tempo: [0-9]+(-[0-9]+)?/))
                    tempo[h] = substr($0, RSTART + 7, RLENGTH - 7) "m"
            }
            END {
                for (i = 1; i <= k; i++) {
                    h = order[i]; v = chance[h]
                    r = (v == "alta") ? 5 : (v == "média-alta") ? 4 : \
                        (v == "média") ? 3 : (v == "baixa") ? 1 : \
                        (v == "média-baixa" || v == "baixa-média") ? 2 : 0
                    printf "%d\t%s\t%s\t%s\t%s\n", r, scope, \
                        (v == "" ? "?" : v), tempo[h], h
                }
            }
        ' "$(catalog "$s")"
    done
}

# Por escopo e, dentro dele, por chance (alta primeiro).
list() { ranked "${1:-}" | sort -t "$TAB" -k2,2 -k1,1nr -s | cut -f2-; }

# Por chance (alta primeiro) em todos os escopos pedidos.
by_chance() { ranked "${1:-}" | sort -t "$TAB" -k1,1nr -k2,2 -s | cut -f2-; }

titles() {
    local s
    for s in $(scopes "${1:-}"); do
        grep -n '^### ' "$(catalog "$s")" | sed "s/^/$s:/"
    done
}

show() {
    local id="$1" s out
    for s in $(scopes); do
        out="$(awk -v id="$id" '
            $0 ~ "^### " id "\\." { on = 1; print; next }
            on && /^(###|##) / { exit }
            on { print }
        ' "$(catalog "$s")")"
        if [ -n "$out" ]; then
            printf '[%s] %s\n' "$s" "$out"
            return 0
        fi
    done
    echo "cenário $id não encontrado (use -l)" >&2
    return 1
}

# Tira acentos para casar com os slugs de exemplos/ (sem iconv).
plain() {
    local t="$1" pair
    for pair in á:a à:a ã:a â:a é:e ê:e í:i ó:o ô:o õ:o ú:u ü:u ç:c
    do
        t="${t//${pair%:*}/${pair#*:}}"
    done
    printf '%s' "$t"
}

# search [escopo] termo...: ranking (título vale 10) e exemplos.
search() {
    local scope="$1" s terms pat ex
    shift
    local IFS="|"
    terms="$(printf "%s" "$*" | tr "A-Z" "a-z")"
    unset IFS
    for s in $(scopes "$scope"); do
        awk -v terms="$terms" -v scope="$s" '
            BEGIN { n = split(terms, T, "|") }
            /^### / { h = $0; order[++k] = h; w = 10 }
            /^## /  { h = "" }
            h != "" {
                l = tolower($0)
                for (i = 1; i <= n; i++) if (index(l, T[i])) c[h] += w
                w = 1
            }
            END {
                for (i = 1; i <= k; i++)
                    if (c[order[i]])
                        printf "%3d  %s  %s\n", c[order[i]], scope, order[i]
            }
        ' "$(catalog "$s")"
    done | sort -rn
    pat="$(plain "$terms" | tr " " "-" | tr -d "/")"
    for s in $(scopes "$scope"); do
        ex="$ROOT/$s/exemplos"
        [ -d "$ex" ] || continue
        ls "$ex" | grep -i -E "$pat" | sed "s|^|--- $s/exemplos/|" \
            || true
    done
}

[ -d "$ROOT" ] || {
    echo "pasta de catálogos não encontrada: $ROOT" >&2
    exit 2
}

case "${1:-}" in
    -h|--help) help ;;
    -s) show "${2:?informe o ID, ex.: -s P1}" ;;
    -c) shift; by_chance "${1:-}" ;;
    -l) shift; titles "${1:-}" ;;
    *)
        scope=""
        if is_scope "${1:-}"; then
            scope="$1"
            shift
        fi
        if [ $# -eq 0 ]; then
            list "$scope"
        else
            search "$scope" "$@"
        fi
        ;;
esac
