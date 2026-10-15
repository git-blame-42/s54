#!/usr/bin/env bash
# common.sh: funções compartilhadas pelos scripts de setup.
# Não execute este arquivo; os outros scripts o carregam com `source`.
#
# Convenções:
# - Cada script grava o que fez em $S50_STATE_DIR/<script>.state
#   (linhas chave=valor). O `-z` lê esse arquivo e desfaz só o que
#   está registrado; o que já existia na máquina não é tocado.
# - Blocos inseridos em ~/.bashrc e ~/.zshrc ficam entre marcadores
#   "# >>> s50-setup:<id> >>>" e "# <<< s50-setup:<id> <<<".

set -euo pipefail

USER="${USER:-$(id -un)}"
HOME="${HOME:-$(getent passwd "$USER" | cut -d: -f6)}"
S50_STATE_DIR="${S50_STATE_DIR:-$HOME/.local/state/s50-setup}"
SCRIPT_PATH="${BASH_SOURCE[1]}"
SCRIPT_NAME="$(basename "$SCRIPT_PATH" .sh)"
STATE_FILE="$S50_STATE_DIR/$SCRIPT_NAME.state"

# --- saída -------------------------------------------------------------

if [[ -t 1 ]]; then
  C_RED=$'\e[31m' C_GREEN=$'\e[32m' C_YELLOW=$'\e[33m'
  C_BOLD=$'\e[1m' C_OFF=$'\e[0m'
else
  C_RED='' C_GREEN='' C_YELLOW='' C_BOLD='' C_OFF=''
fi

info() { printf '%s\n' "$*"; }
step() { printf '\n%s==> %s%s\n' "$C_BOLD" "$*" "$C_OFF"; }
ok()   { printf '%s[OK]%s %s\n' "$C_GREEN" "$C_OFF" "$*"; }
warn() { printf '%s[AVISO]%s %s\n' "$C_YELLOW" "$C_OFF" "$*" >&2; }
fail() { printf '%s[FALHA]%s %s\n' "$C_RED" "$C_OFF" "$*" >&2; }
die()  { fail "$@"; exit 1; }

# --- flags -------------------------------------------------------------

