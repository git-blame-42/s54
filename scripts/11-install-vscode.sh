#!/usr/bin/env bash
# 11-install-vscode.sh: VS Code + extensão Intelephense (PHP). Opcional.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

EXT='bmewburn.vscode-intelephense-client'
KEYRING=/etc/apt/keyrings/packages.microsoft.gpg
SOURCES=/etc/apt/sources.list.d/vscode.list
# Arquivos que o pacote "code" pode criar sozinho no postinst.
PKG_FILES=(/etc/apt/sources.list.d/vscode.sources
  /etc/apt/sources.list.d/vscode.list /etc/apt/keyrings/microsoft.gpg
  /usr/share/keyrings/microsoft.gpg /etc/apt/keyrings/packages.microsoft.gpg)
SETTINGS="$HOME/.config/Code/User/settings.json"
# Pastas que a linha de comando do code cria na primeira execução.
USER_DIRS=("$HOME/.vscode" "$HOME/.config/Code" "$HOME/.cache/Microsoft"
  "$HOME/.dotnet")

usage() {
  cat <<USAGE
uso: $(basename "$0") [-h] [-z]

Opcional (interface gráfica; fora do 00-run-all.sh). Instala o VS
Code pelo repositório apt da Microsoft, se o comando "code" não
existir, e a extensão $EXT.
Em ~/.config/Code/User/settings.json desliga o PHP embutido do VS
Code ("php.suggest.basic" e "php.validate.enable" = false), que
conflita com o Intelephense, só se essas chaves não existirem.
Precisa de php no PATH (scripts/09-install-php.sh), de sudo (apt) e
de rede. Não abre janela: o "code" por linha de comando basta.

Um VS Code que já existia (apt, snap ou .deb) não é tocado: só a
extensão entra, e o -z remove só ela (se não existia antes).

  -h  mostra esta ajuda
  -z  desfaz: remove a extensão, as chaves que inseriu no settings,
      o pacote code, o repositório e o keyring que este script criou
USAGE
}

record_dir_if_new() { # dir
  [[ -e "$1" ]] && return 0
  local list
  list="$(state_get created_dirs)"
  state_set created_dirs "${list:+$list }$1"
}

have_ext() { code --list-extensions 2>/dev/null | grep -qix "$EXT"; }

record_preexisting() {
  [[ -z "$(state_get recorded)" ]] || return 0
  local f pre="" d
  if command -v code >/dev/null 2>&1; then
    state_set code_preexisting 1
    if have_ext; then state_set ext_preexisting 1
    else state_set ext_preexisting 0; fi
  else
    state_set code_preexisting 0
    state_set ext_preexisting 0
  fi
  for f in "${PKG_FILES[@]}"; do [[ -e "$f" ]] && pre="${pre:+$pre }$f"; done
  state_set apt_files_preexisting "$pre"
  for d in "${USER_DIRS[@]}" "$HOME/.config" "$HOME/.config/Code/User" \
      "$HOME/.cache"; do
    record_dir_if_new "$d"
  done
  if [[ -e "$SETTINGS" ]]; then state_set settings_preexisting 1
  else state_set settings_preexisting 0; fi
  state_set recorded 1
}

install_code() {
  step "VS Code"
  if [[ "$(state_get code_preexisting)" == 1 ]]; then
    ok "code já existe: $(command -v code) ($(code --version 2>/dev/null \
      | head -1)). Não será tocado."
    return 0
  fi
  if command -v code >/dev/null 2>&1; then
    ok "já instalado numa execução anterior"
    return 0
  fi
  require_sudo
  if ! command -v gpg >/dev/null 2>&1; then
    apt_update_once
    apt_install_tracked installed_packages gnupg
  fi
  if [[ ! -f "$KEYRING" ]]; then
    sudo install -d -m 0755 /etc/apt/keyrings
    curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
      | gpg --dearmor | sudo tee "$KEYRING" >/dev/null
    sudo chmod 0644 "$KEYRING"
    state_set keyring_created 1
  fi
  if [[ ! -f "$SOURCES" ]]; then
    echo "deb [arch=amd64,arm64,armhf signed-by=$KEYRING]" \
      "https://packages.microsoft.com/repos/code stable main" \
      | sudo tee "$SOURCES" >/dev/null
    state_set sources_created 1
  fi
  sudo apt-get update -qq
  apt_install_tracked installed_packages code
  ok "instalado: $(code --version | head -1)"
}

install_ext() {
  step "Extensão $EXT"
  if [[ "$(state_get ext_preexisting)" == 1 ]]; then
    ok "já estava instalada (fora do -z)"
    return 0
  fi
  code --install-extension "$EXT" --force >/dev/null 2>&1 \
    || die "code --install-extension $EXT falhou."
  have_ext || die "extensão não aparece em code --list-extensions."
  state_set ext_installed 1
  ok "instalada"
}

