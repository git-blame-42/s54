# N13. Filtro que `where` não expressa: público-alvo e "só com vagas"

Pedido, como o professor faria na hora: "Na listagem de Eventos,
filtre pelo público-alvo (`?audience=students`) e por 'só com vagas'
(`?available=1`). O `Model::where` não serve: escreva o SQL no
model."

Chance: média-baixa. Tempo estimado: 25-35 min. Catálogo:
`s50/fvp/4.2/catalogo.md`, N13.

Branch no clone: `fvp/n13-filtro-find-in-set-vagas`, a partir de
`main` (`f31006b` do público; no bundle `5dedab5`, ver README raiz).

| Commit    | Mensagem                                                 |
|-----------|----------------------------------------------------------|
| `1c7ab1a` | `feat: Filter events by audience and available spots`    |

## O que mudou

- `app/Models/Event.php`: `searchFiltered(?string $audience, bool
  $onlyAvailable): array` (`@return array<static>`). Monta o `WHERE`
  só com trechos fixos e liga o valor do usuário por `bindValue`:

```sql
SELECT id, <colunas> FROM events
WHERE FIND_IN_SET(:audience, audience) > 0
  AND capacity > (SELECT COUNT(*) FROM event_user
                  WHERE event_user.event_id = events.id)
ORDER BY id
```

  Cada condição só entra se o filtro foi pedido; sem nenhum, devolve
  todos em ordem de id (a da listagem).
- `EventsController::index`: `audience` só vale se for chave de
  `Event::AUDIENCES` (senão `null`); `available` só vale se for `'1'`.
  Com algum filtro, `$events = Event::searchFiltered(...)` e
  `$paginator = null`; sem filtro, a paginação de sempre.
  `audience`, `onlyAvailable` e `filtering` entram no `compact`.
- `app/views/admin/events/index.phtml`: formulário GET acima da
  tabela com `<select name="audience">` ("Todos os públicos" + os
  rótulos de `AUDIENCES`), checkbox `available=1` "Só com vagas",
  "Filtrar" e, com filtro ativo, "Limpar filtros". Sem paginador,
  mostra "N evento(s) encontrado(s)."; sem resultado com filtro,
  "Nenhum evento encontrado com esses filtros.".
- Testes:
  - `EventTest` (5): FIND_IN_SET casa só o público certo (sozinho ou
    junto de outros no SET); não casa pedaço de valor
    (`'professor'`) nem lista inteira (`'students,professors'`); só
    com vagas exclui o lotado e mantém o quase lotado; os dois
    filtros combinados; sem filtro, todos em ordem de id.
  - `EventsControllerTest` (5): o form aparece; filtro por público
    mostra os 6 sem paginação, com a opção `selected`; só com vagas
    tira o lotado e marca o checkbox; sem resultado mostra a
    mensagem; valores inválidos (`"students') OR 1=1 -- "`,
    `available=sim`) caem na listagem padrão paginada.
  - Cest novo `FilterEventsCest` (2): filtro "Professores" (8
    Eventos ímpares da base numa página só, sem `.pagination`);
    "Só com vagas" (15, sem o Evento 01 lotado), combinado com
    Professores (7) e "Limpar filtros" de volta à listagem paginada.

## Por que assim

- `where()` do framework só faz igualdade (`col = :col`). `audience` é
  um SET (`'students,professors'`): igualdade com `'students'` não
  casa o Evento que tem os dois. `FIND_IN_SET` procura o item na
  lista separada por vírgula. `LIKE '%students%'` também casaria,
  mas pega pedaço de valor (ver o teste com `'professor'`).
- "Só com vagas" depende de outra tabela: a subconsulta correlacionada
  conta as Inscrições de cada Evento no próprio banco, sem carregar
  objetos nem chamar `enrollmentsCount()` por linha (N+1).
- SQL no model, com `prepare` + `bindValue`; o valor do usuário nunca
  é interpolado. A lista fechada (`AUDIENCES`) é uma segunda barreira
  e também o que alimenta o `<select>`.
- Decisão: com filtro, sem paginação. O `Paginator` só aceita
  condições de igualdade (`buildConditions`); passar SQL cru para ele
  exigiria mudar `lib/Paginator.php` e o `Model::paginate`. A lista
  filtrada de um câmpus é curta; a contagem aparece embaixo. Se o
  professor exigir paginação com filtro, ver N9 (abaixo).
- Decisão: valor inválido é ignorado em silêncio e cai em "todos"
  (sem flash). É um filtro de URL; o `<select>` volta para "Todos os
  públicos", então o usuário vê que nada foi filtrado. Se o professor
  pedir aviso, `FlashMessage::danger('Filtro inválido ignorado!')`
  no ramo do `null`.
- Ordem por `id`, igual à listagem sem filtro.

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

Resultado obtido: Unit 164 testes, Integration 50, Acceptance 25 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros. Schema não mudou.

Testes novos:

