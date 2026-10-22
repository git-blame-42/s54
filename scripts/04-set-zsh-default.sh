#!/usr/bin/env bash
# 04-set-zsh-default.sh: faz o zsh ser o shell dos seus terminais.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

BASHRC="$HOME/.bashrc"

usage() {
  cat <<EOF
uso: $(basename "$0") [-h] [-z]

Torna o zsh o shell padrão do seu usuário com chsh (pede sua senha).
Em contas de rede (LDAP) o chsh costuma falhar; nesse caso o script
coloca um bloco no ~/.bashrc que troca para o zsh em terminais
interativos. Não precisa de sudo. Precisa de zsh instalado.

  -h  mostra esta ajuda
  -z  desfaz: volta o shell anterior ou remove o bloco do ~/.bashrc
EOF
}

current_shell() { getent passwd "$USER" | cut -d: -f7; }

run() {
  refuse_if_done
  need_cmd zsh "Rode scripts/01-install-tools.sh antes."
  local zsh_path previous
  zsh_path="$(command -v zsh)"
  previous="$(current_shell)"

  if [[ "$previous" == */zsh ]]; then
    ok "zsh já é o shell padrão ($previous)"
    state_set method none
    state_mark_done
    return 0
  fi
  state_set previous_shell "$previous"

  step "Tentando chsh -s $zsh_path (pode pedir a sua senha)"
  # Não redirecione o stderr do chsh: o prompt de senha do PAM sai por ele.
  if grep -qx "$zsh_path" /etc/shells 2>/dev/null \
      && chsh -s "$zsh_path" \
      && [[ "$(current_shell)" == "$zsh_path" ]]; then
    state_set method chsh
    ok "shell padrão alterado para zsh"
    info "Vale a partir do próximo login (feche e abra o terminal)."
  else
    warn "chsh não funcionou (conta de rede ou sem permissão)."
    step "Usando fallback: bloco no ~/.bashrc"
    rc_block_add "$BASHRC" set-zsh-default "$(cat <<'EOF'
# Troca para o zsh em terminais interativos (chsh indisponível).
# Não troca quando o bash foi chamado com -c (bash -ic 'comando').
case $- in *i*)
  if [ -t 1 ] && [ -z "${ZSH_VERSION:-}" ] \
      && [ -z "${BASH_EXECUTION_STRING:-}" ] \
      && command -v zsh >/dev/null 2>&1; then
    exec zsh -l
  fi
esac
EOF
)"
    state_set method bashrc
    ok "bloco adicionado ao ~/.bashrc"
    info "Vale para todo novo terminal bash interativo."
  fi

  state_mark_done
}

undo() {
  undo_or_nothing
  case "$(state_get method)" in
    chsh)
      local previous
      previous="$(state_get previous_shell)"
      step "Voltando o shell padrão para $previous"
      chsh -s "$previous" || die "chsh falhou; rode à mão: chsh -s $previous"
      ok "shell padrão restaurado"
      ;;
    bashrc)
      step "Removendo o bloco do ~/.bashrc"
      rc_block_remove "$BASHRC" set-zsh-default
      ok "bloco removido"
      ;;
    *)
      info "Este script não mudou nada; nada a desfazer."
      ;;
  esac
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
