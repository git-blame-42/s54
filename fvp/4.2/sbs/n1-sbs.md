# N1 passo a passo: Filtro por tipo com `where`, mantido na paginação

Pedido típico: "na listagem, filtre por tipo: `/admin/events?kind=workshop`;
o filtro continua valendo ao trocar de página". A rubrica pede `where`.
Chance: alta. Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/n1-filtro-por-tipo-paginacao/` (patch,
bundle e árvore `projeto/`). Implementa a forma 2 do catálogo (com
paginação). Pré-requisitos: nenhum (parte de `main`). Schema não muda.

Ordem = fluxo da requisição: model (`Paginator` e `Model::paginate`) ->
controller -> views -> testes. Rota não muda: `admin.events.index` e
`admin.events.paginate` já existem. Na prova, prioridade: funcionar no
navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `lib/Paginator.php`
2. `core/Database/ActiveRecord/Model.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/index.phtml`
5. `app/views/paginator/_pages.phtml`
6. `tests/Unit/Models/Events/EventTest.php`
7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Sem pressa de tempo? Veja a forma 1 no fim (só controller + view).

## 1. `lib/Paginator.php`

a) PHPDoc logo acima do `public function __construct(`:

```php
    /**
     * @param array<string, mixed> $conditions filtros por igualdade (WHERE coluna = valor)
     * @param array<string, mixed> $queryParams parâmetros extras repassados aos links de página (ex.: kind)
     */
    public function __construct(
```

b) Último parâmetro do construtor, depois de `$route`:

```php
        private array $conditions = [],
        private ?string $route = null,
        private array $queryParams = []
    ) {
```

c) Getter novo, depois de `perPage()` (linha ~40):

```php
    /**
     * Parâmetros que os links de página (`_pages.phtml`) mantêm na query string.
     *
     * @return array<string, mixed>
     */
    public function queryParams(): array
    {
        return $this->queryParams;
    }
```

Por quê:

- `conditions` já existe: `buildConditions()` monta
  `WHERE kind = :kind` e `bindConditions()` faz o `bindValue`. É o
  `where` que a rubrica pede. Só igualdade (`LIKE` é N2).
- `_pages.phtml` é incluído por `renderPagesNavigation()`; lá só
  existem `$paginator` e `$this`. Por isso o filtro viaja **dentro** do
  `Paginator` (`queryParams()`), não como variável do controller.
- Parâmetro novo no fim, com default: quem chama hoje não quebra.

## 2. `core/Database/ActiveRecord/Model.php`

Troque a assinatura de `paginate()` (linha ~284) e repasse os dois
arrays:

```php
    /**
     * @param array<string, mixed> $conditions filtros por igualdade repassados ao Paginator
     * @param array<string, mixed> $queryParams parâmetros extras mantidos nos links de página
     */
    public static function paginate(
        int $page = 1,
        int $per_page = 10,
        string $route = null,
        array $conditions = [],
        array $queryParams = []
    ): Paginator {
        return new Paginator(
            class: static::class,
            page: $page,
            per_page: $per_page,
            table: static::$table,
            attributes: static::$columns,
            conditions: $conditions,
            route: $route,
            queryParams: $queryParams
        );
    }
```

Por quê:

- O `paginate()` original não repassa `conditions`: filtrar só no
  controller "não faz nada".
- Modelo pronto: `HasMany::paginate`
  (`core/Database/ActiveRecord/HasMany.php:44-55`) já passa
  `conditions:`.
- `@param array<string, mixed>`: `core/` entra no `phpstan` nível 6;
  `array` sem tipo -> `missingType.iterableValue`.

## 3. `app/Controllers/Admin/EventsController.php`

a) Início de `index()`, antes de `Event::paginate(`; e os dois
argumentos novos na chamada:

```php
    public function index(Request $request): void
    {
        $kind = $this->kindFilter($request);
        $filter = $kind !== null ? ['kind' => $kind] : [];

        $paginator = Event::paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: self::PER_PAGE,
            route: 'admin.events.paginate',
            conditions: $filter,
            queryParams: $filter
        );
```

b) `$kind` no `compact` (a view usa no `selected`):

```php
        $this->render('admin/events/index', compact('events', 'paginator', 'kind', 'title'));
```

c) Método privado novo, antes de `findEvent()`:

```php
    /**
     * `?kind=` só vale se for uma das chaves de Event::KINDS; senão, sem filtro.
     */
    private function kindFilter(Request $request): ?string
    {
        $kind = $request->getParam('kind');

        return is_string($kind) && array_key_exists($kind, Event::KINDS) ? $kind : null;
    }
```

Por quê:

- Controller só lê e valida a entrada; SQL fica em `lib/`/model.
- Lista fechada (`Event::KINDS`): valor desconhecido não vai ao SQL nem
  aos links. `''` ("Todos") também vira `null` = sem filtro.
- `is_string` antes: `?kind[]=x` chega como array e `array_key_exists`
  lança `TypeError` (500).
- Mesmo array em `conditions` (filtra) e `queryParams` (links da
  página 2 mantêm `?kind=`).

## 4. `app/views/admin/events/index.phtml`

a) Depois do `</div>` do botão "Novo Evento", antes do
`<?php if (empty($events)) : ?>`:

```php
<form class="row g-2 align-items-center mb-3" method="get" action="<?= route('admin.events.index') ?>">
    <div class="col-auto">
        <label class="col-form-label" for="filter_kind">Tipo</label>
    </div>
    <div class="col-auto">
        <select class="form-select" id="filter_kind" name="kind">
            <option value="">Todos</option>
            <?php foreach (\App\Models\Event::KINDS as $value => $label) : ?>
                <option value="<?= $value ?>" <?= $kind === $value ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
    </div>
</form>
```

b) Mensagem de lista vazia:

```php
<?php if (empty($events)) : ?>
    <p><?= $kind !== null ? 'Nenhum evento deste tipo.' : 'Nenhum evento cadastrado.' ?></p>
```

Por quê:

- `method="get"`: filtro é leitura, vai na URL (`?kind=lecture`),
  dá pra copiar o link. Sem CSRF, sem PRG.
- Form fora do `if`: aparece mesmo sem resultados, senão o usuário
  fica preso num filtro vazio.
- `selected` com `$kind` vindo do `compact`: view burra, só compara.
- Valores de `Event::KINDS` são constantes do código, não entrada do
  usuário.

## 5. `app/views/paginator/_pages.phtml`

a) No topo do arquivo, antes de `<nav ...>`:

```php
<?php
// URL de cada link: a página mais os parâmetros extras guardados no Paginator (ex.: filtros).
$pageUrl = fn (int $p) => route($paginator->getRouteName(), array_merge(['page' => $p], $paginator->queryParams()));
?>
```

b) Troque os três `route($paginator->getRouteName(), ['page' => X])`:

```php
                <a class="page-link" href="<?= $pageUrl($paginator->previousPage()) ?>">Anterior</a>
```

```php
                <a class="page-link" href="<?= $pageUrl($page) ?>"><?= $page ?></a>
```

```php
                <a class="page-link" href="<?= $pageUrl($paginator->nextPage()) ?>">Próxima</a>
```

Por quê:

- `route()` troca `{page}` na URI e põe o resto como query string
  (`http_build_query`): `/admin/events/page/2?kind=lecture`.
- Sem filtro, `queryParams()` é `[]` e os links ficam iguais aos de
  antes (`/admin/events/page/2`).
- Partial compartilhado: vale para qualquer listagem paginada.

Armadilha: não escreva `'kind' => $kind` aqui. `$kind` não existe no
partial -> warning -> `ErrorsHandler` -> 500.

## 6. `tests/Unit/Models/Events/EventTest.php`

Teste novo depois de
`test_paginate_should_default_the_route_name_to_the_table` (linha
~435), antes do comentário `/* --- relações --- */`:

```php
    public function test_paginate_should_filter_by_conditions_and_keep_query_params(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['kind' => 'lecture', 'title' => "Palestra {$i}"]);
        }
        $this->createEvent(['kind' => 'workshop', 'title' => 'Oficina 1']);

        $paginator = Event::paginate(
            page: 2,
            per_page: 5,
            route: 'admin.events.paginate',
            conditions: ['kind' => 'lecture'],
            queryParams: ['kind' => 'lecture']
        );

        $this->assertEquals(7, $paginator->totalOfRegisters());
        $this->assertEquals(2, $paginator->totalOfPages());
        $this->assertCount(2, $paginator->registers());
        foreach ($paginator->registers() as $event) {
            $this->assertEquals('lecture', $event->kind);
        }
        $this->assertSame(['kind' => 'lecture'], $paginator->queryParams());
        $this->assertSame([], Event::paginate(1, 5)->queryParams());
    }
```

Por quê:

- Prova o `where` no `COUNT(*)` (7, não 8) e no `SELECT` (página 2 só
  com as 2 Palestras restantes).
- Prova que `queryParams` é guardado e que o default é `[]`.
- `test_where_should_filter_by_conditions` já cobre `Model::where`.

## 7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) `use` novo, junto dos outros:

```php
use PHPUnit\Framework\Attributes\DataProvider;
```

b) Testes novos depois de `test_index_lists_five_events_per_page`:

```php
    public function test_index_filters_by_kind_and_keeps_filter_in_page_links(): void
    {
        // 7 Palestras e 3 Oficinas intercaladas: sem o filtro, a página 1 misturaria os tipos.
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['kind' => 'lecture', 'title' => sprintf('Palestra %02d', $i)]);
            if ($i <= 3) {
                $this->createEvent(['kind' => 'workshop', 'title' => sprintf('Oficina %02d', $i)]);
            }
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['kind' => 'lecture']);
        $page2 = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['kind' => 'lecture', 'page' => 2]
        );

        $this->assertStringContainsString('Palestra 01', $page1);
        $this->assertStringContainsString('Palestra 05', $page1);
        $this->assertStringNotContainsString('Palestra 06', $page1);
        $this->assertStringNotContainsString('Oficina 01', $page1);
        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $page1);
        $this->assertStringContainsString('href="/admin/events/page/2?kind=lecture"', $page1);
        $this->assertMatchesRegularExpression('/<option value="lecture"\s+selected>/', $page1);

        $this->assertStringContainsString('Palestra 06', $page2);
        $this->assertStringContainsString('Palestra 07', $page2);
        $this->assertStringNotContainsString('Palestra 05', $page2);
        $this->assertStringNotContainsString('Oficina', strip_tags(explode('<table', $page2)[1]));
        $this->assertStringContainsString('href="/admin/events/page/1?kind=lecture"', $page2);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidKindProvider(): array
    {
        return [
            'vazio (Todos)' => [''],
            'fora da lista' => ['xyz'],
            'array' => [['lecture']],
        ];
    }

    #[DataProvider('invalidKindProvider')]
    public function test_index_ignores_invalid_kind(mixed $kind): void
    {
        $this->createEvent(['kind' => 'lecture', 'title' => 'Palestra 01']);
        $this->createEvent(['kind' => 'workshop', 'title' => 'Oficina 01']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['kind' => $kind]);

        $this->assertStringContainsString('Palestra 01', $response);
        $this->assertStringContainsString('Oficina 01', $response);
        $this->assertStringNotContainsString('?kind=', $response);
        $this->assertStringContainsString('<option value="">Todos</option>', $response);
        $this->assertStringNotContainsString('selected', $response);
    }

    public function test_index_with_kind_without_events_shows_message(): void
    {
        $this->createEvent(['kind' => 'lecture', 'title' => 'Palestra 01']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['kind' => 'workshop']);

        $this->assertStringContainsString('Nenhum evento deste tipo.', $response);
        $this->assertStringNotContainsString('Palestra 01', $response);
    }
