# A12. Middleware Admin autossuficiente (ordem dos middlewares)

Pedido, como o professor faria na hora: "Garanta que o middleware admin
proteja a rota sozinho, sem depender de o Authenticate rodar antes."

Chance: média-baixa. Tempo estimado: 10 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A12.

Este cenário é empilhado sobre o A1 (usa o `deny()` do `BaseMiddleware`).
Branch no clone: `fvp/a12-admin-autossuficiente`, com dois commits acima
de `main`. `main` continua igual ao repositório público (`86c81ca`).

| Commit | Mensagem | Origem |
|--------|----------|--------|
| `432adce` | `refactor: Centralize redirect logic in BaseMiddleware` | cenário A1 (mesmo commit, mesmo SHA) |
| `2cdd9cd` | `refactor: Make Admin middleware self-sufficient` | este cenário |

## O que mudou (só o commit `2cdd9cd`)

- `app/Middleware/Admin.php`: sem usuário logado, chama
  `deny('Você deve estar logado para acessar essa página', 'users.login')`
  e dá `return`; usuário que não é admin continua indo para
  `dashboard.index` com "Acesso restrito a administradores". O docblock
  explica a ordem real dos middlewares em `/admin` (`[Admin, Authenticate]`).
  Saiu o comentário "quem redireciona é o Authenticate".
- `config/routes.php`: só comentário no grupo `admin` explicando a ordem
  e que o aninhamento em `auth` ficou redundante, mas inofensivo. As
  rotas não mudaram.
- `tests/Unit/App/Middleware/AdminSpy.php`: dublê nomeado que estende
  `Admin` e sobrescreve `redirectTo()` para guardar o destino em
  `$redirectedTo`, sem `header()` nem `exit`.
- `tests/Unit/App/Middleware/AdminTest.php`: 3 testes do middleware
  (sem sessão vai para `/login`; participant vai para `/dashboard`; admin
  passa sem redirecionar e sem flash).

4 arquivos, +122 / -3 linhas.

## Por que assim

- Autorização continua em middleware, agora sem depender da ordem do
  `RouteWrapperMiddleware::group` (o grupo interno registra primeiro);
  o `Admin` pode proteger uma rota até fora do grupo `auth`.
- O `return` depois do primeiro `deny()` é o que permite testar o
  middleware com um dublê que não chama `exit`: sem ele, o caminho
  "sem sessão" seguiria e daria erro em `$user->isAdmin()`.
- Reaproveita o `deny()` do A1 (mensagem flash + rota nomeada), sem
  duplicar `FlashMessage` e `header()`.
- Não mexe no `core/` (inverter para `array_unshift` no `group` seria
  alteração de framework) nem inverte o aninhamento das rotas, que fica
  estranho de ler.
- Dublê em classe nomeada em `tests/Unit/App/Middleware/`, PSR-4
  (`Tests\Unit\App\Middleware\AdminSpy`); classe anônima falha no
  PHPStan. Nomes de teste descritivos (`test_handle_..._should_...`).
- Mensagens em pt-BR iguais às do `Authenticate`, então os testes de
  acesso e aceitação existentes não mudam. PSR-12, PHPStan 6, commit
  em inglês `type: Title` (`grading-path.md`).

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload --optimize --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 74 testes, Integration 9, Acceptance 9 (a base
do A1 e o público são 71 / 9 / 9). Testes novos, todos em
`tests/Unit/App/Middleware/AdminTest.php`:

- `test_handle_without_session_should_redirect_to_login`
- `test_handle_with_participant_should_redirect_to_dashboard`
- `test_handle_with_admin_should_let_request_pass`

Conferido também que o primeiro teste falha com o `Admin` do A1 (que
ignora usuário nulo), ou seja, ele cobre a mudança. Continuam verdes
`DashboardAccessTest::test_admin_route_should_redirect_to_login_without_session`
e `AdminDashboardCest::accessAdminWithoutLogin`.

