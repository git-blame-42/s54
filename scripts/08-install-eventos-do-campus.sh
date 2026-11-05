#!/usr/bin/env bash
# 08-install-eventos-do-campus.sh: clona o projeto Eventos do Câmpus
# (REPO_URL, padrão o repositório público) e deixa rodando em
# http://localhost.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FULL="${FULL:-0}"
REPO_URL="${REPO_URL:-https://github.com/VitorThorvi/tsi34d-backend}"
PROJECT_DIR="${EVENTOS_DIR:-$HOME/eventos-do-campus}"
PORTS=(80 8080 4444 5900)

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Baixa e sobe o projeto Eventos do Câmpus:
  1. git clone \$REPO_URL em $PROJECT_DIR
     (outro lugar: EVENTOS_DIR=/caminho $(basename "$0"))
  2. cp .env.example .env; libera as portas que o compose publica;
     ./run composer install; ./run up -d --build
  3. ./run db:reset; ./run db:populate; ./run codecept build
  4. ./run test tests/Unit (tem de passar); com FULL=1, também
     ./run test tests/Integration e ./run test:browser
  5. ajusta o dono de public/assets/uploads (se existir e houver sudo)

Repositório padrão: https://github.com/VitorThorvi/tsi34d-backend
Outro (fork da equipe, URL ssh...):
  REPO_URL=https://github.com/ORG/REPO $(basename "$0")
Suíte inteira (Unit, Integration e navegador; leva alguns minutos):
  FULL=1 $(basename "$0")

Precisa de docker funcionando sem sudo. Porta que o compose publica
(${PORTS[*]}) e já está em uso é liberada: container dono -> docker stop;
processo -> SIGTERM, 3 s, SIGKILL (com sudo se for de outro usuário).
O que foi parado aparece na saída e fica em freed_ports no estado.
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

  record_preexisting_images
  mkdir -p "$(dirname "$PROJECT_DIR")"

  local failed=0
  run_step "git clone" git clone "$REPO_URL" "$PROJECT_DIR" || failed=1
  state_set project_dir "$PROJECT_DIR"
  if (( ! failed )); then
    cd "$PROJECT_DIR"
    info "commit: $(git rev-parse --short HEAD)"
    framework_bootstrap "${PORTS[@]}" || failed=1
  fi
  if (( ! failed )); then
    run_step "./run codecept build" ./run codecept build || failed=1
  fi
  if (( ! failed )); then
    run_step "./run test tests/Unit" ./run test tests/Unit || failed=1
  fi
  if (( ! failed )) && [[ "$FULL" == 1 ]]; then
    run_step "./run test tests/Integration" ./run test tests/Integration \
      || failed=1
    run_step "./run test:browser" ./run test:browser || failed=1
  fi
  if (( ! failed )); then
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
  info "  ./run down               parar;  ./run up -d --build  subir"
  info "  ./run test tests/Unit    testes unitários"
  info "  ./run phpcs              estilo;  ./run phpstan  análise"
  info "  ./run db:reset && ./run db:populate   recriar o banco"
  info
  info "Próximos passos FVP (detalhes: scripts/README.md, seção FVP):"
  info "  $SCRIPTS_DIR/14-fvp-prep.sh   soluções como branches; atalhos"
  info "  fvp -c 4.2               cenários por chance (fvp.sh)"
  info "  $SCRIPTS_DIR/15-fvp-check.sh  quais aplicam no seu main"
  info "  fvp-aplicar --dry-run P1   testa; fvp-aplicar P1 aplica e testa"
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
