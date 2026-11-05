# N16 passo a passo: Busca, filtro e ordenação juntos, mantidos na paginação

Pedido típico: "na listagem, busca pelo título, filtro por tipo e
ordenação escolhida (coluna e direção), tudo junto e preservado ao
trocar de página". Variante: "a listagem aceita `?q=`, `?kind=`,
`?sort=` e `?dir=`, e o link da página 2 mantém os quatro". Chance:
média-alta. Tempo: 35-45 min.

Exemplo pronto: `fvp/4.2/exemplos/n16-busca-filtro-ordenacao/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: sai de `main`,
autocontido (junta N1 forma 2, N2, N3 e N8; não aplique junto com
eles). Schema não muda.

Contexto: o framework não tem `LIKE` nem `ORDER BY`; `where` e
`Paginator` só fazem igualdade unida por `AND`. Hoje a `index` ignora
tudo que não é `page` e a lista sai sem `ORDER BY`.

Ordem = fluxo da requisição: `Paginator` (executa o SQL) -> model
(monta WHERE/ORDER BY, valida) -> controller -> views -> testes. Na
prova, prioridade: funcionar no navegador, depois testar, depois
`phpcs`.

## Arquivos, em ordem

1. `lib/Paginator.php`
2. `app/Models/Event.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/index.phtml`
5. `app/views/paginator/_pages.phtml`
6. `tests/Unit/Models/Events/EventTest.php`
7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
8. `tests/Acceptance/admin/events/SearchEventsCest.php` (novo)

Depois: suíte, `./run phpcs`, `./run phpstan`, `./run test:browser`.

## 1. `lib/Paginator.php`

Cinco pontos.

a) Construtor (linha ~18): docblock acima e quatro parâmetros
opcionais no fim; ajuste da página entre `loadTotals()` e
`loadRegisters()`:

```php
    /**
     * @param array<string, mixed> $conditions filtros por igualdade (WHERE coluna = valor)
     * @param string $where condição SQL extra, com placeholders nomeados, somada às de $conditions com AND
     * @param array<string, mixed> $binds valores dos placeholders de $where
     * @param string|null $orderBy cláusula do ORDER BY; quem chama monta a partir de uma lista fechada
     * @param array<string, string> $queryParams parâmetros mantidos na query string dos links de página
     */
    public function __construct(
        private string $class,
        private int $page,
        private int $per_page,
        private string $table,
        private array $attributes,
        private array $conditions = [],
        private ?string $route = null,
        private string $where = '',
        private array $binds = [],
        private ?string $orderBy = null,
        private array $queryParams = []
    ) {
        $this->loadTotals();
        // Página além da última (ex.: depois de filtrar) vira a última; sem registros, a 1.
        $this->page = max(1, min($this->page, $this->totalOfPages));
        $this->loadRegisters();
    }
```

b) Getter novo depois de `perPage()`:

```php
    /**
     * Parâmetros que os links de página (`_pages.phtml`) mantêm na query string.
     *
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        return $this->queryParams;
    }
```

c) `loadRegisters()` (linha ~122), `ORDER BY` antes do `LIMIT`:

```php
        $attributes = implode(', ', $this->attributes);
        $order = $this->orderBy !== null ? "ORDER BY {$this->orderBy}" : '';

        $sql = <<<SQL
            SELECT id, {$attributes} FROM {$this->table}
            {$this->buildConditions()}
            {$order}
            LIMIT :limit OFFSET :offset
        SQL;
```

d) `buildConditions()` (fim do arquivo), troca o corpo:

```php
    /**
     * O mesmo WHERE serve ao COUNT (loadTotals) e à página (loadRegisters).
     */
    private function buildConditions(): string
    {
        $parts = array_map(
            fn($column) => "{$column} = :{$column}",
            array_keys($this->conditions)
        );
        if ($this->where !== '') {
            $parts[] = "({$this->where})";
        }

        return $parts === [] ? '' : ' WHERE ' . implode(' AND ', $parts);
    }
```

e) `bindConditions()`, troca o corpo:

```php
    private function bindConditions(PDOStatement $stmt): void
    {
        foreach ($this->conditions + $this->binds as $name => $value) {
            $stmt->bindValue($name, $value);
        }
    }
```

Por quê:

- `Paginator` é quem executa o SQL e é o único objeto que
  `_pages.phtml` enxerga; por isso guarda também `queryParams`.
- Parâmetros novos opcionais e no fim: `Event::paginate()` e quem já
  chama o construtor continuam funcionando.
- `COUNT` (`loadTotals`) e página (`loadRegisters`) passam pelo mesmo
  `buildConditions()`/`bindConditions()`: o total não tem como contar
  uma coisa e a página mostrar outra.
- `ORDER BY` só no `SELECT` (no `COUNT` é inútil).
- `$where` entre parênteses: um `OR` futuro não escapa do `AND`.
- `max(1, min(...))`: depois de filtrar, `/page/4?q=Oficina` (1
  página) cairia em "Nenhum evento cadastrado." sem link de volta.
  Sem registros, `totalOfPages` é 0 -> página 1.

Armadilhas:

- `LIKE` só no `SELECT`: "Mostrando 1 - 2 de 16" e links pra páginas
  vazias.
- `conditions + binds`: a chave da esquerda vence. Bind chamado
  `:kind` sumiria calado; use `:q`.

## 2. `app/Models/Event.php`

Cinco pontos.

a) Import, entre `Model` e `Validations`:

```php
use Core\Database\ActiveRecord\Model;
use Lib\Paginator;
use Lib\Validations;
```

b) Docblock da classe, depois de `@property int|string $capacity`:

```php
 * @property int|string $capacity
 *
 * @phpstan-type ListingFilters array{q: string, kind: ?string, sort: string, dir: string}
 */
