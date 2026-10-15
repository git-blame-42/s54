# A11. Voltar para a página pedida depois do login

Pedido, como o professor faria na hora: "Se eu tento abrir `/admin` sem
estar logado, depois do login quero cair em `/admin`, não no painel."

Chance: média. Tempo estimado: 20 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A11.

Branch no clone: `fvp/a11-voltar-pagina-pedida`. `main` continua igual
ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `aec5213` | `feat: Redirect to the requested page after login` |

## O que mudou

- `lib/Authentication/Auth.php`: dois métodos novos.
  `rememberIntendedUrl(string $url)` guarda a página pedida em
  `$_SESSION['redirect_to']`, só se for caminho interno.
  `pullIntendedUrl(string $default)` devolve a página guardada (ou o
  padrão), limpa a chave e revalida o caminho. O privado
  `isInternalPath()` só aceita texto que começa com uma única `/`:
  recusa `https://...`, `//host`, `/\host` e caracteres de controle.
- `app/Middleware/Authenticate.php`: sem login e em requisição GET,
  chama `Auth::rememberIntendedUrl($request->getUri())` antes do flash e
  do redirect para `/login`. `redirectTo()` passa de `private` para
  `protected`, para o dublê do teste unitário poder sobrescrever.
- `app/Controllers/AuthenticationsController.php`: no login com sucesso,
  `redirectTo(Auth::pullIntendedUrl(route('dashboard.index')))`.
- `tests/Unit/App/Middleware/AuthenticateSpy.php` (novo): dublê nomeado
  que grava o destino em vez de `header()` + `exit`.
- `tests/Unit/App/Middleware/AuthenticateTest.php` (novo): 3 testes do
  middleware.
- `tests/Unit/Lib/Authentication/AuthTest.php`: 4 testes dos métodos novos.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`: 2
  testes e um `tearDown` que limpa `redirect_to`.
- `tests/Acceptance/admin/AdminDashboardCest.php`: 1 cenário novo.

Resultado: 8 arquivos, +212 / -2 linhas.

## Por que assim

- Autorização continua no middleware; o controller só pede o destino
  (`grading-path.md`, feedback 2025-2: controllers finos).
- A regra de sessão fica em `Auth`, ao lado de `login`/`logout`, que já
  mexem em `$_SESSION`. Desvio consciente do catálogo, que usa
  `$_SESSION['redirect_to']` direto no middleware e no controller: assim
  a validação de caminho interno fica num lugar só e ganha teste
  unitário. A chave da sessão continua `redirect_to`.
- Só caminhos internos: sem isso, `redirect_to` vira open redirect
  (`//evil.example.com` o navegador entende como outro domínio).
- Só GET: um POST reproduzido como GET depois do login daria 404.
- Parâmetros e URI só pelo `Request` (`getMethod()`, `getUri()`).
- Testes em cada camada (unit, controller, aceitação); dublê nomeado
  em `tests/Unit/App/Middleware/` (classe anônima falha no PHPStan/PSR-4).
- PSR-12 e PHPStan nível 6 sem avisos; comentários em pt-BR,
  identificadores em inglês, commit em inglês `type: Title`.

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

Resultado obtido: Unit 78 testes, Integration 11, Acceptance 10 (eram
71 / 9 / 9 na base). Sem mudança de schema. Testes novos:

- `tests/Unit/App/Middleware/AuthenticateTest.php`:
  `test_get_without_login_should_remember_requested_page_and_redirect_to_login`,
  `test_post_without_login_should_not_remember_requested_page`,
  `test_logged_in_user_should_pass_without_redirect`.
- `tests/Unit/Lib/Authentication/AuthTest.php`:
  `test_pull_intended_url_should_return_remembered_path_and_clear_it`,
  `test_pull_intended_url_without_remembered_path_should_return_default`,
  `test_remember_intended_url_should_ignore_external_urls`,
  `test_pull_intended_url_should_ignore_external_url_in_session`.
- `tests/Integration/Controllers/AuthenticationsControllerTest.php`:
  `test_authenticate_should_redirect_to_requested_page` (sessão com
  `/admin` → `Location: /admin`),
  `test_authenticate_with_external_requested_page_should_redirect_to_dashboard`.
- `tests/Acceptance/admin/AdminDashboardCest.php`:
  `loginAfterRequestingAdminShouldReturnToAdmin` (sem login abre
  `/admin`, cai em `/login`, loga como admin, termina em `/admin`).

No navegador: `http://localhost`. Sem login, abra `/admin`: vai para
`/login` com "Você deve estar logado para acessar essa página". Logue
como `admin@example.com` / `123456`: cai em `/admin` (Painel
Administrativo). Faça logout e logue de novo direto em `/login`: cai em
`/dashboard`, porque a chave foi limpa.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a11-voltar-pagina-pedida` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a11 <caminho>/projeto
git fetch fvp-a11 fvp/a11-voltar-pagina-pedida
git cherry-pick aec5213
git remote remove fvp-a11
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a11-voltar-pagina-pedida:fvp-a11
git cherry-pick aec5213        # o bundle traz o objeto aec5213
git branch -D fvp-a11          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo (no teste, `4539a5b`), com a mesma
mensagem e o mesmo diff.

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: `git am` aplica limpo em `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `app/Middleware/Authenticate.php` com A1 (`BaseMiddleware`): lá o
  `redirectTo` privado some e o corpo vira `$this->deny(...)`. Resolva
  mantendo o `deny(...)` e pondo o bloco `if ($request->getMethod() ===
  'GET') { Auth::rememberIntendedUrl(...); }` antes dele; descarte a
  troca `private` → `protected` (o `redirectTo` do `BaseMiddleware` já é
  protegido, e o `AuthenticateSpy` continua funcionando).
- `AuthenticationsController.php`, `AuthenticationsControllerTest.php`
  e `AdminDashboardCest.php` com A4 (admin cai em `/admin`): combine
  assim: `Auth::pullIntendedUrl($rotaPorPerfil)`, ou seja,
  `redirect_to` tem prioridade e, sem ele, decide pelo perfil.
- `AuthenticationsController.php` e o teste de controller com A9/A10
  (validação e e-mail mantido): mudam o caminho de falha; mantenha os
  dois lados, só a linha do redirect de sucesso é deste cenário.
- `lib/Authentication/Auth.php` e `AuthTest.php` com A13 (regenerar
  sessão): ambos acrescentam métodos no fim da classe; mantenha os dois
  blocos.

Depois de aplicar: não há mudança de schema; rode os testes e o
`./run phpcs` de novo.

## Armadilhas

- Guarde só em GET; um POST reproduzido como GET dá 404.
- Usuário comum que tentou `/admin` volta para `/admin` depois do login
  e o `Admin` o manda para `/dashboard` com "Acesso restrito a
  administradores". Comportamento aceitável: o pedido original foi
  respeitado e a autorização continua valendo.
- `ControllerTestCase::tearDown` só limpa `$_SESSION['user']`; sem o
  `tearDown` novo, `redirect_to` vaza para o próximo teste e o
  `Location: /dashboard` do teste antigo vira `/admin`.
- Nunca redirecione para o valor guardado sem validar: `//host` e
  `/\host` saem do site. O `pullIntendedUrl` revalida mesmo que só o
  middleware escreva a chave.
- `pullIntendedUrl` limpa a chave sempre; sem isso, todo login seguinte
  voltaria para a mesma página.
- O dublê precisa de `redirectTo` `protected` no `Authenticate`; com
  `private`, a sobrescrita não vale e o `exit` encerra o PHPUnit.
