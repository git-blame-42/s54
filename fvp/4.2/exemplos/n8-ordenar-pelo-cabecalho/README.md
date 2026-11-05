# N8. Ordenar clicando no cabeçalho

Pedido, como o professor faria na hora: "Na listagem de Eventos, o
usuário clica no cabeçalho Título, Data ou Vagas para ordenar; clicar
de novo inverte. A ordem tem de continuar ao mudar de página. Por
padrão, data crescente."

Chance: média. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N8.

Branch no clone: `fvp/n8-ordenar-pelo-cabecalho`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                  |
|-----------|-----------------------------------------------------------|
| `0868108` | `feat: Sort events listing by clicking the table header`  |

## O que mudou

- `lib/Paginator.php`: o construtor ganha `?string $orderBy = null` e
  `array $queryParams = []` (no fim, opcionais). `loadRegisters()`
  põe `ORDER BY {$orderBy}` antes do `LIMIT`; `queryParams()` devolve
  os parâmetros extras.
- `core/Database/ActiveRecord/Model.php`: `paginate()` repassa
  `orderBy` e `queryParams` (argumentos nomeados, opcionais).
- `app/Models/Event.php`:
  - `SORTABLE = ['title' => 'Título', 'starts_on' => 'Data',
    'capacity' => 'Vagas']` (lista fechada, com o rótulo do
    cabeçalho), `DEFAULT_SORT = 'starts_on'`, `DEFAULT_DIR = 'asc'`;
  - `normalizeSort($sort, $dir): array{string, string}`: coluna fora
    da lista ou direção diferente de `asc`/`desc` cai no padrão;
  - `orderByClause($sort, $dir)`: monta `"{coluna} ASC|DESC, id ASC"`
    só com valores da lista.
- `EventsController::index`: lê `sort` e `dir` com `getParam`, passa
  pelo `normalizeSort` e chama
  `Event::paginate(..., orderBy: Event::orderByClause($sort, $dir),
  queryParams: ...)`. Na ordenação padrão `queryParams` fica vazio
  (URL limpa); senão vai `['sort' => ..., 'dir' => ...]`. `sort` e
  `dir` entram no `compact`.
- `app/views/paginator/_pages.phtml`: closure
  `$pageUrl = fn (int $p) => route(..., array_merge(['page' => $p],
  $paginator->queryParams()))` nos três tipos de link.
- `app/views/admin/events/index.phtml`: closure `$sortHeader($coluna)`
  gera o link do cabeçalho (`route('admin.events.index', ['sort' =>
  ..., 'dir' => ...])`, escapado com `htmlspecialchars`). Na coluna
  ativa aparece ▲ (asc) ou ▼ (desc) e o link inverte a direção.
- Testes:
  - `tests/Unit/Lib/PaginatorTest.php` (novo, 6): sem `orderBy` mantém
    a ordem do banco; com `orderBy` ordena antes do `LIMIT` (página 2
    certa); `DESC`; `queryParams` vazio por padrão; com `queryParams`
    os links viram `/admin/events/page/2?sort=title&dir=desc`; sem
    eles, ficam sem `?`.
  - `EventTest` (2): `normalizeSort` aceita só a lista e recusa
    tentativas como `'id; DROP TABLE events'`, array e `'DESC'`
    maiúsculo; `orderByClause` desempata por `id`.
  - `EventsControllerTest` (7): padrão por data com ▲; título asc e
    desc; vagas desc; inválido cai no padrão; links do cabeçalho
    invertem a direção da coluna ativa; links de página mantêm
    `?sort=...&dir=...` e a página 2 continua a ordem; padrão sem
    query nos links.
  - Cest novo `SortEventsCest` (3): clicar em "Título", de novo em
    "Título ▲" (vira desc, Evento 16 primeiro), ir à página 2 (URL
    `/admin/events/page/2?sort=title&dir=desc`, Evento 11 a 07);
    vagas asc e desc; URL inválida cai em "Data ▲".
  - `CreateSuccessfullyCest` ajustado: com data crescente por padrão,
    o Evento novo (amanhã) abre a 1ª página em vez de ir para a 4ª.
    Comentários do `BaseEventsCest` e do `ListEventsCest` atualizados
    (a ordem padrão agora é por data, não a do banco).

