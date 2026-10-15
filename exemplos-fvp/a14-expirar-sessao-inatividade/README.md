# A14. Expirar a sessão por inatividade

Pedido, como o professor faria na hora: "Se o usuário ficar 30 minutos
parado, a sessão deve expirar e ele tem de logar de novo."

Chance: baixa. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A14.

Branch no clone: `fvp/a14-expirar-sessao-inatividade`. `main` continua
igual ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `d67688a` | `feat: Expire session after inactivity` |

## O que mudou

- `lib/Authentication/Auth.php`: constante `Auth::TIMEOUT = 1800`
  (segundos); `login()` grava `$_SESSION['user']['last_activity']` e
  limpa o aviso de expiração; `user()` expira a sessão quando
  `time() - last_activity > TIMEOUT` (faz `logout()`, marca
  `$_SESSION['auth_expired']` e retorna `null`), senão renova
  `last_activity`; `check()` passa a ser `user() !== null`; novo
  `wasExpired()`, que lê e consome o aviso; `logout()` apaga
  `$_SESSION['user']` inteiro, não só o `id`.
- `app/Middleware/Authenticate.php`: sem login, a mensagem flash é
  "Sua sessão expirou. Faça login novamente." quando `Auth::wasExpired()`,
  senão a de sempre; `redirectTo` vira `protected` para o dublê de teste.
- `tests/Unit/Lib/Authentication/AuthTest.php`: 7 testes novos de
  expiração, renovação e limpeza da sessão.
- `tests/Unit/App/Middleware/AuthenticateDouble.php` (novo): dublê
  nomeado que guarda o destino do redirect em vez de `header()` + `exit`.
- `tests/Unit/App/Middleware/AuthenticateTest.php` (novo): 3 testes do
  middleware (atividade recente, sessão expirada, sem sessão).
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  1 teste novo, o login pelo controller inicia `last_activity`.

## Por que assim

- Regra de sessão fica no `Auth` (lib), e o middleware só decide a
  mensagem e o redirect: autorização em middleware, controllers finos
  (`grading-path.md`).
- O aviso de expiração fica numa chave fora de `$_SESSION['user']`
  porque em `/admin` o middleware `Admin` chama `Auth::user()` antes do
  `Authenticate`; sem o aviso, a expiração seria detectada no `Admin` e
  o `Authenticate` mostraria a mensagem genérica.
- Sessões antigas sem `last_activity` (logadas antes do deploy) não
  caem na hora: o valor padrão é `time()`, como no catálogo.
- Textos de interface em pt-BR; identificadores e chaves de sessão em
  inglês; commit em inglês `type: Title`.
- PSR-12 e PHPStan nível 6 sem erros; dublê de middleware como classe
  nomeada em `tests/Unit/App/Middleware/` (PSR-4).
- Testes em unit (Auth e middleware) e controller. Aceitação não cobre
  a expiração: o Selenium não consegue envelhecer `last_activity` sem
  esperar 30 minutos.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Schema não mudou; não precisa de `db:reset`.

Resultado obtido: Unit 81 testes, Integration 10, Acceptance 9 (eram
71 / 9 / 9 na base). Testes novos:

- `AuthTest` (`tests/Unit/Lib/Authentication/AuthTest.php`):
  `test_login_should_store_last_activity`,
  `test_check_with_recent_activity_should_be_true`,
  `test_check_after_timeout_should_be_false_and_clear_session`,
  `test_check_should_renew_last_activity`,
  `test_was_expired_should_be_consumed_after_first_read`,
  `test_login_should_clear_expired_flag`,
  `test_logout_should_clear_whole_user_session`.
- `AuthenticateTest` (`tests/Unit/App/Middleware/AuthenticateTest.php`):
  `test_handle_with_recent_activity_should_not_redirect`,
  `test_handle_after_timeout_should_redirect_with_expired_message`,
  `test_handle_without_session_should_redirect_with_login_message`.
- `AuthenticationsControllerTest`:
  `test_authenticate_with_valid_credentials_should_start_activity_clock`.

No navegador: `http://localhost`. Para ver sem esperar 30 minutos, troque
`TIMEOUT` para `10` em `lib/Authentication/Auth.php`, faça login, espere
mais de 10 s e recarregue `/dashboard`: volta para `/login` com "Sua
sessão expirou. Faça login novamente.". Navegando antes dos 10 s, o
relógio renova e a sessão continua. Volte o valor para `1800` depois.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do público (ou
de algo compatível). `s50/exemplos-fvp/a14-expirar-sessao-inatividade` abaixo
é o diretório deste cenário no s50.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a14 s50/exemplos-fvp/a14-expirar-sessao-inatividade/projeto
git fetch fvp-a14 fvp/a14-expirar-sessao-inatividade
git cherry-pick d67688a
git remote remove fvp-a14
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/a14-expirar-sessao-inatividade/solucao.bundle fvp/a14-expirar-sessao-inatividade:fvp-a14
git cherry-pick d67688a      # o cherry-pick cria um commit novo, com outro SHA
git branch -D fvp-a14        # opcional, limpa a ref
```

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/a14-expirar-sessao-inatividade/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/a14-expirar-sessao-inatividade/solucao.patch   # só o diff, sem commit
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

- `lib/Authentication/Auth.php`: A13 (regenerar sessão no login), H1
  (`Auth::attempt`), B5 e B9 também mexem em `login()`/`user()`. Mantenha
  as duas mudanças: as linhas de `last_activity` e `auth_expired` em
  `login()`, e a expiração no começo de `user()`.
- `app/Middleware/Authenticate.php`: A1 (redirect centralizado), A11
  (guardar a página pedida) e A8 mexem no `handle()` ou no `redirectTo`.
  Junte a escolha da mensagem com o que o outro cenário faz antes do
  redirect; se A1 já tirou o `redirectTo` do middleware, ajuste o dublê.
- `tests/Unit/App/Middleware/AuthenticateDouble.php` e
  `AuthenticateTest.php`: F1 e F2 criam arquivos com o mesmo nome.
  Fique com um dublê só e some os métodos de teste dos dois lados.
- `tests/Unit/Lib/Authentication/AuthTest.php`: outros cenários de
  `Auth` acrescentam testes no fim da classe; mantenha todos.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (schema não
muda).

## Armadilhas

- `logout()` precisa apagar `$_SESSION['user']` inteiro; só o `id`
  deixaria `last_activity` velho para o próximo login.
- `check()` antigo testava `isset(id)` antes de `user()`; agora tudo
  passa por `user()`, que é onde a expiração acontece.
- Em `/admin` o `Admin` roda antes do `Authenticate` e já expira a
  sessão; por isso o aviso fica em `$_SESSION['auth_expired']`.
- `ControllerTestCase::tearDown` limpa só `$_SESSION['user']`; os testes
  novos zeram `$_SESSION` no `setUp`/`tearDown` para não vazar o aviso.
- `redirectTo` do middleware precisa ser `protected` para o dublê; com
  `private`, o dublê chama o original e o `exit` mata o PHPUnit.
