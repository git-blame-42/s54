#!/usr/bin/env bash
# 02-install-tmux-conf.sh: baixa o ~/.tmux.conf do repositório de dotfiles.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

TMUX_CONF_URL='https://raw.githubusercontent.com/VitorThorvi/zsh-dot-files/refs/heads/main/.tmux.conf'
TMUX_CONF="$HOME/.tmux.conf"

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Baixa o .tmux.conf de
  $TMUX_CONF_URL
para ~/.tmux.conf. Se já houver um ~/.tmux.conf, ele é guardado e
volta no -z. Não precisa de sudo. Precisa de tmux e curl.

  -h  mostra esta ajuda
  -z  desfaz: restaura o ~/.tmux.conf anterior (ou remove, se não havia)
EOF
}

run() {
  refuse_if_done
  need_cmd tmux "Rode scripts/01-install-tools.sh antes."
  need_cmd curl "Rode scripts/01-install-tools.sh antes."

  step "Baixando o .tmux.conf"
  local tmp
  tmp="$(mktemp)"
  curl -fsSL "$TMUX_CONF_URL" -o "$tmp" || die "download falhou: $TMUX_CONF_URL"
  [[ -s "$tmp" ]] || die "arquivo baixado vazio."

  if [[ -f "$TMUX_CONF" ]]; then
    if cmp -s "$tmp" "$TMUX_CONF"; then
      ok "arquivo ~/.tmux.conf já é igual ao do repositório"
      state_set backup ""
      state_set written 0
      rm -f "$tmp"
      state_mark_done
      return 0
    fi
    mkdir -p "$S50_STATE_DIR"
    cp "$TMUX_CONF" "$S50_STATE_DIR/tmux.conf.before"
    state_set backup "$S50_STATE_DIR/tmux.conf.before"
    info "Backup do ~/.tmux.conf em $S50_STATE_DIR/tmux.conf.before"
  else
    state_set backup ""
  fi

  cat "$tmp" > "$TMUX_CONF"
  rm -f "$tmp"
  state_set written 1
  ok "arquivo ~/.tmux.conf instalado"

  step "Validando com o tmux"
  if tmux -f "$TMUX_CONF" -L s50-check start-server \; kill-server \
      >/dev/null 2>&1; then
    ok "tmux aceitou a configuração"
  else
    warn "tmux reclamou da configuração; confira: tmux source ~/.tmux.conf"
  fi

  state_mark_done
  info
  info "Sessões tmux já abertas: rode dentro delas"
  info "  tmux source-file ~/.tmux.conf"
}

undo() {
  undo_or_nothing
  if [[ "$(state_get written)" == 1 ]]; then
    local backup
    backup="$(state_get backup)"
    if [[ -n "$backup" && -f "$backup" ]]; then
      cat "$backup" > "$TMUX_CONF"
      rm -f "$backup"
      ok "arquivo ~/.tmux.conf anterior restaurado"
    else
      rm -f "$TMUX_CONF"
      ok "arquivo ~/.tmux.conf removido (não existia antes)"
    fi
  else
    info "Este script não alterou o ~/.tmux.conf; nada a desfazer."
  fi
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
