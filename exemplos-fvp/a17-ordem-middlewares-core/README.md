# A17. Ordem dos middlewares corrigida no core

Pedido, como o professor faria na hora: "O `Admin` não pode depender da
ordem em que os middlewares rodam nem chamar o `Authenticate` na mão.
Corrija a ordem de execução no mecanismo de middleware, não na
aplicação."

Chance: média. Tempo estimado: 15 min. Catálogo:
`fvp-possiveis-modificacoes.md`, seção 9 (complemento 2, issues da Entrega 4.1), A17 / issue
ind-management #7.

Branch no clone: `fvp/a17-ordem-middlewares-core`. `main` continua igual
ao repositório público (`86c81ca`).

| Commit | Mensagem |
|--------|----------|
| `9a4778e` | `fix: Run outer middleware group before inner groups` |

## A pegadinha da ordem

Em `config/routes.php` o grupo `admin` está dentro do grupo `auth`:

```php
Route::middleware('auth')->group(function () {          // externo
    Route::middleware('admin')->group(function () {     // interno
        Route::get('/admin', ...);
    });
});
```

`RouteWrapperMiddleware::group()` roda o callback primeiro (que registra
as rotas) e só depois anexa o seu middleware às rotas novas. O callback
do grupo externo contém o grupo interno inteiro, então o interno termina
antes e anexa o `Admin` primeiro:

```
Antes (addMiddleware, anexa no fim)

  group('auth') { group('admin') { get('/admin') } }
     1. get('/admin')             -> /admin: []
     2. fim do group('admin')     -> /admin: [Admin]
     3. fim do group('auth')      -> /admin: [Admin, Authenticate]

  execução de /admin:  Admin  ->  Authenticate      (interno antes do externo)

Depois (prependMiddleware, anexa no início)

     1. get('/admin')             -> /admin: []
     2. fim do group('admin')     -> /admin: [Admin]
     3. fim do group('auth')      -> /admin: [Authenticate, Admin]

  execução de /admin:  Authenticate  ->  Admin      (ordem de escrita)
```

Foi essa inversão que levou o grupo da ind-management #7 a escrever
`(new Authenticate())->handle($request)` dentro do `Admin`. No nosso
`main` ela aparece como o `if ($user !== null && ...)` e o comentário
"quem redireciona é o middleware Authenticate".

## O que mudou

- `core/Router/Route.php`: novo `prependMiddleware(Middleware $middleware)`
  com `array_unshift`. O `addMiddleware` continua igual.
- `core/Router/RouteWrapperMiddleware.php`: `group()` usa
  `prependMiddleware`, com comentário explicando por quê (o callback já
  rodou, o grupo interno já anexou o dele).
- `app/Middleware/Admin.php`: só o comentário. A guarda de usuário nulo
  fica: a ordem agora é garantida pelo core, mas o `Admin` não depende
  dela.
- `tests/Unit/Core/Router/RouteWrapperMiddlewareTest.php`: três testes
  novos e `setUp`/`tearDown` que registram os aliases de teste `outer` e
  `inner` em `Config\App::$middlewareAliases` e restauram o original.
- `tests/Unit/Core/Router/RecordingMiddleware.php` (abstrata),
  `OuterRecordingMiddleware.php` e `InnerRecordingMiddleware.php`:
  middlewares de teste que gravam em `RecordingMiddleware::$calls` a
  ordem em que rodaram.
- `tests/Unit/Core/Router/RouteTest.php`: teste do `prependMiddleware`.
- `tests/Integration/Access/AdminAccessTest.php` (novo): HTTP real com
  cookie, para visitante, participante e admin em `/admin`.

## Por que assim

- O professor pediu na ind-management #7: corrigir "no mecanismo de
  middleware", não contornar na camada de aplicação.
- Corrigir no core vale para qualquer grupo aninhado, presente e futuro
  (ex.: um `auth > organizer`). O contorno no app (o `Admin` instanciar
  e rodar o `Authenticate`) acopla dois middlewares que deveriam ser
  independentes, faz a checagem de login rodar duas vezes na mesma
  requisição e teria de ser repetido em cada middleware novo.
- A ordem de execução passa a seguir a ordem em que os grupos aparecem
  no `routes.php`, que é o que quem lê o arquivo espera.
- O `Admin` continua sem depender da ordem (texto oficial do cenário:
  a issue cobrou independência e ordem). Ele não chama o
  `Authenticate`; sem usuário logado só não decide nada.
- `prependMiddleware` como método novo, sem mudar `addMiddleware`:
  quem anexa middleware direto na rota continua com o comportamento de
  sempre.
- Testes em duas visões: a lista montada (reflexão em `middlewares`,
  com `Authenticate` e `Admin` reais) e a execução real (`runMiddlewares`
  com middlewares que gravam a ordem). O acesso HTTP confere as
  mensagens que o usuário vê.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run phpcs
