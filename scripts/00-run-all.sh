#!/usr/bin/env bash
# 00-run-all.sh: roda os scripts 01 a 08 em ordem, de uma vez só.
# O 09 (docker rootless) fica de fora: é opcional e o framework não
# roda nele.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Scripts com estado, na ordem de instalação. O 05 (só verifica, não
# tem estado) roda à parte: antes do 06 só informa; depois do 06 tem
# de terminar sem FALHA.
STATEFUL=(
  01-install-tools
  02-install-tmux-conf
  03-install-ohmyzsh
  04-set-zsh-default
  06-install-docker
  07-test-framework
  08-install-eventos-do-campus
)

usage() {
  cat <<USAGE
uso: $(basename "$0") [-h] [-z]

Roda, nesta ordem: 01-install-tools, 02-install-tmux-conf,
03-install-ohmyzsh, 04-set-zsh-default, 05-check-requirements,
06-install-docker, 05-check-requirements (tem de ficar sem FALHA),
07-test-framework e 08-install-eventos-do-campus. Não roda o 09
(docker rootless).

Cada script segue as próprias regras (sudo, senha do chsh, recusa se
já concluiu). Um script que já estava concluído antes é pulado e fica
fora do -z deste. Depois do 06, o 05, o 07 e o 08 rodam dentro de
"sg docker", então não é preciso relogar no meio; faça logout/login
no fim. Se um script falhar, este para; corrija e rode de novo, que
ele continua de onde parou.

  -h  mostra esta ajuda
  -z  desfaz, na ordem inversa, só os scripts que este rodou
      (chama o -z de cada um)
USAGE
}

in_list() { # item lista
  [[ " $2 " == *" $1 "* ]]
}

list_without() { # item lista -> lista sem o item
  local x kept=""
  for x in $2; do
    [[ "$x" == "$1" ]] || kept="$kept $x"
  done
  echo "${kept# }"
}

child_completed() { # nome
  local f="$S50_STATE_DIR/${1#[0-9][0-9]-}.state"
  [[ -f "$f" ]] && grep -qx 'completed=1' "$f"
}

# Anota, só na primeira execução, o que já estava concluído antes.
record_pre_done() {
  [[ -z "$(state_get pre_done_recorded)" ]] || return 0
  local name pre=""
  for name in "${STATEFUL[@]}"; do
    if child_completed "$name"; then
      pre="$pre $name"
      info "$name já estava concluído; será pulado (fora do -z)."
    fi
  done
  state_set pre_done "${pre# }"
  state_set pre_done_recorded 1
}

plain() { "$@"; }

# Depois do 06 a sessão atual ainda não está no grupo docker. "sg
# docker" roda o comando já com o grupo, sem relogar.
with_docker_group() {
  if docker_usable; then
    "$@"
  elif sg docker -c 'docker info' >/dev/null 2>&1; then
    sg docker -c "$(printf '%q ' "$@")"
  else
    die "docker não responde para $USER nem via 'sg docker'. Faça" \
      "logout/login e rode $SCRIPT_PATH de novo (continua daqui)."
  fi
}

run_child() { # nome [plain|with_docker_group]
  local name="$1" wrapper="${2:-plain}" ran
  if in_list "$name" "$(state_get ran)"; then
    info "$name: já rodou por este script; pulando."
    return 0
  fi
  if in_list "$name" "$(state_get pre_done)"; then
    info "$name: já estava concluído antes; pulando."
    return 0
  fi
  step "$name"
  if "$wrapper" bash "$SCRIPTS_DIR/$name.sh"; then
    ran="$(state_get ran)"
    state_set ran "${ran:+$ran }$name"
    ok "$name concluído"
  else
    die "$name falhou. Corrija e rode $SCRIPT_PATH de novo: continua daqui."
  fi
}

check_requirements() { # informativo|obrigatorio
  local script="$SCRIPTS_DIR/05-check-requirements.sh"
  step "05-check-requirements ($1)"
  if [[ "$1" == obrigatorio ]]; then
    with_docker_group bash "$script" \
      || die "ainda há FALHA. Corrija e rode $SCRIPT_PATH de novo."
  elif ! bash "$script"; then
    warn "FALHAs acima são esperadas se o docker ainda não está instalado;" \
      "o 06 vem a seguir e o 05 roda de novo depois."
  fi
}

run() {
  refuse_if_done
  [[ "$(id -u)" != 0 ]] || die "rode como seu usuário, não como root."
  record_pre_done

  run_child 01-install-tools
  run_child 02-install-tmux-conf
  run_child 03-install-ohmyzsh
  run_child 04-set-zsh-default
  check_requirements informativo
  run_child 06-install-docker
  check_requirements obrigatorio
  run_child 07-test-framework with_docker_group
  run_child 08-install-eventos-do-campus with_docker_group

  state_mark_done
  info
  ok "Tudo pronto. Faça logout/login: a sessão passa a ver o grupo docker"
  info "e o zsh como shell padrão."
  info "Rootless (opcional, fora daqui): scripts/09-setup-docker-rootless.sh"
}

undo() {
  undo_or_nothing
  local -a list
  local name wrapper i pre
  read -r -a list <<<"$(state_get ran)"
  for ((i = ${#list[@]} - 1; i >= 0; i--)); do
    name="${list[i]}"
    wrapper=plain
    case "$name" in 07-*|08-*) wrapper=with_docker_group ;; esac
    step "$name -z"
    "$wrapper" bash "$SCRIPTS_DIR/$name.sh" -z \
      || die "$name -z falhou. Resolva e rode $SCRIPT_PATH -z de novo."
    state_set ran "$(list_without "$name" "$(state_get ran)")"
  done
  pre="$(state_get pre_done)"
  if [[ -n "$pre" ]]; then
    info "Já estavam concluídos antes e ficam como estão: $pre"
    info "Para desfazer um deles: scripts/<nome>.sh -z"
  fi
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