```

c) Listas fechadas depois de `AUDIENCES` (antes de
`protected static string $table`):

```php
    /** Ordens da listagem (`?sort=`), com o rótulo do formulário; 'id' (cadastro) é o padrão. */
    public const SORTS = [
        'id' => 'Cadastro',
        'title' => 'Título',
        'starts_on' => 'Data',
    ];

    /** Direções da listagem (`?dir=`), com o rótulo do formulário; 'asc' é o padrão. */
    public const DIRS = [
        'asc' => 'Crescente',
        'desc' => 'Decrescente',
    ];
```

d) Quatro métodos novos depois de `hasEnrollments()`:

```php
    /**
     * Lê os parâmetros da listagem (q, kind, sort, dir) como chegaram da requisição.
     *
     * Ausente ou vazio: sem busca, todos os tipos, ordem de cadastro, crescente. Inválido: não some
     * calado; ganha uma mensagem na chave do parâmetro e o valor padrão no lugar.
     *
     * @param array<mixed> $params
     * @return array{0: ListingFilters, 1: array<string, string>}
     */
    public static function listingFilters(array $params): array
    {
        $errors = [];

        $q = $params['q'] ?? null;
        $q = is_string($q) ? trim($q) : $q;
        $qValid = $q === null || (is_string($q) && mb_strlen($q) <= 255);
        if (!$qValid) {
            $errors['q'] = 'Busca inválida (até 255 caracteres).';
        }

        $kind = self::choice($params['kind'] ?? null, self::KINDS);
        $sort = self::choice($params['sort'] ?? null, self::SORTS);
        $dir = self::choice($params['dir'] ?? null, self::DIRS);
        $checks = [
            'kind' => [$kind, 'Tipo inválido.'],
            'sort' => [$sort, 'Ordenação inválida.'],
            'dir' => [$dir, 'Direção inválida.'],
        ];
        foreach ($checks as $param => [$value, $message]) {
            if ($value === false) {
                $errors[$param] = $message;
            }
        }

        $filters = [
            'q' => $qValid && is_string($q) ? $q : '',
            'kind' => is_string($kind) ? $kind : null,
            'sort' => is_string($sort) ? $sort : 'id',
            'dir' => is_string($dir) ? $dir : 'asc',
        ];

        return [$filters, $errors];
    }

    /**
     * Uma página da listagem com busca por título, filtro por tipo e ordenação, tudo junto.
     *
     * O WHERE e o ORDER BY são montados aqui; o Paginator usa o mesmo WHERE (e os mesmos valores)
     * no COUNT e na consulta da página, que recebe o ORDER BY e o LIMIT/OFFSET.
     *
     * @param ListingFilters $filters valores lidos por listingFilters()
     */
    public static function listingPage(array $filters, int $page, int $perPage, string $route): Paginator
    {
        $where = '';
        $binds = [];
        if ($filters['q'] !== '') {
            // '!' escapa os curingas do LIKE: "100%" procura o texto "100%", não "100" seguido de qualquer coisa.
            $term = strtr($filters['q'], ['!' => '!!', '%' => '!%', '_' => '!_']);
            $where = "title LIKE :q ESCAPE '!'";
            $binds['q'] = "%{$term}%";
        }

        // Nome de coluna não aceita bind: só entra o que está na lista. O id desempata valores iguais.
        $column = array_key_exists($filters['sort'], self::SORTS) ? $filters['sort'] : 'id';
        $dir = $filters['dir'] === 'desc' ? 'DESC' : 'ASC';
        $order = $column === 'id' ? "id {$dir}" : "{$column} {$dir}, id ASC";
        $kind = $filters['kind'];

        return new Paginator(
            class: static::class,
            page: $page,
            per_page: $perPage,
            table: static::$table,
            attributes: static::$columns,
            conditions: $kind !== null ? ['kind' => $kind] : [],
            route: $route,
            where: $where,
            binds: $binds,
            orderBy: $order,
            queryParams: self::listingQuery($filters),
        );
    }

    /**
     * Query string dos links de página: só o que difere do padrão.
     *
     * @param ListingFilters $filters
     * @return array<string, string>
     */
    public static function listingQuery(array $filters): array
    {
        return array_filter([
            'q' => $filters['q'],
            'kind' => $filters['kind'] ?? '',
            'sort' => $filters['sort'] === 'id' ? '' : $filters['sort'],
            'dir' => $filters['dir'] === 'asc' ? '' : $filters['dir'],
        ], fn(string $value) => $value !== '');
    }

    /**
     * Valor de uma lista fechada: null se ausente ou vazio, false se inválido.
     *
     * @param array<string, string> $allowed
     */
    private static function choice(mixed $value, array $allowed): string|false|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) && array_key_exists($value, $allowed) ? $value : false;
    }
