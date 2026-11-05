#!/usr/bin/env bash
# 05-check-requirements.sh: confere se a máquina roda o framework da
# disciplina (Docker + Compose v2) e se o Docker rootless é viável.
# Só lê; não altera nada.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

PORTS=(80 8080 4444 5900)
MIN_RAM_MB=4000
MIN_DISK_GB=10

usage() {
  cat <<EOF
uso: $(basename "$0") [-h]

Verifica, sem alterar nada:
  - sistema: Ubuntu 24.04, memória, disco, sudo
  - ferramentas: git, curl, bash
  - docker: cliente, daemon acessível sem sudo, compose v2, modo
  - docker rootless: pré-requisitos e se é viável nesta máquina
  - portas livres que o framework publica: ${PORTS[*]}
  - rede: GitHub, Docker Hub, repositório apt da Docker

Sai com 0 se não houve FALHA (avisos não contam).
Não tem -z: este script não faz alterações.
EOF
}

PASS=0 WARNS=0 FAILS=0
c_ok()   { ok "$*";   PASS=$((PASS + 1)); }
c_warn() { warn "$*"; WARNS=$((WARNS + 1)); }
c_fail() { fail "$*"; FAILS=$((FAILS + 1)); }

check_system() {
  step "Sistema"
  local id='' ver='' pretty=''
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    id="$(. /etc/os-release && echo "${ID:-}")"
    ver="$(. /etc/os-release && echo "${VERSION_ID:-}")"
    pretty="$(. /etc/os-release && echo "${PRETTY_NAME:-}")"
  fi
  if [[ "$id" == ubuntu && "$ver" == 24.04 ]]; then
    c_ok "sistema: $pretty"
  else
    c_warn "sistema: ${pretty:-desconhecido} (esperado Ubuntu 24.04)"
  fi
  info "arquitetura: $(uname -m), kernel $(uname -r)"

  local mem_mb
  mem_mb="$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)"
  if (( mem_mb >= MIN_RAM_MB )); then
    c_ok "memória: ${mem_mb} MB"
  else
    c_warn "memória: ${mem_mb} MB (MySQL + Selenium pedem ~${MIN_RAM_MB})"
  fi

  local free_home free_var
  free_home="$(df -BG --output=avail "$HOME" | tail -1 | tr -dc '0-9')"
  free_var="$(df -BG --output=avail /var/lib | tail -1 | tr -dc '0-9')"
  if (( free_home >= MIN_DISK_GB && free_var >= MIN_DISK_GB )); then
    c_ok "disco livre: ${free_home} GB em \$HOME, ${free_var} GB em /var/lib"
  else
    c_warn "disco livre: ${free_home} GB em \$HOME, ${free_var} GB em" \
      "/var/lib (imagens ocupam alguns GB; mínimo ${MIN_DISK_GB})"
  fi

  if ! command -v sudo >/dev/null 2>&1; then
    c_warn "sudo não instalado: 01-install-tools.sh e 06-install-docker.sh" \
      "não vão rodar"
  elif sudo -n true 2>/dev/null; then
    c_ok "sudo: disponível sem senha"
  elif sudo -n -l 2>&1 | grep -qi 'password'; then
    c_ok "sudo: disponível (vai pedir senha)"
  else
    c_warn "sudo: não consegui confirmar; os scripts de instalação" \
      "vão testar"
  fi
}

check_tools() {
  step "Ferramentas básicas"
  local t
  for t in git curl bash; do
    if command -v "$t" >/dev/null 2>&1; then
      c_ok "$t: $(command -v "$t")"
    else
      c_fail "$t não encontrado (sudo apt install $t)"
    fi
  done
  if (( BASH_VERSINFO[0] < 4 )); then
    c_fail "bash ${BASH_VERSION}: o ./run do framework precisa de bash 4+"
  fi
}

DOCKER_MODE=none   # none | rootful | rootless

