# M6 passo a passo: unicidade (título único na mesma data)

Pedido típico: "não pode haver dois Eventos com o mesmo título na mesma
data; use a validação do framework e mostre o erro no formulário" (ou
só "mesmo título"). Chance: média-alta (duplicidade cobrada na issue
4.2 do grupo 5; `uniqueness` está na rubrica). Tempo: 15-20 min.

Exemplo pronto: `fvp/4.2/exemplos/m6-unicidade-titulo-data/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: parte da `main`.

Ordem = fluxo da validação: model -> testes. `lib/Validations.php`,
schema, controller e views não mudam. Na prova, prioridade: funcionar
no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `app/Models/Event.php`
2. `tests/Unit/Models/Events/EventTest.php`
3. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
4. `tests/Acceptance/admin/events/CreateDuplicateEventCest.php` (novo)

Depois: suíte, `./run phpcs`. Sem `db:populate` (schema igual).

## 1. `app/Models/Event.php`

Fim de `validates()`, depois do `if` de `capacity` (linha ~79-81),
antes do `}` que fecha o método:

```php
        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }

        // Título único na mesma data. Só confere se os dois campos já estão válidos:
        // cada campo guarda uma mensagem só, e a última vence.
        if ($this->errors('title') === null && $this->errors('starts_on') === null) {
            Validations::uniqueness(['title', 'starts_on'], $this);
        }
    }
```

Por quê:

- Reaproveita `Validations::uniqueness` de `lib/Validations.php`
  (linhas ~155-205), a mesma do e-mail em `app/Models/User.php`
  (`Validations::uniqueness('email', $this);`). Nada de SQL no
  controller.
- `uniqueness` aceita string ou array: com array monta
  `title = :title AND starts_on = :starts_on` (binds, sem injeção).
- Na edição, ignora o próprio registro se os campos não mudaram
  (compara com `findById`): editar continua funcionando.
- `if (errors(...) === null)`: `addError` guarda uma mensagem por campo
  e a última vence. Sem o `if`, título repetido com data no passado
  trocaria "não pode estar no passado!" por "já existe...". Com título
  vazio, nem consulta o banco.
- No fim do método: depende das validações anteriores de `title` e
  `starts_on` (inclusive `maxLength` do M2, se aplicado).
- Só o título: troque o array por `'title'`.

## 2. `tests/Unit/Models/Events/EventTest.php`

Testes novos depois de `test_invalid_event_should_not_be_saved` (linha
~241), antes do comentário `/* --- audience (SET) --- */`:

```php
    /* ------------------- unicidade: título + data ------------------- */

    public function test_same_title_on_the_same_date_should_be_invalid(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);

        $duplicate = new Event($this->params(['title' => 'Oficina de Git']));

        $this->assertFalse($duplicate->save());
        $this->assertEquals('já existe um registro com esse dado', $duplicate->errors('title'));
        $this->assertEquals('já existe um registro com esse dado', $duplicate->errors('starts_on'));
        $this->assertCount(1, Event::all());
    }

    public function test_same_title_on_another_date_should_be_valid(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);

        $other = new Event($this->params([
            'title' => 'Oficina de Git',
            'starts_on' => date('Y-m-d', strtotime('+31 days')),
        ]));

        $this->assertTrue($other->save());
        $this->assertCount(2, Event::all());
    }

    public function test_title_uniqueness_ignores_case_and_accents(): void
    {
        // Collation do MySQL (utf8mb4_0900_ai_ci): "a" = "A" e "e" = "ê".
        $this->createEvent(['title' => 'Semana Acadêmica']);

        $duplicate = new Event($this->params(['title' => 'SEMANA ACADEMICA']));

        $this->assertFalse($duplicate->isValid());
        $this->assertEquals('já existe um registro com esse dado', $duplicate->errors('title'));
    }

    public function test_event_can_be_updated_keeping_its_own_title_and_date(): void
    {
        $event = $this->createEvent(['title' => 'Oficina de Git']);

        $event->description = 'Descrição nova.';

        $this->assertTrue($event->save());
        $this->assertEquals('Descrição nova.', Event::findById($event->id)->description);
    }

    public function test_update_to_title_and_date_of_another_event_should_be_invalid(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);
        $event = $this->createEvent(['title' => 'Oficina de Docker']);

        $event->title = 'Oficina de Git';

        $this->assertFalse($event->save());
        $this->assertEquals('já existe um registro com esse dado', $event->errors('title'));
        $this->assertEquals('Oficina de Docker', Event::findById($event->id)->title);
    }

    public function test_uniqueness_should_not_replace_a_more_specific_starts_on_error(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);

        $event = new Event($this->params(['title' => 'Oficina de Git', 'starts_on' => '2030-02-30']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não é uma data válida!', $event->errors('starts_on'));
        $this->assertNull($event->errors('title'));
    }
```

Por quê:

- `createEvent()` salva com `params()` (data fixa +30 dias): mesmo
  título = mesma data = duplicado.
- Seis casos: duplicado falha nos dois campos; outra data passa;
  collation ignora maiúsculas/acentos; editar sem mudar título/data
  passa; editar para título/data de outro falha e não grava; erro de
  data mais específico não é trocado.
- Mensagem exata vem de `uniqueness`: sem `!`.

## 3. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) Depois de
`test_create_with_invalid_data_renders_form_with_errors_and_values`,
antes de `test_create_without_audience_marks_audience_field`:

```php
    public function test_create_with_same_title_and_date_renders_form_with_error(): void
    {
        $this->createEvent();

        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams()]
        );

        $this->assertCount(1, Event::all());
        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertMatchesRegularExpression('/id="event_title"[^>]*is-invalid|is-invalid[^>]*id="event_title"/', $response);
        $this->assertStringContainsString(
            '<div class="invalid-feedback">já existe um registro com esse dado</div>',
            $response
        );
        $this->assertStringContainsString('Existem dados incorretos! Por favor, verifique!', $response);
    }