```

Por quê:

- Validação e SQL no model; controller só repassa. `SORTS`/`DIRS`
  servem de allowlist **e** de rótulo do form (view lê a constante).
- `choice()`: três estados. `null` = ausente/vazio (padrão, sem
  erro); `false` = inválido (erro + padrão); string = válido.
- Inválido não some calado: mensagem na chave do parâmetro, padrão no
  lugar. Sem 500, sem silêncio.
- `is_string` antes de `trim`/`array_key_exists`: `?q[]=x` chega como
  array e daria `TypeError` (500).
- `LIKE` com bind (`:q`): o termo nunca entra no SQL. `strtr` com
  array troca `!`, `%` e `_` numa passada só (o `!!` gerado não é
  reescapado): "100%" não acha "100 dias", "a_b" não acha "axb".
- `ESCAPE '!'`: escape padrão do MySQL é a barra, que também teria de
  ser escapada; `!` explícito vale igual no PostgreSQL.
- `ORDER BY` não aceita bind (nome de coluna não é valor): lista
  fechada. `listingPage()` é pública e pode ser chamada sem
  `listingFilters()`: confere `sort` de novo e troca `dir` por
  `ASC`/`DESC` fixos.
- `, id ASC`: desempate. Valores iguais saem em qualquer ordem e um
  Evento poderia aparecer em duas páginas.
- Padrão `id` (ordem de cadastro, a da base): testes antigos e
  `CreateSuccessfullyCest` seguem verdes.
- `kind` vai em `conditions` (igualdade que o `Paginator` já sabia
  fazer).
- `listingQuery()` só leva o que difere do padrão:
  `PaginateEventsCest` compara URL exata (`/admin/events/page/2`), e
  `http_build_query` mantém `''` (`?kind=`).
- `@phpstan-type ListingFilters`: formato do array num lugar só, pro
  `phpstan`.

Armadilhas:

- `bindValue(':q', "%{$q}%")` sem escapar passa nos testes comuns e
  falha em "100%" ou "a_b". Escape o `!` primeiro.
- `ORDER BY {$request->getParam('sort')}`: injeção de SQL.
- Busca segue a collation (`utf8mb4_0900_ai_ci`): "oficina" acha
  "Oficina", "introducao" acha "Introdução".

## 3. `app/Controllers/Admin/EventsController.php`

a) `index()` (linha ~16), troca `Event::paginate(...)` por:

```php
    public function index(Request $request): void
    {
        [$filters, $errors] = Event::listingFilters($request->getParams());

        $paginator = Event::listingPage(
            $filters,
            page: $this->pageParam($request),
            perPage: self::PER_PAGE,
            route: 'admin.events.paginate'
        );
        $events = $paginator->registers();

        $title = 'Eventos';
        $this->render('admin/events/index', compact('events', 'paginator', 'title', 'filters', 'errors'));
    }
