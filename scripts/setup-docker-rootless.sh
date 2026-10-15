#!/usr/bin/env bash
# setup-docker-rootless.sh: ativa o Docker em modo rootless para o seu
# usuário (daemon roda sem root, como serviço systemd de usuário).
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SYSCTL_FILE=/etc/sysctl.d/99-s50-docker-rootless.conf
DATA_DIR="$HOME/.local/share/docker"
RUNTIME_DIR="${XDG_RUNTIME_DIR:-/run/user/$(id -u)}"

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Configura o Docker rootless para $USER:
  - instala o que falta (uidmap, dbus-user-session,
    docker-ce-rootless-extras) e cria subuid/subgid se não houver
  - roda dockerd-rootless-setuptool.sh install
  - libera a porta 80 para processos sem root (sysctl), porque o
    compose do framework publica 80:80
  - mantém o daemon ligado sem sessão aberta (loginctl enable-linger)
  - exporta DOCKER_HOST no ~/.bashrc e ~/.zshrc

Precisa do docker instalado (scripts/install-docker.sh) e de sudo
para os passos de sistema. Rode scripts/check-requirements.sh antes
para ver se o rootless é viável aqui.

ATENÇÃO (testado): com o Docker rootless, './run composer install'
do framework FALHA ("/app/vendor could not be created"), porque o
./run usa 'docker run -u \$(id -u)' e em rootless esse uid não é o
dono do bind mount. O framework, sem modificação, só roda no Docker
rootful (usuário no grupo docker). Use este script só se precisar
do rootless por outro motivo.

  -h  mostra esta ajuda
  -z  desfaz tudo que este script fez (daemon rootless, dados em
      ~/.local/share/docker, sysctl, linger, DOCKER_HOST, pacotes)
EOF
}

rootful_accessible() {
  [[ -S /var/run/docker.sock ]] \
    && docker -H unix:///var/run/docker.sock info >/dev/null 2>&1
}

subid_has_user() { # arquivo
  grep -qE "^($USER|$(id -u)):" "$1" 2>/dev/null
}

# Primeiro início livre acima de todas as faixas já usadas.
next_subid_start() { # arquivo
  awk -F: 'BEGIN {m=100000} {e=$2+$3; if (e>m) m=e} END {print m}' \
    "$1" 2>/dev/null
}

ensure_packages() {
  step "Pacotes necessários"
  local missing
  missing="$(apt_missing uidmap dbus-user-session)"
  if ! command -v dockerd-rootless-setuptool.sh >/dev/null 2>&1; then
    if apt_installed docker-ce; then
      missing="${missing:+$missing }docker-ce-rootless-extras"
    else
      die "dockerd-rootless-setuptool.sh não existe e o docker aqui não" \
        "é o Docker CE; instale o rootless à mão (docs.docker.com)."
    fi
  fi
  if [[ -z "$missing" ]]; then
    ok "uidmap, dbus-user-session e rootless-extras presentes"
    return 0
  fi
  require_sudo
  apt_update_once
  # shellcheck disable=SC2086
  apt_install_tracked installed_packages $missing
  ok "instalado: $missing"
}

ensure_subids() {
  step "subuid/subgid"
  if subid_has_user /etc/subuid && subid_has_user /etc/subgid; then
    ok "$USER já tem faixas em /etc/subuid e /etc/subgid"
    return 0
  fi
  require_sudo
  local start
  start="$(next_subid_start /etc/subuid)"
  local start_g
  start_g="$(next_subid_start /etc/subgid)"
  (( start_g > start )) && start="$start_g"
  local end=$((start + 65535))
  sudo usermod --add-subuids "$start-$end" --add-subgids "$start-$end" "$USER"
  state_set subids_added "$start-$end"
  ok "faixa $start-$end criada para $USER"
}

