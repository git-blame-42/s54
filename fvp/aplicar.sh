#!/usr/bin/env bash
# aplicar.sh: aplica, no clone do projeto, a solução pronta de um cenário
# FVP (fvp/<escopo>/exemplos/<id>-<slug>/solucao.bundle), por cherry-pick.
# Rode de dentro do clone do projeto, no branch que vai receber a solução.
#
#   aplicar.sh <ID>               mostra os commits, aplica no branch atual
#                                 e roda phpcs, phpstan e os testes do
#                                 README do cenário (seção Como verificar)
#   aplicar.sh --dry-run <ID>     só diz se aplica, sem mexer em nada
#   aplicar.sh --escopo 4.2 <ID>  escolhe o escopo (padrão: o que tiver
#                                 o ID; 4.1 e 4.2 não repetem IDs)
#   aplicar.sh --base main <ID>   com --dry-run: testa contra outro ref
#                                 (padrão: HEAD)
#   aplicar.sh --sem-testes <ID>  aplica e não roda phpcs/phpstan/testes
#   aplicar.sh -h                 esta ajuda
#
# Funciona offline (lê o bundle). Commits que o branch já tem (mesmo
# patch, como a base de um cenário empilhado) são pulados. Em conflito,
# desfaz o cherry-pick, lista os arquivos e aponta o README do cenário.
# Saída do --dry-run: 0 aplica, 1 conflito, 3 já aplicado, 4 cenário só
# de README; 2 é erro.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="${FVP_DIR:-$DIR}"

help() { sed -n '2,22p' "$0" | sed 's/^# \{0,1\}//'; }
die() { printf '[FALHA] %s\n' "$*" >&2; exit 2; }
info() { printf '%s\n' "$*"; }

DRY=0 TESTS=1 SCOPE='' BASE='' ID=''
while (( $# )); do
  case "$1" in
    -h|--help) help; exit 0 ;;
    --dry-run) DRY=1 ;;
    --sem-testes) TESTS=0 ;;
    --escopo) SCOPE="${2:?--escopo precisa de valor, ex.: 4.2}"; shift ;;
    --base) BASE="${2:?--base precisa de um ref, ex.: main}"; shift ;;
    -*) die "opção desconhecida: $1 (veja -h)" ;;
    *) [[ -z "$ID" ]] || die "um ID por vez (veio $ID e $1)"; ID="$1" ;;
  esac
  shift
done
[[ -n "$ID" ]] || { help >&2; exit 2; }
[[ -z "$BASE" || "$DRY" == 1 ]] || die "--base só vale com --dry-run"
BASE="${BASE:-HEAD}"

git rev-parse --is-inside-work-tree >/dev/null 2>&1 \
  || die "rode de dentro do clone do projeto (aqui não é um repositório)."
TOP="$(git rev-parse --show-toplevel)"
cd "$TOP"
git rev-parse -q --verify "$BASE^{commit}" >/dev/null \
  || die "ref não encontrado: $BASE"

# --- ID -> diretório do exemplo ----------------------------------------

id_lower="${ID,,}"
id_upper="${ID^^}"
matches=()
for d in "$ROOT"/*/exemplos/"$id_lower"-*/; do
  [[ -d "$d" ]] || continue
  s="$(basename "$(dirname "$(dirname "$d")")")"
  [[ -z "$SCOPE" || "$s" == "$SCOPE" ]] && matches+=("${d%/}")