```

b) Privado novo antes de `findEvent()`:

```php
    /**
     * Número da página (`/admin/events/page/{page}` ou `?page=`): inteiro maior que zero, senão a 1.
     * A última página é garantida pelo Paginator.
     */
    private function pageParam(Request $request): int
    {
        $page = filter_var($request->getParam('page', 1), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return is_int($page) ? $page : 1;
    }
```

Por quê:

- Controller fino: `q`, `kind`, `sort`, `dir` vão crus ao model
  (`getParams()`), sem `(int)` nem `?? ''`.
- `filters` e `errors` no `compact`: a view preenche o form e mostra
  os avisos.
- `filter_var` com `min_range` 1, não `(int)`: `(int) '2abc'` vira 2.
  Página inválida vira 1 (é navegação, não filtro). Página além da
  última: `Paginator` resolve.
- Rota `/admin/events/page/{page}` não muda: query string chega em
  `getParams()` junto do `page`.

Armadilha: filtro na rota (`/admin/events/page/{page}/{sortBy}`) não
casa o regex de nome de `core/Router/Route.php` (só minúsculas, `_` e
vírgula): 404.

## 4. `app/views/admin/events/index.phtml`

a) Topo do arquivo, antes do `<div class="mb-3">`:

```php
<?php use App\Models\Event; ?>

```

b) Depois do `</div>` do botão "Novo Evento" e antes do
`if (empty($events))`, o form e os avisos:

```php
<form method="get" action="<?= route('admin.events.index') ?>" class="d-flex gap-2 mb-3">
    <label class="visually-hidden" for="filter_q">Buscar por título</label>
    <input class="form-control" type="search" id="filter_q" name="q" placeholder="Título"
           value="<?= htmlspecialchars($filters['q']) ?>">

    <label class="visually-hidden" for="filter_kind">Tipo</label>
    <select class="form-select" id="filter_kind" name="kind">
        <option value="">Todos os tipos</option>
        <?php foreach (Event::KINDS as $value => $label) : ?>
            <option value="<?= $value ?>" <?= $filters['kind'] === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>

    <label class="visually-hidden" for="filter_sort">Ordenar por</label>
    <select class="form-select" id="filter_sort" name="sort">
        <?php foreach (Event::SORTS as $value => $label) : ?>
            <option value="<?= $value ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>

    <label class="visually-hidden" for="filter_dir">Direção</label>
    <select class="form-select" id="filter_dir" name="dir">
        <?php foreach (Event::DIRS as $value => $label) : ?>
            <option value="<?= $value ?>" <?= $filters['dir'] === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
    </select>

    <button type="submit" class="btn btn-secondary">Filtrar</button>
</form>

<?php foreach ($errors as $error) : ?>
    <div class="alert alert-warning" role="alert"><?= htmlspecialchars($error) ?></div>
<?php endforeach; ?>
```

c) Mensagem de lista vazia (linha ~6), troca
`<p>Nenhum evento cadastrado.</p>` por:

```php
    <p><?= Event::listingQuery($filters) !== [] ? 'Nenhum evento encontrado.' : 'Nenhum evento cadastrado.' ?></p>
```

Por quê:

- Form GET: filtro é leitura, URL compartilhável, sem CSRF/PRG.
- Navegador descarta a query string do `action`: os quatro campos
  ficam no form; `action` aponta pra `admin.events.index` sem `page`,
  então filtrar volta à página 1.
- Form antes do `if (empty($events))`: aparece mesmo sem resultado.
- View burra: opções vêm de `KINDS`/`SORTS`/`DIRS`; `selected` usa
  `$filters` já validado; `htmlspecialchars` no `q` (XSS).
- Avisos de `$errors` na view, não em flash: `FlashMessage` guarda um
  texto por tipo (dois inválidos -> só o último) e o
  `_flash_message.phtml` nem escapa.
- Com filtro e sem resultado, "cadastrado" seria falso.

## 5. `app/views/paginator/_pages.phtml`

a) Topo do arquivo, antes do `<nav>`:

```php
<?php
// URL de cada link: a página mais os parâmetros guardados no Paginator (busca, filtro, ordenação).
$pageUrl = fn (int $p) => route($paginator->getRouteName(), ['page' => $p] + $paginator->queryParams());
?>
```

b) Nos três links, troca `route($paginator->getRouteName(), ['page'
=> ...])` pela closure:

```php
                <a class="page-link" href="<?= $pageUrl($paginator->previousPage()) ?>">Anterior</a>
                <a class="page-link" href="<?= $pageUrl($page) ?>"><?= $page ?></a>
                <a class="page-link" href="<?= $pageUrl($paginator->nextPage()) ?>">Próxima</a>
```

Por quê:

- Partial só enxerga `$paginator`: por isso os filtros moram nele.
- `route()` (`core/Router/Router.php`) põe na query string o que não
  é parâmetro da URI (`http_build_query`): `page` vai no caminho,
  `q`/`kind`/`sort`/`dir` viram `?q=...`.
- Closure única: os três links montam a URL igual.

## 6. `tests/Unit/Models/Events/EventTest.php`

`DataProvider` já está importado. Bloco novo depois de
`test_paginate_should_default_the_route_name_to_the_table` e antes de
`/* --- relações --- */`:

```php
    /* ------------------- listagem: busca, filtro e ordenação ------------------- */

    /**
     * Filtros já validados, com o padrão no que não foi informado.
     *
     * @param array<string, mixed> $params
     * @return array{q: string, kind: ?string, sort: string, dir: string}
     */
    private function filters(array $params = []): array
    {
        [$filters, $errors] = Event::listingFilters($params);
        $this->assertSame([], $errors);

        return $filters;
    }

    /**
     * @param array<Event> $events
     * @return array<int, string>
     */
    private function titles(array $events): array
    {
        return array_map(fn(Event $event) => $event->title, $events);
    }

    public function test_listing_filters_should_default_when_params_are_missing_or_empty(): void
    {
        $default = ['q' => '', 'kind' => null, 'sort' => 'id', 'dir' => 'asc'];

        $this->assertSame([$default, []], Event::listingFilters([]));
        $this->assertSame(
            [$default, []],
            Event::listingFilters(['q' => '  ', 'kind' => '', 'sort' => '', 'dir' => ''])
        );
    }

    public function test_listing_filters_should_accept_valid_params(): void
    {
        $this->assertSame(
            [['q' => 'git', 'kind' => 'workshop', 'sort' => 'starts_on', 'dir' => 'desc'], []],
            Event::listingFilters(['q' => ' git ', 'kind' => 'workshop', 'sort' => 'starts_on', 'dir' => 'desc'])
        );
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidListingParamProvider(): array
    {
        return [
            'tipo fora da lista' => ['kind', 'party', 'Tipo inválido.'],
            'coluna real fora da lista' => ['sort', 'description', 'Ordenação inválida.'],
            'coluna com SQL' => ['sort', 'id; DROP TABLE events', 'Ordenação inválida.'],
            'direção maiúscula' => ['dir', 'DESC', 'Direção inválida.'],
            'sort como array' => ['sort', ['title'], 'Ordenação inválida.'],
            'busca como array' => ['q', ['x'], 'Busca inválida (até 255 caracteres).'],
            'busca com 256 caracteres' => ['q', str_repeat('a', 256), 'Busca inválida (até 255 caracteres).'],
        ];
    }

    #[DataProvider('invalidListingParamProvider')]
    public function test_listing_filters_should_report_invalid_param_and_use_the_default(
        string $param,
        mixed $value,
        string $message
    ): void {
        $default = ['q' => '', 'kind' => null, 'sort' => 'id', 'dir' => 'asc'];

        [$filters, $errors] = Event::listingFilters([$param => $value]);

        $this->assertSame([$param => $message], $errors);
        $this->assertSame($default, $filters);
    }

    public function test_listing_filters_should_keep_valid_params_next_to_an_invalid_one(): void
    {
        [$filters, $errors] = Event::listingFilters(['q' => 'git', 'kind' => 'party', 'sort' => 'title', 'dir' => 'up']);

        $this->assertSame(['kind' => 'Tipo inválido.', 'dir' => 'Direção inválida.'], $errors);
        $this->assertSame(['q' => 'git', 'kind' => null, 'sort' => 'title', 'dir' => 'asc'], $filters);
    }

    public function test_listing_filters_should_accept_a_search_with_255_characters(): void
    {
        $this->assertSame(str_repeat('á', 255), $this->filters(['q' => str_repeat('á', 255)])['q']);
    }

    public function test_listing_page_should_search_filter_and_count_with_the_same_where(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Oficina {$i}", 'kind' => 'workshop']);
        }
        $this->createEvent(['title' => 'Oficina na palestra', 'kind' => 'lecture']);
        $this->createEvent(['title' => 'Palestra 1', 'kind' => 'workshop']);
        $this->createEvent(['title' => 'Palestra 2', 'kind' => 'lecture']);

        $filters = $this->filters(['q' => 'Oficina', 'kind' => 'workshop']);
        $page1 = Event::listingPage($filters, 1, 5, 'admin.events.paginate');
        $page2 = Event::listingPage($filters, 2, 5, 'admin.events.paginate');

        $this->assertSame(7, $page1->totalOfRegisters());
        $this->assertSame(2, $page1->totalOfPages());
        $this->assertSame(['Oficina 1', 'Oficina 2', 'Oficina 3', 'Oficina 4', 'Oficina 5'], $this->titles($page1->registers()));
        $this->assertSame(['Oficina 6', 'Oficina 7'], $this->titles($page2->registers()));
        $this->assertSame(7, $page2->totalOfRegisters());
        $this->assertSame(['q' => 'Oficina', 'kind' => 'workshop'], $page1->queryParams());
    }

    public function test_listing_page_should_ignore_case_in_the_search(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);
        $this->createEvent(['title' => 'Palestra de Docker']);

        $page = Event::listingPage($this->filters(['q' => 'GIT']), 1, 5, 'admin.events.paginate');

        $this->assertSame(['Oficina de Git'], $this->titles($page->registers()));
    }

    public function test_listing_page_should_sort_by_the_chosen_column_and_break_ties_by_id(): void
    {
        $this->createEvent(['title' => 'Bravo']);
        $this->createEvent(['title' => 'Delta']);
        $this->createEvent(['title' => 'Alfa']);
        $this->createEvent(['title' => 'Bravo']);
        $bravos = array_map(fn(Event $event) => $event->id, Event::where(['title' => 'Bravo']));

        $desc = Event::listingPage($this->filters(['sort' => 'title', 'dir' => 'desc']), 1, 5, 'admin.events.paginate');
        $asc = Event::listingPage($this->filters(['sort' => 'title']), 1, 5, 'admin.events.paginate');
        $default = Event::listingPage($this->filters(), 1, 5, 'admin.events.paginate');

        $this->assertSame(['Delta', 'Bravo', 'Bravo', 'Alfa'], $this->titles($desc->registers()));
        $this->assertSame(['Alfa', 'Bravo', 'Bravo', 'Delta'], $this->titles($asc->registers()));
        // Títulos iguais saem por id, nas duas direções.
        $this->assertSame($bravos, [$desc->registers()[1]->id, $desc->registers()[2]->id]);
        $this->assertSame($bravos, [$asc->registers()[1]->id, $asc->registers()[2]->id]);
        $this->assertSame(['Bravo', 'Delta', 'Alfa', 'Bravo'], $this->titles($default->registers()));
    }

    public function test_listing_page_should_sort_by_date_descending(): void
    {
        $this->createEvent(['title' => 'Daqui a 10 dias', 'starts_on' => date('Y-m-d', strtotime('+10 days'))]);
        $this->createEvent(['title' => 'Daqui a 30 dias', 'starts_on' => date('Y-m-d', strtotime('+30 days'))]);
        $this->createEvent(['title' => 'Daqui a 20 dias', 'starts_on' => date('Y-m-d', strtotime('+20 days'))]);

        $page = Event::listingPage($this->filters(['sort' => 'starts_on', 'dir' => 'desc']), 1, 5, 'admin.events.paginate');

        $this->assertSame(['Daqui a 30 dias', 'Daqui a 20 dias', 'Daqui a 10 dias'], $this->titles($page->registers()));
    }

    public function test_listing_page_should_treat_percent_and_underscore_as_literal_text(): void
    {
        $this->createEvent(['title' => '100% prático']);
        $this->createEvent(['title' => '100 dias de código']);
        $this->createEvent(['title' => 'Variável a_b']);
        $this->createEvent(['title' => 'Variável axb']);
        $this->createEvent(['title' => 'Atenção! Vagas']);

        $percent = Event::listingPage($this->filters(['q' => '100%']), 1, 5, 'admin.events.paginate');
        $underscore = Event::listingPage($this->filters(['q' => 'a_b']), 1, 5, 'admin.events.paginate');
        $bang = Event::listingPage($this->filters(['q' => 'o! V']), 1, 5, 'admin.events.paginate');
        $injection = Event::listingPage($this->filters(['q' => "' OR '1'='1"]), 1, 5, 'admin.events.paginate');

        $this->assertSame(['100% prático'], $this->titles($percent->registers()));
        $this->assertSame(['Variável a_b'], $this->titles($underscore->registers()));
        $this->assertSame(['Atenção! Vagas'], $this->titles($bang->registers()));
        $this->assertSame(0, $injection->totalOfRegisters());
    }

    public function test_listing_page_beyond_the_last_should_become_the_last(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Oficina {$i}"]);
        }

        $page = Event::listingPage($this->filters(['q' => 'Oficina']), 9, 5, 'admin.events.paginate');
        $empty = Event::listingPage($this->filters(['q' => 'nada']), 3, 5, 'admin.events.paginate');

        $this->assertSame(2, $page->getPage());
        $this->assertSame(['Oficina 6', 'Oficina 7'], $this->titles($page->registers()));
        $this->assertSame(1, $empty->getPage());
        $this->assertSame([], $empty->registers());
    }

    public function test_listing_page_should_not_trust_unchecked_sort_and_dir(): void
    {
        $this->createEvent(['title' => 'Bravo']);
        $this->createEvent(['title' => 'Alfa']);

        $filters = ['q' => '', 'kind' => null, 'sort' => 'title; DROP TABLE events', 'dir' => 'DESC; --'];
        $page = Event::listingPage($filters, 1, 5, 'admin.events.paginate');

        $this->assertSame(['Bravo', 'Alfa'], $this->titles($page->registers()));
        $this->assertCount(2, Event::all());
    }

    public function test_listing_query_should_keep_only_what_differs_from_the_default(): void
    {
        $this->assertSame([], Event::listingQuery($this->filters()));
        $this->assertSame(
            ['q' => '50%', 'kind' => 'lecture', 'sort' => 'starts_on'],
            Event::listingQuery($this->filters(['q' => '50%', 'kind' => 'lecture', 'sort' => 'starts_on', 'dir' => 'asc']))
        );
        $this->assertSame(['dir' => 'desc'], Event::listingQuery($this->filters(['sort' => 'id', 'dir' => 'desc'])));
    }
