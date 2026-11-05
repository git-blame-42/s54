# N4 passo a passo: Mudar `per_page` ou deixar o usuário escolher

Pedido típico: "mostre 10 Eventos por página; depois, deixe o usuário
escolher 5, 10 ou 20, sem perder a escolha ao trocar de página".
Chance: média-alta. Tempo: 5-10 min (mais 10 com a escolha pelo
usuário).

Exemplo pronto: `fvp/4.2/exemplos/n4-per-page/` (patch, bundle e árvore
`projeto/`). O patch faz as duas partes. Pré-requisitos: nenhum (parte
de `main`). Schema não muda.

Ordem = fluxo da requisição: model (`Paginator` e `Model::paginate`) ->
controller -> views -> testes. Rota não muda. Na prova, prioridade:
funcionar no navegador, depois testar, depois `phpcs`.

Só "10 por página" (sem escolha)? Faça só: passo 3a (`PER_PAGE = 10`),
passo 7a e os Cests (8 a 11). Os demais passos são da variante
`?per_page=`.

## Arquivos, em ordem

1. `lib/Paginator.php` (variante)
2. `core/Database/ActiveRecord/Model.php` (variante)
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/index.phtml` (variante)
5. `app/views/paginator/_pages.phtml` (variante)
6. `tests/Unit/Models/Events/EventTest.php` (variante)
7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
8. `tests/Acceptance/admin/events/BaseEventsCest.php`
9. `tests/Acceptance/admin/events/ListEventsCest.php`
10. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`
11. `tests/Acceptance/admin/events/PaginateEventsCest.php`

## 1. `lib/Paginator.php`

a) PHPDoc logo acima do `public function __construct(`:

```php
    /**
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $queryParams parâmetros extras repassados aos links de página (ex.: per_page)
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

- `_pages.phtml` é incluído por `renderPagesNavigation()`; lá só
  existem `$paginator` e `$this`. O `per_page` tem que viajar
  **dentro** do `Paginator`.
- `queryParams` genérico (não um `perPage` fixo no link): mesmo
  mecanismo do N1 (`?kind=`).
- `perPage()` já existe; a view usa para marcar a opção ativa.

## 2. `core/Database/ActiveRecord/Model.php`

`paginate()` (linha ~284): parâmetro novo, PHPDoc e repasse.

```php
    /**
     * @param array<string, mixed> $queryParams parâmetros extras mantidos nos links de página
     */
    public static function paginate(
        int $page = 1,
        int $per_page = 10,
        string $route = null,
        array $queryParams = []
    ): Paginator {
        return new Paginator(
            class: static::class,
            page: $page,
            per_page: $per_page,
            table: static::$table,
            attributes: static::$columns,
            route: $route,
            queryParams: $queryParams
        );
    }
```

Por quê:

- Controller chama `Event::paginate()`, não o `Paginator`; sem
  repassar aqui, o `queryParams:` do controller não chega.
- `@param array<string, mixed>`: `core/` entra no `phpstan` nível 6;
  `array` sem tipo -> `missingType.iterableValue`.

## 3. `app/Controllers/Admin/EventsController.php`

a) Constantes (linha ~12):

```php
    private const PER_PAGE = 10;

    /** Valores aceitos em `?per_page=` (lista fechada: 0 daria divisão por zero no Paginator). */
    private const PER_PAGE_OPTIONS = [5, 10, 20];
```

b) `index()` inteiro:

```php
    public function index(Request $request): void
    {
        $perPage = $this->perPage($request);

        $paginator = Event::paginate(
            page: max(1, (int) $request->getParam('page', 1)),
            per_page: $perPage,
            route: 'admin.events.paginate',
            queryParams: $perPage !== self::PER_PAGE ? ['per_page' => $perPage] : []
        );
        $events = $paginator->registers();

        $perPageOptions = self::PER_PAGE_OPTIONS;

        $title = 'Eventos';
        $this->render('admin/events/index', compact('events', 'paginator', 'perPageOptions', 'title'));
    }
```

c) Método privado novo, antes de `findEvent()`:

```php
    /**
     * `?per_page=` só vale se estiver na lista fechada; qualquer outro valor (0, texto, array) usa o padrão.
     */
    private function perPage(Request $request): int
    {
        $perPage = $request->getParam('per_page');
        if (!is_scalar($perPage) || !ctype_digit((string) $perPage)) {
            return self::PER_PAGE;
        }

        return in_array((int) $perPage, self::PER_PAGE_OPTIONS, true) ? (int) $perPage : self::PER_PAGE;
    }
