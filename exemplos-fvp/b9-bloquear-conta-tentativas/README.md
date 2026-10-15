# B9. Bloquear a conta depois de N tentativas erradas

Pedido, como o professor faria na hora: "Depois de 5 senhas erradas
seguidas, a conta fica bloqueada, mesmo que a senha certa seja digitada."

Chance: baixa. Tempo estimado: 30 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção B9.

Branch no clone: `fvp/b9-bloquear-conta-tentativas`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `7bc6f62` | `feat: Lock account after repeated failed logins` |

## O que mudou

- `database/schema.sql`: coluna `failed_attempts INT NOT NULL DEFAULT 0` em
  `users`.
- `app/Models/User.php`: `@property int $failed_attempts`, coluna em `$columns`,
  `MAX_ATTEMPTS = 5`, construtor zera o contador de registro novo, métodos
  `registerFailedAttempt()`, `resetFailedAttempts()` e `isLocked()`.
- `app/Controllers/AuthenticationsController.php`: `authenticate` verifica o
  bloqueio antes da senha (mensagem "Conta bloqueada por excesso de
  tentativas."), zera o contador no sucesso e soma uma tentativa na falha.
- `tests/Unit/Models/Users/UserTest.php`: 5 testes do contador.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`: 3 testes,
  helper `loginWith()` e `tearDown` que limpa `$_SESSION['flash']`.
- `tests/Acceptance/authentications/LoginCest.php`: fluxo de bloqueio no
  navegador.

## Por que assim

- Controller fino, regra no model (`isLocked`, contador): feedback de
  `grading-path.md` sobre lógica fora do controller.
- Parâmetros só via `$request->getParam()`, como já estava.
- Coluna e identificadores em inglês; mensagem da interface em pt-BR
  (`CLAUDE.md`, `grading-path.md`).
- `NOT NULL DEFAULT 0` mais o zero no construtor: `save()` insere todas as
  colunas de `$columns`; com `null` o MySQL recusaria o INSERT.
- `(int)` antes de somar e comparar: o PDO devolve números como string.
- `if / elseif / else`: no teste de controller o `redirectTo` não
  interrompe a ação; assim só sai um `Location:` (o teste confere isso).
- Testes em três camadas (unit, controller, aceitação); PSR-12 e PHPStan 6
  verdes; commit em inglês `type: Title`.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run db:reset && ./run db:populate   # só se o schema mudou
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 76 testes, Integration 12, Acceptance 10 (eram
71 / 9 / 9 na base). Testes novos:

- `UserTest`: `test_failed_attempts_should_be_zero_by_default`,
  `test_register_failed_attempt_should_increment_counter`,
  `test_is_locked_should_be_false_before_max_attempts`,
  `test_is_locked_should_be_true_after_max_attempts`,
  `test_reset_failed_attempts_should_zero_counter_and_unlock`.
- `AuthenticationsControllerTest`:
  `test_authenticate_with_invalid_credentials_should_increment_failed_attempts`,
  `test_authenticate_after_max_failed_attempts_should_lock_account` (5 erros,
  6ª com a senha certa não loga, um único `Location: /login` e flash de
  bloqueio),
  `test_authenticate_with_valid_credentials_should_reset_failed_attempts`.
- `LoginCest::loginShouldBeBlockedAfterMaxFailedAttempts`.

No navegador: `http://localhost/login` — entre 5 vezes com `user@example.com`
e senha errada ("E-mail ou senha inválidos!"); na 6ª, com `123456`, aparece
"Conta bloqueada por excesso de tentativas." e a página continua em `/login`.
Para desbloquear: `./run db:reset && ./run db:populate`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/b9-bloquear-conta-tentativas`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-b9 s50/exemplos-fvp/b9-bloquear-conta-tentativas/projeto
git fetch fvp-b9 fvp/b9-bloquear-conta-tentativas
git cherry-pick 7bc6f62
git remote remove fvp-b9
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/b9-bloquear-conta-tentativas/solucao.bundle fvp/b9-bloquear-conta-tentativas:fvp-b9
git cherry-pick 7bc6f62        # o bundle traz o objeto; o cherry-pick cria um commit novo
git branch -D fvp-b9           # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/b9-bloquear-conta-tentativas/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/b9-bloquear-conta-tentativas/solucao.patch   # só o diff, sem commit
```

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Controllers/AuthenticationsController.php` (`authenticate`): também
  mexem A4, A9, A10, A11, B5, C3, C4, H1. Mantenha o bloqueio como primeiro
  ramo (depois de checagens de campo vazio do A9 e de conta inativa do B5),
  `resetFailedAttempts()` antes do `Auth::login` e `registerFailedAttempt()`
  no `else`. Com H1 (`Auth::attempt`), busque o usuário antes para checar
  `isLocked()` e registrar a falha.
- `app/Models/User.php`: B3, B5, A7, D3 e outros adicionam colunas no mesmo
  `$columns` e no docblock; junte as duas listas. B5 também mexe no construtor.
- `database/schema.sql`: B3/B5/A7/D3 adicionam linhas depois de
  `last_login_at`; mantenha todas.
- Testes (`UserTest`, `AuthenticationsControllerTest`, `LoginCest`): métodos
  novos no fim da classe; mantenha os dois lados. Se C3 trocou "E-mail ou
  senha inválidos!", ajuste o `see` do `LoginCest` novo.

Depois de aplicar: `./run db:reset` se o schema mudou; rode os testes
e o `./run phpcs` de novo.

## Armadilhas

- Sem `./run db:reset`, o login dá `Unknown column 'failed_attempts'` no
  navegador.
- Sem zerar no construtor, `save()` manda `NULL` para uma coluna `NOT NULL`
  e o cadastro falha.
- `update([...])` não passa por `validates()` e devolve `false` quando nada
  mudou (`rowCount() === 0`); por isso os testes conferem o valor relido com
  `findById`, não o retorno.
- O bloqueio só sai com reset do contador no banco; não há tela de
  desbloqueio (fora do escopo).
- Os testes de controller deixam `$_SESSION['flash']` na sessão; o
  `tearDown` da classe limpa.
