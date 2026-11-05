# O6. Inscritos em página própria, paginada

Pedido, como o professor faria na hora: "Crie uma página só com os
inscritos do Evento, em `/admin/events/{id}/enrollments`, com nome,
e-mail e data da inscrição, 5 por página. No detalhe do Evento, um
link 'Inscritos (N)' leva até ela."

Chance: média-baixa. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, O6.

Branch no clone: `fvp/o6-inscritos-pagina-propria`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                           |
|-----------|----------------------------------------------------|
| `028883a` | `feat: List event enrollments on a paginated page` |

## O que mudou

- `config/routes.php`, no grupo `admin`, depois das rotas de Eventos:
  - `GET /admin/events/{id}/enrollments`
    (`admin.events.enrollments.index`);
  - `GET /admin/events/{id}/enrollments/page/{page}`
    (`admin.events.enrollments.paginate`).
- `app/Controllers/Admin/EnrollmentsController.php` (novo), `index`:
  converte `id` e `page` da rota com `filter_var(...,
  FILTER_VALIDATE_INT, min_range 1)` (método privado `positiveInt`).
  `id` inválido ou Evento inexistente: flash "Evento não encontrado!"
  e volta para `/admin/events`. `page` inválida (`abc`, `0`, `-1`,
  `2x`): flash "Página inválida!" e volta para a página 1 dos
  inscritos. Sem `{page}` na rota vale 1. Depois pagina com
  `$event->enrollments()->paginate(page:, per_page: 5, route:
  'admin.events.enrollments.paginate', routeParams: ['id' => ...])`.
  `$title` = "Inscritos: <título>".
- `app/views/admin/enrollments/index.phtml` (nova): "N inscrito(s) de
  M vagas.", tabela Nome / E-mail / Inscrito em (`d/m/Y H:i`), a
  paginação de sempre e "Voltar ao evento". Sem inscritos
  (`totalOfRegisters() === 0`): "Nenhum inscrito neste evento.".
  Página além da última (há inscritos, mas não nesta página): "Esta
  página não existe." com link para a primeira. Nos dois casos, sem
  tabela nem paginação.
- `app/views/admin/events/show.phtml`: botão "Inscritos (N)" ao lado
  de "Editar".
- `app/Models/Event.php`: `enrollments(): HasMany` por `event_id`.
- `app/Models/Enrollment.php`: `created_at` em `$columns` e
  `save()` sobrescrito: ao criar, define `created_at` com
  `date('Y-m-d H:i:s')` se não veio.
- `lib/Paginator.php`: parâmetro novo `array $routeParams = []` no
  fim do construtor e `routeParams(int $page)`, que devolve os
  parâmetros fixos mais `page`.
- `app/views/paginator/_pages.phtml`: os três links usam
  `$paginator->routeParams(...)` no lugar de `['page' => ...]`.
- `core/Database/ActiveRecord/HasMany.php`: `paginate()` ganha
  `array $routeParams = []` (último, opcional) e repassa ao
  `Paginator`.
- Testes:
  - `EnrollmentTest` (Unit): `created_at` gravado entre dois
    instantes; `enrollments()->paginate()` traz só as Inscrições do
    Evento, conta páginas e monta `routeParams`.
  - `Admin\EnrollmentsControllerTest` (novo, Integration): página 1
    com 5 de 7 e link para `/page/2`; página 2 com os 2 restantes;
    não mostra inscritos de outro Evento; Evento sem inscritos; id
    inexistente redireciona com flash; página 3 de 2 diz "Esta página
    não existe." (e não "Nenhum inscrito"); `page` `abc`/`0`/`-1`/`2x`
    e `id` `abc`/`0`/`<id>x` redirecionam com flash.
  - `EventsControllerTest`: o show tem o link "Inscritos (1)".
  - `EventsAccessTest`: as duas rotas entram em `eventRoutes()`
    (sem sessão vai para `/login`, Participante para `/dashboard`) e
    `test_admin_should_access_event_enrollments` (200 nas duas, 302
    para Evento inexistente).
  - `ListEnrollmentsCest` (novo): abre "Inscritos (7)" a partir do
    show, confere 5 linhas, vai por "Próxima" à página 2 (2 linhas),
    volta pelo "1" e por "Voltar ao evento"; Evento sem inscritos.

