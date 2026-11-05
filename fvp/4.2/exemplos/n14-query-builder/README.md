# N14. Query Builder mínimo no core

Pedido, como o professor faria na hora: "Em vez de SQL à mão no model e
de parâmetros soltos no `paginate`, crie um Query Builder como o da
aula: `Event::query()->where(...)->orderBy(...)->paginate(...)`. Use-o
na listagem de Eventos, ordenada pela data."

Chance: baixa. Tempo estimado: 40-60 min.
Catálogo: `s50/fvp/4.2/catalogo.md`, N14.

Branch no clone: `fvp/n14-query-builder`, a partir de `main`
(`f31006b` do público).

| Commit    | Mensagem                                                     |
|-----------|--------------------------------------------------------------|
| `c19445c` | `feat: Add minimal query builder to the core`                |
| `aeb29e0` | `feat: Order events listing by date with the query builder`  |

## O que mudou

- `core/Database/ActiveRecord/QueryBuilder.php` (classe nova). Guarda
  a classe do model, a tabela, as colunas, a lista de condições
  `[coluna, operador, valor]` e a lista de ordens `[coluna, direção]`.
  Métodos:
  - `where('kind', 'lecture')`: igualdade (dois argumentos);
    `where('starts_on', '>=', $data)`: operador explícito. Operadores
    em lista fechada: `=`, `!=`, `<`, `<=`, `>`, `>=`, `LIKE`.
  - `orderBy('starts_on')` / `orderBy('id', 'DESC')`: só `ASC`/`DESC`.
  - `get()`: executa e devolve `array<T>` (instâncias do model).
  - `paginate(page, perPage, route)`: devolve o `Paginator` de sempre.
  - `toSql()`, `whereSql()`, `orderBySql()`, `bindings()`: o SQL e os
    valores, para teste e para passar ao `Paginator`.
  - Coluna fora de `id` + `$columns`, operador ou direção fora da
    lista, ou valor não escalar: `InvalidArgumentException`.
- `core/Database/ActiveRecord/Model.php`: `query()`, que devolve
  `new QueryBuilder(static::class, static::$table, static::$columns)`
  com `@return QueryBuilder<static>`.
- `lib/Paginator.php`: três parâmetros novos no fim do construtor,
  `string $whereSql = ''`, `array $bindings = []` e
  `?string $orderBy = null`. Com `whereSql`, `buildConditions` e
  `bindConditions` usam o fragmento e os valores do builder; sem ele,
  o caminho antigo (`conditions`) segue igual. `loadRegisters` põe
  `ORDER BY` entre o `WHERE` e o `LIMIT :limit OFFSET :offset`.
- `app/Controllers/Admin/EventsController.php`: `index` usa
  `Event::query()->orderBy('starts_on')->orderBy('id')->paginate(...)`.
- Testes novos:
  - `tests/Unit/Core/Database/QueryBuilderTest.php` (sem banco, 9):
    SQL completo com `:p0`/`:p1`, mesma coluna duas vezes, operador e
    direção em minúsculas, e as cinco exceções (operador, coluna no
    `where`, valor array, coluna no `orderBy`, direção).
  - `tests/Unit/Core/Database/QueryBuilderDatabaseTest.php` (4):
    `get()` filtra e ordena no banco; faixa de datas com a mesma
    coluna; valor com aspas não injeta e `LIKE` funciona; `paginate`
    conta só os filtrados (7 de 14) e mantém a ordem na página 2.
  - `EventsControllerTest`: dois testes novos, com o helper
    `assertTitlesInOrder` (posição com `strpos`):
    `test_index_orders_events_by_date_across_pages` (7 Eventos com
    datas fora de ordem; páginas 1 e 2 em ordem crescente) e
    `test_index_breaks_date_ties_by_id`.
- `CreateSuccessfullyCest`: o Evento novo (amanhã) agora abre a
  página 1 e não aparece na 4. Comentários de `BaseEventsCest` e
  `ListEventsCest` sobre "ordem do banco" atualizados.

## Por que assim

- Escopo mínimo: `where` + `orderBy` + `get` + `paginate`, que é o
  que o pedido cita. Ficaram de fora `count()`, `first()`, `orWhere`,
  `whereIn`, `limit` e condição crua (`FIND_IN_SET`, do N13). Diga
  isso ao professor: o tempo da prova não comporta um builder inteiro.
