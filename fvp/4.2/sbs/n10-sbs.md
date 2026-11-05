# N10 passo a passo: Página e id inválidos sem conversão silenciosa

Pedido típico: "`/admin/events/page/abc` mostra a página 1,
`/admin/events/page/999` mostra tabela vazia e `/admin/events/1abc`
abre o Evento 1. Trate página e id inválidos sem `(int)` às cegas".
Chance: média-alta. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/n10-pagina-id-invalido/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: sai de `main`. Schema
não muda.

Ordem = fluxo da requisição: `Paginator` (quem sabe quantas páginas
existem) -> controller -> testes. Na prova, prioridade: funcionar no
navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `lib/Paginator.php`
2. `app/Controllers/Admin/EventsController.php`
3. `tests/Unit/Lib/PaginatorTest.php` (novo)
4. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
5. `tests/Integration/Access/EventsAccessTest.php`
6. `tests/Acceptance/admin/events/PaginateEventsCest.php`

Depois: suíte, `./run phpcs`, `./run phpstan`, `./run test:browser`.

## 1. `lib/Paginator.php`

Dois pontos.

a) Fim do construtor (linha ~27), `loadRegisters()` só no intervalo:

```php
    ) {
        $this->loadTotals();
        if (!$this->isOutOfRange()) {
            $this->loadRegisters();
        }
    }
```

b) Método novo, depois de `isPage()` e antes de `entriesInfo()`
(linha ~81):

```php
    /**
     * A página pedida não existe: menor que 1 ou depois da última. Sem registros, só a página 1 é válida
     * (a listagem vazia mostra "Nenhum evento cadastrado.").
     */
    public function isOutOfRange(): bool
    {
        return $this->page < 1 || $this->page > max(1, $this->totalOfPages());
    }
```

Por quê:

- Quem sabe o total de páginas é o `Paginator` (`totalOfPages()`,
  calculado em `loadTotals()`). Controller só pergunta: fica fino.
- `loadTotals()` roda antes: o `COUNT` vem primeiro, então
  `isOutOfRange()` já pode ser chamado no construtor.
- Fora do intervalo não consulta à toa nem calcula `OFFSET` gigante;
  `registers()` devolve `[]` (valor inicial da propriedade).
- `max(1, ...)`: tabela vazia tem `totalOfPages()` 0, e a página 1
  tem de continuar válida.

Armadilha: sem o `max(1, ...)`, a página 1 vira "fora do intervalo"
com tabela vazia e o redirect entra em laço para `/page/0`.

## 2. `app/Controllers/Admin/EventsController.php`

Três pontos. `FlashMessage` já está importado no topo.

a) `index()` (linha ~16), validação antes de `Event::paginate` e
checagem logo depois:

```php
    public function index(Request $request): void
    {
        $page = $request->getParam('page') === null ? 1 : $this->positiveInt($request->getParam('page'));
        if ($page === null) {
            FlashMessage::danger('Página inválida!');
            $this->redirectTo(route('admin.events.index'));
            return;
        }

        $paginator = Event::paginate(
            page: $page,
            per_page: self::PER_PAGE,
            route: 'admin.events.paginate'
        );
        if ($paginator->isOutOfRange()) {
            FlashMessage::danger('Página inexistente! Mostrando a última página.');
            $this->redirectTo(route('admin.events.paginate', ['page' => max(1, $paginator->totalOfPages())]));
            return;
        }
        $events = $paginator->registers();
```

(sai a linha `page: max(1, (int) $request->getParam('page', 1)),`)

b) `findEvent()` (linha ~132), troca o corpo:

```php
    private function findEvent(Request $request): ?Event
    {
        // Sem (int) silencioso: "abc" viraria 0 e "1abc" viraria 1 (outro Evento!).
        $id = $this->positiveInt($request->getParam('id'));

        return $id === null ? null : Event::findById($id);
    }
```

c) Método privado novo, logo depois de `findEvent()`:

```php
    /**
     * Inteiro >= 1 vindo da URL, ou null. Aceita só dígitos (ctype_digit), sem zero à esquerda e sem
     * estourar PHP_INT_MAX; o int é aceito porque os testes de controller passam `['page' => 2]`.
     */
    private function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 1 ? $value : null;
        }

        if (!is_string($value) || !ctype_digit($value) || $value !== (string) (int) $value) {
            return null;
        }

        return (int) $value >= 1 ? (int) $value : null;
    }
```

Por quê:

- `(int) 'abc'` = 0, `(int) '1abc'` = 1: o `max(1, (int) ...)`
  escondia o erro e `findById((int) ...)` abria ou removia o Evento
  errado. `ctype_digit` recusa tudo que não for só dígitos.
- Parâmetro de rota chega como string (`{page}`, `{id}`); teste de
  controller passa `int`. Por isso o `is_int` separado.
- `$value !== (string) (int) $value`: recusa `02` (URL canônica
  única) e o estouro de `PHP_INT_MAX` com a mesma regra.
