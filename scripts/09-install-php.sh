#!/usr/bin/env bash
# 09-install-php.sh: PHP nativo (cli + extensões do projeto), composer e
# phpcs/phpcbf/phpstan globais, para lint e LSP fora do Docker.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

PKGS=(php-cli php-mysql php-zip php-xml php-mbstring php-curl unzip
  composer)
TOOLS=(squizlabs/php_codesniffer phpstan/phpstan)
CHOME="$HOME/.config/composer"       # COMPOSER_HOME padrão no Linux
CCACHE="$HOME/.cache/composer"
CBIN="$CHOME/vendor/bin"
# shellcheck disable=SC2016  # o bloco é copiado literalmente para o rc
PATH_BLOCK='case ":$PATH:" in
  *":$HOME/.config/composer/vendor/bin:"*) ;;
  *) export PATH="$HOME/.config/composer/vendor/bin:$PATH" ;;
esac'

usage() {
  cat <<USAGE
uso: $(basename "$0") [-h] [-z]

PHP fora do Docker, para lint, LSP e linha de comando. Instala com
apt o que falta entre ${PKGS[*]}
(as extensões que o projeto usa: pdo_mysql, zip, xml, mbstring,
curl) e, com o composer, phpcs/phpcbf (squizlabs/php_codesniffer) e
phpstan em ~/.config/composer, com ~/.config/composer/vendor/bin no
PATH (bloco em ~/.bashrc e ~/.zshrc). Precisa de sudo e de rede.
Pacotes e ferramentas que já existiam ficam fora do -z.

  -h  mostra esta ajuda
  -z  desfaz: remove as ferramentas globais, o bloco de PATH e os
      pacotes que este script instalou
USAGE
}

composer_g() { # composer global ... (sempre no COMPOSER_HOME padrão)
  COMPOSER_HOME="$CHOME" COMPOSER_CACHE_DIR="$CCACHE" \
    composer global --no-interaction "$@"
}

record_dir_if_new() { # dir
  [[ -e "$1" ]] && return 0
  local list
  list="$(state_get created_dirs)"
  state_set created_dirs "${list:+$list }$1"
}

record_preexisting() {
  [[ -z "$(state_get recorded)" ]] || return 0
  local d k
  for d in "$CHOME" "$CCACHE"; do
    k=chome_preexisting
    [[ "$d" == "$CCACHE" ]] && k=ccache_preexisting
    if [[ -e "$d" ]]; then state_set "$k" 1; else state_set "$k" 0; fi
  done
  # Num ~/.config/composer que já existia, o "global require" cria
  # vendor/ e composer.lock e o "global remove" não os apaga.
  local pre=""
  for d in "$CHOME/vendor" "$CHOME/composer.lock" "$CHOME/.htaccess"; do
    [[ -e "$d" ]] && pre="${pre:+$pre }$d"
  done
  state_set chome_files_preexisting "$pre"
  record_dir_if_new "$HOME/.config"
  record_dir_if_new "$HOME/.cache"
  state_set recorded 1
}

install_packages() {
  step "Pacotes (apt)"
  if [[ "$(state_get packages_done)" == 1 ]]; then
    ok "já tratados numa execução anterior"
    return 0
  fi
  local missing
  missing="$(apt_missing "${PKGS[@]}")"
  if [[ -z "$missing" ]]; then
    state_set installed_packages ""
    ok "todos já instalados (fora do -z)"
  else
    info "faltam: $missing"
    require_sudo
    apt_update_once
    # shellcheck disable=SC2086
    apt_install_tracked installed_packages $missing
    ok "pacotes novos (com dependências): $(state_get installed_packages)"
  fi
  state_set packages_done 1
}

install_tools() {
  step "Ferramentas globais do composer: ${TOOLS[*]}"
  if [[ "$(state_get tools_done)" == 1 ]]; then
    ok "já instaladas numa execução anterior"
    return 0
  fi
  local have t add=""
  have="$(composer_g show -N 2>/dev/null || true)"
  for t in "${TOOLS[@]}"; do
    if grep -qx "$t" <<<"$have"; then
      info "$t já estava no composer global (fora do -z)"
    else
      add="${add:+$add }$t"
    fi
  done
  state_set tools_added "$add"
  if [[ -n "$add" ]]; then
    # shellcheck disable=SC2086
    composer_g require $add
  fi
  state_set tools_done 1
  ok "phpcs, phpcbf e phpstan em $CBIN"
}

add_path() {
  step "PATH"
  local rc
  for rc in "$HOME/.bashrc" "$HOME/.zshrc"; do
    [[ -f "$rc" ]] || continue
    rc_block_remove "$rc" php-tools-path
    rc_block_add "$rc" php-tools-path "$PATH_BLOCK"
  done
  ok "bloco com \$HOME/.config/composer/vendor/bin em ~/.bashrc e ~/.zshrc"
}

verify() {
  step "Teste"
  local tmp
  ok "$(php --version | head -1)"
  tmp="$(mktemp --suffix=.php)"
  printf '<?php\necho "ok";\n' > "$tmp"
  if php -l "$tmp" >/dev/null; then
    ok "php -l funciona"
  else
    fail "php -l falhou"
  fi
  rm -f "$tmp"
  if php -m | grep -qi pdo_mysql; then
    ok "extensão pdo_mysql presente"
  else
    warn "extensão pdo_mysql ausente"
  fi
  ok "$("$CBIN/phpcs" --version)"
  ok "$("$CBIN/phpstan" --version)"
  ok "$(composer --version 2>/dev/null | head -1)"
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  record_preexisting
  install_packages
  install_tools
  add_path
  verify
  state_mark_done
  info
  info "Pronto. Abra um terminal novo (PATH) e teste: phpcs --version"
}

undo() {
  undo_or_nothing
  local rc d pkgs added
  step "Ferramentas globais e PATH"
  for rc in "$HOME/.bashrc" "$HOME/.zshrc"; do
    [[ -f "$rc" ]] && rc_block_remove "$rc" php-tools-path
  done
  added="$(state_get tools_added)"
  case "$(state_get chome_preexisting)" in
    0)
      rm -rf "$CHOME"
      ok "pasta ~/.config/composer removida (criada por este script)"
      ;;
    1)
      if [[ -n "$added" ]] && command -v composer >/dev/null 2>&1; then
        # shellcheck disable=SC2086
        if composer_g remove $added; then
          ok "removidas do composer global: $added"
        else
          warn "não consegui remover $added do composer global"
        fi
      else
        ok "pasta ~/.config/composer já existia; mantida"
      fi
      for d in "$CHOME/vendor" "$CHOME/composer.lock" "$CHOME/.htaccess"; do
        [[ " $(state_get chome_files_preexisting) " == *" $d "* ]] \
          || rm -rf "$d"
      done
      ;;
  esac
  if [[ "$(state_get ccache_preexisting)" == 0 ]]; then
    rm -rf "$CCACHE"
  fi
  for d in $(state_get created_dirs); do
    rmdir "$d" 2>/dev/null || true
  done

  pkgs="$(state_get installed_packages)"
  if [[ -n "$pkgs" ]]; then
    step "Removendo pacotes (e dependências que vieram junto)"
    info "$pkgs"
    require_sudo
    apt_purge_safe "$pkgs"
  fi
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
