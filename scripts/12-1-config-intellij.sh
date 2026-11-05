#!/usr/bin/env bash
# 12-1-config-intellij.sh: configuração da disciplina no IntelliJ IDEA
# (scripts/files/intellij: keymap TSI34D, Darcula, PSR-12, templates do
# framework) e os plugins de scripts/files/intellij/plugins.txt.
# shellcheck source=common.sh
source "$(dirname "${BASH_SOURCE[0]}")/common.sh"

SCRIPTS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CFG_SRC="$SCRIPTS_DIR/files/intellij"
PLUGINS_TXT="$CFG_SRC/plugins.txt"
# Subpastas de files/intellij que vão para o diretório de config do IDE.
CFG_PARTS=(codestyles fileTemplates keymaps options templates)
FONT_PKG=fonts-jetbrains-mono
BACKUP_DIR="$S50_STATE_DIR/intellij-config.before"
CFG_PRE="$S50_STATE_DIR/intellij-config.pre"     # arquivos que já havia
CFG_POST="$S50_STATE_DIR/intellij-config.post"   # arquivos ao terminar
PLUGINS_PRE="$S50_STATE_DIR/intellij-plugins.pre"

usage() {
  cat <<USAGE
uso: $(basename "$0") [-h] [-z]

Opcional (fora do 00). Configura um IntelliJ IDEA já instalado (o 12,
ou snap, Toolbox, tarball ou flatpak, achados como no 12) para a
disciplina:
- copia scripts/files/intellij para ~/.config/JetBrains/<produto><ver>
  (keymap TSI34D, do Linux, com alt+i/j/k/l; tema Darcula; JetBrains
  Mono; PSR-12 com linha de 130 para PHP; .phtml como PHP; live
  templates fw* e file templates TSI34D-* do framework; projeto novo
  com PHP 8.3, phpcs.xml e phpstan.neon do projeto). Um arquivo que já
  existia lá é guardado e volta no -z;
- instala a fonte JetBrains Mono ($FONT_PKG, apt) se faltar;
- instala os plugins de scripts/files/intellij/plugins.txt pela linha
  de comando do IDE ("installPlugins"; não abre janela). Sem rede,
  avisa e segue: rode de novo depois.

PHP exige a edição paga (estudante: licença educacional gratuita da
JetBrains). Numa Community o script avisa e copia a configuração, mas
os plugins de PHP não instalam. Feche o IDE antes de rodar.

  -h  mostra esta ajuda
  -z  desfaz: devolve os arquivos de configuração anteriores (ou apaga o
      diretório, se o script o criou), remove os plugins e a fonte que
      instalou
USAGE
}

record_dir_if_new() { # dir: anota em created_dirs se ainda não existe
  [[ -e "$1" ]] && return 0
  local list
  list="$(state_get created_dirs)"
  state_set created_dirs "${list:+$list }$1"
}

# Escolhe a instalação: a primeira que não é Community/Educational; se
# só houver essas, a primeira (com aviso). Grava os campos no estado.
pick_install() {
  step "IntelliJ IDEA instalado"
  if [[ -n "$(state_get cfg_dir)" ]]; then
    ok "o mesmo da execução anterior: $(state_get product)" \
      "$(state_get version) ($(state_get edition), $(state_get origin))"
    return 0
  fi
  local line chosen="" origin name edition ver bin cfg plug cache dir
  while IFS= read -r line; do
    [[ -n "$line" ]] || continue
    info "achado: $(intellij_describe "$line")"
    [[ -n "$chosen" ]] || chosen="$line"
    case "$(cut -d'|' -f3 <<<"$line")" in
      community|educational) ;;
      *) [[ "$(cut -d'|' -f3 <<<"$chosen")" == unificado ]] \
           || chosen="$line" ;;
    esac
  done < <(intellij_detect)
  [[ -n "$chosen" ]] || die "nenhum IntelliJ IDEA encontrado. Instale" \
    "com scripts/12-install-intellij.sh (ou: sudo snap install" \
    "intellij-idea --classic) e rode de novo."
  IFS='|' read -r origin name edition ver bin cfg plug cache dir \
    <<<"$chosen"
  ok "usando: $(intellij_describe "$chosen")"
  if [[ "$edition" == unificado && "$origin" == *community* ]]; then
    info "o snap ${origin#snap:} $ver já traz o produto único (IU), igual"
    info "ao intellij-idea: PHP funciona com a licença (educacional)."
  fi
  if [[ "$edition" != unificado ]]; then
    warn "edição $edition: sem suporte a PHP. A configuração é copiada," \
      "mas os plugins de PHP não vão instalar nem funcionar. Para PHP:" \
      "sudo snap install intellij-idea --classic (ou" \
      "scripts/12-install-intellij.sh) e licença educacional gratuita" \
      "da JetBrains (jetbrains.com/student)."
  fi
  state_set origin "$origin"
  state_set product "$name"
  state_set edition "$edition"
  state_set version "$ver"
  state_set bin "$bin"
  state_set cfg_dir "$cfg"
  state_set plugins_dir "$plug"
  state_set cache_dir "$cache"
  state_set ide_dir "$dir"
}