- Valores só por `bindValue`, com placeholders numerados (`:p0`,
  `:p1`, ...). Com `:starts_on`, a faixa "de/até" na mesma coluna
  repetiria o nome: o segundo `bindValue` sobrescreve o primeiro e a
  consulta usa a data final nas duas comparações, sem erro.
- Coluna, operador e direção não aceitam bind (são parte do SQL, não
  valores). Por isso passam por lista fechada com
  `in_array(..., true)`; qualquer outra coisa lança exceção. Assim um
  `?sort=` vindo da URL não vira injeção, mesmo que alguém o repasse.
- `toSql()`/`bindings()` públicos: os testes de montagem rodam sem
  banco (rápidos) e o `Paginator` recebe as mesmas peças que o
  `get()` usa, sem duplicar a lógica de condições.
- O `Paginator` continua sendo quem faz `COUNT(*)` e
  `LIMIT/OFFSET`: o builder só entrega `whereSql`, `bindings` e
  `orderBy`. Os parâmetros são opcionais e vêm por último, então
  `Model::paginate`, `HasMany`/`BelongsToMany` e os testes antigos de
  paginação não mudam.
- O nome e o formato de `orderBy` no `Paginator` (sem a palavra
  `ORDER BY`) são os mesmos do N3, para os dois cenários combinarem.
- PHPDoc dos arrays (`list<array{0: string, 1: string, 2: ...}>`) e
  `@template T of Model`: phpstan nível 6 sem erros e `get()` tipado.
- `id` como desempate da data: sem ele, datas iguais trocam de página
  entre requisições.

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
# classe nova: confira o PSR-4
./run composer dump-autoload -o --strict-psr
```

Resultado obtido: Unit 172, Integration 47, Acceptance 23 (base:
159 / 45 / 23). PHPCS e PHPStan sem erros; PSR-4 estrito ok. Schema
não mudou.

No terminal, para ver o SQL montado:

```
docker compose exec -T php php -r 'require "vendor/autoload.php";
  echo App\Models\Event::query()->where("kind", "lecture")
  ->orderBy("starts_on")->toSql(), PHP_EOL;'
```

Saída (uma linha só no terminal):

```
SELECT id, user_id, title, description, kind, modality, audience,
starts_on, location, capacity FROM events WHERE kind = :p0
ORDER BY starts_on ASC
```

No navegador, o populate sozinho não mostra diferença (as datas já
crescem com o `id`). Cadastre em `/admin/events/new` um Evento para
amanhã: ele aparece na primeira linha da página 1. Sem a ordenação,
iria para a página 4.

## Relação com outros cenários

- N3 (`n3-ordenar-por-data`): mesma ordenação, por parâmetro
  `orderBy: 'starts_on, id'` em `Model::paginate`. Aqui a ordem vem do
  builder. O parâmetro `orderBy` do `Paginator` é o mesmo nos dois;
  ao juntar, fique com uma cópia só dele.
- N1 (`n1-filtro-por-tipo-paginacao`): com o builder, o filtro vira
  `->where('kind', $kind)` (valide `$kind` como no N1). O builder não
  leva o filtro para os links da página 2: para isso, use o
  `queryParams` do N1 no `Paginator` e repasse-o em `paginate()`.
- N13 (`n13-filtro-find-in-set-vagas`): `FIND_IN_SET` e a subconsulta
  de vagas não são `coluna operador valor`; o builder recusa. Ficam
  nos métodos do model do N13, ou num `whereRaw(string $sql,
  array $bindings)` futuro (fora do escopo mínimo).
- N2 (`n2-busca-por-titulo`): `->where('title', 'LIKE', "%{$q}%")`
  resolve a busca com bind.
- N15 (`n15-dois-paginadores`): o exemplo põe no `Paginator`
  `comparisons` (`starts_on >= hoje` / `<`) e o mesmo `orderBy` deste
  cenário, mais `pageParam`/`routeParams`. Com o builder, cada tabela
  seria `Event::query()->where('starts_on', '>=', $hoje)->orderBy(...)`,
  mas `paginate()` daqui não repassa `pageParam` nem `routeParams`:
  ao juntar, acrescente os dois à chamada do `Paginator` no builder e
  mantenha um só `orderBy`.

## Como aplicar em outro repositório (cherry-pick)

No s50, `projeto/` vem sem o `.git` interno. Use o caminho do
`solucao.bundle`, que funciona direto; o caminho pelo clone
`projeto/` só vale depois de
`s50/fvp/recriar.sh s50/fvp/4.2/exemplos/n14-query-builder`.

Pré-requisito: o repositório alvo descende de `f31006b` do público
(ou de algo compatível). `<caminho>` abaixo é
`s50/fvp/4.2/exemplos/n14-query-builder`. Os dois commits vão juntos,
nesta ordem (o segundo usa o builder do primeiro).

### 1. A partir do clone `projeto/` (remote temporário)

```
cd /caminho/do/seu/repositorio
git remote add fvp-n14 \
    s50/fvp/4.2/exemplos/n14-query-builder/projeto
