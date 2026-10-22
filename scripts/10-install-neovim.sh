#!/usr/bin/env bash
# 10-install-neovim.sh: Neovim (AppImage) + LazyVim + PHP (intelephense).
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

PKGS=(git curl build-essential unzip ripgrep fd-find nodejs npm)
case "$(uname -m)" in
  x86_64)  NVIM_ASSET='nvim-linux-x86_64.appimage' ;;
  aarch64) NVIM_ASSET='nvim-linux-arm64.appimage' ;;
  *)       NVIM_ASSET="" ;;
esac
NVIM_URL="https://github.com/neovim/neovim/releases/latest/download/$NVIM_ASSET"
STARTER_URL='https://github.com/LazyVim/starter'
NVIM_MIN=0.10                       # LazyVim atual exige >= 0.10
NVIM_HOME="$HOME/.local/share/nvim-appimage"   # AppImage extraído
NVIM_BIN="$HOME/.local/bin/nvim"
NVIM_CFG="$HOME/.config/nvim"
NVIM_DATA=("$HOME/.local/share/nvim" "$HOME/.local/state/nvim"
  "$HOME/.cache/nvim" "$HOME/.cache/tree-sitter")
CFG_BACKUP="$S50_STATE_DIR/nvim-config.before"
# shellcheck disable=SC2016  # o bloco é copiado literalmente para o rc
PATH_BLOCK='case ":$PATH:" in
  *":$HOME/.local/bin:"*) ;;
  *) export PATH="$HOME/.local/bin:$PATH" ;;
esac'

usage() {
  cat <<USAGE
uso: $(basename "$0") [-h] [-z]

Instala o Neovim mais recente (AppImage extraído em
~/.local/share/nvim-appimage, link em ~/.local/bin/nvim; o apt do
24.04 traz 0.9, velho demais para o LazyVim), o LazyVim starter em
~/.config/nvim com o extra de PHP usando intelephense, e deixa
plugins, LSP e parsers (php, lua) pré-instalados em modo headless.
Pacotes apt: ${PKGS[*]}.
Precisa de php no PATH (scripts/09-install-php.sh), de sudo (apt) e
de rede. Um nvim >= $NVIM_MIN já presente é usado em vez de baixar;
um ~/.config/nvim existente é guardado e volta no -z.

  -h  mostra esta ajuda
  -z  desfaz: remove nvim, configuração, dados do LazyVim e pacotes
      que este script instalou; devolve o ~/.config/nvim anterior
USAGE
}

nvim_version_ok() { # caminho -> 0 se a versão é >= NVIM_MIN
  local v
  v="$("$1" --version 2>/dev/null | sed -n '1s/^NVIM v\([0-9.]*\).*/\1/p')"
  [[ -n "$v" ]] || return 1
  [[ "$(printf '%s\n%s\n' "$NVIM_MIN" "$v" | sort -V | head -1)" \
    == "$NVIM_MIN" ]]
}

record_dir_if_new() { # dir: anota em created_dirs se ainda não existe
  [[ -e "$1" ]] && return 0
  local list
  list="$(state_get created_dirs)"
  state_set created_dirs "${list:+$list }$1"
}