```

Por quê:

- Regra no model, testada sem HTTP: padrão, válidos, 7 inválidos
  (data provider), válidos ao lado de inválidos, limite de 255.
- `listingPage`: mesmo WHERE no `COUNT` e na página (7 de 10, 2
  páginas), caixa ignorada, desempate por `id`, `%`/`_`/`!` literais,
  aspas sem injeção, página além da última vira a última, `sort`/`dir`
  não conferidos não entram no SQL.
- `createEvent` já existe no arquivo (`EVT:59-65`).

## 7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Testes novos depois de `test_index_lists_five_events_per_page`
(termina em `assertStringNotContainsString('Evento 5', $page2);`):

```php
    public function test_index_keeps_search_kind_and_sort_in_links(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Oficina {$i}"]);
        }
        $this->createEvent(['title' => 'Oficina X', 'kind' => 'lecture']);
        $this->createEvent(['title' => 'Palestra 1']);

        $page1 = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['q' => 'Oficina', 'kind' => 'workshop', 'sort' => 'title', 'dir' => 'desc']
        );

        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $page1);
        $this->assertStringNotContainsString('Oficina X', $page1);
        $this->assertStringNotContainsString('Palestra 1', $page1);
        $this->assertLessThan(strpos($page1, 'Oficina 3'), strpos($page1, 'Oficina 7'));
        $this->assertStringContainsString(
            '/admin/events/page/2?q=Oficina&kind=workshop&sort=title&dir=desc',
            $page1
        );
        // O formulário volta preenchido.
        $this->assertStringContainsString('value="Oficina"', $page1);
        $this->assertMatchesRegularExpression('/<option value="workshop"\s+selected>/', $page1);
        $this->assertMatchesRegularExpression('/<option value="title"\s+selected>/', $page1);
        $this->assertMatchesRegularExpression('/<option value="desc"\s+selected>/', $page1);
    }

    public function test_index_second_page_keeps_search_kind_and_sort(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Oficina {$i}"]);
        }
        $this->createEvent(['title' => 'Oficina X', 'kind' => 'lecture']);

        $page2 = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['q' => 'Oficina', 'kind' => 'workshop', 'sort' => 'title', 'dir' => 'desc', 'page' => '2']
        );

        $this->assertStringContainsString('Mostrando 6 - 7 de 7', $page2);
        $this->assertLessThan(strpos($page2, 'Oficina 1'), strpos($page2, 'Oficina 2'));
        $this->assertStringNotContainsString('Oficina 3', $page2);
        $this->assertStringContainsString(
            '/admin/events/page/1?q=Oficina&kind=workshop&sort=title&dir=desc',
            $page2
        );
    }

    public function test_index_with_invalid_sort_shows_warning_and_registration_order(): void
    {
        $this->createEvent(['title' => 'Bravo']);
        $this->createEvent(['title' => 'Alfa']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['sort' => 'description']);

        $this->assertStringContainsString(
            '<div class="alert alert-warning" role="alert">Ordenação inválida.</div>',
            $response
        );
        $this->assertLessThan(strpos($response, 'Alfa'), strpos($response, 'Bravo'));
        $this->assertMatchesRegularExpression('/<option value="id"\s+selected>/', $response);
        $this->assertStringNotContainsString('sort=description', $response);
    }

    public function test_index_shows_every_invalid_param_and_keeps_the_valid_ones(): void
    {
        $this->createEvent(['title' => 'Oficina de Git', 'kind' => 'workshop']);
        $this->createEvent(['title' => 'Palestra de Git', 'kind' => 'lecture']);
        $this->createEvent(['title' => 'Oficina de Docker', 'kind' => 'workshop']);

        $response = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['q' => 'Git', 'kind' => 'party', 'sort' => 'title', 'dir' => ['desc']]
        );

        $this->assertStringContainsString('Tipo inválido.', $response);
        $this->assertStringContainsString('Direção inválida.', $response);
        $this->assertSame(2, substr_count($response, 'alert-warning'));
        $this->assertStringNotContainsString('party', $response);
        // Busca e ordenação válidas continuam valendo; tipo e direção voltam ao padrão.
        $this->assertLessThan(strpos($response, 'Palestra de Git'), strpos($response, 'Oficina de Git'));
        $this->assertStringNotContainsString('Oficina de Docker', $response);
    }

    public function test_index_without_params_keeps_registration_order_and_clean_links(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->createEvent(['title' => "Evento {$i}", 'starts_on' => date('Y-m-d', strtotime('+' . (20 - $i) . ' days'))]);
        }

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertLessThan(strpos($response, 'Evento 5'), strpos($response, 'Evento 1'));
        $this->assertStringContainsString('href="/admin/events/page/2"', $response);
        $this->assertStringNotContainsString('alert-warning', $response);
    }

    public function test_index_search_escapes_the_term_and_shows_message_without_results(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['q' => '<b>"x"</b>']);

        $this->assertStringContainsString('Nenhum evento encontrado.', $response);
        $this->assertStringContainsString('value="&lt;b&gt;&quot;x&quot;&lt;/b&gt;"', $response);
        $this->assertStringNotContainsString('<b>"x"</b>', $response);
        $this->assertStringNotContainsString('Oficina de Git', $response);
    }

    public function test_index_page_param_must_be_a_positive_integer(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $text = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => '2abc']);
        $beyond = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => '9']);

        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $text);
        $this->assertStringContainsString('Mostrando 6 - 7 de 7', $beyond);
    }