```

b) Depois de `test_update_with_invalid_data_renders_form_and_keeps_record`,
antes de `test_update_capacity_below_enrollments_is_rejected`:

```php
    public function test_update_without_changes_keeps_working_with_uniqueness(): void
    {
        $event = $this->createEvent();

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams()]
        );

        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento atualizado com sucesso!', $_SESSION['flash']['success']);
    }
```

Por quê:

- `createEvent()` e `validParams()` do teste usam os mesmos dados:
  segundo `create` é duplicado. Controller renderiza `new` de novo
  (sem redirect), erro sob o título, flash de erro.
- `update` sem mudança: `uniqueness` ignora o próprio registro, PRG
  normal (redirect para o show + flash de sucesso).

## 4. `tests/Acceptance/admin/events/CreateDuplicateEventCest.php` (novo)

Arquivo inteiro:

```php
<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Título único na mesma data: "Evento 01" já existe na data startsOn(1).
 */
class CreateDuplicateEventCest extends BaseEventsCest
{
    public function createWithSameTitleAndDate(AcceptanceTester $page): void
    {
        $page->wantTo('Tentativa de cadastro com título e data de um Evento existente');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData([
            'title' => $this->title(1),
            'starts_on' => $this->startsOn(1),
        ]));
        $page->click('Salvar');

        $page->see('Existem dados incorretos! Por favor, verifique!');
        $page->seeElement('#event_title.is-invalid');
        $page->see('já existe um registro com esse dado', '#event_title ~ .invalid-feedback');
        $page->seeInField('#event_title', $this->title(1));
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function createWithSameTitleOnAnotherDate(AcceptanceTester $page): void
    {
        $page->wantTo('Cadastro com o título de um Evento existente em outra data');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData([
            'title' => $this->title(1),
            'starts_on' => $this->startsOn(2),
        ]));
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        Assert::assertCount(self::TOTAL_EVENTS + 1, Event::all());
    }
}
```

Por quê:

- Herda `BaseEventsCest`: login de admin, `seedEvents` (16 Eventos,
  títulos `Evento 01..16` únicos), `fillEventForm`, `validEventData`.
- `title(1)`/`startsOn(1)`: título e data do Evento semeado nº 1;
  `startsOn(2)` = outra data, deve salvar.
- Prova no navegador real: erro sob o campo, valor mantido, nada
  gravado; em outra data, flash de sucesso.

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

No navegador: copie título e data de um Evento da listagem para "Novo
Evento" e salve. Form volta com "já existe um registro com esse dado"
sob o título e sob a data, valores preenchidos.

Commit (inglês): `feat: Require unique event title on the same date`.

Mensagem própria (se pedirem): parâmetro opcional em `uniqueness`
(`string $message = 'já existe um registro com esse dado'`) em vez de
mudar o texto fixo, que é compartilhado com o e-mail do `User`.

## Armadilhas (resumo)

- `uniqueness` só ignora o próprio registro quando título **e** data
  não mudaram; editar outros campos continua funcionando.
- Mensagem sem `!` e marcada em todos os campos da lista;
  `assertCount(1, $this->errorsOf($event))` falha por isso.
- Sem o `if (errors(...) === null)`, "já existe..." sobrescreve erro
  mais específico de `title`/`starts_on`.
- Collation `utf8mb4_0900_ai_ci`: "SEMANA ACADEMICA" = "Semana
  Acadêmica". Para diferenciar, collation `_as_cs` na coluna.
- Populate e `BaseEventsCest` já têm títulos únicos. Teste novo que
  cria dois Eventos com `createEvent()` sem `title` passa a falhar
  (mesmo título padrão e data): passe títulos diferentes.
- Com só `'title'`, mesmo título em datas diferentes também falha:
  confira `grep -n "createEvent(" tests/`.
- `Model::update([...])` não valida: só `save()` confere a unicidade.
- Não há `UNIQUE` no schema. Se pedirem no banco:
  `UNIQUE KEY uq_events_title_date (title, starts_on)` em
  `database/schema.sql` e trate a `PDOException` como o
  `safeDestroy()` faz.
