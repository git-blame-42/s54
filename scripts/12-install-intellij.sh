#!/usr/bin/env bash
# 12-install-intellij.sh: IntelliJ IDEA Community (snap). Opcional.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SNAP=intellij-idea-community

usage() {
  cat <<USAGE
uso: $(basename "$0") [-h] [-z]

Opcional (interface gráfica; fora do 00-run-all.sh). Instala o
IntelliJ IDEA Community com "snap install $SNAP --classic"
(e o snapd, se faltar). Se já houver "idea" ou
"$SNAP" no PATH, o snap instalado ou o JetBrains
Toolbox em ~/.local/share/JetBrains/Toolbox, não instala nada.

Atenção: a edição Community não tem o plugin de PHP (só PhpStorm ou
Ultimate). Serve para Java/Kotlin e edição genérica. Para PHP use o
11 (VS Code + Intelephense) ou o 10 (Neovim).

  -h  mostra esta ajuda
  -z  desfaz: remove o snap do IntelliJ, os snaps que vieram junto
      (core22, snapd...) e o pacote snapd, só o que este script instalou
USAGE
}

snap_names() { # snaps instalados, separados por espaço
  command -v snap >/dev/null 2>&1 || return 0
  snap list 2>/dev/null | awk 'NR > 1 {print $1}' | xargs
}

# Remove os snaps dados, na ordem que o snapd aceita: aplicativos,
# depois bases (core*, bare) e por último o próprio snapd. Um snap que
# outro ainda usa (base de um snap que já existia) fica, com aviso.
remove_snaps() { # nomes...
  local s order=() cur
  cur=" $(snap_names) "
  for s in "$@"; do case "$s" in core*|bare|snapd) ;; *) order+=("$s") ;;
    esac; done
  for s in "$@"; do case "$s" in core*|bare) order+=("$s") ;; esac; done
  for s in "$@"; do [[ "$s" == snapd ]] && order+=("$s"); done
  for s in "${order[@]}"; do
    [[ "$cur" == *" $s "* ]] || continue
    if sudo snap remove --purge "$s" >/dev/null 2>&1; then
      ok "snap $s removido"
    else
      warn "snap $s mantido (outro snap ainda depende dele)"
    fi
  done
}

idea_present() {
  command -v idea >/dev/null 2>&1 \
    || command -v "$SNAP" >/dev/null 2>&1 \
    || snap list "$SNAP" >/dev/null 2>&1 \
    || [[ -d "$HOME/.local/share/JetBrains/Toolbox" ]]
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  step "Situação atual"
  if [[ -z "$(state_get idea_preexisting)" ]]; then
    if idea_present; then state_set idea_preexisting 1
    else state_set idea_preexisting 0; fi
  fi
  if [[ "$(state_get idea_preexisting)" == 1 ]]; then
    ok "IntelliJ IDEA (ou JetBrains Toolbox) já existe; nada a fazer."
    state_mark_done
    return 0
  fi
  require_sudo
  if ! command -v snap >/dev/null 2>&1; then
    step "snapd"
    apt_update_once
    apt_install_tracked installed_packages snapd
    sudo systemctl enable --now snapd.socket >/dev/null 2>&1 || true
    ok "snapd instalado"
  fi
  if [[ -e "$HOME/snap" ]]; then state_set snap_dir_preexisting 1
  else state_set snap_dir_preexisting 0; fi
  if [[ -z "$(state_get snaps_recorded)" ]]; then
    state_set snaps_before "$(snap_names)"
    state_set snaps_recorded 1
  fi
  step "snap install $SNAP --classic (download grande)"
  if snap list "$SNAP" >/dev/null 2>&1; then
    ok "já instalado numa execução anterior"
  else
    sudo snap install "$SNAP" --classic
    state_set snap_installed 1
  fi
  # Snaps que vieram junto (core22, snapd...): o -z tira também.
  local before s added=""
  before=" $(state_get snaps_before) "
  for s in $(snap_names); do
    [[ "$before" == *" $s "* ]] || added="$added $s"
  done
  state_set snaps_added "${added# }"
  info "snaps novos: ${added# }"
  step "Teste"
  snap list "$SNAP" | tail -1
  ok "binário: $(command -v "$SNAP" || echo /snap/bin/$SNAP)"
  state_mark_done
  info
  info "Pronto. Abra: $SNAP (ou pelo menu). Sem plugin de PHP na Community."
}

undo() {
  undo_or_nothing
  local pkgs
  if [[ "$(state_get idea_preexisting)" == 1 ]]; then
    info "IntelliJ já existia; este script não instalou nada."
    state_remove
    return 0
  fi
  local added
  added="$(state_get snaps_added)"
  if [[ -n "$added" ]] && command -v snap >/dev/null 2>&1; then
    step "Removendo os snaps que este script instalou: $added"
    require_sudo
    # shellcheck disable=SC2086  # lista de nomes separada por espaço
    remove_snaps $added
  elif [[ "$(state_get snap_installed)" == 1 ]]; then
    step "snap remove --purge $SNAP"
    require_sudo
    sudo snap remove --purge "$SNAP"
    ok "snap removido"
  fi
  if [[ "$(state_get snap_dir_preexisting)" == 0 ]]; then
    rm -rf "$HOME/snap"
  fi
  pkgs="$(state_get installed_packages)"
  if [[ -n "$pkgs" ]]; then
    step "Removendo pacotes (e dependências que vieram junto)"
    info "$pkgs"
    require_sudo
    sudo systemctl disable --now snapd.socket snapd.service \
      >/dev/null 2>&1 || true
    apt_purge_safe "$pkgs"
    sudo rm -rf /var/lib/snapd /var/cache/snapd /snap 2>/dev/null || true
  fi
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