```

Por quê:

- Lista fechada: `0` dá divisão por zero em `Paginator::loadTotals`
  (`ceil($total / $this->per_page)`); valor enorme devolve a tabela
  inteira. Nunca o valor cru.
- `is_scalar` + `ctype_digit` antes do cast: `?per_page[]=5` é array
  e `(int) 'abc'` daria 0. (Versão curta do catálogo, também segura:
  `(int) getParam('per_page', self::PER_PAGE)` +
  `in_array(..., [5, 10, 20], true)`.)
- `per_page` nos links só quando difere do padrão: URLs antigas
  (`/admin/events/page/2`) não mudam.
- `$perPageOptions` via `compact`: view não lê constante privada.

## 4. `app/views/admin/events/index.phtml`

Depois do `</table>`, antes de
`<?php $paginator->renderPagesNavigation(); ?>` (linha ~40):

```php
    </table>

    <div class="per-page mb-2">
        Por página:
        <?php foreach ($perPageOptions as $option) : ?>
            <a class="btn btn-sm btn-outline-secondary <?= $paginator->perPage() === $option ? 'active' : '' ?>"
               href="<?= route('admin.events.index', ['per_page' => $option]) ?>"><?= $option ?></a>
        <?php endforeach; ?>
    </div>

    <?php $paginator->renderPagesNavigation(); ?>
```

Por quê:

- Links (GET), não form: escolher é leitura, vai na URL.
- `route('admin.events.index', ['per_page' => 5])`: `per_page` não está
  na URI, vira query string -> `/admin/events?per_page=5` (volta à
  página 1, certo ao mudar o tamanho).
- `active` compara com `$paginator->perPage()`: view só exibe.
- Classe `per-page`: o Cest clica em `'5', '.per-page'`.

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

- `route()` troca `{page}` e põe o resto como query string:
  `/admin/events/page/2?per_page=5`.
- Sem `queryParams`, página 1 mostra 5 e página 2 volta para 10 (salto
  no "Mostrando ...").

Armadilha: não escreva `'per_page' => $perPage` aqui. Variável
indefinida no partial -> warning -> `ErrorsHandler` -> 500.

## 6. `tests/Unit/Models/Events/EventTest.php`

Teste novo depois de
`test_paginate_should_default_the_route_name_to_the_table` (linha
~435):

```php
    public function test_paginate_should_keep_the_given_query_params(): void
    {
        $paginator = Event::paginate(1, 5, 'admin.events.paginate', ['per_page' => 5]);

        $this->assertSame(['per_page' => 5], $paginator->queryParams());
        $this->assertSame([], Event::paginate(1, 5)->queryParams());
    }