./run phpstan
./run composer dump-autoload -o --strict-psr
./run test tests/Unit
./run test tests/Integration
./run test:browser
```

Resultado obtido: Unit 75 testes, Integration 12, Acceptance 9 (eram
71 / 9 / 9 na base). PHPCS e PHPStan sem erros; `dump-autoload
--strict-psr` sem avisos (exige `-o`). Schema não mudou.

Sem a mudança no `core/`, os três testes novos de
`RouteWrapperMiddlewareTest` e o de `RouteTest` falham.

Testes novos:

- `RouteWrapperMiddlewareTest::test_nested_groups_should_run_outer_middleware_first`
- `RouteWrapperMiddlewareTest::test_nested_groups_should_only_add_outer_middleware_to_outer_routes`
- `RouteWrapperMiddlewareTest::test_run_middlewares_should_execute_outer_group_before_inner_group`
- `RouteTest::test_prepend_middleware_should_put_it_before_existing_ones`
- `AdminAccessTest::test_visitor_should_be_redirected_to_login_with_login_message`
- `AdminAccessTest::test_participant_should_be_redirected_to_dashboard_with_restricted_message`
- `AdminAccessTest::test_admin_should_access_admin_area`

O acceptance `tests/Acceptance/admin/AdminDashboardCest.php` já cobria
os três casos no navegador (visitante, usuário comum, admin) e continua
passando; não foi preciso teste de aceitação novo.

No navegador, nada muda para o usuário: visitante em `/admin` vai para
`/login` com "Você deve estar logado para acessar essa página";
`user@example.com` vai para `/dashboard` com "Acesso restrito a
administradores"; o admin entra.

## Relação com A1 e A12

- A1 (`../a1-centralizar-redirect-middlewares`) troca, no `Admin.php`,
  `FlashMessage` + `redirectTo` por `deny()` do `BaseMiddleware`. O A17
  só mexe no comentário logo acima do `if`: testado em clone limpo,
  A17 por cima do A1 aplica sem conflito.
- A12 (`../a12-admin-autossuficiente`, empilhado no A1) reescreve o
  `handle()` do `Admin` (ramo `$user === null` com `deny(...,
  'users.login')`) e põe um docblock e um comentário em `routes.php`
  dizendo que a ordem real é `[Admin, Authenticate]`. Testado: A17 por
  cima do A12 conflita em `app/Middleware/Admin.php`. Fique com o código
  do A12 e corrija os textos: depois do A17 a ordem é
  `[Authenticate, Admin]`, e o aninhamento em `auth` deixa de ser
  "redundante" (é ele que roda primeiro). A12 e A17 se completam: A12
  dá a independência, A17 a ordem; a issue cobrou os dois.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno (o git não versiona
repositório dentro de repositório). Use o caminho do `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`../recriar.sh <cenario>` (devolve o `.git`, offline, a partir do bundle).

Pré-requisito: o repositório alvo descende do commit `86c81ca` do
público (ou de algo compatível). `<caminho>` abaixo é o diretório
deste cenário no s50: `s50/exemplos-fvp/a17-ordem-middlewares-core`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-a17 s50/exemplos-fvp/a17-ordem-middlewares-core/projeto
git fetch fvp-a17 fvp/a17-ordem-middlewares-core
git cherry-pick 9a4778e
git remote remove fvp-a17
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch s50/exemplos-fvp/a17-ordem-middlewares-core/solucao.bundle fvp/a17-ordem-middlewares-core:fvp-a17
git cherry-pick 9a4778e        # o bundle traz o objeto; o cherry-pick cria commit novo
git branch -D fvp-a17          # opcional, limpa a ref
```

O cherry-pick cria commit novo; o SHA no seu repositório vai ser outro.

### 3. A partir do `solucao.patch` (`git am`)

```
git am s50/exemplos-fvp/a17-ordem-middlewares-core/solucao.patch       # cria commit novo com a mesma mensagem
git apply --3way s50/exemplos-fvp/a17-ordem-middlewares-core/solucao.patch   # só o diff, sem commit
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

- `app/Middleware/Admin.php`: A12 (conflito testado, ver acima), A8
  (403), F1 e F2 também mexem no `handle()`. O A17 só troca o
  comentário: fique com o código do outro cenário e ajuste o texto
  sobre a ordem para `[Authenticate, Admin]`.
- `config/routes.php` (só se o alvo tiver o A12): não conflita, mas o
  comentário do A12 fica errado; reescreva-o.
- `tests/Integration/Access/AdminAccessTest.php`: A8 cria um arquivo
  com o mesmo nome (add/add). Junte os métodos numa classe só. Com o
  A8, usuário comum recebe 403 em vez de redirect: ajuste
  `test_participant_should_be_redirected_to_dashboard_with_restricted_message`
  para o comportamento do A8 (ou remova-o, o A8 já cobre).
- `core/Router/*` e os testes de `tests/Unit/Core/Router`: nenhum outro
  cenário mexe.

Depois de aplicar: não há mudança de schema; rode
`./run composer dump-autoload -o --strict-psr` (três classes novas em
`tests/Unit/Core/Router`), `./run phpcs`, `./run phpstan` e os testes de
novo.

## Armadilhas

- `Router::reset()` no início de cada teste de grupo, senão
  `getRoute(0)` pega a rota de outro teste.
- O `MockController` de `tests/Unit/Core/Router` só tem `action()`.
- Alias de teste em `Config\App::$middlewareAliases` é estático: guarde o
  array no `setUp` e restaure no `tearDown`, senão vaza para outros
  testes.
- Cada alias precisa de uma classe própria (o `group()` faz
  `new $class()` sem argumentos); por isso `OuterRecordingMiddleware` e
  `InnerRecordingMiddleware` em vez de um middleware só com parâmetro.
- Classe anônima de teste falha no PHPStan e não segue o PSR-4; use
  classes nomeadas no diretório do teste.
- O teste antigo (`$middlewares[0]` é `Middleware`) continua passando;
  ele não pegava a ordem.
- Trocar só o `addMiddleware` para `array_unshift` também inverteria a
  ordem, mas muda o contrato de quem anexa middleware direto na rota;
  o método novo deixa a intenção explícita.
- Se o `vendor/` foi copiado sem os links de `vendor/bin`, o `./run test`
  falha com `phpunit: not found`; `./run composer install` recria.