git fetch fvp-n14 fvp/n14-query-builder
git cherry-pick c19445c aeb29e0
git remote remove fvp-n14
```

### 2. A partir do `solucao.bundle` (não precisa do clone)

```
git fetch \
    s50/fvp/4.2/exemplos/n14-query-builder/solucao.bundle \
    fvp/n14-query-builder:fvp-n14
# o bundle traz os objetos; o cherry-pick cria commits novos
git cherry-pick c19445c aeb29e0
# opcional, limpa a ref
git branch -D fvp-n14
```

### 3. A partir do `solucao.patch` (`git am`)

```
# cria os dois commits com as mesmas mensagens
git am s50/fvp/4.2/exemplos/n14-query-builder/solucao.patch
# só o diff, sem commit
git apply --3way \
    s50/fvp/4.2/exemplos/n14-query-builder/solucao.patch
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

- `lib/Paginator.php` (construtor, `loadRegisters`,
  `buildConditions`, `bindConditions`): N1, N3, N4, N9 e N15 também
  acrescentam parâmetros. Fique com todos, os deste por último; no
  SQL a ordem é `WHERE ... ORDER BY ... LIMIT ...`. Se o N3 já
  trouxe `orderBy`, mantenha um só.
- `Model.php`: `query()` entra logo antes de `where()`; outro cenário
  que mexe em `paginate()` não conflita na lógica.
- `EventsController::index`: se outro cenário passa `conditions:` ou
  `orderBy:` a `Event::paginate`, troque pela cadeia do builder
  (`->where(...)`, `->orderBy(...)`) e junte os argumentos.
- `CreateSuccessfullyCest`: se outro cenário mudou a parte da
  listagem, fique com a versão deste (Evento novo na página 1).

Depois de aplicar: `./run composer dump-autoload -o --strict-psr`
(classe nova), os testes e o `./run phpcs` (sem mudança de schema).

## Armadilhas

- Interpolar coluna, operador ou direção sem lista fechada: injeção
  de SQL. Valor sempre por `bindValue`, nunca no texto do SQL.
- Placeholder com o nome da coluna (`:starts_on`): de/até na mesma
  coluna repete o nome. Com os prepares emulados do PDO MySQL (o
  padrão aqui) não há erro: o segundo `bindValue` vence e a faixa
  some em silêncio (conferido: `>= :s AND <= :s` com 2000 e 2100
  conta 0).
- `where('x', null)` geraria `x = NULL`, que nunca é verdadeiro; aqui
  valor não escalar lança exceção. `IS NULL` ficou fora do escopo.
- `LIMIT`/`OFFSET` seguem com `PDO::PARAM_INT` no `Paginator`; como
  string, o MySQL recusa (`'5'` entre aspas).
- `ORDER BY` depois do `LIMIT`: erro de sintaxe.
- Com `whereSql` preenchido, o `Paginator` ignora `conditions`: ligar
  os dois juntos faria o PDO reclamar de parâmetro sem placeholder.
- Classe nova em `core/`: namespace `Core\Database\ActiveRecord` e
  nome do arquivo iguais ao da classe, senão o `--strict-psr` falha.
- phpstan nível 6: `QueryBuilder` é genérico; `Model::query()` sem
  `@return QueryBuilder<static>` dá erro de tipo genérico ausente.
- `CreateSuccessfullyCest` esperava o Evento novo na página 4: com a
  ordenação ele sobe para a página 1. É o Cest que quebra.