```

Por quê: prova o repasse `Model::paginate` -> `Paginator` e o default
`[]`. Os testes de paginação do `EventTest` usam `paginate(1, 5)`
explícito: não dependem de `PER_PAGE`, não mudam.

## 7. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) Troque `test_index_lists_five_events_per_page` (linhas ~39-57) por:

```php
    public function test_index_lists_ten_events_per_page(): void
    {
        // Títulos com zero à esquerda: 'Evento 1' casaria com 'Evento 10', 'Evento 11'...
        for ($i = 1; $i <= 12; $i++) {
            $this->createEvent(['title' => sprintf('Evento %02d', $i)]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertMatchesRegularExpression('/<h1>\s*Eventos\s*<\/h1>/', $page1);
        $this->assertStringContainsString('Novo Evento', $page1);
        $this->assertStringContainsString('Evento 01', $page1);
        $this->assertStringContainsString('Evento 10', $page1);
        $this->assertStringNotContainsString('Evento 11', $page1);
        $this->assertStringContainsString('Mostrando 1 - 10 de 12', $page1);
        $this->assertStringContainsString('href="/admin/events/page/2"', $page1);
        $this->assertStringContainsString('Evento 11', $page2);
        $this->assertStringContainsString('Evento 12', $page2);
        $this->assertStringNotContainsString('Evento 10', $page2);
    }
```

b) `use` novo (variante):

```php
use PHPUnit\Framework\Attributes\DataProvider;
```

c) Testes novos logo depois (variante):

```php
    public function test_index_uses_per_page_param_and_keeps_it_in_page_links(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => sprintf('Evento %02d', $i)]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['per_page' => '5']);
        $page2 = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['per_page' => '5', 'page' => 2]
        );

        $this->assertStringContainsString('Evento 05', $page1);
        $this->assertStringNotContainsString('Evento 06', $page1);
        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $page1);
        $this->assertStringContainsString('href="/admin/events/page/2?per_page=5"', $page1);
        $this->assertStringContainsString('Evento 06', $page2);
        $this->assertStringContainsString('Evento 07', $page2);
        $this->assertStringNotContainsString('Evento 05', $page2);
        $this->assertStringContainsString('href="/admin/events/page/1?per_page=5"', $page2);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPerPageProvider(): array
    {
        return [
            'zero' => ['0'],
            'fora da lista' => ['7'],
            'negativo' => ['-5'],
            'texto' => ['abc'],
            'array' => [['5']],
        ];
    }

    #[DataProvider('invalidPerPageProvider')]
    public function test_index_ignores_invalid_per_page(mixed $perPage): void
    {
        for ($i = 1; $i <= 12; $i++) {
            $this->createEvent(['title' => sprintf('Evento %02d', $i)]);
        }

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['per_page' => $perPage]);

        $this->assertStringContainsString('Evento 10', $response);
        $this->assertStringNotContainsString('Evento 11', $response);
        $this->assertStringContainsString('Mostrando 1 - 10 de 12', $response);
        $this->assertStringContainsString('href="/admin/events/page/2"', $response);
        $this->assertStringNotContainsString('/admin/events/page/2?per_page=', $response);
    }
```

Por quê:

- O teste antigo criava 7 Eventos e esperava "Evento 6" só na página
  2: com 10 por página falha. 12 Eventos = 2 páginas.
- `sprintf('Evento %02d')`: com 10+ Eventos, `'Evento 1'` casa com
  `'Evento 10'` (mesmo truque do `BaseEventsCest.php:63-66`).
- Variante: link da página 2 mantém `?per_page=5`; inválidos (`0`,
  `7`, `-5`, `abc`, array) caem no padrão sem `per_page` no link.

## 8. `tests/Acceptance/admin/events/BaseEventsCest.php`

a) Constante (linha ~23):

```php
    protected const PER_PAGE = 10;
```

b) Docblock da classe (linha ~14), troque "4 páginas de 5" por:

```php
 * Reproduz o formato do populate: 16 Eventos (2 páginas de 10), um Evento lotado (3/3 vagas, caso P02),
```

Por quê: os Cests usam `self::PER_PAGE` para contar linhas; tem que
bater com o controller.

## 9. `tests/Acceptance/admin/events/ListEventsCest.php`

a) Comentário antes do `grabMultiple` (linha ~19):

```php
        // Primeira página: os 10 primeiros Eventos (ordem do banco, que no populate coincide com a data).
```

b) `dontSee` no fim do método (linha ~41), sem números fixos:

```php
        $page->dontSee($this->title(self::PER_PAGE + 1));
        $page->dontSee($this->title(self::TOTAL_EVENTS));
```

Por quê: `title(6)` estaria na página 1 com 10 por página. Derivar de
`PER_PAGE` evita quebrar de novo.

## 10. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`

Depois de `$page->see($data['location']);` (linha ~32):

```php
        // O Evento novo vai para o fim da listagem (ordem do banco): 17 Eventos, 2ª página (10 por página).
        $page->amOnPage('/admin/events/page/2');
        $page->see($data['title'], 'table');
        $page->see('Mostrando 11 - 17 de 17');
```