check_docker() {
  step "Docker"
  if ! command -v docker >/dev/null 2>&1; then
    c_fail "docker não instalado. Rode: scripts/06-install-docker.sh"
    return 0
  fi
  c_ok "cliente: $(docker --version 2>/dev/null)"

  if docker_compose_v2; then
    c_ok "compose v2: $(docker compose version --short 2>/dev/null)"
  else
    c_fail "'docker compose' (v2) ausente; o ./run do framework exige." \
      "Rode: scripts/06-install-docker.sh"
  fi

  if docker_usable; then
    local sec
    sec="$(docker info --format '{{.SecurityOptions}}' 2>/dev/null)"
    if [[ "$sec" == *rootless* ]]; then
      DOCKER_MODE=rootless
      c_ok "daemon acessível sem sudo (modo ROOTLESS)"
    else
      DOCKER_MODE=rootful
      c_ok "daemon acessível sem sudo (modo rootful)"
    fi
    info "servidor: $(docker info --format '{{.ServerVersion}}' 2>/dev/null)," \
      "driver $(docker info --format '{{.Driver}}' 2>/dev/null)," \
      "cgroup $(docker info --format '{{.CgroupVersion}}' 2>/dev/null)"
    return 0
  fi

  # Daemon não responde: por quê?
  if [[ -S /var/run/docker.sock ]]; then
    if id -nG "$USER" | grep -qw docker; then
      c_fail "daemon ativo, você está no grupo docker, mas a sessão não" \
        "recarregou os grupos. Faça logout/login ou: newgrp docker"
    elif getent group docker >/dev/null; then
      c_fail "daemon ativo, mas você não está no grupo docker." \
        "Rode: scripts/06-install-docker.sh (adiciona ao grupo)"
    else
      c_fail "daemon ativo, mas não há grupo docker." \
        "Rode: scripts/06-install-docker.sh"
    fi
  elif [[ -n "${DOCKER_HOST:-}" ]]; then
    c_fail "DOCKER_HOST=$DOCKER_HOST não responde." \
      "Rootless parado? systemctl --user start docker"
  else
    c_fail "docker instalado, mas o daemon não responde." \
      "Rootful: sudo systemctl start docker." \
      "Rootless: systemctl --user start docker"
  fi
}

subid_ok() { # arquivo
  local line count
  line="$(grep -E "^($USER|$(id -u)):" "$1" 2>/dev/null | head -1)"
  [[ -n "$line" ]] || return 1
  count="$(echo "$line" | cut -d: -f3)"
  (( count >= 65536 ))
}

# Impedimento do rootless. Ele é opcional (o framework não roda nele),
# então só conta como FALHA quando não há docker rootful funcionando.
# Mexe em "blockers" da check_rootless (escopo dinâmico do bash).
r_block() {
  blockers=1
  if [[ "$DOCKER_MODE" == rootful ]]; then
    c_warn "$@" "(impede só o rootless)"
  else
    c_fail "$@"
  fi
}