- `EventTest`:
  - `test_search_filtered_by_audience_should_match_only_events_...`
    (`..._with_that_audience`)
  - `test_search_filtered_by_audience_should_not_match_parts_of_a_...`
    (`..._value`)
  - `test_search_filtered_only_available_should_exclude_full_events`
  - `test_search_filtered_should_combine_both_filters`
  - `test_search_filtered_without_filters_should_return_all_events_...`
    (`..._in_id_order`)
- `EventsControllerTest`:
  - `test_index_shows_the_filter_form`
  - `test_index_filters_by_audience_without_pagination`
  - `test_index_filters_only_events_with_available_spots`
  - `test_index_with_filters_and_no_results_shows_message`
  - `test_index_ignores_invalid_filter_values`
- `FilterEventsCest`:
  - `filterByAudience`
  - `filterOnlyWithAvailableSpotsAndClear`

No navegador (populate): `/admin/events`, "Comunidade externa" + "Só
com vagas", Filtrar: 6 Eventos, sem paginação. Por HTTP:

```
docker compose exec web curl -s -c /tmp/c \
    -d 'user[email]=admin@example.com&user[password]=123456' \
    http://localhost/login
docker compose exec web curl -s -b /tmp/c \
    'http://localhost/admin/events?audience=community&available=1' \
    | grep encontrado
```

## Relação com outros cenários

- N1 (`n1-filtro-por-tipo-paginacao`): filtro por `kind` com
  igualdade, que o `Paginator` aceita, e por isso mantém a paginação
  (`queryParams`). Com os dois, ou o `kind` vira mais uma condição de
  `searchFiltered` (`kind = :kind`, sem paginação), ou tudo vai para
  o `Paginator` do N9.
- N9 (`n9-filtro-por-periodo`): estende o `Paginator` com condições
  que não são igualdade e mantém a paginação. Para paginar este
  filtro, siga o N9: um parâmetro a mais no `Paginator` com os
  trechos fixos de SQL (`FIND_IN_SET`, subconsulta) e os binds, usado
  também no `COUNT(*)` do total.
- N11 (`n11-model-count`): `Model::count()` resolve o N+1 da coluna
  "inscritos/vagas"; a subconsulta daqui é a mesma ideia dentro do
  `WHERE`.

## Como aplicar em outro repositório (cherry-pick)

Pré-requisito: o repositório alvo descende de `f31006b` (ou de algo
compatível). `<caminho>` abaixo é o diretório deste cenário no s50:
`s50/fvp/4.2/exemplos/n13-filtro-find-in-set-vagas`.

### 1. A partir do clone `projeto/` (remote temporário)

```
# antes: devolve o .git do projeto/ a partir do bundle
s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n13-filtro-find-in-set-vagas
cd /caminho/do/seu/repositorio
git remote add fvp-n13 \
    s50/fvp/4.2/exemplos/n13-filtro-find-in-set-vagas/projeto
git fetch fvp-n13 fvp/n13-filtro-find-in-set-vagas
git cherry-pick 1c7ab1a
git remote remove fvp-n13
```

### 2. A partir do `solucao.bundle`

```
D=s50/fvp/4.2/exemplos/n13-filtro-find-in-set-vagas
git fetch $D/solucao.bundle fvp/n13-filtro-find-in-set-vagas:fvp-n13
git cherry-pick 1c7ab1a
# opcional, limpa a ref
git branch -D fvp-n13
```

### 3. A partir do `solucao.patch`

```
D=s50/fvp/4.2/exemplos/n13-filtro-find-in-set-vagas
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

- `EventsController::index`: N1, N3, N4, N8, N9 mexem na mesma chamada
  de `Event::paginate`. Decida um caminho (lista filtrada sem
  paginação ou tudo no `Paginator`) e junte as variáveis no
  `compact`.
- `index.phtml`: outros formulários de filtro acima da tabela (N1,
  N2, N9). Junte num `<form method="get">` só, senão um filtro apaga
  o outro ao enviar.
- `app/Models/Event.php`: os `use Core\Database\Database;` e
  `use PDO;` podem já existir (R9); deixe um de cada.

## Armadilhas

- `WHERE audience = :audience`: não casa Evento com mais de um
  público. É exatamente o que o `where()` faria.
- `LIKE CONCAT('%', :audience, '%')`: casa pedaço de valor.
- Interpolar `$audience` no SQL: injeção. O teste manda
  `"students') OR 1=1 -- "` e confere que a listagem não muda.
- `FIND_IN_SET` com espaço depois da vírgula não casa
  (`'students, staff'`); o MySQL grava SET sem espaço, então aqui não
  acontece, mas acontece com `VARCHAR` digitado à mão.
- A subconsulta precisa do `events.id` qualificado (`event_user.
  event_id = events.id`); sem o prefixo, `id` seria o da própria
  `event_user` e a conta sai errada.
- `$paginator` vira `null` com filtro: a view precisa do
  `if ($paginator !== null)` antes de `renderPagesNavigation()`, senão
  erro fatal.
- `available` vem como string: compare com `'1'`, não com `true`.
- Checkbox desmarcado não é enviado: ausência é "todos", e é por isso
  que não há `available=0`.