- Sem `page` (rota `/admin/events`): `getParam` dá `null` -> página
  1, como antes.
- Redirect + flash, não 404: framework não tem 404 própria pra essa
  rota, o usuário volta a uma listagem útil, e o teste confere
  `Location` (mesmo padrão de "Evento não encontrado!").
- Depois da última vai pra última (não pra 1): é o que acontece ao
  remover o único Evento da última página.
- `findEvent` serve `show`, `edit`, `update` e `destroy`: id inválido
  -> `null` -> "Evento não encontrado!" de sempre nas quatro.

Armadilhas:

- `is_numeric` no lugar de `ctype_digit`: aceita `1.5`, `1e3`, `-1`
  e ` 1`.
- `ctype_digit(2)` (int) é obsoleto no PHP 8.1+ e trata o número como
  código ASCII: `false`. Daí o `is_int` antes.
- Esquecer o `return` depois do `redirectTo()`: no teste de
  controller o redirect só imprime `Location:` e o método segue
  renderizando.
- Com N1/N4 aplicados, o redirect pra última página tem de levar
  `kind`/`per_page` (`array_merge(['page' => ...],
  $paginator->queryParams())`).

## 3. `tests/Unit/Lib/PaginatorTest.php` (novo)

Arquivo inteiro:

```php
<?php

namespace Tests\Unit\Lib;

use App\Models\User;
use Tests\TestCase;

class PaginatorTest extends TestCase
{
    private function createUsers(int $total): void
    {
        for ($i = 1; $i <= $total; $i++) {
            $user = new User([
                'name' => "Usuário {$i}",
                'email' => "usuario{$i}@example.com",
                'password' => '123456',
                'password_confirmation' => '123456',
            ]);
            $user->save();
        }
    }

    public function test_is_out_of_range_should_be_false_for_existing_pages(): void
    {
        $this->createUsers(7);

        $this->assertFalse(User::paginate(1, 5)->isOutOfRange());
        $this->assertFalse(User::paginate(2, 5)->isOutOfRange());
    }

    public function test_is_out_of_range_should_be_true_after_the_last_page(): void
    {
        $this->createUsers(7);

        $paginator = User::paginate(3, 5);

        $this->assertTrue($paginator->isOutOfRange());
        $this->assertEquals(2, $paginator->totalOfPages());
        $this->assertSame([], $paginator->registers());
    }

    public function test_is_out_of_range_should_be_true_below_page_one(): void
    {
        $this->createUsers(2);

        $this->assertTrue(User::paginate(0, 5)->isOutOfRange());
        $this->assertTrue(User::paginate(-1, 5)->isOutOfRange());
    }

    public function test_page_one_of_an_empty_table_should_not_be_out_of_range(): void
    {
        $paginator = User::paginate(1, 5);

        $this->assertEquals(0, $paginator->totalOfPages());
        $this->assertFalse($paginator->isOutOfRange());
        $this->assertTrue(User::paginate(2, 5)->isOutOfRange());
    }
}
```

Por quê:

- `Paginator` é de `lib/`, genérico: testa com `User` pra não depender
  de Eventos. `paginate()` vem do `Model`, vale pra qualquer model.
- Cobre os 4 casos: existente, depois da última (sem registros
  carregados), abaixo de 1, tabela vazia.

## 4. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) Import no topo, entre `Auth` e `ControllerTestCase`:

```php
use Lib\Authentication\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\Controllers\ControllerTestCase;
```

b) Testes novos depois de `test_index_lists_five_events_per_page`
(termina em `assertStringNotContainsString('Evento 5', $page2);`):

```php
    public function test_index_accepts_page_as_digit_string(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => '2']);

        $this->assertStringContainsString('Evento 6', $response);
        $this->assertStringContainsString('Mostrando 6 - 7 de 7', $response);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPageProvider(): array
    {
        return [
            'texto' => ['abc'],
            'zero' => ['0'],
            'negativo' => ['-1'],
            'dígitos com sufixo' => ['2abc'],
            'zero à esquerda' => ['02'],
            'decimal' => ['1.5'],
            'estouro de inteiro' => ['99999999999999999999'],
            'array' => [['2']],
        ];
    }

    #[DataProvider('invalidPageProvider')]
    public function test_index_with_invalid_page_redirects_to_first_page(mixed $page): void
    {
        $this->createEvent();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => $page]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Página inválida!', $_SESSION['flash']['danger']);
    }

    public function test_index_with_page_after_the_last_redirects_to_last_page(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => '999']);

        $this->assertSame('Location: /admin/events/page/2', $response);
        $this->assertEquals('Página inexistente! Mostrando a última página.', $_SESSION['flash']['danger']);
    }

    public function test_index_with_page_two_and_no_events_redirects_to_page_one(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => '2']);

        $this->assertSame('Location: /admin/events/page/1', $response);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIdSuffixProvider(): array
    {
        return [
            'texto' => ['abc'],
            'dígitos com sufixo' => ['{id}abc'],
            'zero à esquerda' => ['0{id}'],
            'negativo' => ['-{id}'],
        ];
    }

    #[DataProvider('invalidIdSuffixProvider')]
    public function test_show_with_invalid_id_redirects_to_index(string $pattern): void
    {
        $event = $this->createEvent();
        $id = str_replace('{id}', (string) $event->id, $pattern);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $id]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_destroy_with_id_followed_by_letters_does_not_remove_the_event(): void
    {
        // Antes, (int) "1abc" virava 1 e removia o Evento 1.
        $event = $this->createEvent();

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => "{$event->id}abc"]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }
```

