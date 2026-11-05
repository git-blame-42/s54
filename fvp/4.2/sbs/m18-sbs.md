# M18 passo a passo: Janela de datas: antecedência mínima e limite máximo

Pedido típico: "Evento tem que ser cadastrado com pelo menos 3 dias de
antecedência e no máximo 365 dias à frente. Hoje aceita 31/12/9999.
Mude a validação do framework e escreva os testes." Chance: média-alta.
Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/m18-janela-de-datas/` (patch, bundle e
árvore `projeto/`). Pré-requisitos: nenhum (base `main`). Schema não muda.

Forma do exemplo: estende `notPastDate` com `int $minDays = 0,
?int $maxDays = null` no fim (em vez de função nova), constantes
`STARTS_ON_MIN_DAYS = 3` e `STARTS_ON_MAX_DAYS = 365` no `Event`.

Ordem = fluxo da requisição: lib de validação -> model -> view -> testes.
Na prova, prioridade: funcionar no navegador, depois testar, depois
`phpcs`.

## Arquivos, em ordem

1. `lib/Validations.php`
2. `app/Models/Event.php`
3. `app/views/admin/events/_form.phtml`
4. `tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php`
5. `tests/Unit/Models/Events/EventTest.php`
6. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
7. `tests/Acceptance/admin/events/BaseEventsCest.php`
8. `tests/Acceptance/admin/events/CreateWithInvalidDataCest.php`

Depois: suíte, `./run phpcs`. Sem `db:populate` (schema igual).

## 1. `lib/Validations.php`

Três pontos em `notPastDate` (linhas ~109-138).

a) Docblock, depois da primeira linha ("A data não pode ser anterior a
hoje..."):

```php
     * Janela opcional: $minDays exige antecedência mínima (a data deve ser >= hoje + $minDays dias) e
     * $maxDays limita o quanto ela pode estar à frente (<= hoje + $maxDays dias). Com os padrões
     * (0 e null) a regra é só "não passada", sem limite máximo.
     *
```

b) Assinatura: dois parâmetros novos **no fim**, depois de `$now`:

```php
    public static function notPastDate(
        $attribute,
        $obj,
        string $format = 'Y-m-d',
        ?\DateTimeImmutable $now = null,
        int $minDays = 0,
        ?int $maxDays = null
    ) {
```

c) Depois do `if ($date < $today)` ("não pode estar no passado!"), antes
do `return true;`, e o helper `days()` logo abaixo do método:

```php
        if ($minDays > 0 && $date < $today->modify("+{$minDays} days")) {
            $obj->addError($attribute, 'deve ter pelo menos ' . self::days($minDays) . ' de antecedência!');
            return false;
        }

        if ($maxDays !== null && $date > $today->modify("+{$maxDays} days")) {
            $obj->addError($attribute, 'não pode passar de ' . self::days($maxDays) . ' a partir de hoje!');
            return false;
        }

        return true;
    }

    /** "1 dia", "3 dias". */
    private static function days(int $days): string
    {
        return $days === 1 ? '1 dia' : "{$days} dias";
    }
```

Por quê:

- Regra reutilizável mora no `Validations`; o número mora no model.
- Parâmetros no fim com padrão neutro (`0`, `null`): toda chamada
  antiga de `notPastDate` continua igual (9999-12-31 ainda passa sem
  janela).
- Ordem dos checks: inválida -> passado -> antecedência -> limite. Data
  passada mantém "não pode estar no passado!" (testes da base contam com
  ela).
- `$today` já vem do método (fuso padrão, `setTime(0, 0)`); `modify`
  soma dias de calendário. Limite inclusivo: +3 e +365 valem.
- `$now` injetável (já existia) deixa o teste de limite determinístico.
- `days()` só faz o singular ("1 dia").

Armadilha: compare com `$today->modify("+N days")`, não com `strtotime`
+ 86400 s.

## 2. `app/Models/Event.php`

a) Constantes, depois do array `AUDIENCES` e antes de
`protected static string $table`:

```php
    /** Antecedência mínima, em dias, para a data do Evento (hoje + 3 em diante). */
    public const STARTS_ON_MIN_DAYS = 3;

    /** Limite máximo, em dias, para a data do Evento (até hoje + 365). */
    public const STARTS_ON_MAX_DAYS = 365;
```

b) `validates()`, bloco de `starts_on`: renomeie a chamada:

```php
        Validations::notEmpty('starts_on', $this) &&
            Validations::validDate('starts_on', $this) &&
            $this->validatesStartsOnWindow();
