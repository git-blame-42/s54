#!/usr/bin/env bash
# 08-install-eventos-do-campus.sh: clona o projeto Eventos do Câmpus do
# repositório público e deixa rodando em http://localhost.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

REPO_URL=https://github.com/VitorThorvi/tsi34d-backend
PROJECT_DIR="${EVENTOS_DIR:-$HOME/eventos-do-campus}"
PORTS=(80 8080 4444 5900)

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Baixa e sobe o projeto Eventos do Câmpus:
  1. git clone $REPO_URL
     em $PROJECT_DIR
     (outro lugar: EVENTOS_DIR=/caminho $(basename "$0"))
  2. cp .env.example .env; ./run composer install; ./run up -d
  3. ./run db:reset; ./run db:populate; ./run codecept build
  4. ajusta o dono de public/assets/uploads (se existir e houver sudo)

Precisa de docker funcionando sem sudo e das portas ${PORTS[*]} livres.
Se $PROJECT_DIR já existir, o script para e não toca nele.

  -h  mostra esta ajuda
  -z  desfaz: derruba os containers, apaga volumes, imagens que não
      existiam antes e o diretório do projeto
EOF
}

fix_uploads_owner() {
  if [[ ! -d public/assets/uploads ]]; then
    info "sem public/assets/uploads"
    return 0
  fi
  if ! sudo_ok; then
    warn "sem sudo: rode depois: sudo chown -R www-data:www-data" \
      "public/assets/uploads"
    return 0
  fi
  if sudo chown -R www-data:www-data public/assets/uploads; then
    ok "public/assets/uploads agora é de www-data"
  else
    warn "chown falhou; rode à mão: sudo chown -R www-data:www-data" \
      "public/assets/uploads"
  fi
}

run() {
  refuse_if_done
  if [[ -e "$PROJECT_DIR" ]]; then
    die "$PROJECT_DIR já existe. Remova-o ou aponte EVENTOS_DIR para" \
      "outro lugar."
  fi
  require_docker_ready
  require_free_ports "${PORTS[@]}"

  record_preexisting_images
  mkdir -p "$(dirname "$PROJECT_DIR")"

  local failed=0
  run_step "git clone" git clone "$REPO_URL" "$PROJECT_DIR" || failed=1
  state_set project_dir "$PROJECT_DIR"
  if (( ! failed )); then
    cd "$PROJECT_DIR"
    info "commit: $(git rev-parse --short HEAD)"
    framework_bootstrap || failed=1
  fi
  if (( ! failed )); then
    run_step "./run codecept build" ./run codecept build || failed=1
    run_step "Dono de public/assets/uploads" fix_uploads_owner || true
  fi

  print_step_results
  if (( failed )); then
    fail "instalação incompleta. Corrija e rode de novo, ou -z para limpar."
    return 1
  fi

  state_mark_done
  info
  info "Projeto em: $PROJECT_DIR"
  info "Aplicação:  http://localhost"
  info "Login:      admin@example.com (admin) ou user@example.com"
  info "            senha 123456"
  info
  info "Comandos (dentro de $PROJECT_DIR):"
  info "  ./run ps                 containers"
  info "  ./run down               parar;  ./run up -d  subir"
  info "  ./run test tests/Unit    testes unitários"
  info "  ./run phpcs              estilo;  ./run phpstan  análise"
  info "  ./run db:reset && ./run db:populate   recriar o banco"
}

undo() {
  undo_or_nothing
  local dir
  dir="$(state_get project_dir)"
  if [[ -n "$dir" && -d "$dir" ]]; then
    step "Derrubando containers e apagando $dir"
    docker_usable && compose_teardown "$dir"
    remove_dir_maybe_root "$dir"
    ok "removido"
  else
    info "diretório do projeto já não existe."
  fi
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
