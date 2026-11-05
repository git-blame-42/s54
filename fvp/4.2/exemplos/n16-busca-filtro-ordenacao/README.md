# N16. Busca, filtro e ordenação juntos, mantidos na paginação

Pedido, como o professor faria na hora: "Na listagem de Eventos,
busca pelo título, filtro por tipo e ordenação escolhida (coluna e
direção), tudo junto e preservado ao trocar de página." Variante: "a
listagem aceita `?q=`, `?kind=`, `?sort=` e `?dir=`, e o link da
página 2 mantém os quatro".

Chance: média-alta. Tempo estimado: 35-45 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N16.

Branch no clone: `fvp/n16-busca-filtro-ordenacao`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).
Autocontido: não depende de N1, N2, N3 nem N8 (ver "Relação com
outros cenários").

| Commit    | Mensagem                                              |
|-----------|-------------------------------------------------------|
| `f9d7cf3` | `feat: Search, filter and sort admin events together` |

## O que mudou

- `lib/Paginator.php`: quatro parâmetros opcionais no fim do
  construtor, nesta ordem: `where` (condição extra com
  placeholders), `binds` (valores dela), `orderBy` e `queryParams`
  (com o getter `queryParams()`).
  - `buildConditions()` junta as igualdades de `conditions` com o
    `where` (`AND`); o mesmo texto serve ao `COUNT(*)` de
    `loadTotals()` e ao `SELECT` de `loadRegisters()`.
  - `bindConditions()` liga `conditions + binds`.
  - `loadRegisters()` põe o `ORDER BY` antes do `LIMIT`.
  - Depois do `COUNT`, `page = max(1, min(page, totalOfPages))`:
    página além da última vira a última.
- `app/Models/Event.php`:
  - `SORTS = ['id' => 'Cadastro', 'title' => 'Título', 'starts_on'
    => 'Data']` e `DIRS = ['asc' => 'Crescente', 'desc' =>
    'Decrescente']` (listas fechadas, com o rótulo do formulário);
  - `listingFilters(array $params): array`: devolve `[$filters,
    $errors]`. Ausente ou vazio é o padrão (`q` vazio, `kind`
    nulo, `sort` `id`, `dir` `asc`). Inválido ganha mensagem na
    chave do parâmetro ("Busca inválida (até 255 caracteres).",
    "Tipo inválido.", "Ordenação inválida.", "Direção inválida.")
    e o valor padrão. O privado `choice()` confere cada lista;
  - `listingPage($filters, $page, $perPage, $route): Paginator`:
    monta `title LIKE :q ESCAPE '!'` (com `!`, `%` e `_`
    escapados por `strtr`), `kind` em `conditions` e o ORDER BY
    (`"{coluna} ASC|DESC, id ASC"`, ou `id ASC|DESC` no padrão),
    conferindo `sort` e `dir` de novo;
  - `listingQuery($filters)`: só o que difere do padrão, para os
    links de página.
- `app/Controllers/Admin/EventsController.php` (`index`): `[$filters,
  $errors] = Event::listingFilters($request->getParams())`, depois
  `Event::listingPage(...)`; `filters` e `errors` entram no
  `compact`. O `page` sai do novo privado `pageParam()`
  (`filter_var` com `min_range` 1, senão 1), no lugar do `(int)`.
- `app/views/paginator/_pages.phtml`: closure `$pageUrl = fn (int
  $p) => route(..., ['page' => $p] + $paginator->queryParams())`
  nos três tipos de link.
- `app/views/admin/events/index.phtml`: `use App\Models\Event;` no
  topo; `<form method="get">` para `route('admin.events.index')`
  com `filter_q`, `filter_kind` ("Todos os tipos" + `KINDS`),
  `filter_sort` (`SORTS`) e `filter_dir` (`DIRS`), botão
  "Filtrar"; cada erro em `<div class="alert alert-warning">` com
  `htmlspecialchars`; sem resultado com filtro, "Nenhum evento
  encontrado.".
- Testes:
  - `EventTest` (+19): padrão com `[]` e com tudo vazio; valores
    válidos; 7 inválidos (data provider: `kind=party`,
    `sort=description`, `sort="id; DROP TABLE events"`,
    `dir=DESC`, `sort[]`, `q[]`, `q` com 256 caracteres); válidos
    mantidos ao lado de inválidos; 255 caracteres aceitos; 7
    Oficinas + 1 Palestra "Oficina..." + 2 sem: total 7, 2
    páginas, 2 na página 2; maiúsculas ignoradas; título desc e
    asc com iguais saindo por `id`; data desc; "100%" não acha
    "100 dias", "a_b" não acha "axb", `!` literal, aspas sem
    injeção; página 9 vira a 2 (e sem resultado, a 1);
    `listingPage` recusa `sort`/`dir` não conferidos;
    `listingQuery` só fora do padrão.
  - `EventsControllerTest` (+7): os três juntos (o teste do
    catálogo) com formulário preenchido; página 2 com links;
    `sort=description` com "Ordenação inválida." e ordem de
    cadastro; dois inválidos, dois avisos, válidos mantidos; sem
    parâmetros, links limpos; termo escapado e "Nenhum evento
    encontrado."; `page=2abc` vira 1 e `page=9` vira a última.
  - Cest novo `SearchEventsCest` (2): busca "1", Título,
    Decrescente, "Filtrar" (8 Eventos, Evento 16 primeiro); página
    2 com `?q=1&sort=title&dir=desc` na URL e Evento 01 na última
    linha; Palestra junto (Eventos 13 e 01). E
    `?kind=party&sort=title&dir=desc` com o aviso e a ordem mantida.

## Por que assim

- O framework não tem `LIKE` nem `ORDER BY`: `where` e `Paginator`
  só fazem igualdade. O WHERE e o ORDER BY nascem no model
  (`listingPage`); o `Paginator` executa, porque é ele que o
  `_pages.phtml` enxerga. O `COUNT` e a página passam pelo mesmo
  `buildConditions()`/`bindConditions()`: o total não tem como
  contar uma coisa e a página mostrar outra.
- `LIKE` com bind: o termo nunca entra no SQL. `%` e `_` são
  curingas; sem escape, "100%" acharia "100 dias" e "a_b" acharia
  "axb". O escape padrão do MySQL é a barra invertida, que também
  teria de ser escapada; `ESCAPE '!'` deixa a regra explícita.
- `ORDER BY` não aceita bind (nome de coluna não é valor): lista
  fechada. `listingPage()` é pública e pode ser chamada sem
  `listingFilters()`, por isso confere `sort` de novo e troca
  `dir` por `ASC`/`DESC` fixos.
- Desempate por `id`: com valores iguais o MySQL devolve em
  qualquer ordem, e um Evento poderia aparecer em duas páginas.
- Padrão `id` (ordem de cadastro, a que a base já mostrava): o
  `test_index_lists_five_events_per_page` (todos os Eventos na
  mesma data) e o `CreateSuccessfullyCest` seguem verdes.
- Padrão fora da query string: o `PaginateEventsCest` compara a URL
  exata (`/admin/events/page/2`). `http_build_query` mantém `''`
  (`?kind=`), por isso `listingQuery()` filtra.
- Inválido mostra aviso e usa o padrão, sem 500 e sem silêncio. Os
  avisos vão em `$errors` na view, não em flash: `FlashMessage`
  guarda um texto por tipo, e com dois inválidos só o último
  apareceria. O `_flash_message.phtml` nem escapa a saída.
- `is_string` antes de `trim` e de `array_key_exists`: `?q[]=x`
  chega como array e daria `TypeError` (500).
- Página além da última vira a última no `Paginator`: depois de
  filtrar, `/admin/events/page/4?q=Oficina` cairia em "Nenhum
  evento cadastrado.", sem paginação para voltar.
- `page` com `filter_var`, não `(int)`: `(int) '2abc'` vira 2.
  Página inválida vira 1 (é navegação, não filtro).

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

Resultado obtido: Unit 178, Integration 52, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela (populate), logado como admin:

- buscar "de", Título, Decrescente: "Mostrando 1 - 5 de 14", link
  `/admin/events/page/2?q=de&sort=title&dir=desc`; na página 2 a
  ordem continua.
- `/admin/events/page/9?q=Oficina`: as 4 Oficinas, "Mostrando 1 -
  4 de 4".
- `/admin/events?kind=x&dir=up`: avisos "Tipo inválido." e
  "Direção inválida." e a listagem padrão.

## Relação com outros cenários

Este cenário reúne, num só pedido, o que quatro cenários fazem
separados. Não é empilhado: sai de `main` e não precisa deles.

- N1 (`n1-filtro-por-tipo-paginacao`), forma 2: filtro por tipo em
  `conditions` e `queryParams` no `Paginator`. Aqui também, mas o
  tipo inválido gera aviso em vez de "sem filtro" calado.
- N2 (`n2-busca-por-titulo`): busca com `LIKE`, sem paginação e com
  `%`/`_` como curinga. Aqui a busca pagina e os escapa.
- N3 (`n3-ordenar-por-data`): `orderBy` no `Paginator` com ordem
  fixa. Aqui a ordem vem de `SORTS`/`DIRS`.
- N8 (`n8-ordenar-pelo-cabecalho`): ordenação escolhida pelos
  links do cabeçalho, com lista fechada; inválido cai no padrão
  calado e o padrão vira data. Aqui a escolha é no formulário, o
  inválido gera aviso e o padrão continua o cadastro.
- Não aplique N16 junto com N1, N2, N3 ou N8: mexem nos mesmos
  pontos (`index`, `index.phtml`, `_pages.phtml`, `Paginator`).
  Fique com este, que já cobre os quatro.
- O12 (`o12-busca-ordenacao-json`) é empilhado sobre este e sobre o
  O4: a listagem JSON reaproveita `listingFilters` e `listingPage`
  e responde 422 no inválido.
- N4 (`n4-per-page`) também usa `queryParams`; se aplicar os dois,
  junte `per_page` ao array de `listingQuery`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n16-busca-filtro-ordenacao`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n16-busca-filtro-ordenacao`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n16 \
    s50/fvp/4.2/exemplos/n16-busca-filtro-ordenacao/projeto
git fetch fvp-n16 fvp/n16-busca-filtro-ordenacao
git cherry-pick f9d7cf3
git remote remove fvp-n16
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n16-busca-filtro-ordenacao/solucao.bundle \
    fvp/n16-busca-filtro-ordenacao:fvp-n16
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick f9d7cf3
# opcional, limpa a ref
git branch -D fvp-n16
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n16-busca-filtro-ordenacao/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n16-busca-filtro-ordenacao/solucao.patch
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

- `lib/Paginator.php`: N1, N3, N4 e N8 também acrescentam
  parâmetros no fim do construtor. Fique com um só `queryParams` e
  um só `orderBy`; mantenha `where`, `binds` e o ajuste da página.
- `EventsController::index`: quase todo cenário de listagem mexe
  aqui. A chamada `Event::paginate(...)` virou
  `Event::listingPage(...)`; leve o que o outro cenário acrescentou
  para `listingFilters`/`listingPage`.
- `index.phtml` e `_pages.phtml`: formulários no topo e a closure
  `$pageUrl`; mantenha uma closure só.
- `EventTest` e `EventsControllerTest`: testes novos no mesmo
  ponto; fique com os dois lados (e um só `use ...DataProvider`).

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- `LIKE` só no `SELECT`: "Mostrando 1 - 2 de 16" e links para
  páginas vazias. O `COUNT` tem de usar o mesmo WHERE; o
  `ORDER BY` fica só no `SELECT`.
- `conditions + binds`: a chave da esquerda vence. Um bind chamado
  `:kind` sumiria calado; por isso o da busca é `:q`.
- `bindValue(':q', "%{$q}%")` sem escapar passa nos testes comuns e
  falha em "100%" ou "a_b". Escape o próprio `!` primeiro.
- `ORDER BY {$request->getParam('sort')}`: injeção de SQL. Só
  nomes da lista entram no SQL.
- `?sort=id&dir=asc` sempre nos links quebra o
  `PaginateEventsCest` (URL exata). Só o que foge do padrão vai.
- Form GET: o navegador descarta a query string do `action`. Os
  quatro campos ficam no form; filtrar volta à página 1.
- Filtro na rota (`/admin/events/page/{page}/{sortBy}`) não casa o
  regex do `Route.php` (só minúsculas, `_` e vírgula): 404.
- `ControllerTestCase::get` acumula os `params` no mesmo `Request`:
  um segundo `get` sem `q` ainda busca o `q` anterior. Um cenário
  por teste.
- Padrão `starts_on`: quebra o `CreateSuccessfullyCest` (N3) e, sem
  desempate, o teste de cinco por página (mesma data para todos).
- Avisos em flash: só o último de dois inválidos apareceria.
- Nos testes de controller, "Oficina"/"Palestra" aparecem no
  `<select>`; confira os títulos dos Eventos, não o rótulo do tipo.
- A busca segue a collation (`utf8mb4_0900_ai_ci`): "oficina" acha
  "Oficina" e "introducao" acha "Introdução".