```

c) Método privado `validatesStartsOnNotPast` vira
`validatesStartsOnWindow`. Mantém o `if (!$this->newRecord())` do
topo; troca só a última linha:

```php
    /**
     * A data deve ficar na janela de hoje + STARTS_ON_MIN_DAYS até hoje + STARTS_ON_MAX_DAYS dias ao
     * criar. Na edição, só é cobrada quando `starts_on` muda: assim um Evento que já aconteceu (ou que
     * está a menos de 3 dias) ainda pode ter a descrição, o local etc. corrigidos.
     */
    private function validatesStartsOnWindow(): void
    {
        // ... if (!$this->newRecord()) { ... return; } igual à base

        Validations::notPastDate(
            'starts_on',
            $this,
            minDays: self::STARTS_ON_MIN_DAYS,
            maxDays: self::STARTS_ON_MAX_DAYS
        );
    }
```

Por quê:

- Validação no model, nunca no controller. Controller não muda.
- Constante: professor pede "5 dias" amanhã, só ela muda. A view também
  lê dali.
- Argumentos nomeados pulam `$format` e `$now` sem repetir padrões.
- Fica dentro do método que só valida no create ou quando a data muda:
  Evento a 2 dias ainda pode ter a descrição corrigida.

## 3. `app/views/admin/events/_form.phtml`

Bloco de `starts_on` (linhas ~70-76). `min`/`max` entre o `name` e o
`value`; `form-text` depois do `$feedback`:

```php
        <input type="date" class="form-control<?= $invalid('starts_on') ?>" id="event_starts_on" name="event[starts_on]"
            min="<?= date('Y-m-d', strtotime('+' . Event::STARTS_ON_MIN_DAYS . ' days')) ?>"
            max="<?= date('Y-m-d', strtotime('+' . Event::STARTS_ON_MAX_DAYS . ' days')) ?>"
            value="<?= htmlspecialchars((string) $event->starts_on) ?>">
        <?= $feedback('starts_on') ?>
        <div class="form-text">
            De <?= Event::STARTS_ON_MIN_DAYS ?> a <?= Event::STARTS_ON_MAX_DAYS ?> dias a partir de hoje.
        </div>
```

Por quê:

- `use App\Models\Event;` já está no topo do arquivo.
- `min`/`max` = só UX do seletor. O form tem `novalidate` (`_form.phtml:16`):
  quem barra é o `validates()`.
- Números lidos da constante: view burra, sem número mágico.
- Opcional na prova; o teste de integração do exemplo confere.

Armadilha: sem `novalidate`, `min`/`max` travariam o envio (e os Cests
de data inválida) e a edição de Evento já próximo.

## 4. `tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php`

Depois de `test_not_past_date_should_accept_a_custom_format` (linha
~118) e antes do bloco `fuso America/Sao_Paulo`:

```php
    /* ------------------- janela: $minDays e $maxDays ------------------- */

    public function test_not_past_date_without_window_should_accept_any_future_date(): void
    {
        $this->model->day = '9999-12-31'; // @phpstan-ignore-line

        $this->assertTrue(Validations::notPastDate('day', $this->model));
    }

    public function test_not_past_date_should_require_the_minimum_advance(): void
    {
        $now = new \DateTimeImmutable('2026-11-05 10:00:00');

        foreach (['2026-11-05', '2026-11-07'] as $value) {
            $this->model->day = $value; // @phpstan-ignore-line

            $this->assertFalse(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, minDays: 3), $value);
            $this->assertEquals('deve ter pelo menos 3 dias de antecedência!', $this->model->errors('day'), $value);
        }

        $this->model->day = '2026-11-08'; // @phpstan-ignore-line
        $this->assertTrue(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, minDays: 3));
    }

    public function test_not_past_date_with_minimum_advance_should_keep_the_past_message(): void
    {
        $now = new \DateTimeImmutable('2026-11-05 10:00:00');
        $this->model->day = '2026-11-04'; // @phpstan-ignore-line

        $this->assertFalse(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, minDays: 3));
        $this->assertEquals('não pode estar no passado!', $this->model->errors('day'));
    }

    public function test_not_past_date_should_reject_dates_after_the_maximum(): void
    {
        $now = new \DateTimeImmutable('2026-11-05 10:00:00');

        $this->model->day = '2027-11-05'; // @phpstan-ignore-line
        $this->assertTrue(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, maxDays: 365));

        foreach (['2027-11-06', '9999-12-31'] as $value) {
            $this->model->day = $value; // @phpstan-ignore-line

            $this->assertFalse(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, maxDays: 365), $value);
            $this->assertEquals('não pode passar de 365 dias a partir de hoje!', $this->model->errors('day'), $value);
        }
    }

    public function test_not_past_date_window_messages_should_use_singular_for_one_day(): void
    {
        $now = new \DateTimeImmutable('2026-11-05 10:00:00');

        $this->model->day = '2026-11-05'; // @phpstan-ignore-line
        $this->assertFalse(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, minDays: 1, maxDays: 1));
        $this->assertEquals('deve ter pelo menos 1 dia de antecedência!', $this->model->errors('day'));

        $this->model->day = '2026-11-07'; // @phpstan-ignore-line
        $this->assertFalse(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, minDays: 1, maxDays: 1));
        $this->assertEquals('não pode passar de 1 dia a partir de hoje!', $this->model->errors('day'));

        $this->model->day = '2026-11-06'; // @phpstan-ignore-line
        $this->assertTrue(Validations::notPastDate('day', $this->model, 'Y-m-d', $now, minDays: 1, maxDays: 1));
    }