# Anota o que já existia, antes de rodar qualquer nvim (o próprio
# "nvim --version" cria ~/.local/state/nvim). Só na primeira execução.
record_preexisting() {
  [[ -z "$(state_get recorded)" ]] || return 0
  local d pre=""
  if [[ -e "$NVIM_CFG" ]]; then
    state_set cfg_preexisting 1
  else
    state_set cfg_preexisting 0
  fi
  for d in "${NVIM_DATA[@]}"; do [[ -e "$d" ]] && pre="${pre:+$pre }$d"; done
  state_set data_preexisting "$pre"
  state_set data_recorded 1
  if [[ -e "$HOME/.npm" ]]; then
    state_set npm_preexisting 1
  else
    state_set npm_preexisting 0
  fi
  for d in "$HOME/.local/bin" "$HOME/.local/share" "$HOME/.config" \
      "$HOME/.cache"; do
    record_dir_if_new "$d"
  done
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

install_nvim() {
  step "Neovim"
  if [[ -z "$(state_get nvim_installed)" ]]; then
    local have
    have="$(command -v nvim || true)"
    if [[ -n "$have" && "$have" != "$NVIM_BIN" ]] \
        && nvim_version_ok "$have"; then
      state_set nvim_installed 0
    else
      [[ -z "$have" ]] || warn "nvim em $have é antigo; vou pôr um novo" \
        "em ~/.local/bin, que vem antes no PATH"
      state_set nvim_installed 1
    fi
  fi
  if [[ "$(state_get nvim_installed)" == 0 ]]; then
    ok "nvim do sistema já serve: $(nvim --version | head -1)"
    return 0
  fi
  if [[ -x "$NVIM_HOME/usr/bin/nvim" && -L "$NVIM_BIN" ]]; then
    ok "já instalado: $("$NVIM_BIN" --version | head -1)"
  else
    [[ -n "$NVIM_ASSET" ]] \
      || die "sem AppImage do Neovim para $(uname -m) (só x86_64 e arm64)."
    local tmp
    tmp="$(mktemp -d)"
    info "baixando $NVIM_URL"
    curl -fsSL "$NVIM_URL" -o "$tmp/nvim.appimage"
    chmod +x "$tmp/nvim.appimage"
    (cd "$tmp" && ./nvim.appimage --appimage-extract >/dev/null)
    rm -rf "$NVIM_HOME"
    mkdir -p "$HOME/.local/share" "$HOME/.local/bin"
    mv "$tmp/squashfs-root" "$NVIM_HOME"
    rm -rf "$tmp"
    ln -sfn "$NVIM_HOME/usr/bin/nvim" "$NVIM_BIN"
    ok "instalado: $("$NVIM_BIN" --version | head -1)"
  fi
  local rc
  for rc in "$HOME/.bashrc" "$HOME/.zshrc"; do
    [[ -f "$rc" ]] || continue
    rc_block_remove "$rc" neovim-path
    rc_block_add "$rc" neovim-path "$PATH_BLOCK"
  done
  ok "PATH com \$HOME/.local/bin: bloco em ~/.bashrc e ~/.zshrc"
}

nvim_cmd() { # caminho do nvim a usar
  if [[ "$(state_get nvim_installed)" == 1 ]]; then
    echo "$NVIM_BIN"
  else
    command -v nvim
  fi
}

install_lazyvim() {
  step "LazyVim starter em ~/.config/nvim"
  if [[ "$(state_get cfg_preexisting)" == 1 && ! -e "$CFG_BACKUP" ]]; then
    mkdir -p "$S50_STATE_DIR"
    mv "$NVIM_CFG" "$CFG_BACKUP"
    warn "havia ~/.config/nvim; guardado em $CFG_BACKUP (volta no -z)"
  fi
  if [[ -d "$NVIM_CFG" ]]; then
    ok "já existe (execução anterior)"
  else
    mkdir -p "$HOME/.config"
    git clone -q --depth 1 "$STARTER_URL" "$NVIM_CFG"
    rm -rf "$NVIM_CFG/.git"
    ok "starter clonado"
  fi
  mkdir -p "$NVIM_CFG/lua/plugins"
  cat > "$NVIM_CFG/lua/plugins/php.lua" <<'LUA'
-- PHP com intelephense (o phpactor padrão falhou em ir-para-definição
-- e autocompletar no teste da disciplina).
vim.g.lazyvim_php_lsp = "intelephense"
return { { import = "lazyvim.plugins.extras.lang.php" } }
LUA
  ok "extra de PHP: lua/plugins/php.lua (intelephense)"
}

# headless descrição comandos... Falha vira aviso: o nvim refaz ao abrir.
headless() {
  local what="$1" nvim
  shift
  nvim="$(nvim_cmd)"
  info "$what"
  if "$nvim" --headless "$@" +qa </dev/null >/dev/null 2>&1; then
    ok "$what"
  else
    warn "$what: falhou em headless; o nvim tenta de novo ao abrir"
  fi
}

preinstall_plugins() {
  step "Plugins, LSP e parsers (headless; pode levar alguns minutos)"
  headless "lazy sync (plugins)" "+Lazy! sync"
  headless "mason: intelephense" \
    "+Lazy! load mason.nvim mason-lspconfig.nvim" \
    "+MasonInstall intelephense"
  headless "treesitter: php e lua" \
    "+Lazy! load nvim-treesitter" "+TSInstall! php lua" "+sleep 60"
}

verify() {
  step "Teste"
  local nvim n
  nvim="$(nvim_cmd)"
  ok "$("$nvim" --version | head -1) em $nvim"
  n="$("$nvim" --headless "+lua print(#require('lazy').plugins())" +qa \
    </dev/null 2>&1 | tr -dc '0-9')"
  if [[ -n "$n" && "$n" -gt 1 ]]; then
    ok "LazyVim carrega: $n plugins"
  else
    warn "não consegui contar os plugins do LazyVim"
  fi
  if [[ -x "$HOME/.local/share/nvim/mason/bin/intelephense" ]]; then
    ok "intelephense instalado pelo mason"
  else
    warn "intelephense ainda não instalado (mason faz ao abrir um .php)"
  fi
  if compgen -G "$HOME/.local/share/nvim/*/*/parser/php.so" >/dev/null \
      || compgen -G "$HOME/.local/share/nvim/*/parser/php.so" >/dev/null; then
    ok "parser treesitter de PHP compilado"
  else
    warn "parser de PHP ainda não compilado (:TSInstall php no nvim)"
  fi
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  need_cmd curl "Rode scripts/01-install-tools.sh antes."
  need_cmd php "Rode scripts/09-install-php.sh antes (php-cli)."
  record_preexisting
  install_packages
  install_nvim
  install_lazyvim
  preinstall_plugins
  verify
  state_mark_done
  info
  info "Pronto. Abra um terminal novo (PATH) e rode: nvim"
}

undo() {
  undo_or_nothing
  local rc d pkgs
  step "Neovim"
  for rc in "$HOME/.bashrc" "$HOME/.zshrc"; do
    [[ -f "$rc" ]] && rc_block_remove "$rc" neovim-path
  done
  if [[ "$(state_get nvim_installed)" == 1 ]]; then
    rm -f "$NVIM_BIN"
    rm -rf "$NVIM_HOME"
    ok "nvim removido de ~/.local/bin e ~/.local/share/nvim-appimage"
  else
    ok "nvim do sistema mantido (não foi este script que instalou)"
  fi

  step "Configuração e dados do LazyVim"
  case "$(state_get cfg_preexisting)" in
    0)
      rm -rf "$NVIM_CFG"
      ok "configuração do LazyVim removida de ~/.config/nvim"
      ;;
    1)
      if [[ -e "$CFG_BACKUP" ]]; then
        rm -rf "$NVIM_CFG"
        mkdir -p "$HOME/.config"
        mv "$CFG_BACKUP" "$NVIM_CFG"
        ok "configuração anterior restaurada em ~/.config/nvim"
      else
        ok "a configuração em ~/.config/nvim é a sua; mantida"
      fi
      ;;
  esac
  if [[ "$(state_get data_recorded)" == 1 ]]; then
    local pre
    pre="$(state_get data_preexisting)"
    for d in "${NVIM_DATA[@]}"; do
      if [[ " $pre " == *" $d "* ]]; then
        warn "$d já existia antes; mantido (com o que o LazyVim pôs nele)"
      else
        rm -rf "$d"
      fi
    done
    ok "dados do LazyVim removidos (os que este script criou)"
  fi
  if [[ "$(state_get npm_preexisting)" == 0 && -e "$HOME/.npm" ]]; then
    rm -rf "$HOME/.npm"
    ok "cache ~/.npm (criado pelo mason) removido"
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
