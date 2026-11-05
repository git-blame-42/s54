# N1. Filtro por tipo com `where`, mantido na paginação

Pedido, como o professor faria na hora: "Na listagem de Eventos,
permita filtrar por tipo: `/admin/events?kind=workshop`. O filtro tem
que continuar valendo quando o usuário muda de página."

Chance: média-alta. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N1.

Branch no clone: `fvp/n1-filtro-por-tipo-paginacao`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                       |
|-----------|----------------------------------------------------------------|
| `0fe3c48` | `feat: Filter events by kind keeping the filter in pagination` |

## O que mudou

Implementada a forma 2 do catálogo (com paginação).

- `app/Controllers/Admin/EventsController.php`:
  - Método privado `kindFilter(Request $request): ?string`: devolve
    o `?kind=` só se for string e chave de `Event::KINDS`; senão
    `null` (sem filtro).
  - `index` monta `$filter = ['kind' => $kind]` (ou `[]`) e passa
    `conditions: $filter, queryParams: $filter` ao `Event::paginate`.
  - `$kind` entra no `compact` (a view usa para o `selected`).
- `core/Database/ActiveRecord/Model.php`: `paginate()` ganha
  `array $conditions = []` e `array $queryParams = []`, cada um com
  `@param array<string, mixed>`, repassados ao `Paginator` (modelo:
  `HasMany::paginate`).
- `lib/Paginator.php`: parâmetro novo no fim do construtor,
  `private array $queryParams = []`, e o getter `queryParams()` com
  `@return array<string, mixed>`. O `conditions` já existia (o
  `buildConditions` faz `WHERE kind = :kind`).
- `app/views/paginator/_pages.phtml`: os três links usam
  `array_merge(['page' => X], $paginator->queryParams())`, o que gera
  `/admin/events/page/2?kind=lecture`.
- `app/views/admin/events/index.phtml`: `<form method="get">` para
  `route('admin.events.index')` com `<select name="kind"
  id="filter_kind">` ("Todos" + `Event::KINDS`, `selected` no atual)
  e botão "Filtrar". O formulário fica acima da tabela, então aparece
  mesmo sem resultados; nesse caso a mensagem é "Nenhum evento deste
  tipo.".
- Testes:
  - `EventTest`:
    `test_paginate_should_filter_by_conditions_and_keep_query_params`
    (7 Palestras + 1 Oficina; página 2 de 5 traz só as 2 Palestras
    que sobram). O teste de `where` já existia
    (`test_where_should_filter_by_conditions`).
  - `EventsControllerTest`:
    - `test_index_filters_by_kind_and_keeps_filter_in_page_links`:
      7 Palestras e 3 Oficinas intercaladas; com `kind=lecture`, a
      página 1 tem Palestra 01 a 05, nenhuma Oficina, o link
      `/admin/events/page/2?kind=lecture` e a opção `selected`; a
      página 2 tem Palestra 06 e 07.
    - `test_index_ignores_invalid_kind` (data provider: `''`,
      `'xyz'`, `['lecture']`): lista tudo, sem `?kind=` nos links.
    - `test_index_with_kind_without_events_shows_message`.
  - O Cest do filtro fica no S1 (`s1-teste-aceitacao-filtro`).

## Por que assim

- A rubrica pede `where`: aqui é o `WHERE kind = :kind` do
  `Paginator` (mesma igualdade do `Model::where`), com `bindValue`.
- O `_pages.phtml` é incluído dentro de
  `Paginator::renderPagesNavigation()`; lá só existem `$paginator` e
  `$this`. Por isso o filtro viaja dentro do `Paginator`
  (`queryParams()`).
- `is_string` antes do `array_key_exists`: `?kind[]=x` chega como
  array e o `array_key_exists` lançaria `TypeError` (500).
- Lista fechada (`Event::KINDS`): valor desconhecido não vai para o
  SQL e não aparece nos links.

### Forma 1 (alternativa rápida, sem paginação no filtro)

