#!/usr/bin/env bash
# 06-install-docker.sh: instala o Docker Engine (CE) com Compose v2 pelo
# repositório oficial da Docker e coloca o usuário no grupo docker.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

DOCKER_PKGS=(docker-ce docker-ce-cli containerd.io docker-buildx-plugin
  docker-compose-plugin docker-ce-rootless-extras)
CONFLICTS=(docker.io docker-compose docker-compose-v2 docker-doc
  docker-buildx podman-docker containerd runc)
KEYRING=/etc/apt/keyrings/docker.asc
SOURCES=/etc/apt/sources.list.d/docker.sources
GPG_URL=https://download.docker.com/linux/ubuntu/gpg

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Se o docker NÃO existe: adiciona o repositório apt da Docker e instala
${DOCKER_PKGS[*]}.
Se o docker JÁ existe: não mexe nele; só garante o 'docker compose' v2.
Em ambos os casos coloca seu usuário no grupo docker (precisa de
logout/login depois). Precisa de sudo.

Tudo que o script fez fica registrado; o -z desfaz só isso. Um docker
que já estava na máquina nunca é removido.

  -h  mostra esta ajuda
  -z  desfaz: tira do grupo, remove pacotes/repositório que o script
      instalou e, se o docker não existia antes, apaga /var/lib/docker
      e o grupo docker (se ficou vazio)
EOF
}

install_from_docker_repo() {
  local conflicting='' p missing
  for p in "${CONFLICTS[@]}"; do
    apt_installed "$p" && conflicting="${conflicting:+$conflicting }$p"
  done
  if [[ -n "$conflicting" ]]; then
    die "pacotes que conflitam com o Docker CE já estão instalados:" \
      "$conflicting. Não removo o que não instalei; decida à mão" \
      "(sudo apt remove $conflicting) e rode de novo."
  fi

  step "Preparando o repositório apt da Docker"
  require_sudo
  apt_update_once
  missing="$(apt_missing ca-certificates curl)"
  if [[ -n "$missing" ]]; then
    # shellcheck disable=SC2086
    apt_install_tracked installed_packages $missing
  fi

  if [[ ! -f "$KEYRING" ]]; then
    sudo install -m 0755 -d /etc/apt/keyrings
    sudo curl -fsSL "$GPG_URL" -o "$KEYRING"
    sudo chmod a+r "$KEYRING"
    state_set keyring_created 1
    ok "chave GPG em $KEYRING"
  fi

  if [[ ! -f "$SOURCES" ]]; then
    local codename arch
    # shellcheck disable=SC1091
    codename="$(. /etc/os-release \
      && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")"
    arch="$(dpkg --print-architecture)"
    sudo tee "$SOURCES" >/dev/null <<EOF
Types: deb
URIs: https://download.docker.com/linux/ubuntu
Suites: $codename
Components: stable
Architectures: $arch
Signed-By: $KEYRING
EOF
    state_set sources_created 1
    ok "repositório em $SOURCES ($codename, $arch)"
  fi
  sudo apt-get update -q

  step "Instalando os pacotes do Docker"
  missing="$(apt_missing "${DOCKER_PKGS[@]}")"
  # shellcheck disable=SC2086
  apt_install_tracked installed_packages $missing
  sudo systemctl enable --now docker.service >/dev/null 2>&1 || true
  ok "instalado: $(docker --version)"
}

ensure_compose_v2() {
  if docker_compose_v2; then
    ok "compose v2: $(docker compose version --short)"
    return 0
  fi
  local pkg=''
  if apt_installed docker-ce; then
    pkg=docker-compose-plugin
  elif apt_installed docker.io; then
    pkg=docker-compose-v2
  fi
  [[ -n "$pkg" ]] || die "docker existe sem 'docker compose' v2 e não sei" \
    "de que pacote veio; instale o plugin compose v2 à mão."
  step "Instalando $pkg (compose v2)"
  require_sudo
  apt_update_once
  apt_install_tracked installed_packages "$pkg"
  ok "compose v2: $(docker compose version --short)"
}

