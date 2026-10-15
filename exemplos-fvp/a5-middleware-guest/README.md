# A5. Middleware guest: logado não vê /login

Pedido, como o professor faria na hora: "Quem já está logado não deve
ver a tela de login. Se abrir `/login`, mande para o painel."

Chance: média. Tempo estimado: 20 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção A5.

Branch no clone: `fvp/a5-middleware-guest`. `main` continua igual ao
repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `96c8446` | `feat: Add guest middleware to hide login from authenticated users` |

## O que mudou

- `app/Middleware/Guest.php` (novo): implementa
  `Core\Http\Middleware\Middleware`; se `Auth::check()` for verdadeiro,
  redireciona para `route('dashboard.index')`. O `redirectTo()` é
  `protected` (não `private`, como nos middlewares existentes) para o
  teste unitário conseguir substituí-lo.
- `config/App.php`: alias `'guest' => \App\Middleware\Guest::class`.
- `config/routes.php`: `GET /login` e `POST /login` entram em
  `Route::middleware('guest')->group(...)`. `GET /logout` fica fora.
- `tests/Unit/App/Middleware/GuestDouble.php` (novo): dublê que estende
  `Guest` e guarda o destino em `$redirectedTo` em vez de chamar
  `header()` + `exit`.
- `tests/Unit/App/Middleware/GuestTest.php` (novo): visitante passa;
  usuário logado é mandado para `/dashboard`.
- `tests/Acceptance/authentications/LoginCest.php`: caso
  `loggedUserIsRedirectedFromLoginToDashboard`.

Resultado: 6 arquivos, +126 / -3 linhas.

## Por que assim

- Autorização em middleware, não dentro da ação `new` do
  `AuthenticationsController` (`grading-path.md`, feedback 2025-2:
  "autorização em middleware").
- Mesmo mecanismo dos middlewares `auth` e `admin`: classe em
  `app/Middleware/`, alias em `config/App.php`, grupo em
  `config/routes.php`. O professor reconhece o padrão na hora.
- Cenário independente do A1: `Guest` implementa a interface direto e tem seu
  próprio `redirectTo()`. Se o A1 já estiver aplicado, troque por
  `class Guest extends BaseMiddleware`, apague o `redirectTo()` da classe (o da
  base já é `protected`) e o `use Core\Http\Middleware\Middleware`.
- O `POST /login` também fica no grupo: quem já está logado e reenvia o
  formulário vai para o painel em vez de refazer o login.
- Sem mensagem flash: não é erro, é atalho. Se o professor quiser aviso,
  acrescente `FlashMessage::success('Você já está logado.')` antes do
  redirecionamento (ou `info`, se o cenário C1 estiver aplicado).
- Testes em duas camadas: unitário do middleware com dublê (mesma ideia
  do `redirectTo` sobrescrito em `ControllerTestCase`) e aceitação no
  navegador. Teste de acesso com Guzzle não cobre o caso logado (não há
  cookie de sessão); o caso sem sessão (`GET /login` → 200) já existe em
  `DashboardAccessTest::test_public_route_should_be_accessible_without_session`.
- PSR-12, PHPStan 6, PSR-4 estrito sem avisos; textos pt-BR;
  identificadores em inglês; commit em inglês `type: Title`.

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

Resultado obtido: Unit 73 testes, Integration 9, Acceptance 10 (eram
71 / 9 / 9 na base). Testes novos:

- `tests/Unit/App/Middleware/GuestTest.php`:
  `test_handle_should_let_visitor_through_without_session`,
  `test_handle_should_redirect_logged_user_to_dashboard`.
- `tests/Acceptance/authentications/LoginCest.php`:
  `loggedUserIsRedirectedFromLoginToDashboard`.

No navegador: `http://localhost`. Sem login, `/login` abre normalmente.
Logue com `user@example.com` / `123456`; digite `/login` na barra de
endereços: cai em `/dashboard` com "Painel do Usuário". Clique em "Sair":
volta para `/login` (o logout não está no grupo `guest`).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é
`s50/exemplos-fvp/a5-middleware-guest` (relativo ao clone do s50).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a5 <caminho>/projeto
git fetch fvp-a5 fvp/a5-middleware-guest
git cherry-pick 96c8446
git remote remove fvp-a5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle fvp/a5-middleware-guest:fvp-a5
git cherry-pick 96c8446
git branch -D fvp-a5          # opcional, limpa a ref
```

Testado num clone limpo de `86c81ca`: aplica sem conflito. O
cherry-pick cria um commit novo; o SHA pode ser outro, o conteúdo é o
mesmo (`git diff fvp-a5 HEAD` vazio).

### 3. A partir do `solucao.patch` (`git am`)

```
git am <caminho>/solucao.patch              # cria commit novo com a mesma mensagem
git apply --3way <caminho>/solucao.patch    # só o diff, sem commit
```

Testado: os dois aplicam limpo sobre `86c81ca`.

### Se der conflito

```
git status                 # arquivos marcados "both modified"
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue # ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar neste cenário e por quê:

- `config/routes.php`: qualquer cenário que mexe nas rotas de login ou
  logout (A2 troca `/logout` para DELETE, A3 move `/logout` para o
  grupo `auth`, B7 acrescenta `/users/new`). Resolução: mantenha as
  rotas do alvo e só embrulhe `GET /login` e `POST /login` no grupo
  `guest`; `/logout` nunca entra no grupo.
- `config/App.php`: A7 acrescenta o alias `organizer`. Fique com as
  duas linhas.
- `tests/Acceptance/authentications/LoginCest.php`: A4, C2 e C3 alteram
  os `see(...)` desse arquivo. Fique com os textos do alvo e acrescente o
  método novo.
- `app/Middleware/Guest.php`: não conflita, mas se o alvo tem A1,
  estenda `BaseMiddleware` (ver "Por que assim").

Depois de aplicar: rode `./run phpcs`, `./run phpstan` e os testes de
novo. Não há mudança de schema.

## Armadilhas

- `/logout` dentro do grupo `guest`: usuário logado clica em "Sair" e
  cai no painel sem sair. É o erro mais comum.
- `route('dashboard.index')` dentro do middleware consulta o `Router`.
  No teste unitário as rotas não estão carregadas; sem
  `Router::reset(); require Constants::rootPath()->join('config/routes.php');`
  no `setUp`, dá `Route with name dashboard.index not found`.
- `redirectTo()` com `header()` + `exit` não dá para testar direto
  (o `exit` mata o PHPUnit). Por isso o método é `protected` e existe o
  dublê. PHPStan nível 6 recusa propriedade em classe anônima
  (`Access to an undefined property`), e o `--strict-psr` exige uma
  classe por arquivo: daí `GuestDouble.php` separado.
- `Auth::check()` faz uma consulta ao banco a cada chamada; é o
  comportamento da base, não otimize no laboratório.
- Em `/admin`, `Admin` roda antes de `Authenticate`; aqui não importa,
  o grupo `guest` só tem um middleware.
