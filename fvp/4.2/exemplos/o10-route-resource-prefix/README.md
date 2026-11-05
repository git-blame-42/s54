# O10. `Route::resource` e `Route::prefix` no core

Pedido, como o professor faria na hora: "Registrar as 8 rotas do CRUD
uma a uma é repetitivo. Crie no framework um atalho que registre as
rotas RESTful de uma vez e use nas rotas de Eventos." Variante: "um
prefixo `/admin` para o grupo, sem repetir em cada rota".

Chance: média-baixa. Tempo estimado: 25-40 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O10: "`Route::resource` ou
`Route::prefix` no core".

Branch no clone: `fvp/o10-route-resource-prefix`, a partir de `main`
(`f31006b` do público).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `4822619` | `feat: Add Route::resource and Route::prefix to the...`  |

(mensagem completa: `feat: Add Route::resource and Route::prefix to
the router`)

## O que mudou

- `core/Router/Route.php`:
  - constante `RESOURCE` com as 8 linhas (verbo, sufixo da URI, ação,
    sufixo do nome) na ordem do `config/routes.php` atual: `index`,
    `paginate` (`/page/{page}`, ação `index`), `new`, `create`,
    `show`, `edit`, `update`, `destroy`. PHPDoc
    `@var list<array{0: string, 1: string, 2: string, 3: string}>`.
  - `Route::resource(string $uri, string $controller, string $name):
    void`: percorre `RESOURCE` e chama `self::$verb(...)` (o próprio
    `get/post/put/delete`) com `->name("{$name}.{$route}")`.
  - `Route::prefix(string $prefix): RoutePrefix`, ao lado de
    `middleware()`.
  - `get/post/put/delete` passam a URI por `RoutePrefix::apply()`;
    fora de um grupo de prefixo a URI fica igual.
- `core/Router/RoutePrefix.php` (novo): pilha estática de prefixos.
  `group()` empilha, chama o callback e desempilha num `finally`;
  `apply()` concatena a pilha na URI (`/` dentro de `/admin` vira
  `/admin`, não `/admin/`).
- `config/routes.php`: as 8 linhas `admin.events.*` viram
  `Route::resource('/admin/events', AdminEventsController::class,
  'admin.events');`, no mesmo lugar (dentro de `auth` e `admin`).
  Nomes, URIs, verbos, ações e ordem iguais aos de antes.
- Testes:
  - `RouteTest` (Unit, 7 novos): 8 rotas com método, URI, ação e nome
    na ordem; `getRoutePathByName` (`admin.events.edit` com `id` 3 dá
    `/admin/events/3/edit`); `GET /admin/events/new` despacha para
    `new`, não `show`; `/page/2` vai para `index` com `page=2`; cada
    verbo vai para a sua ação (PUT/DELETE via `_method`); `resource`
    dentro de `middleware('auth')` e `middleware('admin')` dá os dois
    middlewares às 8 rotas (e não à rota de fora); o
    `config/routes.php` real registra as 8 rotas com a mesma tabela
    de antes, logo depois de `admin.dashboard.index`, com
    `Authenticate` e `Admin`.
  - `RoutePrefixTest` (Unit, novo, 6): prefixa só as rotas de dentro
    do grupo; `/` vira o prefixo; grupos aninhados somam na ordem;
    exceção no callback não deixa o prefixo grudado; `prefix` +
    `resource` dá as mesmas URIs e `/admin/events/new` vai para
    `new`; prefixo dentro de `middleware` mantém o middleware.
  - `MockResourceController` (apoio): uma ação por rota, cada uma
    escreve o próprio nome e o parâmetro.
  - Nenhum teste existente mudou.

## Por que assim

- A ordem é o ponto: o `Router` despacha a primeira rota que casa.
  Com `new` e `page/{page}` antes de `{id}` dentro da constante,
  quem usa `resource` não tem como errar a ordem.
- Mesmos nomes de antes (`admin.events.*`): views, controllers e
  testes que usam `route(...)` não mudam. As suítes Integration e
  Acceptance inteiras verdes, sem mexer nelas, provam isso.
- `resource` chama `get/post/put/delete`, então herda tudo deles
  (prefixo, registro no `Router`). Não precisou mudar o `Router` nem
  o `RouteWrapperMiddleware`: o `group` conta as rotas antes e depois
  do callback, e as 8 do `resource` entram nessa conta.
- `paginate` usa a ação `index`, como no `routes.php` da base; por
  isso a constante tem ação e nome separados.
