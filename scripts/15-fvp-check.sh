#!/usr/bin/env bash
# 15-fvp-check.sh: diz quais soluções prontas da FVP (chance alta e
# média-alta) aplicam sem conflito no main ATUAL do clone do projeto.
# Só lê: não muda branch, árvore nem índice.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPTS_DIR/.." && pwd)"
FVP_SH="$REPO_ROOT/fvp.sh"
APLICAR_SH="$REPO_ROOT/fvp/aplicar.sh"
EVENTOS_STATE="$S50_STATE_DIR/install-eventos-do-campus.state"
SCOPE="${FVP_ESCOPO:-4.2}"
BASE="${FVP_BASE:-main}"

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Para cada cenário de chance alta ou média-alta do escopo $SCOPE (lista
de "fvp.sh -c $SCOPE"), simula o cherry-pick da solução pronta contra
o $BASE atual do clone do projeto ($(project_dir)) e diz:
  aplica       entra sem conflito
  conflito     lista os arquivos (veja "Se der conflito" no README)
  já aplicado  o $BASE já tem os commits
  só README    cenário sem commits (receita no README)
Usa fvp/aplicar.sh --dry-run; não muda branch, árvore nem índice.

Outro escopo, ref ou clone:
  FVP_ESCOPO=4.1 FVP_BASE=minha-branch EVENTOS_DIR=/caminho \\
    $(basename "$0")

  -h  mostra esta ajuda
  -z  nada a desfazer (este script só lê)
EOF
}

project_dir() {
  local d=''
  [[ -f "$EVENTOS_STATE" ]] && d="$(sed -n 's/^project_dir=//p' \
    "$EVENTOS_STATE" | tail -n 1)"
  echo "${EVENTOS_DIR:-${d:-$HOME/eventos-do-campus}}"
}

# Uma linha do relatório: ID, situação (alinhada por caractere, não
# por byte: "só README" tem acento) e título.
row() { # id situacao titulo
  printf '  %-4s %s%*s %s\n' "$1" "$2" $((11 - ${#2})) '' "$3"
}

run() {
  need_cmd git "Rode scripts/01-install-tools.sh antes."
  local dir short line chance id rc out n_ok=0 n_conf=0 n_other=0
  local -a conflicts=()
  dir="$(project_dir)"
  git -C "$dir" rev-parse -q --verify "$BASE^{commit}" >/dev/null 2>&1 \
    || die "$dir não é clone git com o ref $BASE. Rode o 08 antes" \
      "(ou EVENTOS_DIR=/caminho)."
  cd "$dir" || die "não consegui entrar em $dir"
  short="$(git rev-parse --short "$BASE")"
  step "Soluções da FVP $SCOPE contra $BASE ($short) em $dir"
  # fvp.sh -c: escopo TAB chance TAB tempo TAB "### ID. título". O tempo
  # pode vir vazio, por isso o awk (read com IFS de tab juntaria campos).
  while IFS='|' read -r chance line; do
    [[ "$chance" == alta || "$chance" == média-alta ]] || continue
    id="${line#\#\#\# }"
    id="${id%%.*}"
    rc=0
    out="$("$APLICAR_SH" --dry-run --base "$BASE" --escopo "$SCOPE" \
      "$id" 2>&1)" || rc=$?
    case "$rc" in
      0) row "$id" aplica "${line#*. }"
         n_ok=$((n_ok + 1)) ;;
      1) row "$id" CONFLITO "${line#*. }"
         printf '        %s\n' "$(sed -n 's/^RESULTADO: conflito //p' \
           <<<"$out")"
         conflicts+=("$id")
         n_conf=$((n_conf + 1)) ;;
      3) row "$id" 'já aplicado' "${line#*. }"
         n_other=$((n_other + 1)) ;;
      4) row "$id" 'só README' "${line#*. }"
         n_other=$((n_other + 1)) ;;
      *) row "$id" '?' "$(tail -n 1 <<<"$out")"
         n_other=$((n_other + 1)) ;;
    esac
  done < <("$FVP_SH" -c "$SCOPE" | awk -F '\t' '{print $2 "|" $4}')
  info
  info "$n_ok aplicam, $n_conf com conflito, $n_other outros."
  if (( n_conf )); then
    info "Conflitam: ${conflicts[*]}. Antes de aplicar um deles:"
    info "  fvp -s <ID>   e o README do exemplo, seção 'Se der conflito'"
  fi
}

undo() {
  info "Este script só lê; nada a desfazer."
}

parse_flags "$@"
"$ACTION"