done
if (( ${#matches[@]} == 0 )); then
  die "sem exemplo pronto para $id_upper${SCOPE:+ no escopo $SCOPE}." \
    "Veja o catálogo: fvp.sh -s $id_upper"
fi
if (( ${#matches[@]} > 1 )); then
  die "$id_upper existe em mais de um escopo; use --escopo:" \
    "${matches[*]#"$ROOT"/}"
fi
EX="${matches[0]}"
SLUG="$(basename "$EX")"
SCOPE="$(basename "$(dirname "$(dirname "$EX")")")"
TITLE="$(sed -n "s/^### $id_upper\. //p" "$ROOT/$SCOPE/catalogo.md" \
  2>/dev/null | head -n 1)"
info "$id_upper. ${TITLE:-$SLUG}"
info "exemplo: fvp/$SCOPE/exemplos/$SLUG"

BUNDLE="$EX/solucao.bundle"
if [[ ! -f "$BUNDLE" ]]; then
  info "Cenário só de README (sem commits): leia $EX/README.md"
  [[ "$DRY" == 1 ]] && info "RESULTADO: so-readme"
  exit 4
fi

# --- commits do bundle -------------------------------------------------

heads="$(git bundle list-heads "$BUNDLE")" \
  || die "bundle ilegível: $BUNDLE"
main_sha="$(awk '$2 == "refs/heads/main" {print $1}' <<<"$heads")"
tip="$(awk -v r="refs/heads/fvp/$SLUG" '$2 == r {print $1}' <<<"$heads")"
[[ -n "$main_sha" && -n "$tip" ]] \
  || die "bundle sem main ou sem fvp/$SLUG: $BUNDLE"
if ! git cat-file -e "$tip^{commit}" 2>/dev/null; then
  # Traz só os objetos (FETCH_HEAD); não cria branch.
  git fetch -q "$BUNDLE" "refs/heads/fvp/$SLUG" \
    || die "não consegui ler o bundle $BUNDLE"
fi

# "+ sha" falta no BASE; "- sha" o BASE já tem (mesmo patch).
mapfile -t cherry < <(git cherry "$BASE" "$tip" "$main_sha")
todo=()
info
info "Commits do cenário (bundle main..fvp/$SLUG), contra $BASE:"
for line in "${cherry[@]}"; do
  sha="${line#? }"
  subj="$(git log -1 --format='%h %s' "$sha")"
  if [[ "$line" == +* ]]; then
    todo+=("$sha")
    info "  aplicar     $subj"
  else
    info "  já aplicado $subj"
  fi
done
if (( ${#todo[@]} == 0 )); then
  info "Nada a aplicar: $BASE já tem todos os commits do cenário."
  [[ "$DRY" == 1 ]] && info "RESULTADO: ja-aplicado"
  exit 3
fi

# --- dry-run: cherry-pick simulado (git merge-tree, sem índice) --------

simulate() { # base commits... -> imprime arquivos em conflito
  local cur="$1" c out tree rc
  shift
  for c in "$@"; do
    rc=0
    out="$(git merge-tree --write-tree --no-messages --name-only \
      --merge-base="$c^" "$cur" "$c")" || rc=$?
    if (( rc == 1 )); then
      printf '%s\n' "$out" | tail -n +2 | sort -u
      return 1
    fi
    (( rc == 0 )) || die "git merge-tree falhou (precisa de git >= 2.38)"
    tree="$(head -n 1 <<<"$out")"
    cur="$(GIT_AUTHOR_NAME=fvp GIT_AUTHOR_EMAIL=fvp@localhost \
      GIT_COMMITTER_NAME=fvp GIT_COMMITTER_EMAIL=fvp@localhost \
      git commit-tree "$tree" -p "$cur" -m simulado)"
  done
}

if [[ "$DRY" == 1 ]]; then
  rc=0
  conflicts="$(simulate "$(git rev-parse "$BASE")" "${todo[@]}")" || rc=$?
  if (( rc == 0 )); then
    info "Aplica sem conflito em $BASE (${#todo[@]} commit(s))."
    info "RESULTADO: aplica"
    exit 0
  fi
  (( rc == 1 )) || exit 2
  info "Conflito em: $(xargs <<<"$conflicts")"
  info "Veja 'Se der conflito' em $EX/README.md"
  info "RESULTADO: conflito $(xargs <<<"$conflicts")"
  exit 1
fi

# --- aplicar de verdade ------------------------------------------------

[[ -n "$(git config user.email || true)" ]] \
  || die "git sem identidade. Configure: git config user.name 'Nome';" \
    "git config user.email 'voce@exemplo.com'"
if ! git diff --quiet || ! git diff --cached --quiet; then
  die "há alterações não commitadas. Faça commit ou git stash antes."
fi
if git rev-parse -q --verify CHERRY_PICK_HEAD >/dev/null; then
  die "há um cherry-pick em andamento. Termine (--continue) ou" \
    "desista (git cherry-pick --abort) antes."
fi

before="$(git rev-parse --short HEAD)"
branch="$(git branch --show-current)"
info
info "Aplicando em ${branch:-HEAD destacado} ($before)..."
if ! git -c advice.mergeConflict=false cherry-pick "${todo[@]}"; then
  conflicts="$(git diff --name-only --diff-filter=U | xargs)"
  git cherry-pick --abort 2>/dev/null || true
  printf '[FALHA] conflito em: %s\n' "${conflicts:-?}" >&2
  info "Nada ficou aplicado (cherry-pick desfeito; HEAD em $before)."
  info "Como resolver: seção 'Se der conflito' de $EX/README.md."
  info "À mão: git cherry-pick $(git rev-parse --short "${todo[@]}" \
    | tr '\n' ' ')"
  info "  edite os arquivos, git add <arquivo>, git cherry-pick --continue"
  exit 1
fi
info "Aplicado: $(git rev-list --count "$before"..HEAD) commit(s)." \
  "Para desfazer: git reset --hard $before"

(( TESTS )) || exit 0

# --- verificação: phpcs, phpstan e os comandos do README ---------------

[[ -x ./run ]] || { info "sem ./run aqui; pulei os testes."; exit 0; }

# Linhas ./run e do selenium do primeiro bloco de "## Como verificar".
mapfile -t cmds < <(awk '
  /^## Como verificar/ { sec = 1; next }
  sec && /^## / { exit }
  sec && /^```/ { if (blk) exit; blk = 1; next }
  blk { print }
' "$EX/README.md" | sed 's/[[:space:]]#.*$//' | grep -E \
  '^(\./run |docker compose (up -d|stop) selenium)' || true)
printf '%s\n' "${cmds[@]}" | grep -qx './run phpstan' \
  || cmds=('./run phpstan' "${cmds[@]}")
printf '%s\n' "${cmds[@]}" | grep -qx './run phpcs' \
  || cmds=('./run phpcs' "${cmds[@]}")
# Selenium que já estava de pé (./run up -d sobe ele) continua de pé.
if [[ -n "$(docker compose ps -q --status running selenium 2>/dev/null)" ]]
then
  mapfile -t cmds < <(printf '%s\n' "${cmds[@]}" \
    | grep -vx 'docker compose stop selenium' || true)
fi

results=() failed=0
for c in "${cmds[@]}"; do
  info
  info "==> $c"
  # </dev/null: o ./run composer usa docker run --interactive.
  if bash -c "$c" </dev/null; then
    results+=("OK     $c")
  else
    results+=("FALHA  $c")
    failed=1
  fi
done
info
info "==> Resultado ($id_upper, aplicado sobre $before)"
printf '  %s\n' "${results[@]}"
if (( failed )); then
  info "Há FALHA. Compare com 'Resultado obtido' do README do cenário."
  info "Para desfazer a aplicação: git reset --hard $before"
  exit 1
fi