- `prefix` foi feito também (o catálogo pede um ou outro) porque
  coube sem tocar no `Router`: uma classe pequena, mesmo formato do
  `middleware()->group()`. A pilha com `finally` é o que o catálogo
  descreve; permite grupos aninhados.
- O `config/routes.php` não usa `prefix`: o pedido principal é o
  `resource('/admin/events', ...)`. Com prefixo ficaria
  `Route::prefix('/admin')->group(fn () =>
  Route::resource('/events', ...))`, com as mesmas rotas (testado
  em `RoutePrefixTest`).
- Sem `only`/`except`: o recurso de Eventos usa as 8 ações. Um
  recurso sem alguma ação registra as outras à mão.

## Como verificar

```
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 172, Integration 45, Acceptance 23
(base 159 / 45 / 23; nenhum teste de Integration ou Cest novo nem
alterado). PHPCS e PHPStan (que cobre `core/`) sem erros; PSR-4
estrito ok. Schema não mudou.

Só os testes do roteador:

```
./run test tests/Unit/Core/Router
```

Na tela (populate): logue como `admin@example.com` / `123456` e use o
CRUD de `/admin/events` como antes: listagem, página 2, "Novo Evento"
(`/admin/events/new` abre o formulário, não um 404 de Evento),
editar e remover.

## Relação com outros cenários

- O7 (`o7-route-patch`): `Route::patch` não existe na base. Se o O7
  estiver aplicado e o pedido for `update` com PATCH, troque `'put'`
  por `'patch'` na linha de `update` da `RESOURCE` (e o `_method` do
  form).
- O1 (`o1-rota-eventos-lotados`) e outras rotas fixas novas em
  `/admin/events/...` (P8 exportar CSV): têm de vir ANTES do
  `Route::resource`, senão o `{id}` do `show` captura o caminho.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. O caminho pelo clone
`projeto/` só vale depois de, na raiz do s50:

```
fvp/recriar.sh fvp/4.2/exemplos/o10-route-resource-prefix
```

O `solucao.bundle` funciona direto.

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/o10-route-resource-prefix`. Cherry-pick cria
commit novo (SHA diferente); preserva conteúdo e mensagem.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o10 <caminho>/projeto
git fetch fvp-o10 fvp/o10-route-resource-prefix
git cherry-pick 4822619
git remote remove fvp-o10
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch <caminho>/solucao.bundle \
  fvp/o10-route-resource-prefix:fvp-o10
git cherry-pick 4822619
# opcional, limpa a ref
git branch -D fvp-o10
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am <caminho>/solucao.patch
# só o diff, sem commit
git apply --3way <caminho>/solucao.patch
```

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
# ou: git am --continue
git cherry-pick --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `config/routes.php`: quase todo cenário acrescenta rotas no grupo
  `admin`. Fique com o `Route::resource(...)` no lugar das 8 linhas
  `admin.events.*` e mantenha as rotas novas do outro cenário; as
  fixas em `/admin/events/...` vão ANTES do `resource`. Se o outro
  cenário mudou uma das 8 (ex.: O7 com PATCH), mude a `RESOURCE`
  em vez de manter a linha avulsa duplicada.
- `core/Router/Route.php`: O7 acrescenta `patch()` no mesmo trecho.
  Fique com os dois; o `patch()` também deve usar
  `RoutePrefix::apply($uri)`.
- `RouteTest.php`: testes novos no fim da classe; mantenha todos.

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classes novas), `./run phpcs`, `./run phpstan` e os testes.

## Armadilhas

- Ordem: `new` e `page/{page}` depois de `{id}` fazem
  `/admin/events/new` cair no `show` com `id = "new"` (404 de Evento
  não encontrado). O teste do despacho pega isso.
- `resource` fora do grupo `admin` deixa as 8 rotas sem
  middleware: qualquer um entra. O `group` só aplica middleware às
  rotas registradas DENTRO do callback.
- Testes do roteador: o `Router` é singleton e vaza entre testes.
  Use `Router::reset()` no início e no fim (o `RouterTest` zera por
  reflexão no `tearDown`).
- Pilha de prefixo sem `finally`: uma exceção no callback deixa o
  prefixo grudado em todas as rotas seguintes.
- Nome de parâmetro só com `[a-z,_]` (regex do `Route::isSameUri`):
  `{eventId}` não é reconhecido como parâmetro.
- `self::$verb(...)` chama o método estático pelo nome guardado na
  constante (`'get'`, `'post'`...): o verbo tem de existir em
  `Route` (`patch` só com o O7).
- `core/` entra no phpstan nível 6: PHPDoc da constante
  (`list<array{...}>`) e dos helpers de teste, senão falha.