## Por que assim

- Lista fechada: `ORDER BY` não aceita `bindValue` (só valores, não
  nomes de coluna). Por isso o valor da requisição nunca entra no
  SQL: ele só escolhe uma chave de `SORTABLE`, e o SQL usa a chave
  validada. É a mesma ideia do `inclusion` nas validações.
- A regra (colunas permitidas e cláusula) fica no model; o controller
  só lê os parâmetros e repassa; a view só monta os links.
- Desempate por `id ASC`: com datas, títulos ou vagas iguais o MySQL
  pode devolver em qualquer ordem, e um Evento apareceria em duas
  páginas (ou em nenhuma).
- `orderBy` no `Paginator` (como o N3) e não `usort` na página: o
  `usort` ordenaria só os 5 da página atual.
- `queryParams` no `Paginator` (como N1 e N4): o `_pages.phtml` só
  enxerga `$paginator`; variável do controller não chega lá.
- Valor inválido cai no padrão em vez de dar erro: é um parâmetro de
  URL, não um formulário.
- Padrão `starts_on asc` muda a ordem da base (antes era a do banco).
  No populate e no `BaseEventsCest` a ordem de id já coincide com a
  de data, então só o Cest que cria um Evento novo mudou.

## Como verificar

```
./run up -d db php php_test web
./run phpcs
./run phpstan
./run test tests/Unit
./run test tests/Integration
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Resultado obtido: Unit 167 testes, Integration 52, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `PaginatorTest`:
  - `test_without_order_by_should_keep_the_database_order`
  - `test_order_by_should_be_applied_before_the_limit`
  - `test_order_by_desc`
  - `test_query_params_should_default_to_empty`
  - `test_query_params_should_be_kept_in_the_page_links`
  - `test_page_links_without_query_params_stay_unchanged`
- `EventTest`:
  - `test_normalize_sort_should_accept_only_the_allowed_columns_...`
    (`..._and_directions`)
  - `test_order_by_clause_should_break_ties_by_id`
- `EventsControllerTest`:
  - `test_index_orders_by_starts_on_asc_by_default`
  - `test_index_orders_by_title_asc_and_desc`
  - `test_index_orders_by_capacity_desc`
  - `test_index_with_invalid_sort_falls_back_to_default`
  - `test_index_header_links_toggle_the_direction_of_the_active_...`
    (`..._column`)
  - `test_index_keeps_sort_in_the_page_links`
  - `test_index_with_default_sort_keeps_page_links_without_query`
- `SortEventsCest`:
  - `sortByTitleClickingTheHeaderAndKeepItOnPage2`
  - `sortByCapacity`
  - `invalidSortFallsBackToDate`

No navegador (populate): `/admin/events` mostra "Data ▲". Clique em
"Vagas" e depois em "Vagas ▲": o Evento de 300 vagas abre a lista, e
o link "2" da paginação leva `?sort=capacity&dir=desc`. Por HTTP:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    'http://localhost/admin/events?sort=capacity&dir=desc' \
    | grep -E '▲|▼|page/2'
```

## Relação com outros cenários

- N3 (`n3-ordenar-por-data`): mesmo `orderBy` no `Paginator` e no
  `Model::paginate`, com ordem fixa. Este cenário é o N3 com a ordem
  escolhida pelo usuário; se o N3 já estiver aplicado, fique com o
  `orderBy` dele no `Paginator` e troque o `'starts_on, id'` fixo do
  controller por `Event::orderByClause($sort, $dir)`.
- N1 (`n1-filtro-por-tipo-paginacao`) e N4 (`n4-per-page`): também
  criam `queryParams` e a closure `$pageUrl`. Com eles, junte os
  parâmetros num array só (ex.: `['kind' => ..., 'sort' => ...,
  'dir' => ...]`) e faça os links do cabeçalho levarem o filtro e o
  `per_page` junto (e vice-versa: o filtro leva `sort`/`dir`).