# Insere/remove as duas chaves em settings.json via python3 (JSON puro;
# se o arquivo tiver comentários, avisa e não mexe).
settings_edit() { # add | remove chave...
  python3 -I - "$1" "$SETTINGS" "${@:2}" <<'PY'
import json, sys, os
mode, path = sys.argv[1], sys.argv[2]
keys = {"php.suggest.basic": False, "php.validate.enable": False}
data = {}
if os.path.exists(path):
    try:
        with open(path, encoding="utf-8") as f:
            data = json.load(f)
    except ValueError:
        print("JSON inválido ou com comentários; não alterado")
        sys.exit(2)
if mode == "add":
    added = [k for k in keys if k not in data]
    for k in added:
        data[k] = keys[k]
    print(" ".join(added))
else:
    wanted = sys.argv[3:] if len(sys.argv) > 3 else []
    for k in wanted:
        if data.get(k) == keys[k]:
            del data[k]
os.makedirs(os.path.dirname(path), exist_ok=True)
with open(path, "w", encoding="utf-8") as f:
    json.dump(data, f, indent=4, ensure_ascii=False)
    f.write("\n")
PY
}

configure_settings() {
  step "settings.json: desliga o PHP embutido do VS Code"
  if [[ -n "$(state_get settings_keys)" ]]; then
    ok "já feito numa execução anterior"
    return 0
  fi
  local out
  if out="$(settings_edit add)"; then
    state_set settings_keys "${out:-none}"
    if [[ -n "$out" ]]; then ok "inseridas: $out"
    else ok "chaves já existiam; nada inserido"; fi
  else
    warn "settings.json não alterado: $out"
    state_set settings_keys none
  fi
}

verify() {
  step "Teste"
  ok "$(code --version | head -1) em $(command -v code)"
  if have_ext; then ok "extensão $EXT presente"
  else fail "extensão ausente"; fi
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  need_cmd curl "Rode scripts/01-install-tools.sh antes."
  need_cmd php "Rode scripts/09-install-php.sh antes (php-cli)."
  need_cmd python3 "sudo apt install python3"
  record_preexisting
  install_code
  install_ext
  configure_settings
  verify
  state_mark_done
  info
  info "Pronto. Abra: code"
}

undo() {
  undo_or_nothing
  local keys f d pkgs pre
  step "Extensão e settings"
  keys="$(state_get settings_keys)"
  if [[ -n "$keys" && "$keys" != none ]]; then
    # shellcheck disable=SC2086
    if settings_edit remove $keys >/dev/null; then
      ok "chaves removidas do settings.json: $keys"
    else
      warn "não consegui editar o settings.json"
    fi
  fi
  if [[ "$(state_get settings_preexisting)" == 0 && -e "$SETTINGS" ]] \
      && [[ "$(tr -d '[:space:]' < "$SETTINGS")" == "{}" ]]; then
    rm -f "$SETTINGS"
  fi
  if [[ "$(state_get ext_installed)" == 1 ]]; then
    if command -v code >/dev/null 2>&1; then
      code --uninstall-extension "$EXT" >/dev/null 2>&1 || true
    fi
    # O uninstall só marca como obsoleta; a pasta e o VSIX em cache ficam.
    rm -rf "$HOME/.vscode/extensions/$EXT-"* \
      "$HOME/.config/Code/CachedExtensionVSIXs/$EXT-"*
    rmdir "$HOME/.config/Code/CachedExtensionVSIXs" 2>/dev/null || true
    ok "extensão $EXT removida"
  fi

  if [[ "$(state_get code_preexisting)" == 0 ]]; then
    step "VS Code (instalado por este script)"
    pkgs="$(state_get installed_packages)"
    if [[ -n "$pkgs" ]]; then
      require_sudo
      apt_purge_safe "$pkgs"
    fi
    pre="$(state_get apt_files_preexisting)"
    for f in "${PKG_FILES[@]}"; do
      if [[ -e "$f" && " $pre " != *" $f "* ]]; then
        sudo rm -f "$f"
      fi
    done
    ok "pacote, repositório e keyring removidos"
  else
    ok "VS Code pré-existente mantido"
  fi
  # Pastas de usuário que a CLI do code criou por causa deste script.
  for d in "${USER_DIRS[@]}"; do
    if [[ " $(state_get created_dirs) " == *" $d "* ]]; then
      rm -rf "$d"
    fi
  done
  for d in $(state_get created_dirs); do
    rmdir "$d" 2>/dev/null || true
  done
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
