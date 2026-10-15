#!/usr/bin/env bash
# test-framework.sh: baixa o template do framework da disciplina, sem
# modificações, num diretório temporário, sobe tudo com docker compose,
# roda os testes unitários e apaga tudo no fim.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

TEMPLATE_URL=https://github.com/SI-DABE/tsi34d-framework-template
PORTS=(80 8080 4444 5900)
TMP_DIR=''

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Prova que esta máquina roda o framework da disciplina:
  1. git clone $TEMPLATE_URL
     em /tmp/tsi34d-framework.XXXXXX (sem alterar nada no código)
  2. cp .env.example .env; ./run composer install; ./run up -d
  3. ./run db:reset; ./run db:populate; GET http://localhost
  4. ./run test tests/Unit
  5. derruba os containers, apaga volumes, imagens que não existiam
     antes e o diretório temporário

Precisa de docker funcionando sem sudo e das portas ${PORTS[*]} livres.
Se der tudo certo, não sobra nada na máquina.

  -h  mostra esta ajuda
  -z  limpa o que ficou de uma execução interrompida (containers,
      imagens e diretório temporário registrados no estado)
EOF
}

cleanup() {
  local status=$?
  trap - EXIT
  [[ -n "$TMP_DIR" ]] || exit "$status"
  step "Limpando"
  cd /
  compose_teardown "$TMP_DIR/template"
  remove_dir_maybe_root "$TMP_DIR"
  state_remove
  ok "nada ficou na máquina"
  exit "$status"
}

run_unit_tests() {
  if [[ -d tests/Unit ]]; then
    ./run test tests/Unit
  else
    ./run test
  fi
}

run() {
  if state_exists; then
    warn "há restos de uma execução anterior; limpando antes."
    undo
  fi
  require_docker_ready
  require_free_ports "${PORTS[@]}"

  record_preexisting_images
  TMP_DIR="$(mktemp -d /tmp/tsi34d-framework.XXXXXX)"
  state_set tmpdir "$TMP_DIR"
  trap cleanup EXIT
  info "diretório temporário: $TMP_DIR"

  local failed=0
  run_step "git clone do template" \
    git clone --depth 1 "$TEMPLATE_URL" "$TMP_DIR/template" || failed=1
  if (( ! failed )); then
    cd "$TMP_DIR/template"
    info "commit: $(git rev-parse --short HEAD)"
    framework_bootstrap || failed=1
  fi
  if (( ! failed )); then
    run_step "./run test tests/Unit" run_unit_tests || failed=1
  fi

  print_step_results
  if (( failed )); then
    fail "a máquina NÃO rodou o framework até o fim; veja o passo com FALHA."
    return 1
  fi
  ok "a máquina roda o framework da disciplina."
  info "Próximo passo: scripts/install-eventos-do-campus.sh"
}

undo() {
  undo_or_nothing
  local dir
  dir="$(state_get tmpdir)"
  if [[ -n "$dir" && -d "$dir" ]]; then
    step "Limpando $dir"
    docker_usable && compose_teardown "$dir/template"
    remove_dir_maybe_root "$dir"
    ok "removido"
  else
    info "diretório temporário já não existe."
  fi
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