Por quê: antes abria `/admin/events/page/4` ("Mostrando 16 - 17 de
17"); com 10 por página o Evento 17 está na página 2.

## 11. `tests/Acceptance/admin/events/PaginateEventsCest.php`

Reescreva o arquivo (o antigo clica nas páginas 3 e 4, que não existem
mais). Helper ganha o parâmetro `$perPage`:

```php
    /** Confere que a página mostra exatamente os Eventos dela (ordem de data). */
    private function seeEventsOfPage(AcceptanceTester $page, int $number, int $perPage): void
    {
        $first = ($number - 1) * $perPage + 1;
        $last = min($number * $perPage, self::TOTAL_EVENTS);
        // resto igual ao original
```

Cenário principal (`paginateEvents`), 2 páginas:

```php
        // Página 1: Eventos 01 a 10.
        $this->seeEventsOfPage($page, 1, self::PER_PAGE);
        $page->see('Mostrando 1 - 10 de 16');
        $page->seeElement('.pagination');
        // Anterior, 1, 2, Próxima.
        $page->seeNumberOfElements('.pagination .page-item', 4);

        // Página 2 (última) por "Próxima": Eventos 11 a 16.
        $page->click('Próxima', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2, self::PER_PAGE);
        $page->see('Mostrando 11 - 16 de 16');

        // Volta por "Anterior".
        $page->click('Anterior', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/1');
        $this->seeEventsOfPage($page, 1, self::PER_PAGE);

        // Página 2 pelo número.
        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2, self::PER_PAGE);
```

`openPageTwoAndThreeDirectly` vira `openPageTwoDirectly`:

```php
    public function openPageTwoDirectly(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (acesso direto à página 2)');

        $page->amOnPage('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2, self::PER_PAGE);
        $page->dontSee($this->title(1));
    }
```

Cenários novos (variante):

```php
    public function choosePerPage(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (usuário escolhe 5 por página)');
        $page->amOnPage('/admin/events');

        $page->click('5', '.per-page');
        $page->seeCurrentUrlEquals('/admin/events?per_page=5');
        $this->seeEventsOfPage($page, 1, 5);
        $page->see('Mostrando 1 - 5 de 16');
        // Anterior, 1, 2, 3, 4, Próxima.
        $page->seeNumberOfElements('.pagination .page-item', 6);

        // Os links de página mantêm o per_page.
        $page->click('Próxima', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2?per_page=5');
        $this->seeEventsOfPage($page, 2, 5);
        $page->see('Mostrando 6 - 10 de 16');

        $page->click('4', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/4?per_page=5');
        $this->seeEventsOfPage($page, 4, 5);

        // 20 por página: tudo numa página só.
        $page->click('20', '.per-page');
        $page->seeCurrentUrlEquals('/admin/events?per_page=20');
        $this->seeEventsOfPage($page, 1, 20);
        $page->see('Mostrando 1 - 16 de 16');
    }

    public function invalidPerPageFallsBackToDefault(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (per_page inválido usa o padrão)');

        $page->amOnPage('/admin/events?per_page=0');
        $this->seeEventsOfPage($page, 1, self::PER_PAGE);
        $page->see('Mostrando 1 - 10 de 16');
    }
```

Por quê:

- Com 10 por página e 16 Eventos há só 2 páginas: reescreva o
  cenário, não só os textos.
- `.page-item` conta Anterior + números + Próxima: 4 com 10, 6 com 5.
- Variante no navegador real: escolha de 5 e 20, `per_page` mantido
  nos links, `?per_page=0` cai no padrão.

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
```

Sem `db:populate`: schema não mudou.

No navegador: `/admin/events` mostra "Mostrando 1 - 10 de 16" e duas
páginas. Clique em "5" em "Por página:"; a "Próxima" leva a
`/admin/events/page/2?per_page=5`. `/admin/events?per_page=0` mostra a
listagem normal de 10.

Commit (inglês): `feat: Show 10 events per page and allow choosing per_page`.

## Armadilhas (resumo)

- `per_page` 0 dá divisão por zero no `Paginator`: nunca o valor cru;
  lista fechada.
- Com 10+ Eventos nos testes, `'Evento 1'` casa com `'Evento 10'`: use
  `sprintf('Evento %02d', $i)`.
- `test_index_lists_five_events_per_page` assume 5: ajuste (12
  Eventos).
- `PaginateEventsCest` clica nas páginas 3 e 4: reescreva.
- `CreateSuccessfullyCest` abre `/admin/events/page/4`: vira página 2.
- Esquecer `per_page` nos links: página 2 volta para o padrão.
- `$perPage` dentro de `_pages.phtml`: variável indefinida, 500.
- `array` sem PHPDoc em `Model::paginate`: `phpstan` nível 6.
- `EventTest` usa `paginate(1, 5)` explícito: não muda.
- Aplicando junto com N1: uma só propriedade `queryParams`; junte
  `kind` e `per_page` no mesmo array.