## Por que assim

- Controller novo para o recurso filho (`Admin\EnrollmentsController`),
  fino como o de Eventos; SQL só no model (`HasMany::paginate` monta o
  `WHERE event_id = :event_id` com `bindValue`).
- O problema do cenário é o link de página: `_pages.phtml` chamava
  `route($nome, ['page' => N])` e a rota tem `{id}`, que ficaria
  literal no `href`. A correção mais curta que serve a qualquer rota
  foi o `Paginator` guardar os parâmetros fixos. `route()` preenche
  `{id}` e `{page}`; parâmetro que não está na URI vira query string,
  então `routeParams` também serve para manter um filtro (N1).
  As mudanças em `lib/` e `core/` só acrescentam parâmetros opcionais
  no fim: chamadas antigas (`Event::paginate`) continuam iguais.
- Alternativa sem mexer no `lib/`: uma cópia do `_pages.phtml` para
  os inscritos, recebendo `$event`. Mais código duplicado.
- "Data da inscrição" pede `created_at`, que já existe em
  `event_user` com `DEFAULT CURRENT_TIMESTAMP`, mas o `Paginator` só
  seleciona `$columns`. Ao pô-la em `$columns`, `Model::save()` manda
  `NULL` explícito e o `NOT NULL` derruba o `INSERT`; por isso o
  `save()` do `Enrollment` preenche a data antes.
- Sem `(int)` nos parâmetros: `(int) 'abc'` é 0 e `(int) '2x'` é 2,
  então uma URL errada viraria outra página em silêncio. O
  `filter_var` recusa e o controller avisa. Na view, a capacidade sai
  com `htmlspecialchars((string) ...)`, sem conversão.
- A mensagem de lista vazia olha `totalOfRegisters()`, não o array da
  página: em `/page/9` o array vem vazio mas o Evento tem inscritos.
- Nome e e-mail por `$enrollment->user` (o `__get` chama
  `user()->get()`): uma consulta por linha, no máximo 5 por página.
  O3 (`participants()`, `BelongsToMany`) faria uma consulta só, mas
  `BelongsToMany` não pagina.

## Como verificar

```
./run up -d          # espere ~15 s o MySQL subir
./run composer dump-autoload -o --strict-psr
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
./run db:populate
```

Resultado obtido: Unit 161 testes, Integration 58, Acceptance 25
(eram 159 / 45 / 23 na base). PHPCS e PHPStan sem erros; PSR-4
estrito ok; schema sem mudança; `db:populate` cria 16 Eventos e 14
Inscrições.

Na tela (admin), Evento 1 do populate: "Inscritos (3)" no detalhe;
a página lista Usuário, Mariana e Carlos com a data do populate. Para
ver a paginação no populate, troque `PER_PAGE` para 2 e abra o
Evento 1 (3 inscritos, 2 páginas).

## Relação com outros cenários

- O3 (`o3-inscritos-no-show`): mostra os inscritos no próprio show,
  sem paginar. Se aplicar os dois, deixe no show só o link
  "Inscritos (N)" (ou os primeiros nomes) e a lista completa aqui.
  O3 adiciona `participants()` no mesmo ponto do `Event.php`.
- P13 (`p13-admin-inscreve-remove-participante`): conflita. Também
  cria `Admin\EnrollmentsController` e `Event::enrollments()`, e usa
  rotas `/admin/events/{event_id}/enrollments...`. Para juntar:
  um controller só com `index`, `create` e `destroy`; um
  `enrollments()` só; e o mesmo nome de parâmetro nas três rotas
  (`{event_id}`; aí o `index` lê `event_id` e passa
  `routeParams: ['event_id' => ...]`). Os forms de inscrever/remover
  podem ficar nesta página.