```

Por quê:

- Molde: `test_index_lists_five_events_per_page` (linhas ~39-57).
- Primeiro teste prova o caminho todo: `?kind=` -> controller ->
  `WHERE` -> view, link da página 2 com `?kind=lecture` e `selected`.
- Data provider cobre `''`, fora da lista e array (o `TypeError`).
- `sprintf('%02d')`: `'Palestra 1'` casaria com `'Palestra 10'`.

Armadilhas:

- Não confira ausência de "Oficina"/"Palestra" na página inteira: os
  rótulos estão no `<select>`. Confira títulos (`Oficina 01`) ou só o
  trecho da `<table>` (por isso o `explode('<table', ...)`).
- `ControllerTestCase::get` acumula `params` no mesmo `Request` dentro
  de um teste: na segunda chamada repita `kind`.

## Forma 1 (alternativa rápida, sem paginação no filtro)

Só controller + view; não mexe em `Paginator`, `Model` nem
`_pages.phtml`. Custo: todos os Eventos do tipo numa página só.

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

Na `index.phtml`, envolva a última chamada:

```php
    <?php if ($paginator !== null) : ?>
        <?php $paginator->renderPagesNavigation(); ?>
    <?php endif; ?>
```

Armadilha: sem esse `if`, `$paginator` nulo na view dá 500.

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

Sem `db:populate`: schema não mudou.

No navegador: `/admin/events?kind=workshop` mostra só Oficinas, com
"Oficina" selecionado; `/admin/events?kind[]=x` responde 200 com a
listagem normal. Populate tem no máximo 5 Eventos por tipo (5
Palestras, 4 Oficinas, 4 Minicursos, 3 Semanas acadêmicas), nenhum
passa de uma página: para ver página 2 filtrada, cadastre mais
Eventos do mesmo tipo e confira o link
`/admin/events/page/2?kind=...`.

Commit (inglês): `feat: Filter events by kind keeping the filter in pagination`.

## Armadilhas (resumo)

- `Model::paginate` original não repassa `conditions`: filtro no
  controller "não faz nada".
- `$kind` dentro de `_pages.phtml`: variável indefinida no partial,
  warning, `ErrorsHandler`, 500.
- `?kind[]=x` chega como array: `is_string` antes do
  `array_key_exists`, senão `TypeError`.
- `array` sem PHPDoc em `app/`, `core/`: `phpstan` nível 6,
  `missingType.iterableValue`.
- Sem `queryParams`, a página 2 perde o filtro.
- Forma 1: `$paginator` nulo na view dá 500.
- `where` só faz igualdade: `LIKE` é N2.