```

Por quê:

- Caminho todo: `getParams()` -> model -> `Paginator` -> view e
  links. O primeiro é o teste do catálogo, mais o form preenchido.
- Inválido: aviso escapado, padrão aplicado, nada do valor ruim nos
  links. Dois inválidos -> dois avisos (prova o "não flash").
- Sem parâmetros: links limpos (`/page/2` sem `?`), ordem de
  cadastro; os testes antigos da `index` seguem verdes.
- Confere títulos dos Eventos, não o rótulo: "Oficina"/"Palestra"
  também aparecem no `<select>`.

Armadilha: `ControllerTestCase` usa um `Request` por teste e
`addParams` acumula; um segundo `get` sem `q` ainda busca o `q`
anterior. Um cenário por teste, ou passe as quatro chaves (`'q' =>
''`). No último teste funciona porque só `page` muda.

## 8. `tests/Acceptance/admin/events/SearchEventsCest.php` (novo)

Arquivo inteiro:

```php
<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Busca por título, filtro por tipo e ordenação escolhida, juntos e mantidos ao trocar de página.
 */
class SearchEventsCest extends BaseEventsCest
{
    public function searchSortPaginateAndFilter(AcceptanceTester $page): void
    {
        $page->wantTo('Buscar, ordenar e filtrar a listagem, mantendo tudo ao paginar');
        $page->amOnPage('/admin/events');

        // "1" casa o Evento 01 e os Eventos 10 a 16 ("Evento 1" não casaria o 01).
        $page->fillField('#filter_q', '1');
        $page->selectOption('#filter_sort', 'Título');
        $page->selectOption('#filter_dir', 'Decrescente');
        $page->click('Filtrar');
        $page->see('Mostrando 1 - 5 de 8');
        $this->seeTitlesInOrder($page, [16, 15, 14, 13, 12]);

        // Página 2: busca e ordenação continuam na URL e na tela.
        $page->click('2', '.pagination');
        $page->seeInCurrentUrl('/admin/events/page/2?q=1&sort=title&dir=desc');
        $page->see('Mostrando 6 - 8 de 8');
        $this->seeTitlesInOrder($page, [11, 10, 1]);
        $page->see($this->title(1), 'table tbody tr:last-child');
        $page->seeInField('#filter_q', '1');
        $page->seeOptionIsSelected('#filter_sort', 'Título');
        $page->seeOptionIsSelected('#filter_dir', 'Decrescente');

        // Filtro por tipo junto: Palestras são os Eventos 01, 05, 09 e 13; só 13 e 01 casam a busca.
        $page->selectOption('#filter_kind', 'Palestra');
        $page->click('Filtrar');
        $page->see('Mostrando 1 - 2 de 2');
        $this->seeTitlesInOrder($page, [13, 1]);
    }