```

Por quê:

- `$now` fixo (2026-11-05): limites exatos sem depender do relógio.
  +2 recusa, +3 aceita; +365 aceita, +366 recusa.
- Primeiro teste prova compatibilidade: sem janela, nada muda.
- `$this->model` é o model fake do arquivo; `// @phpstan-ignore-line`
  porque `day` é atributo dinâmico.

## 5. `tests/Unit/Models/Events/EventTest.php`

a) `test_starts_on_today_should_be_valid` (linhas ~228-233) **sai**
(hoje deixou de ser válido). No lugar, quatro testes:

```php
    public function test_starts_on_should_have_the_minimum_advance(): void
    {
        foreach (['today', '+1 day', '+2 days'] as $offset) {
            $event = new Event($this->params(['starts_on' => date('Y-m-d', strtotime($offset))]));

            $this->assertFalse($event->isValid(), $offset);
            $this->assertEquals('deve ter pelo menos 3 dias de antecedência!', $event->errors('starts_on'), $offset);
        }
    }

    public function test_starts_on_inside_the_window_should_be_valid(): void
    {
        foreach (['+3 days', '+30 days', '+365 days'] as $offset) {
            $event = new Event($this->params(['starts_on' => date('Y-m-d', strtotime($offset))]));

            $this->assertTrue($event->isValid(), $offset);
        }
    }

    public function test_starts_on_after_the_maximum_should_be_invalid(): void
    {
        foreach ([date('Y-m-d', strtotime('+366 days')), '9999-12-31'] as $value) {
            $event = new Event($this->params(['starts_on' => $value]));

            $this->assertFalse($event->isValid(), $value);
            $this->assertEquals('não pode passar de 365 dias a partir de hoje!', $event->errors('starts_on'), $value);
        }
    }

    public function test_populate_offsets_should_fit_the_window(): void
    {
        // database/Populate/populate.php usa hoje + 14 até hoje + 82 dias.
        foreach (['+14 days', '+82 days'] as $offset) {
            $event = new Event($this->params(['starts_on' => date('Y-m-d', strtotime($offset))]));

            $this->assertTrue($event->isValid(), $offset);
        }
    }
```

b) Bloco "não passada só no create", depois de
`test_future_event_should_reject_a_past_starts_on_on_edit` (antes de
`test_past_starts_on_should_still_be_rejected_on_create`):

```php
    public function test_new_starts_on_outside_the_window_should_be_rejected_on_edit(): void
    {
        $event = $this->createPastEvent();

        $event->starts_on = date('Y-m-d', strtotime('+1 day'));
        $this->assertFalse($event->save());
        $this->assertEquals('deve ter pelo menos 3 dias de antecedência!', $event->errors('starts_on'));

        $event->starts_on = '9999-12-31';
        $this->assertFalse($event->save());
        $this->assertEquals('não pode passar de 365 dias a partir de hoje!', $event->errors('starts_on'));
    }

    public function test_event_less_than_three_days_ahead_can_be_edited_when_starts_on_does_not_change(): void
    {
        $event = $this->createEvent();
        $soon = date('Y-m-d', strtotime('+1 day'));
        $this->assertTrue($event->update(['starts_on' => $soon]));

        $found = Event::findById($event->id);
        $found->location = 'Laboratório B203';

        $this->assertTrue($found->save());
        $this->assertEquals($soon, Event::findById($event->id)->starts_on);
    }
```

Por quê:

- No model, datas relativas (`strtotime`), como a base; o `$now` fixo
  fica no teste da lib.
- `test_populate_offsets...` protege o `db:populate` (+14 a +82).
- `update()` não valida: serve pra pôr o Evento a 1 dia e provar que
  editar outro campo continua funcionando.
- `createPastEvent()` já existe no arquivo (move a data pro passado).

