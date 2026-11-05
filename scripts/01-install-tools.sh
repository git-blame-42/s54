#!/usr/bin/env bash
# 01-install-tools.sh: instala git, curl, tmux, zsh, micro e ncdu (apt).
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

TOOLS=(git curl tmux zsh micro ncdu)

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Instala com apt: ${TOOLS[*]}.
(git e curl são usados pelos outros scripts; a imagem minimal do
Ubuntu não traz git.) Precisa de sudo. Pacotes que já existiam na
máquina não são tocados e ficam fora do -z.

  -h  mostra esta ajuda
  -z  desfaz: remove só os pacotes que este script instalou
EOF
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404

  step "Conferindo o que já está instalado"
  local missing t
  missing="$(apt_missing "${TOOLS[@]}")"
  for t in "${TOOLS[@]}"; do
    if apt_installed "$t"; then
      ok "$t já instalado (fora do -z)"
    else
      info "$t: será instalado"
    fi
  done

  if [[ -z "$missing" ]]; then
    # Numa retomada, "installed" guarda o que a execução anterior pediu.
    [[ -n "$(state_get installed)" ]] || state_set installed ""
    state_mark_done
    info "Nada a instalar."
    return 0
  fi

  step "Instalando: $missing"
  require_sudo
  apt_update_once
  local prev
  prev="$(state_get installed)"
  state_set installed "${prev:+$prev }$missing"
  # shellcheck disable=SC2086
  apt_install_tracked installed_packages $missing
  for t in $missing; do
    ok "$t instalado: $(command -v "$t" || echo '?')"
  done
  info "pacotes novos (com dependências): $(state_get installed_packages)"

  state_mark_done
  info
  info "Pronto. Próximos passos sugeridos:"
  info "  scripts/03-install-ohmyzsh.sh     # oh-my-zsh + plugin tmux"
  info "  scripts/04-set-zsh-default.sh     # zsh como shell padrão"
}

undo() {
  undo_or_nothing
  local installed
  installed="$(state_get installed)"

  if [[ -z "$installed" ]]; then
    info "Este script não instalou nenhum pacote; nada a remover."
    state_remove
    return 0
  fi

  if [[ " $installed " == *" zsh "* ]]; then
    local shell_now
    shell_now="$(getent passwd "$USER" | cut -d: -f7)"
    if [[ "$shell_now" == */zsh ]]; then
      die "zsh ainda é seu shell padrão. Rode 04-set-zsh-default.sh -z antes."
    fi
    if [[ -d "$HOME/.oh-my-zsh" ]]; then
      warn "pasta ~/.oh-my-zsh existe;" \
        "considere 03-install-ohmyzsh.sh -z antes."
    fi
  fi

  local pkgs
  pkgs="$(state_get installed_packages)"
  [[ -n "$pkgs" ]] || pkgs="$installed"
  step "Removendo (com as dependências que vieram junto): $pkgs"
  require_sudo
  apt_purge_safe "$pkgs"
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
