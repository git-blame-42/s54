#!/usr/bin/env bash
# 03-install-ohmyzsh.sh: instala o oh-my-zsh e ativa o plugin tmux.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

OMZ_DIR="$HOME/.oh-my-zsh"
ZSHRC="$HOME/.zshrc"
OMZ_INSTALLER='https://raw.githubusercontent.com/ohmyzsh/ohmyzsh/master/tools/install.sh'

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Instala o oh-my-zsh em ~/.oh-my-zsh (modo não interativo, sem trocar
o shell padrão) e adiciona o plugin tmux ao ~/.zshrc. Não precisa de
sudo. Se o oh-my-zsh já existir, só o plugin é adicionado.

Precisa de zsh, git e curl (scripts/01-install-tools.sh instala o zsh).

  -h  mostra esta ajuda
  -z  desfaz: remove o oh-my-zsh (se foi este script que instalou),
      restaura o ~/.zshrc anterior e tira o plugin tmux
EOF
}

# Insere, logo antes da linha "source $ZSH/oh-my-zsh.sh", um bloco que
# acrescenta o plugin tmux. Funciona com plugins=(...) em uma ou em
# várias linhas, sem reescrever a lista do usuário.
add_tmux_plugin() {
  local tmp
  rc_block_remove "$ZSHRC" ohmyzsh-tmux
  grep -q '^source \$ZSH/oh-my-zsh.sh' "$ZSHRC" \
    || die "não achei 'source \$ZSH/oh-my-zsh.sh' em $ZSHRC."
  tmp="$(mktemp)"
  awk '
    /^source \$ZSH\/oh-my-zsh.sh/ && !done {
      print "# >>> s50-setup:ohmyzsh-tmux >>>"
      print "plugins+=(tmux)"
      print "# <<< s50-setup:ohmyzsh-tmux <<<"
      done = 1
    }
    { print }
  ' "$ZSHRC" > "$tmp"
  cat "$tmp" > "$ZSHRC"
  rm -f "$tmp"
}

run() {
  refuse_if_done
  need_cmd zsh "Rode scripts/01-install-tools.sh antes."
  need_cmd git \
    "Rode scripts/01-install-tools.sh antes (ou: sudo apt install git)."
  need_cmd curl "Rode scripts/01-install-tools.sh antes."

  if [[ -d "$OMZ_DIR" ]]; then
    step "oh-my-zsh já existe em $OMZ_DIR (fica fora do -z)"
    state_set omz_installed 0
  else
    step "Instalando o oh-my-zsh"
    if [[ -f "$ZSHRC" ]]; then
      mkdir -p "$S50_STATE_DIR"
      cp "$ZSHRC" "$S50_STATE_DIR/zshrc.before-ohmyzsh"
      state_set zshrc_backup "$S50_STATE_DIR/zshrc.before-ohmyzsh"
      info "Backup do ~/.zshrc em $S50_STATE_DIR/zshrc.before-ohmyzsh"
    else
      state_set zshrc_backup ""
    fi
    # --unattended: não troca o shell, não abre o zsh, não pergunta.
    sh -c "$(curl -fsSL "$OMZ_INSTALLER")" "" --unattended
    state_set omz_installed 1
    ok "oh-my-zsh instalado"
  fi

  step "Ativando o plugin tmux no ~/.zshrc"
  add_tmux_plugin
  state_set plugin_added 1
  ok "plugin tmux ativado"

  step "Testando o zsh com a configuração nova"
  if zsh -ic 'echo "$plugins"' 2>/dev/null | grep -qw tmux; then
    ok "zsh carrega o oh-my-zsh com o plugin tmux"
  else
    warn "não consegui confirmar o plugin; abra um zsh e rode: echo \$plugins"
  fi

  state_mark_done
  info
  info "Pronto. Para usar agora: exec zsh"
  info "Para o zsh abrir sozinho em todo terminal:"
  info "  scripts/04-set-zsh-default.sh"
}

undo() {
  undo_or_nothing

  if [[ "$(state_get omz_installed)" == 1 ]]; then
    step "Removendo o oh-my-zsh"
    rm -rf "$OMZ_DIR"
    # Caches do compinit gerados ao carregar o oh-my-zsh.
    rm -f "$HOME"/.zcompdump*
    local backup
    backup="$(state_get zshrc_backup)"
    if [[ -n "$backup" && -f "$backup" ]]; then
      cp "$backup" "$ZSHRC"
      rm -f "$backup"
      # O instalador também guardou uma cópia do mesmo arquivo.
      rm -f "$HOME/.zshrc.pre-oh-my-zsh"
      ok "arquivo ~/.zshrc anterior restaurado"
    else
      rm -f "$ZSHRC"
      ok "arquivo ~/.zshrc removido (não existia antes)"
    fi
  else
    step "oh-my-zsh não foi instalado por este script; só tiro o plugin"
    if [[ -f "$ZSHRC" ]]; then
      rc_block_remove "$ZSHRC" ohmyzsh-tmux
      ok "plugin tmux removido do ~/.zshrc"
    fi
  fi

  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