check_rootless() {
  step "Docker rootless: é viável aqui?"
  local blockers=0 needs_sudo=0

  if command -v dockerd-rootless-setuptool.sh >/dev/null 2>&1; then
    c_ok "dockerd-rootless-setuptool.sh presente (docker-ce-rootless-extras)"
  else
    c_warn "docker-ce-rootless-extras ausente (06-install-docker.sh instala)"
    needs_sudo=1
  fi

  if command -v newuidmap >/dev/null 2>&1 \
      && command -v newgidmap >/dev/null 2>&1; then
    c_ok "newuidmap/newgidmap presentes (pacote uidmap)"
  else
    c_warn "pacote uidmap ausente (13-setup-docker-rootless.sh instala)"
    needs_sudo=1
  fi

  if subid_ok /etc/subuid && subid_ok /etc/subgid; then
    c_ok "/etc/subuid e /etc/subgid têm >= 65536 ids para $USER"
  else
    c_warn "sem faixa de subuid/subgid para $USER" \
      "(13-setup-docker-rootless.sh cria, com sudo)"
    needs_sudo=1
  fi

  if [[ -n "${XDG_RUNTIME_DIR:-}" ]] \
      && systemctl --user show-environment >/dev/null 2>&1; then
    c_ok "systemd de usuário ativo (XDG_RUNTIME_DIR=$XDG_RUNTIME_DIR)"
  else
    r_block "sem sessão systemd de usuário; rootless precisa dela." \
      "Entre por login normal (ssh/terminal), não por su."
  fi

  local f=/proc/sys/kernel/unprivileged_userns_clone
  if [[ -r $f ]] && [[ "$(cat $f)" != 1 ]]; then
    r_block "kernel.unprivileged_userns_clone=0: user namespaces bloqueados"
  else
    c_ok "user namespaces sem privilégio: permitidos pelo kernel"
  fi
  f=/proc/sys/user/max_user_namespaces
  if [[ -r $f ]] && (( $(cat $f) == 0 )); then
    r_block "user.max_user_namespaces=0: user namespaces desligados"
  fi

  f=/proc/sys/kernel/apparmor_restrict_unprivileged_userns
  if [[ -r $f ]] && [[ "$(cat $f)" == 1 ]]; then
    if compgen -G "/etc/apparmor.d/*rootlesskit*" >/dev/null; then
      c_ok "AppArmor restringe userns, mas há perfil para o rootlesskit"
    else
      c_warn "Ubuntu 24.04 restringe userns via AppArmor; falta o perfil" \
        "do rootlesskit (vem no pacote docker-ce-rootless-extras)"
      needs_sudo=1
    fi
  else
    c_ok "AppArmor não restringe user namespaces"
  fi

  local port_start
  port_start="$(sysctl -n net.ipv4.ip_unprivileged_port_start 2>/dev/null \
    || echo 1024)"
  if (( port_start <= 80 )); then
    c_ok "porta 80 liberada para processos sem root (sysctl=$port_start)"
  else
    c_warn "net.ipv4.ip_unprivileged_port_start=$port_start: em rootless" \
      "o compose não consegue publicar a porta 80" \
      "(13-setup-docker-rootless.sh ajusta, com sudo)"
    needs_sudo=1
  fi

  if [[ "$(stat -fc %T /sys/fs/cgroup 2>/dev/null)" == cgroup2fs ]]; then
    c_ok "cgroup v2"
  else
    c_warn "cgroup v1: rootless funciona, mas sem limites de recursos"
  fi

  info
  if [[ "$DOCKER_MODE" == rootless ]]; then
    c_warn "o Docker está em modo ROOTLESS para este usuário: o" \
      "'./run composer install' do framework vai falhar (uid do bind" \
      "mount). Use o rootful: 13-setup-docker-rootless.sh -z e grupo docker."
  elif (( blockers )); then
    info "Veredito: rootless NÃO é viável aqui (veja os itens acima)."
  elif (( needs_sudo )); then
    info "Veredito: rootless seria viável (faltam passos com sudo)."
  else
    info "Veredito: rootless é viável (scripts/13-setup-docker-rootless.sh)."
  fi
  info "Mas atenção (testado): o framework, sem modificação, NÃO roda em" \
    "rootless: './run composer install' falha porque o ./run usa" \
    "'docker run -u \$(id -u)'. Use o Docker rootful (grupo docker)."
}

check_ports() {
  step "Portas que o framework publica"
  local p busy=()
  for p in "${PORTS[@]}"; do
    if port_in_use "$p"; then busy+=("$p"); fi
  done
  if (( ${#busy[@]} == 0 )); then
    c_ok "portas livres: ${PORTS[*]}"
  else
    c_fail "portas em uso: ${busy[*]} (o docker compose up vai falhar)." \
      "Veja quem usa: sudo ss -ltnp"
  fi
}

check_network() {
  step "Rede"
  local url
  for url in https://github.com https://registry-1.docker.io/v2/ \
      https://download.docker.com; do
    if curl -sS -m 10 -o /dev/null "$url" 2>/dev/null; then
      c_ok "alcançável: $url"
    else
      c_fail "sem resposta: $url"
    fi
  done
}

summary() {
  step "Resumo"
  info "OK: $PASS   AVISO: $WARNS   FALHA: $FAILS"
  if (( FAILS == 0 )); then
    info "Pronto para o framework. Teste de verdade:"
    info "  scripts/07-test-framework.sh"
    return 0
  fi
  info "Corrija as FALHAs e rode de novo."
  return 1
}

run() {
  check_system
  check_tools
  check_docker
  check_rootless
  check_ports
  check_network
  summary
}

# Só -h; este script não altera nada, então não há -z.
while getopts ':h' opt; do
  case "$opt" in
    h) usage; exit 0 ;;
    *) usage >&2; die "opção inválida: -$OPTARG (este script só tem -h)" ;;
  esac
done
shift $((OPTIND - 1))
[[ $# -eq 0 ]] || { usage >&2; die "argumentos inesperados: $*"; }
run
