#!/usr/bin/env bash
# 12-install-intellij.sh: IntelliJ IDEA (snap). Opcional.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
INTELLIJ_EDITION="${INTELLIJ_EDITION:-}"
INTELLIJ_CONFIG="${INTELLIJ_CONFIG:-0}"
case "$INTELLIJ_EDITION" in
  ''|unificado) SNAP=intellij-idea ;;
  community)    SNAP=intellij-idea-community ;;
  *) die "INTELLIJ_EDITION=$INTELLIJ_EDITION inválido (use community" \
    "ou deixe vazio)." ;;
esac

usage() {
  cat <<USAGE
uso: $(basename "$0") [-h] [-z]

Opcional (interface gráfica; fora do 00-run-all.sh). Instala o
IntelliJ IDEA com "snap install $SNAP --classic" (e o snapd, se
faltar). Antes procura um IntelliJ já instalado, de qualquer origem:
snap (intellij-idea, -ultimate, -community, -educational), JetBrains
Toolbox (~/.local/share/JetBrains/Toolbox/apps), tarball (/opt/idea*,
~/idea*, idea ou idea.sh no PATH) ou flatpak. Se achar, mostra
produto, edição, versão e origem e não instala nada (o -z também não
remove).

O padrão é o snap intellij-idea, o produto único da JetBrains desde o
2025.3. O suporte a PHP (plugin com.jetbrains.php, que o
12-1-config-intellij.sh instala) exige a assinatura paga; estudante
tem licença educacional gratuita da JetBrains (jetbrains.com/student).
Este script não embute licença nem conta: o login é feito no IDE.

Variáveis:
  INTELLIJ_EDITION=community  instala o intellij-idea-community. Até o
                              2025.2 era a Community (sem PHP); no
                              2026.3 o snap já traz o produto único
                              (productCode IU), igual ao intellij-idea
  INTELLIJ_CONFIG=1           ao final, roda o 12-1-config-intellij.sh
                              (keymap, tema, PSR-12, templates, plugins)

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

# Mostra as instalações do IntelliJ que já existem. Retorna 1 se não há.
report_existing() {
  local line found=1
  while IFS= read -r line; do
    [[ -n "$line" ]] || continue
    ok "já instalado: $(intellij_describe "$line")"
    info "    binário: $(cut -d'|' -f5 <<<"$line")"
    found=0
  done < <(intellij_detect)
  return "$found"
}

config_if_asked() {
  [[ "$INTELLIJ_CONFIG" == 1 ]] || return 0
  step "INTELLIJ_CONFIG=1: 12-1-config-intellij.sh"
  "$SCRIPTS_DIR/12-1-config-intellij.sh"
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  step "Situação atual"
  if [[ -z "$(state_get idea_preexisting)" ]]; then
    if report_existing; then state_set idea_preexisting 1
    else state_set idea_preexisting 0; fi
  fi
  if [[ "$(state_get idea_preexisting)" == 1 ]]; then
    ok "IntelliJ IDEA já existe; nada a instalar (o -z não o remove)."
    state_mark_done
    config_if_asked
    return 0
  fi
  info "nenhum IntelliJ IDEA encontrado; vou instalar o snap $SNAP"
  require_sudo
  if ! command -v snap >/dev/null 2>&1; then
    step "snapd"
    apt_update_once
    apt_install_tracked installed_packages snapd
    sudo systemctl enable --now snapd.socket >/dev/null 2>&1 || true
    ok "snapd instalado"
  fi
  if [[ -n "$(state_get snap_dir_preexisting)" ]]; then :
  elif [[ -e "$HOME/snap" ]]; then state_set snap_dir_preexisting 1
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
    state_set snap_name "$SNAP"
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
  info "Pronto. Abra: $SNAP (ou pelo menu)."
  if grep -q '"productCode": *"IC"' "/snap/$SNAP/current/product-info.json" \
      2>/dev/null; then
    info "Edição Community: sem suporte a PHP."
  else
    info "PHP: faça login com a licença (educacional) no primeiro uso."
  fi
  if [[ "$INTELLIJ_CONFIG" == 1 ]]; then
    config_if_asked
  else
    info "Configuração da disciplina: $SCRIPTS_DIR/12-1-config-intellij.sh"
  fi
}

undo() {
  undo_or_nothing
  local pkgs
  if [[ -f "$S50_STATE_DIR/1-config-intellij.state" ]]; then
    warn "a configuração do 12-1 continua; para tirá-la:" \
      "$SCRIPTS_DIR/12-1-config-intellij.sh -z"
  fi
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
    local name
    name="$(state_get snap_name)"
    name="${name:-$SNAP}"
    step "snap remove --purge $name"
    require_sudo
    sudo snap remove --purge "$name"
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