ensure_apparmor() {
  local f=/proc/sys/kernel/apparmor_restrict_unprivileged_userns
  [[ -r $f && "$(cat $f)" == 1 ]] || return 0
  step "AppArmor (Ubuntu 24.04 restringe user namespaces)"
  if compgen -G "/etc/apparmor.d/*rootlesskit*" >/dev/null; then
    ok "perfil do rootlesskit já existe"
    return 0
  fi
  local bin profile
  bin="$(command -v rootlesskit)" || die "rootlesskit não encontrado."
  profile="/etc/apparmor.d/$(echo "$bin" | sed -e 's@^/@@' -e 's@/@.@g')"
  require_sudo
  sudo tee "$profile" >/dev/null <<EOF
abi <abi/4.0>,
include <tunables/global>

"$bin" flags=(unconfined) {
  userns,

  include if exists <local/$(basename "$profile")>
}
EOF
  sudo systemctl restart apparmor.service
  state_set apparmor_profile "$profile"
  ok "perfil criado em $profile"
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  [[ "$(id -u)" != 0 ]] || die "rode como seu usuário, não como root."
  need_cmd docker "Rode scripts/install-docker.sh antes."
  systemctl --user show-environment >/dev/null 2>&1 \
    || die "sem sessão systemd de usuário (XDG_RUNTIME_DIR)." \
      "Entre por login normal, não por su."

  ensure_packages
  ensure_subids
  ensure_apparmor

  step "Instalando o daemon rootless (dockerd-rootless-setuptool.sh)"
  local unit="$HOME/.config/systemd/user/docker.service"
  if [[ -f "$unit" ]]; then
    if systemctl --user is-active --quiet docker.service; then
      ok "já existe um docker.service de usuário ativo; fica (fora do -z)"
      state_set rootless_installed 0
    else
      die "existe $unit, mas o serviço não está ativo (instalação" \
        "rootless antiga ou quebrada). Limpe antes com:" \
        "dockerd-rootless-setuptool.sh uninstall -f;" \
        "rootlesskit rm -rf ~/.local/share/docker"
    fi
  else
    [[ -d "$DATA_DIR" ]] && state_set data_dir_preexisting 1 \
      || state_set data_dir_preexisting 0
    [[ -e "$HOME/.docker" ]] && state_set docker_cfg_preexisting 1 \
      || state_set docker_cfg_preexisting 0
    [[ -d "$HOME/.config/systemd/user" ]] \
      && state_set systemd_user_dir_preexisting 1 \
      || state_set systemd_user_dir_preexisting 0
    local force=()
    if rootful_accessible; then
      warn "o Docker rootful também está acessível; fica como está"
      force=(--force)
    fi
    dockerd-rootless-setuptool.sh install "${force[@]}"
    state_set rootless_installed 1
    ok "docker.service de usuário instalado"
  fi

  step "Porta 80 para processos sem root"
  local port_start
  port_start="$(sysctl -n net.ipv4.ip_unprivileged_port_start 2>/dev/null \
    || echo 1024)"
  if (( port_start <= 80 )); then
    ok "já liberada (sysctl=$port_start)"
  else
    require_sudo
    echo 'net.ipv4.ip_unprivileged_port_start=0' \
      | sudo tee "$SYSCTL_FILE" >/dev/null
    sudo sysctl --system >/dev/null
    state_set sysctl_created 1
    ok "net.ipv4.ip_unprivileged_port_start=0 em $SYSCTL_FILE"
  fi

  step "Daemon ligado mesmo sem sessão (linger)"
  local linger
  linger="$(loginctl show-user "$USER" -p Linger --value 2>/dev/null || true)"
  if [[ "$linger" == yes ]]; then
    ok "linger já ativo"
  elif sudo_available || command -v sudo >/dev/null 2>&1; then
    require_sudo
    sudo loginctl enable-linger "$USER"
    state_set linger_enabled 1
    ok "linger ativado"
  else
    warn "sem sudo: o daemon rootless para quando você sai da sessão"
  fi

  step "DOCKER_HOST nos arquivos rc"
  local content="export DOCKER_HOST=unix://$RUNTIME_DIR/docker.sock"
  rc_block_add "$HOME/.bashrc" docker-rootless "$content"
  state_set rc_bashrc 1
  if [[ -f "$HOME/.zshrc" ]]; then
    rc_block_add "$HOME/.zshrc" docker-rootless "$content"
    state_set rc_zshrc 1
  fi
  ok "$content"

  step "Teste"
  export DOCKER_HOST="unix://$RUNTIME_DIR/docker.sock"
  if docker info --format '{{.SecurityOptions}}' 2>/dev/null \
      | grep -q rootless; then
    ok "docker responde em modo rootless"
  else
    warn "docker ainda não respondeu; tente: systemctl --user restart docker"
  fi

  state_mark_done
  info
  info "Abra um terminal novo (para o DOCKER_HOST valer) e rode:"
  info "  scripts/check-requirements.sh"
}

