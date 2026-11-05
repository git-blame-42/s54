# N5. Só Eventos futuros ou passados

Pedido, como o professor faria na hora: "Na listagem, crie uma aba
com os Eventos que ainda vão acontecer e outra com os que já
passaram, cada uma em ordem de data."

Chance: média. Tempo estimado: 20-30 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N5.

Branch no clone: `fvp/n5-eventos-futuros-passados`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                  |
|-----------|-------------------------------------------|
| `80733fb` | `feat: Add upcoming and past events tabs` |

## O que mudou

- `app/Models/Event.php` (depois de `hasEnrollments()`):
  - `upcoming(): array`: `WHERE starts_on >= :today ORDER BY
    starts_on, id` (hoje conta como próximo).
  - `past(): array`: `WHERE starts_on < :today ORDER BY starts_on
    DESC, id DESC` (o mais recente primeiro).
  - Os dois com `@return array<static>` e chamando o privado
    `byStartsOn(string $condition)`, que faz `prepare`,
    `bindValue(':today', date('Y-m-d'))` e `new static($row)`.
    Imports novos: `Core\Database\Database` e `PDO`.
- `app/Controllers/Admin/EventsController.php`:
  - `SCOPES = ['upcoming', 'past']` (lista fechada).
  - `index`: `$scope = $request->getParam('scope')`, validado com
    `is_string` + `in_array(..., true)`. Com escopo,
    `$events = Event::upcoming()` ou `Event::past()` e
    `$paginator = null`; sem, o caminho de antes. `$scope` sempre no
    `compact` (`null` em "Todos").
- `app/views/admin/events/index.phtml`:
  - Abas (`ul.nav-tabs.events-scope`): "Todos" (`/admin/events`),
    "Próximos" (`?scope=upcoming`) e "Passados" (`?scope=past`),
    com `active` na atual.
  - Lista vazia: "Nenhum evento próximo." / "Nenhum evento
    passado." / "Nenhum evento cadastrado.".
  - `renderPagesNavigation()` só se `$paginator !== null`.
- Testes:
  - `EventTest`: `upcoming` (hoje, amanhã e ontem: volta hoje e
    amanhã, nessa ordem) e `past` (ontem e semana passada, o mais
    recente primeiro). Helper `createEventOn($title, '-1 day')`,
    no molde do `createPastEvent` (grava a data com `update`).
  - `EventsControllerTest`: aba `upcoming` com 6 futuros criados
    fora de ordem e 1 passado (todos numa página, ordem de data,
    aba ativa), aba `past`, aba `past` vazia e escopo inválido
    (`''`, `'all'`, array: listagem paginada normal).
  - Aceitação: `tests/Acceptance/admin/events/ScopeEventsCest.php`
    (`extends BaseEventsCest`): move o Evento 02 para ontem, clica
    em "Próximos" (15 linhas, sem paginação), "Passados" (só o 02)
    e "Todos"; e a aba de passados vazia.

## Por que assim

- `Model::where` só faz igualdade (`coluna = :valor`): `>=` e `<`
  pedem SQL próprio, e SQL fica no model.
- "Hoje" vem do PHP (`date('Y-m-d')` por `bindValue`), não do
  `CURDATE()` do MySQL: é o mesmo relógio e fuso que a validação
  "não pode estar no passado!" usa. Com `CURDATE()` funciona igual
  se os fusos do PHP e do MySQL baterem; saiba dizer a diferença.
- `$condition` em `byStartsOn` é texto fixo do model (não vem do
  usuário), por isso pode ser interpolado; o valor (`:today`) vai
  por `bindValue`.
- `?scope=` validado por lista fechada: valor desconhecido (ou
  array, `?scope[]=past`) cai em "Todos".
- Sem paginação nas abas: o `Paginator` só sabe igualdade. Paginar
  as abas pediria condição com operador no `Paginator` (fora do
  escopo do pedido).