Armadilha: não "conserte" a validação pra fazer o teste "hoje é válido"
passar; o teste é que inverte.

## 6. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois de `test_create_with_invalid_data_renders_form_with_errors_and_values`
(termina na linha ~137), antes de
`test_create_without_audience_marks_audience_field`:

```php
    public function test_create_with_date_outside_the_window_renders_form_with_error(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['starts_on' => '9999-12-31'])]
        );

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString(
            '<div class="invalid-feedback">não pode passar de 365 dias a partir de hoje!</div>',
            $response
        );
        $this->assertStringContainsString('value="9999-12-31"', $response);
    }

    public function test_new_form_limits_the_date_input_to_the_window(): void
    {
        $response = $this->get(action: 'new', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('min="' . date('Y-m-d', strtotime('+3 days')) . '"', $response);
        $this->assertStringContainsString('max="' . date('Y-m-d', strtotime('+365 days')) . '"', $response);
        $this->assertStringContainsString('De 3 a 365 dias a partir de hoje.', $response);
    }
```

Por quê:

- Prova o caminho POST -> model -> re-render do form (sem redirect) com
  o erro embaixo do campo e o valor digitado mantido.
- Segundo teste cobre o `min`/`max` e o texto de ajuda da view.
- `validParams()` usa `+10 days`: cabe na janela, não muda.

## 7. `tests/Acceptance/admin/events/BaseEventsCest.php`

`validEventData` (linha ~171), `+1 day` vira `+7 days`:

```php
            'starts_on' => date('Y-m-d', strtotime('+7 days')),
```

Por quê: é a fábrica de dados válidos dos Cests. `+1 day` fica fora da
janela e `CreateSuccessfullyCest` quebra.

## 8. `tests/Acceptance/admin/events/CreateWithInvalidDataCest.php`

Método novo no fim da classe:

```php
    public function createWithDateOutsideTheWindow(AcceptanceTester $page): void
    {
        $page->wantTo('Tentativa de cadastro com data fora da janela (antecedência mínima e limite máximo)');

        $cases = [
            date('Y-m-d', strtotime('+1 day')) => 'deve ter pelo menos 3 dias de antecedência!',
            date('Y-m-d', strtotime('+400 days')) => 'não pode passar de 365 dias a partir de hoje!',
        ];

        foreach ($cases as $startsOn => $message) {
            $page->amOnPage('/admin/events/new');
            $page->see('De 3 a 365 dias a partir de hoje.');
            $this->fillEventForm($page, $this->validEventData(['starts_on' => $startsOn]));
            $page->click('Salvar');

            $page->see(self::FLASH);
            $page->seeElement('#event_starts_on.is-invalid');
            $page->see($message, '#event_starts_on ~ .invalid-feedback');
            $page->seeInField('#event_starts_on', $startsOn);
        }

        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }
```

Por quê: mesma prova do teste de integração, no navegador real. O
`fillEventForm` põe a data por `executeJS`, então o `min`/`max` não
atrapalha (e o form tem `novalidate`).

## Fechamento

```sh
./run test tests/Unit/Lib/Validations/NumberAndDateValidationsTest.php
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

Sem `db:populate` obrigatório (schema igual); rodar confirma que os 16
Eventos cabem na janela.

No navegador (`/admin/events/new`): data de amanhã -> "deve ter pelo
menos 3 dias de antecedência!"; 31/12/9999 -> "não pode passar de 365
dias a partir de hoje!"; ontem -> "não pode estar no passado!".

Commit (inglês): `feat: Event date window with minimum advance and
maximum limit`.

## Armadilhas (resumo)

- `test_starts_on_today_should_be_valid` passa a falhar: troque pelo
  teste de antecedência.
- `BaseEventsCest::validEventData` com `+1 day` quebra
  `CreateSuccessfullyCest`: use `+7 days`.
- Outros testes usam `+5`, `+10`, `+30`, `+90` dias: cabem em 3..365.
  Outro intervalo: `grep -rn strtotime tests database` antes.
- Populate vai de +14 a +82 dias: mínimo > 14 ou máximo < 82 faz o
  `db:populate` lançar `RuntimeException`.
- Chame o check de passado antes: data passada mantém "não pode estar no
  passado!" (testes e Cests da base conferem).
- Janela só no create ou quando a data muda; senão Evento próximo não
  pode mais ter a descrição corrigida.
- "1 ano" não é 365 dias (bissexto): se pedirem "1 ano", use
  `modify('+1 year')` e teste o dia exato.
- `min`/`max` no HTML não barram nada (`novalidate`); a regra vale no
  model.
- `$now` fixo só no teste da `Validations`; no `Event`, datas relativas.