Por quê:

- Teste de controller chama a ação direto com `Request` montado; o
  redirect só imprime `Location: ...`, daí o `assertSame`.
- Data provider: 8 páginas inválidas num teste só; o id usa
  `{id}` porque o id real só existe depois de `createEvent()`.
- `'2'` como string: prova que a rota real (string) segue ok.
- `destroy` com `1abc` é o caso grave: antes removia outro Evento.
- `$_SESSION['flash']` limpo no `setUp` do teste: flash não vaza.

## 5. `tests/Integration/Access/EventsAccessTest.php`

Teste novo antes de
`test_admin_should_be_redirected_to_index_when_event_does_not_exist`:

```php
    public function test_admin_should_be_redirected_when_page_or_id_is_invalid(): void
    {
        $client = $this->loggedClient($this->admin);

        $page = $client->get('/admin/events/page/abc');
        $this->assertEquals(302, $page->getStatusCode());
        $this->assertEquals('/admin/events', $page->getHeaderLine('Location'));

        $outOfRange = $client->get('/admin/events/page/999');
        $this->assertEquals(302, $outOfRange->getStatusCode());
        $this->assertEquals('/admin/events/page/1', $outOfRange->getHeaderLine('Location'));

        $id = $client->get("/admin/events/{$this->event->id}abc");
        $this->assertEquals(302, $id->getStatusCode());
        $this->assertEquals('/admin/events', $id->getHeaderLine('Location'));
    }
```

Por quê:

- HTTP real, passando pelo Router: parâmetro chega como **string**,
  como no navegador. O teste de controller não prova isso.
- `loggedClient`: sem sessão a rota de admin daria 302 pro login e o
  teste passaria por engano.
- `/page/999` -> `/page/1`: o `setUp` cria um só Evento (1 página).

## 6. `tests/Acceptance/admin/events/PaginateEventsCest.php`

Método novo antes do privado `seeEventsOfPage` (que já existe):

```php
    public function openNonexistentPage(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (página inexistente ou inválida)');

        // Depois da última: vai para a última (4) com aviso.
        $page->amOnPage('/admin/events/page/9');
        $page->seeCurrentUrlEquals('/admin/events/page/4');
        $page->see('Página inexistente! Mostrando a última página.');
        $this->seeEventsOfPage($page, 4);

        // Não numérica: volta para a primeira com aviso.
        $page->amOnPage('/admin/events/page/abc');
        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Página inválida!');
        $this->seeEventsOfPage($page, 1);
    }
```

Por quê: navegador real (Selenium) com os 16 Eventos do
`BaseEventsCest` (4 páginas). Confere URL depois do redirect, flash
na tela e os Eventos certos da página.

## Fechamento

```sh
./run test tests/Unit/Lib/PaginatorTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser      # PaginateEventsCest
docker compose stop selenium
```

Sem `db:populate` (schema não mudou). No navegador (populate, admin):
`/admin/events/page/9` -> `/page/4` com "Página inexistente!...";
`/admin/events/page/abc` e `/page/0` -> `/admin/events` com "Página
inválida!"; `/admin/events/1abc` -> `/admin/events` com "Evento não
encontrado!".

Commit (inglês): `fix: Validate page and id params without silent int casts`.

## Armadilhas (resumo)

- Parâmetro de rota chega como string; padrão `getParam('page', 1)`
  é `int`: trate os dois (`is_int` separado, ausente = página 1).
- `ctype_digit('')` é `false`; `ctype_digit('007')` é `true`: pra
  recusar zero à esquerda, compare `(string) (int) $page === $page`.
- `totalOfPages` é 0 sem Eventos: página 1 continua válida
  (`max(1, ...)`), senão laço de redirect.
- `(int) 'abc'` vira 0 e cai em "não encontrado" por sorte;
  `(int) '12abc'` vira 12 e abre outro Evento: é o que a validação
  fecha.
- Sem a correção, página além da última nem chega ao `entriesInfo`:
  `index.phtml` mostra "Nenhum evento cadastrado." (falso) e a
  navegação fica no `else`, sem link de volta.
- `is_numeric` aceita `1.5`, `1e3`, `-1`, ` 1`: use `ctype_digit`.
- Sem `return` depois de `redirectTo()`: segue renderizando.
- Catálogo sugere flash + volta à página 1 também pra página além da
  última; o exemplo redireciona pra última. Escolha um e teste.