- Passados em ordem decrescente: quem abre a aba quer ver o que
  acabou de acontecer primeiro. Se o professor pedir crescente,
  troque o `DESC` (e o teste de ordem).
- Nome do parâmetro: `scope`, como no catálogo. Se o professor
  pedir outro nome (ex.: `?when=`), é só trocar a chave e os links.

## Como verificar

```
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 161, Integration 49, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela: o populate só tem Eventos futuros. "Próximos" mostra os 16
numa página; "Passados" mostra "Nenhum evento passado.". Para ver um
passado, mude a data no banco (o formulário recusa data passada):

```
docker compose exec db mysql -uroot -p<senha> <banco> \
    -e "UPDATE events SET starts_on = CURDATE() - INTERVAL 1 DAY \
WHERE id = 2"
```

## Relação com outros cenários

- N2 (`n2-busca-por-titulo`) usa o mesmo molde (método com SQL no
  model, `$paginator = null`, `if` na view). Juntos, mantenha um só
  `if ($paginator !== null)` e decida a precedência entre busca e
  aba no `index`.
- N1 (`n1-filtro-por-tipo-paginacao`) e N4 (`n4-per-page`) mexem na
  chamada de `Event::paginate`, que aqui fica no `else`.
- N3 (ordenar por data) ordena a listagem paginada; aqui a ordem já
  vem no SQL das abas.
- S1 (`s1-teste-aceitacao-filtro`) ensina o roteiro de Cest que o
  `ScopeEventsCest` segue.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n5-eventos-futuros-passados`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n5-eventos-futuros-passados`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n5 \
    s50/fvp/4.2/exemplos/n5-eventos-futuros-passados/projeto
git fetch fvp-n5 fvp/n5-eventos-futuros-passados
git cherry-pick 80733fb
git remote remove fvp-n5
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n5-eventos-futuros-passados/solucao.bundle \
    fvp/n5-eventos-futuros-passados:fvp-n5
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 80733fb
# opcional, limpa a ref
git branch -D fvp-n5
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n5-eventos-futuros-passados/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n5-eventos-futuros-passados/solucao.patch
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

Arquivos que costumam conflitar neste cenário e por quê:

- `EventsController::index`: busca (N2), filtro (N1), per_page (N4)
  e ordenação (N3) mexem no mesmo trecho. Mantenha o
  `if ($scope !== null)` e o `else` com a chamada combinada de
  `Event::paginate`; junte as variáveis no `compact`.
- `index.phtml`: outros blocos no topo e o `if ($paginator !==
  null)` no fim (o N2 tem o mesmo `if`: fique com um só).
- `Event.php`: métodos novos depois de `hasEnrollments()` (N2) e os
  `use` no topo; fique com todos, sem repetir `use PDO`.
- Testes: métodos novos no mesmo ponto; fique com os dois lados. O
  helper `createPastEvent` do `EventsControllerTest` é novo; o do
  `EventTest` (sem parâmetros) já existia e não mudou.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Tentar com `Event::where(['starts_on' => ...])`: só igualdade,
  traz só os Eventos de um dia.
- `array_filter` no controller sobre `Event::all()`: funciona, mas
  é filtro fora do SQL e fora do model; o professor cobra SQL no
  model.
- Comparar com `NOW()` em vez de data: `starts_on` é `DATE` e vira
  `hoje 00:00:00`, menor que `NOW()`; o Evento de hoje sai de
  "Próximos" e vai para "Passados". Compare data com data.
- Criar Evento passado pelo `save()` nos testes: a validação
  recusa. Use `update()` depois de criar (não valida), como o
  `createPastEvent` do `EventTest`.
- `$scope` fora do `compact`: variável indefinida na view, 500.
- `$paginator->renderPagesNavigation()` com `$paginator` nulo: 500
  nas abas.
- Sem `@return array<static>` nos métodos novos: PHPStan nível 6.
- `static::byStartsOn()` num método `private`: o PHPStan acusa
  "Unsafe call to private method ... through static::"; use
  `self::`.