undo() {
  undo_or_nothing

  if [[ "$(state_get rootless_installed)" == 1 ]]; then
    step "Removendo o daemon rootless"
    export DOCKER_HOST="unix://$RUNTIME_DIR/docker.sock"
    local force=()
    rootful_accessible && force=(--force)
    dockerd-rootless-setuptool.sh uninstall "${force[@]}" || true
    # Garantia extra: serviço parado e unidade removida.
    systemctl --user disable --now docker.service >/dev/null 2>&1 || true
    rm -f "$HOME/.config/systemd/user/docker.service"
    systemctl --user daemon-reload >/dev/null 2>&1 || true
    unset DOCKER_HOST
    docker context use default >/dev/null 2>&1 || true
    docker context rm -f rootless >/dev/null 2>&1 || true
    ok "docker.service de usuário removido"
    if [[ "$(state_get data_dir_preexisting)" == 0 && -d "$DATA_DIR" ]]; then
      if command -v rootlesskit >/dev/null 2>&1; then
        rootlesskit rm -rf "$DATA_DIR" || rm -rf "$DATA_DIR" || true
      else
        rm -rf "$DATA_DIR" || true
      fi
      ok "dados em $DATA_DIR removidos"
    fi
    if [[ "$(state_get docker_cfg_preexisting)" == 0 ]]; then
      rm -rf "$HOME/.docker"
    fi
    # Diretórios vazios que o setuptool/systemd deixam para trás.
    rmdir "$HOME/.config/systemd/user/default.target.wants" 2>/dev/null \
      || true
    if [[ "$(state_get systemd_user_dir_preexisting)" == 0 ]]; then
      rmdir "$HOME/.config/systemd/user" "$HOME/.config/systemd" \
        "$HOME/.config" 2>/dev/null || true
    fi
    rmdir "$HOME/.local/share" 2>/dev/null || true
  fi

  step "Arquivos rc"
  rc_block_remove "$HOME/.bashrc" docker-rootless
  rc_block_remove "$HOME/.zshrc" docker-rootless
  ok "DOCKER_HOST removido do ~/.bashrc e ~/.zshrc"

  if [[ "$(state_get sysctl_created)" == 1 ]]; then
    require_sudo
    sudo rm -f "$SYSCTL_FILE"
    sudo sysctl -w net.ipv4.ip_unprivileged_port_start=1024 >/dev/null
    sudo sysctl --system >/dev/null
    ok "sysctl restaurado"
  fi

  if [[ "$(state_get linger_enabled)" == 1 ]]; then
    require_sudo
    sudo loginctl disable-linger "$USER"
    ok "linger desativado"
  fi

  if [[ -n "$(state_get apparmor_profile)" ]]; then
    require_sudo
    sudo rm -f "$(state_get apparmor_profile)"
    sudo systemctl restart apparmor.service || true
    ok "perfil AppArmor removido"
  fi

  if [[ -n "$(state_get subids_added)" ]]; then
    require_sudo
    local range
    range="$(state_get subids_added)"
    sudo usermod --del-subuids "$range" --del-subgids "$range" "$USER"
    ok "faixa $range removida"
  fi

  local pkgs
  pkgs="$(state_get installed_packages)"
  if [[ -n "$pkgs" ]]; then
    require_sudo
    step "Removendo pacotes (e dependências que vieram junto): $pkgs"
    apt_purge_safe "$pkgs"
  fi

  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
