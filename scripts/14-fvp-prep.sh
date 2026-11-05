#!/usr/bin/env bash
# 14-fvp-prep.sh: prepara o material da FVP no clone do projeto:
# importa as soluções prontas (fvp/*/exemplos/*/solucao.bundle) como
# branches e põe os atalhos fvp e fvp-aplicar no shell.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPTS_DIR/.." && pwd)"
FVP_SH="$REPO_ROOT/fvp.sh"
APLICAR_SH="$REPO_ROOT/fvp/aplicar.sh"
EVENTOS_STATE="$S50_STATE_DIR/install-eventos-do-campus.state"

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Prepara a FVP, sem rede:
  1. importa cada fvp/<escopo>/exemplos/<cenario>/solucao.bundle como o
     branch fvp/<escopo>/<cenario> no clone do projeto (o do 08:
     $(project_dir))
  2. põe no ~/.zshrc e no ~/.bashrc (os que existirem):
       alias fvp=$FVP_SH
       alias fvp-aplicar=$APLICAR_SH
Branch que já existia com o mesmo nome fica como está. Não muda o
branch atual nem a árvore do clone. Depois, para ver se as soluções
aplicam no seu main: scripts/15-fvp-check.sh

Outro clone: EVENTOS_DIR=/caminho $(basename "$0")

  -h  mostra esta ajuda
  -z  desfaz: apaga os branches fvp/... que este script criou e tira os
      atalhos dos arquivos rc
EOF
}

# Clone do projeto: o que o 08 registrou, senão EVENTOS_DIR ou o padrão.
project_dir() {
  local d=''
  [[ -f "$EVENTOS_STATE" ]] && d="$(sed -n 's/^project_dir=//p' \
    "$EVENTOS_STATE" | tail -n 1)"
  echo "${EVENTOS_DIR:-${d:-$HOME/eventos-do-campus}}"
}

rc_files() {
  local rc
  for rc in "$HOME/.zshrc" "$HOME/.bashrc"; do
    [[ -f "$rc" ]] && echo "$rc"
  done
  return 0
}

import_bundles() { # dir
  local dir="$1" b ex slug scope ref done_list n_new=0 n_old=0 n_err=0
  done_list=" $(state_get branches) "
  for b in "$REPO_ROOT"/fvp/*/exemplos/*/solucao.bundle; do
    [[ -f "$b" ]] || continue
    ex="$(dirname "$b")"
    slug="$(basename "$ex")"
    scope="$(basename "$(dirname "$(dirname "$ex")")")"
    ref="fvp/$scope/$slug"
    if [[ "$done_list" == *" $ref "* ]]; then
      n_new=$((n_new + 1))
      continue
    fi
    if git -C "$dir" rev-parse -q --verify "refs/heads/$ref" >/dev/null
    then
      n_old=$((n_old + 1))
      continue
    fi
    if git -C "$dir" fetch -q "$b" "refs/heads/fvp/$slug:refs/heads/$ref"
    then
      state_set branches "$(state_get branches) $ref"
      n_new=$((n_new + 1))
    else
      warn "não consegui importar $b"
      n_err=$((n_err + 1))
    fi
  done
  ok "$n_new branch(es) fvp/... deste script; $n_old já existiam"
  (( n_err == 0 )) || { fail "$n_err bundle(s) com erro"; return 1; }
}

install_aliases() {
  local rc block found=0
  block="alias fvp=$(printf '%q' "$FVP_SH")
alias fvp-aplicar=$(printf '%q' "$APLICAR_SH")"
  while read -r rc; do
    [[ -n "$rc" ]] || continue
    rc_block_add "$rc" fvp-aliases "$block"
    found=1
    ok "atalhos em $rc"
  done < <(rc_files)
  (( found )) || warn "sem ~/.zshrc nem ~/.bashrc; use o caminho:" \
    "$FVP_SH e $APLICAR_SH"
  state_set aliases 1
}

run() {
  refuse_if_done
  need_cmd git "Rode scripts/01-install-tools.sh antes."
  [[ -x "$FVP_SH" && -x "$APLICAR_SH" ]] \
    || die "não achei $FVP_SH ou $APLICAR_SH; o clone está incompleto?"
  local dir
  dir="$(project_dir)"
  git -C "$dir" rev-parse --is-inside-work-tree >/dev/null 2>&1 \
    || die "$dir não é um clone git do projeto. Rode o 08 antes" \
      "(ou EVENTOS_DIR=/caminho $(basename "$0"))."
  state_set project_dir "$dir"

  run_step "Importando as soluções da FVP em $dir" \
    import_bundles "$dir" || die "importação incompleta; rode de novo."
  run_step "Atalhos fvp e fvp-aplicar" install_aliases

  state_mark_done
  info
  info "Abra um terminal novo (atalhos) e, dentro de $dir:"
  info "  fvp -c 4.2                      cenários por chance"
  info "  fvp -s P1                       um cenário inteiro"
  info "  git log --oneline main..fvp/4.2/<cenario>   commits da solução"
  info "  fvp-aplicar --dry-run P1        aplica no branch atual?"
  info "  fvp-aplicar P1                  aplica e roda os testes"
  info "Conflitos contra o seu main: $SCRIPTS_DIR/15-fvp-check.sh"
}

undo() {
  undo_or_nothing
  local dir ref cur n=0 rc
  dir="$(state_get project_dir)"
  step "Branches fvp/..."
  if [[ -n "$dir" ]] && git -C "$dir" rev-parse --git-dir >/dev/null 2>&1
  then
    cur="$(git -C "$dir" branch --show-current)"
    for ref in $(state_get branches); do
      if [[ "$ref" == "$cur" ]]; then
        warn "$ref é o branch atual; troque de branch e apague à mão"
        continue
      fi
      git -C "$dir" branch -q -D "$ref" 2>/dev/null && n=$((n + 1))
    done
    ok "$n branch(es) apagado(s) em $dir"
  else
    info "clone ${dir:-?} não existe mais; nada a apagar."
  fi
  if [[ "$(state_get aliases)" == 1 ]]; then
    for rc in "$HOME/.zshrc" "$HOME/.bashrc"; do
      [[ -f "$rc" ]] && rc_block_remove "$rc" fvp-aliases
    done
    ok "atalhos removidos de ~/.zshrc e ~/.bashrc"
  fi
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