No navegador: `http://localhost/admin` sem login vai para `/login` com
"Você deve estar logado para acessar essa página"; com
`user@example.com` / `123456` vai para `/dashboard` com "Acesso restrito
a administradores"; com `admin@example.com` / `123456` abre o "Painel
Administrativo". Para provar a autossuficiência, tire temporariamente
`Route::middleware('admin')->group(...)` de dentro do grupo `auth`: o
comportamento é o mesmo.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público. `<caminho>` abaixo é `s50/exemplos-fvp/a12-admin-autossuficiente`
(relativo ao clone do s50).

Primeiro veja se o alvo já tem o A1 (`ls app/Middleware/BaseMiddleware.php`
ou `git log --oneline | grep "Centralize redirect"`). Se tem, pegue só
`2cdd9cd`. Se não tem, pegue os dois, na ordem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a12 <caminho>/projeto
git fetch fvp-a12 fvp/a12-admin-autossuficiente

git cherry-pick 2cdd9cd             # alvo já tem o A1
git cherry-pick 432adce 2cdd9cd     # alvo sem o A1: os dois, nesta ordem
git cherry-pick main..fvp-a12/fvp/a12-admin-autossuficiente   # mesma coisa, por intervalo

git remote remove fvp-a12
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a12-admin-autossuficiente:fvp-a12
git cherry-pick 2cdd9cd             # alvo já tem o A1
git cherry-pick main..fvp-a12       # alvo sem o A1: aplica os dois commits
git branch -D fvp-a12               # opcional, limpa a ref
```

Testado em clone limpo de `86c81ca`: `git cherry-pick main..fvp-a12`
aplica os dois sem conflito, e `git cherry-pick 432adce` seguido de
`git cherry-pick 2cdd9cd` também. O cherry-pick cria commits novos; o
SHA no seu repositório vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

O patch contém os dois commits (formato mbox). Em alvo sem o A1
(testado, aplica limpo):

```
git am <caminho>/solucao.patch       # cria os dois commits, mesmas mensagens
```

Em alvo que já tem o A1, o primeiro patch falha. Pule-o:

```
git am <caminho>/solucao.patch
git am --skip                        # pula o A1, aplica o A12
```

Ou gere um patch só do A12 no clone `projeto/` com
`git format-patch -1 2cdd9cd --stdout > a12.patch` e aplique esse.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Middleware/Admin.php`: A8 (responder 403) e F1/F2 (testes de
  middleware) também reescrevem o `handle()`. Mantenha o ramo
  `$user === null` com `deny(..., 'users.login')` e `return`, e aplique
  a outra mudança só no ramo "não é admin".
- `tests/Unit/App/Middleware/AdminTest.php`: A8 e F1 criam um arquivo
  com o mesmo nome (F1 com o dublê `AdminDouble`). Fique com um único
  `AdminTest`, um único dublê, e junte os métodos de teste sem repetir
  nomes.
- `config/routes.php`: só o comentário do grupo `admin`; se o alvo
  acrescentou rotas no grupo (D1, D2, E1), mantenha as rotas e o
  comentário.

Depois de aplicar: não há mudança de schema; rode
`./run composer dump-autoload --strict-psr`, `./run phpcs`,
`./run phpstan` e os testes de novo.

## Armadilhas

- Esquecer o `return` depois do primeiro `deny()`: em produção o `exit`
  esconde o erro, mas no teste o dublê não sai e o código chega a
  `$user->isAdmin()` em `null` (erro fatal).
- `deny()` e `redirectTo()` precisam ser `protected` no `BaseMiddleware`
  (como no A1) para o dublê sobrescrever. Se o alvo tiver `private`,
  o dublê não intercepta e o teste morre no `exit`.
- O helper `route()` precisa das rotas carregadas: no teste unitário,
  `Router::reset()` e `require config/routes.php` no `setUp`.
- Dublê anônimo (`new class extends Admin`) falha no PHPStan e não segue
  o PSR-4; use classe nomeada no próprio diretório do teste.
- Mudar `group` no `core/` para inverter a ordem é alteração de
  framework; combine com o professor antes.