# Anota o que já existia, antes de rodar o IDE (o installPlugins cria
# config, cache, ~/.java e ~/snap/<nome>). Só na primeira execução.
record_preexisting() {
  [[ -z "$(state_get recorded)" ]] || return 0
  local cfg plug cache origin d
  cfg="$(state_get cfg_dir)"
  plug="$(state_get plugins_dir)"
  cache="$(state_get cache_dir)"
  origin="$(state_get origin)"
  mkdir -p "$S50_STATE_DIR"
  if [[ -d "$cfg" ]]; then
    state_set cfg_preexisting 1
    (cd "$cfg" && find . -type f | sort) > "$CFG_PRE"
  else
    state_set cfg_preexisting 0
  fi
  if [[ -d "$plug" ]]; then
    state_set plugins_preexisting 1
    (cd "$plug" && ls -A) > "$PLUGINS_PRE"
  else
    state_set plugins_preexisting 0
  fi
  if [[ -e "$cache" ]]; then state_set cache_preexisting 1
  else state_set cache_preexisting 0; fi
  if [[ -e "$HOME/.java" ]]; then state_set java_preexisting 1
  else state_set java_preexisting 0; fi
  if [[ "$origin" == snap:* ]]; then
    record_dir_if_new "$HOME/snap"
    record_dir_if_new "$HOME/snap/${origin#snap:}"
  fi
  for d in "$(dirname "$cfg")" "$(dirname "$plug")" "$(dirname "$cache")" \
      "$HOME/.config" "$HOME/.local/share" "$HOME/.cache" "$HOME/.local"; do
    record_dir_if_new "$d"
  done
  state_set recorded 1
}

font_present() {
  if command -v fc-list >/dev/null 2>&1 \
      && fc-list 2>/dev/null | grep -qi 'JetBrains Mono'; then
    return 0
  fi
  apt_installed "$FONT_PKG"
}

install_font() {
  step "Fonte JetBrains Mono"
  if [[ "$(state_get font_done)" == 1 ]]; then
    ok "já tratada numa execução anterior"
    return 0
  fi
  if font_present; then
    state_set installed_packages ""
    ok "já instalada (fora do -z)"
  elif sudo_ok; then
    apt_update_once
    apt_install_tracked installed_packages "$FONT_PKG"
    ok "instalada: $(state_get installed_packages)"
  else
    warn "sem sudo: $FONT_PKG não instalado. O IDE já traz a JetBrains" \
      "Mono embutida; o editor funciona igual."
    state_set installed_packages ""
  fi
  state_set font_done 1
}

# plugin_ids [marketplace|bundled]: IDs do plugins.txt, um por linha.
# Os bundled são os que vêm depois da linha "# === bundled".
plugin_ids() {
  awk -v want="$1" '
    /^# === marketplace/ { sec = "marketplace"; next }
    /^# === bundled/ { sec = "bundled"; next }
    /^# === /        { sec = "outro"; next }
    { sub(/^[ \t]+/, ""); sub(/[ \t]+$/, "") }
    /^#/ || /^$/     { next }
    sec == want      { print }
  ' "$PLUGINS_TXT"
}

# IDs dos plugins que vêm com o IDE (lidos dos plugin.xml de
# <dir>/plugins), um por linha. Vazio se não houver python3.
ide_bundled_ids() {
  command -v python3 >/dev/null 2>&1 || return 0
  python3 -I - "$(state_get ide_dir)/plugins" <<'PY' 2>/dev/null || true
import glob, re, sys, zipfile
for d in glob.glob(sys.argv[1] + "/*/lib"):
    for j in sorted(glob.glob(d + "/*.jar")):
        try:
            x = zipfile.ZipFile(j).read("META-INF/plugin.xml").decode()
        except Exception:
            continue
        m = re.search(r"<id>([^<]+)</id>", x) or \
            re.search(r"<name>([^<]+)</name>", x)
        if m:
            print(m.group(1).strip())
        break
PY
}

