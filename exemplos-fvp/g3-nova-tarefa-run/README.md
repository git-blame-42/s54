# G3. Nova tarefa no script run

Pedido, como o professor faria na hora: "Adicionem ao `run` uma tarefa
`db:seed`, que recria e popula o banco, e uma tarefa `ci`, que roda
localmente as verificações do job de qualidade do CI".

Chance: baixa. Tempo estimado: 5 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção G3.

Branch no clone: `fvp/g3-nova-tarefa-run`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `de8a469` | `chore: Add db:seed and ci tasks to run script` |

## O que mudou

- `run`: nova função `db:seed`, que chama `db:reset` e depois
  `db:populate`.
- `run`: nova função `ci`, que chama `composer dump-autoload --optimize
  --strict-psr`, `phpcs` e `phpstan` (esta já faz `codecept build`).
- `README.md` (do projeto): `./run db:seed` documentado logo após
  "Populate database"; nova subseção "Run the CI checks locally" com
  `./run ci`, logo após os linters.

## Por que assim

- Cada tarefa do `run` é uma função bash; o `time ${@}` no fim chama a
  função pelo primeiro argumento. Basta declarar `function db:seed`
  e `function ci`.
- Reaproveita as funções existentes em vez de copiar comandos: se o
  `db:reset` ou o `phpstan` mudarem, as tarefas novas acompanham.
- `ci` espelha os passos do job `code_quality` de
  `.github/workflows/ci.yml` (PSR-4 Checker, Codeception build, PHPCS,
  PHPStan). O `codecept build` não aparece explícito porque a função
  `phpstan` do `run` já o executa; chamá-lo de novo só duplicaria
  trabalho.
- A função `composer` do `run` repassa os argumentos (`"${@}"`), por
  isso a chamada é `composer dump-autoload --optimize --strict-psr`.
- O `run` usa `set -o errexit`: se `phpcs` falhar, `phpstan` não roda
  e `./run ci` sai com código diferente de zero, como no CI.
- Comentário curto em pt-BR acima de cada função; nomes das tarefas e
  mensagem de commit em inglês (`grading-path.md`).
- ShellCheck (`koalaman/shellcheck:stable`) antes e depois: os mesmos
  13 avisos herdados (SC1091, SC2068, SC2086, SC2124); nenhum novo.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:seed        # prova a tarefa 1
./run db:console     # SELECT COUNT(*) FROM users;  -> 2
./run ci             # prova a tarefa 2
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Saída resumida de `./run db:seed`:

```
 fvp-g3-db-1 Copied database/schema.sql to fvp-g3-db-1:/tmp/
Usuários criados: admin@example.com (admin) e user@example.com (participant), senha 123456
```

`SELECT COUNT(*) FROM users;` depois do `db:seed`: 2 (também 2 ao rodar
`db:seed` uma segunda vez, porque o reset recria as tabelas).

Saída resumida de `./run ci` (código de saída 0):

```
Generated optimized autoload files containing 2732 classes
...................................................... 54 / 54 (100%)
Building Actor classes for suites: acceptance
 50/50 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%
 [OK] No errors
Task completed in 0m5.180s
```

Resultado obtido: Unit 71 testes, Integration 9, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos: nenhum; a mudança é só no script
bash e na documentação, e a prova é rodar as duas tarefas.

No navegador: nada muda na aplicação. Depois do `./run db:seed`, o
login em `http://localhost` com `admin@example.com` / `123456` funciona.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `s50/exemplos-fvp/g3-nova-tarefa-run`
abaixo é o diretório deste cenário no s50.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-g3 s50/exemplos-fvp/g3-nova-tarefa-run/projeto
git fetch fvp-g3 fvp/g3-nova-tarefa-run
git cherry-pick de8a469
git remote remove fvp-g3
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/g3-nova-tarefa-run/solucao.bundle fvp/g3-nova-tarefa-run:fvp-g3
git cherry-pick de8a469        # o bundle traz o objeto; o cherry-pick cria um commit no alvo
git branch -D fvp-g3           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/g3-nova-tarefa-run/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/g3-nova-tarefa-run/solucao.patch   # só o diff, sem commit
```

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê: só `run` e
`README.md`, e o risco é baixo. No `run`, as funções novas entram logo
depois de `db:populate`; só conflita se a equipe também mexeu no fim
do arquivo (outra tarefa nova perto dali). No `README.md`, os trechos
entram após "Populate database" e após o bloco do PHPStan; conflita se
essas seções foram reescritas. Em ambos, a resolução é manter os dois
lados.

Depois de aplicar: não há mudança de schema; rode `./run ci` e os
testes de novo.

## Armadilhas

- Nome da função igual a um comando do sistema: `ci` e `db:seed` não
  colidem, mas uma tarefa chamada, por exemplo, `test` ou `composer`
  sobrescreve o comando dentro do script (é o que o `run` já faz de
  propósito com `composer`, `phpcs` e `phpstan`).
- Chamar `composer dump-autoload` sem passar pela função do `run`:
  fora do container, depende do Composer instalado na máquina do
  laboratório.
- Repetir `codecept build` em `ci`: funciona, mas duplica o passo que
  `phpstan` já faz.
- `db:seed` antes do `./run up -d` ou com o MySQL ainda subindo:
  falha no `docker compose exec db`. Espere ~15 s.
- `db:seed` apaga tudo: é `db:reset` (recria as tabelas) seguido de
  `db:populate`. Não use em banco com dados que precisam ficar.
- Usar `./run ci` em vez de `./run test`: a tarefa `ci` reproduz só o
  job `code_quality`; os testes continuam em `./run test` e
  `./run test:browser`.