- N4 (`n4-per-page`): muda `per_page` da listagem de Eventos e mexe em
  `Paginator`/`_pages.phtml`. Se o usuário escolher o tamanho, inclua
  `per_page` em `routeParams` (vai para a query string) para ele não
  se perder ao trocar de página.
- N1 (filtro mantido na paginação): a forma com `queryParams` resolve
  o mesmo problema dos links; fique com um dos dois mecanismos.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o `solucao.bundle`,
que funciona direto; o caminho pelo clone `projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/o6-inscritos-pagina-propria`.

Pré-requisito: o repositório alvo descende de `f31006b` do público (ou
de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/o6-inscritos-pagina-propria`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-o6 \
    s50/fvp/4.2/exemplos/o6-inscritos-pagina-propria/projeto
git fetch fvp-o6 fvp/o6-inscritos-pagina-propria
git cherry-pick 028883a
git remote remove fvp-o6
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/o6-inscritos-pagina-propria/solucao.bundle \
    fvp/o6-inscritos-pagina-propria:fvp-o6
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 028883a
# opcional, limpa a ref
git branch -D fvp-o6
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/o6-inscritos-pagina-propria/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/o6-inscritos-pagina-propria/solucao.patch
```

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe nova), os testes e o `./run phpcs`.

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

Arquivos que costumam conflitar neste cenário e por quê:

- `config/routes.php`: todo cenário com rota nova no grupo `admin`
  (O1, P13...) acrescenta no mesmo ponto; fique com as duas.
- `lib/Paginator.php` e `_pages.phtml`: N1, N3, N4 mexem no
  construtor e nos links. Mantenha os parâmetros novos dos dois, no
  fim e opcionais, e nos links use `routeParams(...)` somado ao que o
  outro cenário passa.
- `app/Models/Event.php` (`enrollments()`) e
  `Admin\EnrollmentsController`: P13 cria os mesmos (ver acima).
- `show.phtml`: botões ao lado de "Editar" (P6, P5) e a seção de O3.
- `EventsAccessTest::eventRoutes()`: outras rotas novas na mesma
  lista; fique com todas.

## Armadilhas

- `route('admin.events.enrollments.paginate', ['page' => 2])` deixa
  `{id}` literal no link: `/admin/events/{id}/enrollments/page/2`.
  O teste de controller confere o `href` justamente por isso.
- Parâmetro de rota só `[a-z,_]` (`Route.php:90`): `{eventId}` não
  casa e vira 404.
- Sem `ORDER BY`, o MySQL lê `event_user` pelo índice único
  `(event_id, user_id)`: os inscritos saem na ordem do `user_id`, não
  na ordem da inscrição. Um teste que espere a ordem de criação das
  Inscrições falha se os usuários foram criados em outra ordem.
  Ordenar por `created_at` é o N3 aplicado aqui.
- `created_at` em `$columns` sem preencher: o `INSERT` manda `NULL` e
  falha (`NOT NULL`). O `DEFAULT` do schema só vale quando a coluna
  não é citada.
- No Cest, o Codeception reaproveita a instância entre cenários:
  participantes guardados em `$this->participants` no cenário anterior
  já foram apagados do banco e o `seedEvents` da base tentaria
  inscrevê-los (erro de chave estrangeira). O `_before` do Cest novo
  zera a lista antes de chamar o da base.
- `empty($enrollments)` para "Nenhum inscrito": numa página além da
  última a tela diria "7 inscrito(s)" e "Nenhum inscrito" juntos.
- `max(1, (int) $request->getParam('page'))` parece seguro, mas
  `/page/abc` vira página 1 e `/page/2x` vira página 2 sem aviso.
- `$title` vai cru para o `<h1>` do layout: `htmlspecialchars` no
  título do Evento, como o `show` já faz.