# parse_flags "$@": aceita só -h e -z. Define ACTION=run ou ACTION=undo.
# O script precisa definir `usage` antes de chamar.
parse_flags() {
  export ACTION=run
  local opt
  while getopts ':hz' opt; do
    case "$opt" in
      h) usage; exit 0 ;;
      z) ACTION=undo ;;
      *) usage >&2; die "opção inválida: -$OPTARG" ;;
    esac
  done
  shift $((OPTIND - 1))
  [[ $# -eq 0 ]] || { usage >&2; die "argumentos inesperados: $*"; }
}

# --- estado ------------------------------------------------------------

state_exists() { [[ -f "$STATE_FILE" ]]; }

state_get() { # chave -> valor (vazio se não existe)
  [[ -f "$STATE_FILE" ]] || return 0
  sed -n "s/^$1=//p" "$STATE_FILE" | tail -n 1
}

state_set() { # chave valor
  mkdir -p "$S50_STATE_DIR"
  touch "$STATE_FILE"
  local tmp
  tmp="$(mktemp)"
  grep -v "^$1=" "$STATE_FILE" > "$tmp" || true
  printf '%s=%s\n' "$1" "$2" >> "$tmp"
  cat "$tmp" > "$STATE_FILE"
  rm -f "$tmp"
}

state_remove() {
  rm -f "$STATE_FILE"
  # Diretórios vazios que só existiam por causa do estado.
  rmdir "$S50_STATE_DIR" "$HOME/.local/state" "$HOME/.local" 2>/dev/null \
    || true
}

state_mark_done() {
  state_set finished_at "$(date '+%Y-%m-%d %H:%M')"
  state_set completed 1
}

# Sai se o script já concluiu antes. Se começou e não terminou, avisa
# e segue (cada etapa é idempotente).
refuse_if_done() {
  if [[ "$(state_get completed)" == 1 ]]; then
    info "Este script já foi executado em $(state_get finished_at)."
    info "Nada a fazer. Para desfazer: $SCRIPT_PATH -z"
    exit 0
  fi
  if state_exists; then
    warn "Execução anterior não concluída; continuando de onde parou."
  fi
}

# Chamada no início do -z. Sai se o script nunca rodou.
undo_or_nothing() {
  if ! state_exists; then
    info "Este script não rodou nesta máquina (sem $STATE_FILE)."
    info "Nada a desfazer."
    exit 0
  fi
}

# --- sistema -----------------------------------------------------------

need_cmd() { # cmd [dica]
  command -v "$1" >/dev/null 2>&1 && return 0
  die "comando '$1' não encontrado.${2:+ $2}"
}

warn_if_not_ubuntu_2404() {
  local id='' ver=''
  if [[ -r /etc/os-release ]]; then
    # shellcheck disable=SC1091
    id="$(. /etc/os-release && echo "${ID:-}")"
    ver="$(. /etc/os-release && echo "${VERSION_ID:-}")"
  fi
  if [[ "$id" != ubuntu || "$ver" != 24.04 ]]; then
    warn "Testado no Ubuntu 24.04; aqui: ${id:-?} ${ver:-?}. Seguindo."
  fi
}

sudo_available() { # sem pedir senha
  command -v sudo >/dev/null 2>&1 && sudo -n true 2>/dev/null
}

# Retorna 0 se dá para usar sudo: sem senha, ou pedindo a senha no
# terminal (uma vez). Não encerra o script.
sudo_ok() {
  command -v sudo >/dev/null 2>&1 || return 1
  sudo_available && return 0
  [[ -t 0 ]] || return 1
  sudo -v
}

# Como sudo_ok, mas encerra com mensagem clara se não der.
require_sudo() {
  command -v sudo >/dev/null 2>&1 || die "sudo não está instalado."
  sudo_ok || die "esta etapa precisa de sudo e você não tem permissão" \
    "(ou não há terminal para digitar a senha)."
}

apt_installed() { # pacote
  dpkg-query -W -f '${Status}' "$1" 2>/dev/null | grep -q 'ok installed'
}

apt_update_once() {
  [[ -n "${_APT_UPDATED:-}" ]] && return 0
  sudo apt-get update -q
  _APT_UPDATED=1
}

apt_install() { # pacotes...
  sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -q "$@"
}

# Pacotes instalados, um por linha, sem sufixo de arquitetura (para
# medir o que o apt acrescenta e comparar com a simulação de purge).
installed_packages_list() {
  dpkg-query -W -f '${Package} ${Status}\n' 2>/dev/null \
    | awk '$NF == "installed" {print $1}' | sort -u
}

# Instala pacotes e acumula no estado, na chave dada, TODOS os pacotes
# novos: os pedidos e as dependências que vieram junto. Assim o -z
# devolve a máquina ao que era, sem precisar de autoremove.
apt_install_tracked() { # chave pacotes...
  local key="$1" before after new current
  shift
  before="$(installed_packages_list)"
  apt_install "$@"
  after="$(installed_packages_list)"
  new="$(comm -13 <(echo "$before") <(echo "$after") | tr '\n' ' ')"
  new="${new% }"
  current="$(state_get "$key")"
  state_set "$key" "${current:+$current }$new"
}

# Lista o que o apt removeria (simulação) ao fazer purge dos pacotes.
apt_purge_simulate() { # pacotes...
  sudo apt-get -s purge "$@" 2>/dev/null \
    | awk '/^(Purg|Remv) /{print $2}' | sed 's/:[a-z0-9]*$//' | sort -u
}

# Purge só dos pacotes da lista. Se o apt fosse arrastar algo de fora
# da lista (outra coisa passou a depender deles), mantém esse pacote
# e avisa. Pacotes já ausentes são ignorados.
apt_purge_safe() { # pacotes (string separada por espaço)
  local p keep=() wanted extra ok_list=()
  for p in $1; do apt_installed "$p" && keep+=("$p"); done
  (( ${#keep[@]} )) || return 0
  wanted="$(printf '%s\n' "${keep[@]}" | sort -u)"
  extra="$(comm -23 <(apt_purge_simulate "${keep[@]}") <(echo "$wanted"))"
  if [[ -n "$extra" ]]; then
    for p in "${keep[@]}"; do
      extra="$(comm -23 <(apt_purge_simulate "$p") <(echo "$wanted"))"
      if [[ -z "$extra" ]]; then
        ok_list+=("$p")
      else
        warn "mantendo $p: removê-lo levaria junto $(echo "$extra" | xargs)"
      fi
    done
    keep=("${ok_list[@]}")
  fi
  (( ${#keep[@]} )) || return 0
  apt_purge "${keep[@]}"
}

apt_purge() { # pacotes...
  sudo DEBIAN_FRONTEND=noninteractive apt-get purge -y -q "$@"
}

# Lista, entre os pacotes dados, os que ainda NÃO estão instalados.
apt_missing() { # pacotes... -> lista separada por espaço
  local p out=()
  for p in "$@"; do apt_installed "$p" || out+=("$p"); done
  echo "${out[*]:-}"
}

# --- blocos em arquivos rc ---------------------------------------------

rc_block_remove() { # arquivo id
  local file="$1" id="$2" tmp
  [[ -f "$file" ]] || return 0
  tmp="$(mktemp)"
  awk -v s="# >>> s50-setup:$id >>>" -v e="# <<< s50-setup:$id <<<" '
    $0 == s { skip = 1; next }
    $0 == e { skip = 0; next }
    !skip   { print }
  ' "$file" > "$tmp"
  cat "$tmp" > "$file"
  rm -f "$tmp"
}

rc_block_add() { # arquivo id conteudo
  local file="$1" id="$2" content="$3"
  rc_block_remove "$file" "$id"
  {
    printf '\n# >>> s50-setup:%s >>>\n' "$id"
    printf '%s\n' "$content"
    printf '# <<< s50-setup:%s <<<\n' "$id"
  } >> "$file"
}

# --- docker ------------------------------------------------------------

docker_usable() { docker info >/dev/null 2>&1; }

docker_compose_v2() { docker compose version >/dev/null 2>&1; }

docker_image_exists() { # imagem
  docker image inspect "$1" >/dev/null 2>&1
}

# Espera o MySQL do compose aceitar conexões TCP (o servidor temporário
# da inicialização só escuta no socket, por isso -h127.0.0.1).
wait_for_mysql() { # [segundos]
  local limit="${1:-180}" i
  for ((i = 0; i < limit; i += 3)); do
    if docker compose exec -T db \
        mysqladmin ping -h127.0.0.1 -uroot --silent >/dev/null 2>&1; then
      return 0
    fi
    sleep 3
  done
  return 1
}

# Lista as portas TCP em escuta, uma por linha.
listening_ports() {
  if command -v ss >/dev/null 2>&1; then
    ss -Hltn 2>/dev/null | awk '{print $4}' | sed 's/.*://' | sort -un
  elif command -v netstat >/dev/null 2>&1; then
    netstat -ltn 2>/dev/null | awk 'NR>2 {print $4}' | sed 's/.*://' \
      | sort -un
  fi
}

port_in_use() { # porta
  listening_ports | grep -qx "$1"
}

# --- framework da disciplina ------------------------------------------

# Imagens que o compose do framework puxa (fora a que ele constrói).
FRAMEWORK_IMAGES=(nginx:latest mysql:8.4.0
  selenium/standalone-chrome-debug:latest composer:2.7.2)

# Registra quais dessas imagens já existiam, para o -z não removê-las.
record_preexisting_images() {
  local img have=()
  for img in "${FRAMEWORK_IMAGES[@]}"; do
    docker_image_exists "$img" && have+=("$img")
  done
  state_set preexisting_images "${have[*]:-}"
}

# Derruba o compose do diretório (containers, rede, volumes), apaga as
# imagens construídas e as puxadas que não existiam antes.
compose_teardown() { # dir
  local dir="$1" img pre
  [[ -f "$dir/docker-compose.yml" ]] || return 0
  (
    cd "$dir"
    [[ -f .env ]] || cp .env.example .env
    docker compose down -v --rmi local --remove-orphans
  ) || true
  pre=" $(state_get preexisting_images) "
  for img in "${FRAMEWORK_IMAGES[@]}"; do
    [[ "$pre" == *" $img "* ]] && continue
    docker image rm -f "$img" >/dev/null 2>&1 || true
  done
}

STEP_RESULTS=()

# run_step "nome" comando args...: executa, mede o tempo e guarda o
# resultado para o resumo final. Retorna o status do comando.
run_step() {
  local name="$1" start=$SECONDS
  shift
  step "$name"
  if "$@"; then
    STEP_RESULTS+=("OK     $((SECONDS - start))s  $name")
    return 0
  fi
  STEP_RESULTS+=("FALHA  $((SECONDS - start))s  $name")
  return 1
}

print_step_results() {
  step "Resultado"
  printf '  %s\n' "${STEP_RESULTS[@]}"
}

check_http() { # url codigos-aceitos...
  local url="$1" code e
  shift
  code="$(curl -s -o /dev/null -w '%{http_code}' -m 15 "$url" || true)"
  for e in "$@"; do
    if [[ "$code" == "$e" ]]; then
      ok "$url -> HTTP $code"
      return 0
    fi
  done
  fail "$url -> HTTP ${code:-sem resposta} (esperado: $*)"
  return 1
}

ensure_env_file() { [[ -f .env ]] || cp .env.example .env; }

# Sobe o framework no diretório atual (já clonado): .env, composer,
# compose up, banco, populate e uma requisição HTTP. Para no primeiro
# erro e retorna 1.
framework_bootstrap() {
  run_step "Arquivo .env" ensure_env_file || return 1
  run_step "./run composer install" ./run composer install || return 1
  run_step "./run up -d" ./run up -d || return 1
  run_step "Esperando o MySQL aceitar conexões" wait_for_mysql || return 1
  run_step "./run db:reset" ./run db:reset || return 1
  run_step "./run db:populate" ./run db:populate || return 1
  run_step "Aplicação responde em http://localhost" \
    check_http http://localhost/ 200 302 || return 1
}

require_docker_ready() {
  need_cmd git "Rode scripts/install-tools.sh antes (ou: sudo apt install git)."
  need_cmd curl "Rode scripts/install-tools.sh antes."
  need_cmd docker "Rode scripts/install-docker.sh antes."
  docker_usable || die "o docker não responde sem sudo." \
    "Rode scripts/check-requirements.sh para ver o motivo."
  docker_compose_v2 || die "'docker compose' v2 ausente." \
    "Rode scripts/install-docker.sh."
}

require_free_ports() { # portas...
  local p busy=()
  for p in "$@"; do port_in_use "$p" && busy+=("$p"); done
  (( ${#busy[@]} == 0 )) || die "portas em uso: ${busy[*]}." \
    "Pare quem as usa (sudo ss -ltnp) ou derrube outro compose."
}

# Remove um diretório que pode ter arquivos criados por containers
# (dono root). Tenta rm; se falhar, apaga de dentro de um container.
remove_dir_maybe_root() { # dir [imagem]
  local dir="$1" image="${2:-nginx:latest}" parent base
  [[ -e "$dir" ]] || return 0
  rm -rf "$dir" 2>/dev/null && return 0
  parent="$(cd "$(dirname "$dir")" && pwd)"
  base="$(basename "$dir")"
  if docker_usable && docker_image_exists "$image"; then
    docker run --rm -v "$parent:/target" "$image" rm -rf "/target/$base"
  fi
  if [[ -e "$dir" ]] && sudo_ok; then
    sudo rm -rf "$dir"
  fi
  [[ ! -e "$dir" ]] || die "não consegui remover $dir; remova à mão."
}