Se o tempo estiver curto, dá para filtrar com `Model::where` e
desligar a paginação só quando há filtro:

```php
// EventsController::index
$kind = $request->getParam('kind');
$kind = is_string($kind) && array_key_exists($kind, Event::KINDS)
    ? $kind : null;

if ($kind !== null) {
    $events = Event::where(['kind' => $kind]);
    $paginator = null;
} else {
    $paginator = Event::paginate(/* como antes */);
    $events = $paginator->registers();
}
// $kind no compact, junto com events, paginator e title
```

Na `index.phtml`, troque a última chamada por
`<?php if ($paginator !== null) : ?>` +
`$paginator->renderPagesNavigation()` + `endif`. Não mexe em
`Paginator`, `Model` nem `_pages.phtml`; o custo é mostrar todos os
Eventos do tipo numa página só.

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

Resultado obtido: Unit 160, Integration 50, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela (populate): `/admin/events?kind=workshop` mostra só Oficinas
com "Oficina" selecionado; `/admin/events?kind[]=x` responde 200 com
a listagem normal. Com o `BaseEventsCest` (16 Eventos, 4 tipos em
rodízio) cada tipo tem 4 Eventos: para ver a página 2 filtrada,
cadastre mais Eventos do mesmo tipo (o S1 faz isso no Cest).

## Relação com outros cenários

- S1 (`s1-teste-aceitacao-filtro`) é empilhado sobre este: escreve
  o Cest do filtro (filtrar, ver só o tipo, página 2 com o filtro).
- N4 (`n4-per-page`) usa o mesmo `queryParams` no `Paginator`; as
  duas branches saem de `main`. Se aplicar as duas, fique com uma só
  propriedade `queryParams` e junte `kind` e `per_page` no mesmo
  array.
- N2 (busca) e N5 (futuros/passados) desligam a paginação nos seus
  modos (forma 1). Combinados com este, decida se o filtro vale
  junto com a busca (`where` não faz `LIKE`).

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n1 \
    s50/fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao/projeto
git fetch fvp-n1 fvp/n1-filtro-por-tipo-paginacao
git cherry-pick 0fe3c48
git remote remove fvp-n1
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao/solucao.bundle \
    fvp/n1-filtro-por-tipo-paginacao:fvp-n1
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick 0fe3c48
# opcional, limpa a ref
git branch -D fvp-n1
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao/solucao.patch
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

- `lib/Paginator.php` e `Model::paginate`: per_page (N4) e ordenação
  (N3) também acrescentam parâmetros. Fique com todos, sem repetir
  `queryParams`.
- `EventsController::index`: junte os argumentos nomeados na mesma
  chamada de `Event::paginate(...)` e as variáveis no `compact`.
- `index.phtml`: busca (N2) e abas (N5) também põem formulário ou
  links no topo; mantenha os dois blocos.
- `EventsControllerTest` e `EventTest`: testes novos no mesmo ponto;
  fique com os dois lados (e com um só `use ...DataProvider`).

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `Model::paginate` original não repassa `conditions`: passar o
  filtro só para o controller "não faz nada".
- `'kind' => $kind` dentro do `_pages.phtml`: variável indefinida no
  partial, warning, `ErrorsHandler`, 500.
- Sem `queryParams`, a página 2 volta sem filtro (e mostra outra
  faixa "Mostrando ...").
- `array` sem PHPDoc em `Model::paginate` (`core/`): PHPStan nível
  6, `missingType.iterableValue`.
- Nos testes, não confira a ausência de "Oficina"/"Palestra" na
  página inteira: os rótulos aparecem no `<select>`. Confira os
  títulos dos Eventos (`Oficina 01`), ou só o trecho da tabela.
- `ControllerTestCase::get` acumula os `params` no mesmo `Request`
  dentro de um teste: na segunda chamada repita `kind`.
- Forma 1: `$paginator` nulo chamado na view dá 500.