    public function invalidParamShowsWarningAndUsesDefault(AcceptanceTester $page): void
    {
        $page->wantTo('Ver um aviso quando a listagem recebe um parâmetro inválido');
        $page->amOnPage('/admin/events?kind=party&sort=title&dir=desc');

        $page->see('Tipo inválido.', '.alert-warning');
        // O tipo recusado volta a "Todos os tipos"; a ordenação válida continua valendo.
        $page->seeOptionIsSelected('#filter_kind', 'Todos os tipos');
        $page->see('Mostrando 1 - 5 de 16');
        $page->see($this->title(16), 'table tbody tr:first-child');
    }

    /**
     * Linhas da tabela: exatamente estes Eventos, nesta ordem.
     *
     * @param array<int, int> $numbers
     */
    private function seeTitlesInOrder(AcceptanceTester $page, array $numbers): void
    {
        $page->seeNumberOfElements('table tbody tr', count($numbers));
        foreach ($numbers as $index => $number) {
            $page->see($this->title($number), 'table tbody tr:nth-child(' . ($index + 1) . ')');
        }
    }
}
```

Por quê:

- Navegador real: prova que o form GET envia os quatro campos e que o
  link "2" leva a query string.
- `BaseEventsCest` semeia 16 Eventos `Evento %02d` com tipos em
  rodízio; `title($n)` monta o título.
- Termo "1", não "Evento 1": "Evento 01" não contém "Evento 1" (daria
  7, não 8).

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser      # SearchEventsCest
docker compose stop selenium
```