install_plugins() {
  step "Plugins (plugins.txt, pela linha de comando do IDE)"
  [[ -s "$PLUGINS_TXT" ]] \
    || die "não achei $PLUGINS_TXT; o clone está incompleto?"
  local -a bin
  local id out log fails=0
  read -r -a bin <<<"$(state_get bin)"
  if command -v curl >/dev/null 2>&1 \
      && ! curl -sf -m 15 -o /dev/null https://plugins.jetbrains.com/
  then
    warn "sem acesso a plugins.jetbrains.com: plugins não instalados." \
      "Com rede, rode de novo: $SCRIPT_PATH -z && $SCRIPT_PATH"
    return 0
  fi
  local bundled
  rm -f "$S50_STATE_DIR/intellij-plugins.extra"
  bundled="$(ide_bundled_ids)"
  while IFS= read -r id; do
    if grep -qxF "$id" <<<"$bundled"; then
      ok "$id: bundled (já vem com o IDE)"
    else
      warn "$id: devia vir com o IDE e não achei; tento pelo marketplace"
      echo "$id" >> "$S50_STATE_DIR/intellij-plugins.extra"
    fi
  done < <(plugin_ids bundled)
  log="$(mktemp)"
  while IFS= read -r id; do
    if (cd "$HOME" && timeout 600 "${bin[@]}" installPlugins "$id") \
        </dev/null >"$log" 2>&1; then :; fi
    out="$(grep -E '^(installed plugin|already installed|unknown plugins)' \
      "$log" | head -n 1 || true)"
    case "$out" in
      "installed plugin"*)  ok "$id: instalado" ;;
      "already installed"*) ok "$id: já estava (bundled ou instalado)" ;;
      "unknown plugins"*)
        warn "$id: sem versão compatível com este IDE no marketplace"
        fails=$((fails + 1)) ;;
      *)
        warn "$id: falhou ($(grep -vE '^(INFO|WARN|[A-Z][a-z]{2} [0-9])' \
          "$log" | tail -n 1))"
        fails=$((fails + 1)) ;;
    esac
  done < <(plugin_ids marketplace
    sort -u "$S50_STATE_DIR/intellij-plugins.extra" 2>/dev/null || true)
  rm -f "$log" "$S50_STATE_DIR/intellij-plugins.extra"
  (( fails == 0 )) || warn "$fails plugin(s) não instalado(s); o resto" \
    "segue. Se o IDE estava aberto, feche-o e rode -z e de novo."
}

# Copia scripts/files/intellij para o diretório de config. Arquivo que
# já existia (e é diferente) vai para o backup, uma vez só.
install_config() {
  step "Configuração em $(state_get cfg_dir)"
  local cfg part rel
  cfg="$(state_get cfg_dir)"
  for part in "${CFG_PARTS[@]}"; do
    [[ -d "$CFG_SRC/$part" ]] \
      || die "não achei $CFG_SRC/$part; o clone está incompleto?"
  done
  mkdir -p "$cfg"
  while IFS= read -r rel; do
    rel="${rel#./}"
    if [[ -f "$cfg/$rel" && ! -e "$BACKUP_DIR/$rel" ]] \
        && grep -qxF "./$rel" "$CFG_PRE" 2>/dev/null \
        && ! cmp -s "$CFG_SRC/$rel" "$cfg/$rel"; then
      mkdir -p "$(dirname "$BACKUP_DIR/$rel")"
      cp -p "$cfg/$rel" "$BACKUP_DIR/$rel"
      warn "$rel já existia; guardado (volta no -z)"
    fi
    mkdir -p "$(dirname "$cfg/$rel")"
    cp "$CFG_SRC/$rel" "$cfg/$rel"
  done < <(cd "$CFG_SRC" && find "${CFG_PARTS[@]}" -type f | sort)
  ok "keymap TSI34D, Darcula, JetBrains Mono, PSR-12, templates fw*"
}