- S1 (`s1-teste-aceitacao-filtro`): modelo de Cest de listagem, como
  o `SortEventsCest`.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/n8-ordenar-pelo-cabecalho`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n8-ordenar-pelo-cabecalho
cd /caminho/do/seu/repositorio
git remote add fvp-n8 \
    s50/fvp/4.2/exemplos/n8-ordenar-pelo-cabecalho/projeto
git fetch fvp-n8 fvp/n8-ordenar-pelo-cabecalho
git cherry-pick 0868108
git remote remove fvp-n8
```

### 2. A partir do `solucao.bundle`

```
D=s50/fvp/4.2/exemplos/n8-ordenar-pelo-cabecalho
git fetch $D/solucao.bundle fvp/n8-ordenar-pelo-cabecalho:fvp-n8
git cherry-pick 0868108
# opcional, limpa a ref
git branch -D fvp-n8
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/n8-ordenar-pelo-cabecalho
# cria commit novo com a mesma mensagem
git am $D/solucao.patch
# só o diff, sem commit
git apply --3way $D/solucao.patch
```

### Se der conflito

```
# arquivos marcados "both modified"
git status
# edite, remova os marcadores <<<<<<< ======= >>>>>>>
git add <arquivo>
git cherry-pick --continue
# ou: git am --continue
# desistir: git cherry-pick --abort / git am --abort
```

Arquivos que costumam conflitar e por quê:

- `lib/Paginator.php` (construtor e `loadRegisters`) e
  `Model::paginate`: N3 cria `orderBy`; N1 e N4 criam `queryParams`.
  Fique com um parâmetro de cada (não duplique) e com os dois no
  final do construtor; confira a ordem dos argumentos se algum
  chamador usar posição em vez de nome.
- `_pages.phtml`: N1 e N4 criam a mesma closure `$pageUrl`; mantenha
  uma só.
- `EventsController::index`: junte as chamadas num único
  `Event::paginate(...)` com todos os argumentos nomeados, e todas as
  variáveis no `compact`.
- `index.phtml`: o `<thead>` e o topo do arquivo; N1/N4 acrescentam o
  formulário de filtro acima da tabela.
- `CreateSuccessfullyCest`: o N3 fez o mesmo ajuste (Evento novo na
  1ª página); fique com uma versão.

## Armadilhas

- Interpolar `$request->getParam('sort')` no SQL: injeção de SQL
  (`?sort=id;DROP TABLE events`). Sempre passe pela lista fechada.
- `ORDER BY` depois do `LIMIT` dá erro de sintaxe; antes dele e
  depois do `WHERE`.
- Sem `id` no desempate, a paginação pode repetir ou pular Eventos
  com valor igual (o teste da página 2 pega isso).
- Ordem de texto é a da collation da tabela (`utf8mb4_0900_ai_ci`):
  ignora maiúsculas e acentos. "Álgebra", "algoritmos" e "Arte"
  saem em ordem alfabética "natural", não na ordem de bytes que um
  `strcmp`/`usort` daria (onde "Á" e minúsculas vão para o fim).
  Títulos iguais a menos de caixa/acento empatam, e quem decide é o
  `id`. Se o professor quiser diferenciar, `ORDER BY title COLLATE
  utf8mb4_0900_as_cs`, ainda vindo da lista fechada.
- `$sort` e `$dir` fora do `compact`: a closure do cabeçalho usa
  variável indefinida, warning, 500.
- `route()` com chaves que não estão na URI vira query string
  (`?sort=...&dir=...`); na view do cabeçalho o `&` sai escapado
  (`&amp;`) porque passa por `htmlspecialchars`; nos links de página
  (molde N1/N4) sai cru. O navegador aceita os dois; os testes
  conferem cada forma.
- `click('Título', 'thead')` no Cest: depois de ordenar, o texto vira
  "Título ▲"; clique por esse texto para inverter.
- No `PaginatorTest` (Unit), `route()` precisa das rotas: o `setUp`
  faz `Router::reset()` e carrega `config/routes.php`, como o
  `ControllerTestCase`.
- `lib/` fora do phpstan: o PHPDoc dos parâmetros novos do
  `Paginator` é para quem lê; o `Model.php` (em `core/`) é conferido.