Sem `db:populate` (schema não mudou). No navegador (populate, admin):

- buscar "de", Título, Decrescente: "Mostrando 1 - 5 de 14", link
  `/admin/events/page/2?q=de&sort=title&dir=desc`; na página 2 a
  ordem continua.
- `/admin/events/page/9?q=Oficina`: as 4 Oficinas, "Mostrando 1 - 4
  de 4".
- `/admin/events?kind=x&dir=up`: avisos "Tipo inválido." e "Direção
  inválida." e a listagem padrão.

Commit (inglês): `feat: Search, filter and sort admin events together`.

## Armadilhas (resumo)

- `COUNT` e página com o mesmo `WHERE` (`buildConditions()`); `LIKE`
  só no `SELECT` dá total errado e páginas vazias. `ORDER BY` só no
  `SELECT`.
- `conditions + binds`: chave da esquerda vence; bind `:kind` some
  calado. Use `:q`.
- `%` e `_` digitados: escapar. Escape padrão do MySQL é a barra;
  `ESCAPE '!'` deixa explícito e vale no PostgreSQL.
- `ORDER BY` não aceita bind: lista fechada, conferida de novo em
  `listingPage()`; `dir` vira `ASC`/`DESC` fixos.
- Padrão fora da query string: `PaginateEventsCest` compara URL exata.
  `http_build_query` mantém `''` (`?kind=`): `listingQuery()` filtra.
- Form GET: navegador descarta a query string do `action`; os quatro
  campos no form, `action` sem `page`.
- Página fora do intervalo depois de filtrar: sem o ajuste no
  construtor, "Nenhum evento cadastrado." sem paginação. Alternativa:
  `redirectTo` pra última página.
- Filtro na rota (`/page/{page}/{sortBy}`): não casa o regex de
  `Route.php`, 404. `/admin/events/page/abc` chega como `'abc'`.
- `ControllerTestCase`: `params` acumulam entre `get()` do mesmo
  teste.
- Padrão `starts_on`: quebra `CreateSuccessfullyCest` (N3) e, sem
  desempate por `id`, o teste de cinco por página (mesma data).
- Dois inválidos em flash: só o último aparece. Use `$errors` na view
  com `htmlspecialchars`.
- `?q[]=x` chega como array: `is_string` antes de `trim()`.
- Busca segue a collation: "oficina" acha "Oficina", "introducao"
  acha "Introdução".