verify() {
  step "Teste"
  local cfg plug f bad=0
  cfg="$(state_get cfg_dir)"
  plug="$(state_get plugins_dir)"
  for f in options/linux/keymap.xml keymaps/TSI34D.xml codestyles/Default.xml \
      templates/TSI34D.xml; do
    [[ -s "$cfg/$f" ]] || { fail "faltou $cfg/$f"; bad=1; }
  done
  if command -v python3 >/dev/null 2>&1; then
    if (cd "$cfg" && find "${CFG_PARTS[@]}" -name '*.xml' -print0 \
        | xargs -0 python3 -I -c 'import sys, xml.dom.minidom as m
for f in sys.argv[1:]: m.parse(f)'); then
      ok "XML bem formado em todos os arquivos copiados"
    else
      fail "algum XML copiado está malformado"; bad=1
    fi
  fi
  if [[ -d "$plug" ]]; then
    ok "plugins em $plug:"
    find "$plug" -mindepth 1 -maxdepth 1 -printf '    %f\n' | sort
  else
    warn "nenhum plugin de marketplace instalado"
  fi
  if font_present; then ok "fonte JetBrains Mono no sistema"
  else warn "fonte JetBrains Mono só a embutida no IDE"; fi
  (( bad == 0 )) || die "configuração incompleta; veja as falhas acima."
}

run() {
  refuse_if_done
  warn_if_not_ubuntu_2404
  pick_install
  record_preexisting
  install_font
  install_plugins
  install_config
  local cfg
  cfg="$(state_get cfg_dir)"
  (cd "$cfg" && find . -type f | sort) > "$CFG_POST"
  verify
  state_mark_done
  info
  info "Pronto. Abra o IntelliJ: keymap TSI34D (Settings > Keymap), tema"
  info "Darcula. phpcs/phpstan e o interpretador PHP valem para projetos"
  info "abertos pela primeira vez; veja scripts/README.md (seção 12-1)."
}

undo() {
  undo_or_nothing
  local cfg plug cache origin name d rel pkgs
  cfg="$(state_get cfg_dir)"
  plug="$(state_get plugins_dir)"
  cache="$(state_get cache_dir)"
  origin="$(state_get origin)"

  step "Plugins"
  if [[ -z "$plug" ]]; then
    :
  elif [[ "$(state_get plugins_preexisting)" == 0 ]]; then
    rm -rf "$plug"
    ok "removido $plug (não existia antes)"
  elif [[ -d "$plug" ]]; then
    while IFS= read -r name; do
      grep -qxF "$name" "$PLUGINS_PRE" 2>/dev/null && continue
      rm -rf "${plug:?}/$name"
      ok "plugin removido: $name"
    done < <(cd "$plug" && ls -A)
  fi

  step "Configuração"
  if [[ -z "$cfg" ]]; then
    :
  elif [[ "$(state_get cfg_preexisting)" == 0 ]]; then
    rm -rf "$cfg"
    ok "removido $cfg (não existia antes; login e ajustes feitos no IDE" \
      "depois também saem)"
  elif [[ -d "$cfg" ]]; then
    # Sai o que esta execução criou (arquivos do post que não estavam no
    # pre); voltam os que foram guardados.
    while IFS= read -r rel; do
      grep -qxF "$rel" "$CFG_PRE" 2>/dev/null && continue
      rm -f "$cfg/${rel#./}"
    done < <(cat "$CFG_POST" 2>/dev/null)
    if [[ -d "$BACKUP_DIR" ]]; then
      (cd "$BACKUP_DIR" && find . -type f) | while IFS= read -r rel; do
        mkdir -p "$(dirname "$cfg/$rel")"
        cp -p "$BACKUP_DIR/$rel" "$cfg/$rel"
      done
    fi
    find "$cfg" -mindepth 1 -type d -empty -delete 2>/dev/null || true
    ok "arquivos anteriores restaurados em $cfg"
  fi
  if [[ -n "$cache" && "$(state_get cache_preexisting)" == 0 ]]; then
    rm -rf "$cache"
    ok "cache $cache removido"
  fi
  if [[ "$(state_get java_preexisting)" == 0 && -e "$HOME/.java" ]]; then
    rm -rf "$HOME/.java"
    ok "$HOME/.java (preferências Java criadas pelo IDE) removido"
  fi
  if [[ "$origin" == snap:* ]]; then
    name="$HOME/snap/${origin#snap:}"
    # Enquanto o snap existe, o snapd cuida desta pasta (o 12 -z a tira
    # junto com o snap); só some aqui se der.
    if [[ " $(state_get created_dirs) " == *" $name "* ]] \
        && ! rm -rf "$name" 2>/dev/null; then
      info "$name fica (do snapd; sai com o snap)"
    fi
  fi
  for d in $(state_get created_dirs); do
    # Em ~/.local/share/JetBrains o IDE deixa "discovery" (vazio).
    if [[ "$(basename "$d")" == JetBrains && -d "$d" ]]; then
      find "$d" -mindepth 1 -maxdepth 1 -type d -empty -delete \
        2>/dev/null || true
    fi
    rmdir "$d" 2>/dev/null || true
  done

  pkgs="$(state_get installed_packages)"
  if [[ -n "$pkgs" ]]; then
    step "Removendo pacotes"
    info "$pkgs"
    require_sudo
    apt_purge_safe "$pkgs"
  fi
  rm -rf "$BACKUP_DIR" "$CFG_PRE" "$CFG_POST" "$PLUGINS_PRE"
  state_remove
  ok "Desfeito."
}

parse_flags "$@"
"$ACTION"