ensure_docker_group() {
  step "Grupo docker"
  if ! getent group docker >/dev/null; then
    require_sudo
    sudo groupadd docker
    state_set group_created 1
    ok "grupo docker criado"
  fi
  if id -nG "$USER" | grep -qw docker; then
    ok "$USER já está no grupo docker"
  else
    require_sudo
    sudo usermod -aG docker "$USER"
    state_set group_added 1
    ok "$USER adicionado ao grupo docker"
  fi
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  [[ "$(id -u)" != 0 ]] || die "rode como seu usuário, não como root."

  step "Situação atual"
  # O pacote docker-ce cria o grupo docker; anota antes de instalar se
  # ele já existia (e não sobrescreve ao retomar uma execução parada).
  if [[ -z "$(state_get group_preexisting)" ]]; then
    if getent group docker >/dev/null; then
      state_set group_preexisting 1
    else
      state_set group_preexisting 0
    fi
  fi
  if command -v docker >/dev/null 2>&1; then
    state_set docker_preexisting 1
    ok "docker já instalado: $(docker --version). Não será tocado."
    ensure_compose_v2
  else
    state_set docker_preexisting 0
    [[ -e "$HOME/.docker" ]] && state_set docker_cfg_preexisting 1 \
      || state_set docker_cfg_preexisting 0
    info "docker não encontrado; vou instalar pelo repositório da Docker."
    install_from_docker_repo
  fi

  ensure_docker_group

  step "Teste"
  if docker_usable; then
    ok "docker responde sem sudo nesta sessão"
  elif sg docker -c 'docker info' >/dev/null 2>&1; then
    ok "docker funciona; a sessão atual ainda não viu o grupo novo"
    warn "faça logout/login (ou rode: newgrp docker) antes de continuar"
  else
    warn "docker ainda não responde para $USER; faça logout/login e rode" \
      "scripts/05-check-requirements.sh"
  fi

  state_mark_done
  info
  info "Depois de relogar: scripts/05-check-requirements.sh"
}

confirm_default_yes() { # pergunta
  [[ -t 0 ]] || return 0
  local answer
  read -r -p "$1 [S/n] " answer
  [[ -z "$answer" || "$answer" =~ ^[sSyY] ]]
}

undo() {
  undo_or_nothing
  local pkgs
  pkgs="$(state_get installed_packages)"
  require_sudo

  if [[ "$(state_get group_added)" == 1 ]]; then
    step "Tirando $USER do grupo docker"
    sudo gpasswd -d "$USER" docker
  fi

  if [[ -n "$pkgs" ]]; then
    if [[ "$(state_get docker_preexisting)" == 0 ]]; then
      step "Parando o serviço"
      sudo systemctl disable --now docker.service docker.socket \
        containerd.service >/dev/null 2>&1 || true
    fi
    step "Removendo pacotes (e dependências que vieram junto)"
    info "$pkgs"
    apt_purge_safe "$pkgs"
  fi

  # O purge do docker-ce não remove o grupo que o pacote criou. Se o
  # grupo não existia antes deste script e ficou sem membros, sai junto.
  if getent group docker >/dev/null \
      && { [[ "$(state_get group_created)" == 1 ]] \
        || [[ "$(state_get group_preexisting)" == 0 ]]; }; then
    if [[ -z "$(getent group docker | cut -d: -f4)" ]]; then
      sudo groupdel docker || warn "não consegui remover o grupo docker"
      ok "grupo docker removido"
    else
      warn "grupo docker ainda tem membros; mantendo"
    fi
  fi

  if [[ "$(state_get docker_preexisting)" == 0 ]]; then
    if [[ "$(state_get docker_cfg_preexisting)" == 0 && -e "$HOME/.docker" ]]
    then
      rm -rf "$HOME/.docker"
      ok "pasta ~/.docker removida (não existia antes)"
    fi
    if [[ -d /var/lib/docker || -d /var/lib/containerd ]]; then
      step "Dados do Docker (imagens, containers, volumes)"
      info "O docker não existia antes deste script; /var/lib/docker e"
      info "/var/lib/containerd foram criados por ele."
      if confirm_default_yes "Apagar /var/lib/docker e /var/lib/containerd?"
      then
        sudo rm -rf /var/lib/docker /var/lib/containerd
        ok "dados removidos"
      else
        info "mantidos; para apagar depois:"
        info "  sudo rm -rf /var/lib/docker /var/lib/containerd"
      fi
    fi
  fi

  if [[ "$(state_get sources_created)" == 1 ]]; then
    sudo rm -f "$SOURCES"
    ok "repositório apt removido"
  fi
  if [[ "$(state_get keyring_created)" == 1 ]]; then
    sudo rm -f "$KEYRING"
    ok "chave GPG removida"
  fi
  if [[ "$(state_get sources_created)" == 1 ]]; then
    sudo apt-get update -q >/dev/null 2>&1 || true
  fi

  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
