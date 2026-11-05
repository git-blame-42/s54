# N15. Dois paginadores na mesma tela (próximos/passados)

Pedido, como o professor faria na hora: "Na listagem de Eventos, mostre
duas tabelas, Próximos e Passados, cada uma com a sua paginação de 5.
Trocar de página em uma não pode mexer na outra."

Chance: baixa. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N15.

Branch no clone: `fvp/n15-dois-paginadores`, a partir de `main`
(`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `ec15dda` | `feat: Paginate upcoming and past events in two tables`  |

## O que mudou

- `lib/Paginator.php`: quatro parâmetros novos no fim do construtor,
  todos opcionais (quem já chama não muda):
  - `array $routeParams = []`: parâmetros fixos dos links de página
    (aqui, a página atual da outra tabela);
  - `string $pageParam = 'page'`: nome do parâmetro de página nos
    links, com o getter `getPageParam()`;
  - `array $comparisons = []`, no formato
    `['starts_on' => ['>=', '2026-11-05']]`: `buildConditions()`
    gera `starts_on >= :starts_on_cmp` junto com as igualdades de
    `conditions`, e `bindConditions()` faz o `bindValue`. Operador
    fora de `<`, `<=`, `>`, `>=`, `<>` lança
    `InvalidArgumentException`;
  - `?string $orderBy = null`: `ORDER BY` entre o `WHERE` e o
    `LIMIT` (o `COUNT(*)` não precisa).
  - Método novo `routeParams(int $page)`: os fixos mais
    `[$pageParam => $page]`.
- `app/views/paginator/_pages.phtml`: closure `$pageUrl` no topo,
  `htmlspecialchars(route($paginator->getRouteName(),
  $paginator->routeParams($page)))`; os três links usam
  `$pageUrl(X)`.
- `app/Models/Event.php` (depois de `hasEnrollments()`):
  - `paginateUpcoming(page, per_page, route, pageParam,
    routeParams)`: `starts_on >= hoje`, `ORDER BY starts_on, id`;
  - `paginatePast(...)`: `starts_on < hoje`,
    `ORDER BY starts_on DESC, id DESC` (o mais recente primeiro);
  - os dois chamam o privado `paginateByStartsOn`, que monta o
    `Paginator` com `date('Y-m-d')`. Import novo: `Lib\Paginator`.
- `app/Controllers/Admin/EventsController.php`, `index`:
  - `PAST_PAGE_PARAM = 'past_page'` e o privado `pageNumber()`:
    ausente vira 1; aceita só dígitos (`ctype_digit`), sem zero à
    esquerda nem estouro (`$value === (string) (int) $value`), e
    `>= 1`. Inválido (`abc`, `2abc`, `0`, `-1`, `02`, `1.5`, array)
    devolve `null`, e o `index` responde com a flash "Página
    inválida!", redirect para `/admin/events` e `return`;
  - página depois da última, em qualquer das tabelas: flash "Página
    inexistente! Mostrando a última página." e redirect para a
    última página daquela tabela, mantendo a outra (privado
    `indexUrl()`), ex.: `/admin/events/page/2?past_page=99` vai para
    `/admin/events/page/2?past_page=2`;
  - "Próximos" usa o `page` e a rota de sempre
    (`admin.events.paginate`, `/admin/events/page/{page}`);
    "Passados" usa `past_page` na query string de
    `admin.events.index`;
  - cada paginador recebe em `routeParams` a página da outra tabela,
    só quando ela é maior que 1 (página 1 fica fora da URL);
  - `compact('upcoming', 'past', 'title')`.
- `app/views/admin/events/_table.phtml` (novo): a tabela que estava no
  `index.phtml`, lendo `$paginator` e `$emptyMessage`; chama
  `renderPagesNavigation()` no fim.
- `app/views/admin/events/index.phtml`: "Novo Evento" e duas seções,
  `#upcoming-events` ("Próximos") e `#past-events` ("Passados"), cada
  uma com `require __DIR__ . '/_table.phtml'`. Vazias: "Nenhum evento
  próximo." / "Nenhum evento passado." (sem tabela nem paginação).
- Testes:
  - `EventTest` (+6): `paginateUpcoming` (hoje entra, ontem não,
    ordem de data com Eventos criados fora de ordem, 2 páginas);
    `paginatePast` (hoje não entra, mais recente primeiro); passados
    vazio; `pageParam` padrão `page`; `pageParam` próprio com
    `routeParams`; operador inválido lança exceção.
  - `EventsControllerTest` (+19, 10 do data provider): as duas
    tabelas paginam separadas; `past_page=2` não mexe em "Próximos" e
    os links de "Próximos" levam `?past_page=2`; as duas na página 2
    (link `/admin/events?page=2&amp;past_page=1`); vazias de cada
    lado; páginas inválidas (data provider) redirecionam com "Página
    inválida!"; `'2'` como string é aceita; página depois da última
    em "Passados" e em "Próximos" vai para a última mantendo a outra;
    `past_page=2` sem passados vai para `/admin/events`.
  - `TwoPaginatorsCest` (novo, 3): move os Eventos 01 a 07 para o
    passado, pagina "Passados", depois "Próximos", depois volta em
    "Passados", conferindo URL e linhas das duas tabelas a cada
    clique; `?past_page=99` (vai para a última com o aviso) e
    `?past_page=abc` ("Página inválida!"); sem passados, uma
    paginação só.
  - `CreateSuccessfullyCest`: o Evento novo (amanhã) agora é a 1ª
    linha de "Próximos" ("Mostrando 1 - 5 de 17"), e não a página 4.

## Por que assim

- O problema do cenário: o `Paginator` só conhece `page`
  (`_pages.phtml` monta `route($nome, ['page' => N])`) e a rota
  `"$table.paginate"`. Com duas tabelas e o mesmo `page`, clicar na
  página 2 de uma move as duas. Por isso o nome do parâmetro virou
  configurável no construtor, com `'page'` de padrão.
- `_pages.phtml` só enxerga `$paginator` (incluído por
  `renderPagesNavigation()`). A página da outra tabela tem de viajar
  dentro do `Paginator`: `routeParams`. Parâmetro que não está na URI
  vira query string (`Router::appendQueryParams`).
- "Próximos" fica com `page` e a rota `/admin/events/page/{page}`:
  links antigos e os Cests de paginação da base continuam valendo.
  "Passados" usa a query string de `admin.events.index`, porque
  `admin.events.paginate` exige `{page}` na URI.
- `Model::paginate` não repassa condições e o `Paginator` só faz
  igualdade. `>=` e `<` pedem `comparisons`; o `Event` monta o
  `Paginator` num método próprio (como o N9), sem mexer em `core/`.
- `ORDER BY` explícito: sem ele, o `WHERE starts_on < :hoje` fez o
  MySQL ler pelo índice `index_events_on_starts_on`, e a página de
  passados veio do mais antigo para o mais novo, não na ordem do id.
  A ordem "do banco" depende do plano da consulta.
- Operador e `orderBy` entram no SQL como texto: vêm só do model
  (lista fechada de operadores), nunca do usuário. Valores sempre por
  `bindValue`.
- Partial `_table.phtml`: a tabela aparece duas vezes; copiar o
  `<table>` no `index.phtml` também funciona, com o dobro de código.
- `htmlspecialchars` no `href`: com dois parâmetros na query string o
  link leva `&`, que vira `&amp;` no HTML.
- Página inválida com flash + redirect, sem `(int)` às cegas:
  `(int) 'abc'` é 0 e `(int) '2abc'` é 2, e o `max(1, ...)`
  esconderia o erro mostrando a página 1. Mesma regra do N10
  (`n10-pagina-id-invalido`), aplicada aos dois parâmetros.
- Página depois da última vai para a última (não para a 1), sem
  perder a página da outra tabela. Sem isso, `?past_page=99` mostra
  "Nenhum evento passado." e nenhuma paginação para voltar (o
  `_table.phtml` só desenha a tabela e a paginação se houver
  registros).

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

Resultado obtido: Unit 165, Integration 64, Acceptance 26 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Na tela: o populate só tem Eventos futuros. "Próximos" mostra 5 de 16
(4 páginas); "Passados" mostra "Nenhum evento passado.". Para ver as
duas paginações, leve seis Eventos para o passado (o formulário
recusa data passada):

```
# root sem senha (MYSQL_ALLOW_EMPTY_PASSWORD); <banco> = DB_DATABASE
docker compose exec db mysql -uroot <banco> \
    -e "UPDATE events SET starts_on = CURDATE() - INTERVAL id DAY \
WHERE id <= 6"
```

Abra `/admin/events?past_page=2`: "Passados" na página 2 ("Mostrando
6 - 6 de 6"), "Próximos" na 1 ("Mostrando 1 - 5 de 10"); o link "2"
de "Próximos" aponta para `/admin/events/page/2?past_page=2`. Depois,
`./run db:populate` devolve os dados.

## Relação com outros cenários

- N5 (`n5-eventos-futuros-passados`): mesmas listas em abas, sem
  paginação ("o `Paginator` só sabe igualdade"). Este cenário é a
  versão paginada. Juntos, fique com uma tela só (abas ou duas
  tabelas); os `upcoming()`/`past()` do N5 podem ficar para outras
  telas, e a ordem é a mesma nos dois.
- O6 (`o6-inscritos-pagina-propria`): também põe `routeParams` no
  `Paginator`, no mesmo ponto (logo depois de `route`) e com o mesmo
  método `routeParams(int $page)`. Para juntar, fique com a versão
  deste cenário do `Paginator` (o `routeParams()` daqui usa
  `$pageParam` em vez de `'page'` fixo; com o padrão dá o mesmo) e
  com a closure `$pageUrl` do `_pages.phtml` (faz a mesma chamada do
  O6, com linhas abaixo de 130 colunas). O `HasMany::paginate` do O6
  continua igual, passando `routeParams:`.
- N1 (`n1-filtro-por-tipo-paginacao`): `queryParams` resolve o mesmo
  problema dos links que `routeParams`; fique com um só mecanismo. Com
  filtro por tipo nas duas tabelas, passe `kind` em `conditions` e em
  `routeParams` dos dois paginadores.
- N3 (`n3-ordenar-por-data`): também cria `?string $orderBy` no
  `Paginator`, com o mesmo `ORDER BY` antes do `LIMIT`; fique com um
  só parâmetro.
- N9 (`n9-filtro-por-periodo`): `ranges` faz `BETWEEN`.
  `comparisons` é indexado por coluna, então não faz duas
  comparações na mesma coluna; para período, use o `ranges` do N9.
- N4 (`n4-per-page`): se o usuário escolhe o tamanho, ponha
  `per_page` em `routeParams` dos dois paginadores.
- N10 (`n10-pagina-id-invalido`): mesma validação de página
  (`ctype_digit`, flash + redirect), só para `page`, e
  `Paginator::isOutOfRange()`. Juntos, fique com um helper só para
  ler página (o N10 chama de `positiveInt`) e use-o nos dois
  parâmetros; o "depois da última" daqui precisa manter a página da
  outra tabela no redirect.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n15-dois-paginadores`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n15-dois-paginadores`.

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n15 \
    s50/fvp/4.2/exemplos/n15-dois-paginadores/projeto
git fetch fvp-n15 fvp/n15-dois-paginadores
git cherry-pick ec15dda
git remote remove fvp-n15
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n15-dois-paginadores/solucao.bundle \
    fvp/n15-dois-paginadores:fvp-n15
# o bundle traz o objeto; o cherry-pick cria commit novo
git cherry-pick ec15dda
# opcional, limpa a ref
git branch -D fvp-n15
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria commit novo com a mesma mensagem
git am s50/fvp/4.2/exemplos/n15-dois-paginadores/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n15-dois-paginadores/solucao.patch
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

- `lib/Paginator.php`: construtor, `buildConditions()`,
  `bindConditions()` e `loadRegisters()` (O6, N1, N3, N4, N9 mexem
  aí). Fique com todos os parâmetros novos, sem repetir nome
  (`routeParams`, `orderBy`).
- `app/views/paginator/_pages.phtml`: os três links. Fique com uma
  closure `$pageUrl` só, que use `routeParams()` (e `queryParams()`,
  se veio do N1/N4).
- `app/views/admin/events/index.phtml`: a tabela saiu daqui para o
  `_table.phtml`. Quem mexe na linha da tabela (coluna nova, badge)
  aplica a mudança no `_table.phtml`; o que fica acima da tabela
  (filtro, busca, abas) continua no `index.phtml`.
- `EventsController::index`: N1, N2, N3, N4 e N5 mexem no mesmo
  trecho. Mantenha as duas chamadas (`paginateUpcoming` e
  `paginatePast`) e repasse a elas o que os outros acrescentam; junte
  as variáveis no `compact`.
- `Event.php`: métodos novos depois de `hasEnrollments()` (N5, N9) e
  os `use` no topo; fique com todos.
- Testes: métodos novos no mesmo ponto; fique com os dois lados. O
  `CreateSuccessfullyCest` muda também no N3 (Evento novo na página
  1); o resultado é o mesmo.

Depois de aplicar: rode os testes e o `./run phpcs` de novo (sem
mudança de schema).

## Armadilhas

- Rota com `{pastPage}` ou `{page2}` (ex.:
  `/admin/events/page/{page}/past/{pastPage}`): parâmetros de rota só
  casam `{[a-z,_]+}`. Maiúscula ou dígito no nome e o trecho é tratado
  como texto fixo: a rota nunca casa. Use `{past_page}`, ou a query
  string, como aqui.
- As duas tabelas com o mesmo `page`: a página 2 de uma move a outra.
- Link de uma tabela sem a página da outra: a outra volta para a 1.
- Tentar passar a página da outra tabela para o `_pages.phtml` por
  variável da view: ele só enxerga `$paginator`.
- Esquecer o `ORDER BY` e escrever teste/Cest supondo a ordem do id:
  com o `WHERE` por `starts_on`, o MySQL pode ler pelo índice e a ordem
  muda. Aconteceu aqui na tabela de passados.
- `Event::where`/`Model::paginate`: só igualdade e sem condições;
  `starts_on >= hoje` não sai deles.
- Comparar com `NOW()` em vez de data: `starts_on` é `DATE`; o
  Evento de hoje sairia de "Próximos". Aqui "hoje" é
  `date('Y-m-d')` do PHP, por `bindValue`.
- Operador ou `orderBy` vindo da requisição: vira injeção de SQL. Só
  texto fixo do model.
- `&` cru no `href`: use `htmlspecialchars` no link.
- `max(1, (int) $request->getParam('past_page'))`: `abc` vira página
  1 em silêncio e `2abc` vira 2. Valide com `ctype_digit`.
- Página fora do intervalo (`?past_page=99`): sem tratamento, a
  tabela some ("Nenhum evento passado.") junto com a paginação, e o
  usuário não tem link para voltar. Redirecione para a última página
  (ou trate como no N10).
- Criar Evento passado com `save()` nos testes: a validação recusa.
  Use `update()` depois de criar (não valida).
- `static::paginateByStartsOn()` num método `private`: o PHPStan acusa
  "Unsafe call to private method ... through static::"; use `self::`.
