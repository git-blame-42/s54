# M4 passo a passo: segunda data (`ends_on`) não anterior a `starts_on`

Pedido típico: "o Evento passa a ter data de término (`ends_on`),
obrigatória, que não pode ser antes do início; mostre no form, na
listagem e no detalhe". Variante: prazo de inscrição
(`registration_deadline`) até a data do Evento. Chance: média-alta.
Tempo: 25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/m4-segunda-data-ends-on/` (patch,
bundle e árvore `projeto/`). Sem pré-requisito: parte da `main`. É o
roteiro de M1 (campo novo) + validação entre dois campos.

Ordem = fluxo da requisição: banco -> model -> controller -> views ->
dados -> testes. Na prova, prioridade: funcionar no navegador, depois
testar, depois `phpcs`.

## Arquivos, em ordem

1. `database/schema.sql`
2. `app/Models/Event.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/_form.phtml`
5. `app/views/admin/events/index.phtml`
6. `app/views/admin/events/show.phtml`
7. `database/Populate/populate.php`
8. `tests/Unit/Models/Events/EventTest.php`
9. `tests/Unit/Models/Events/EnrollmentTest.php`
10. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
11. `tests/Integration/Access/EventsAccessTest.php`
12. `tests/Acceptance/admin/events/BaseEventsCest.php`
13. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`
14. `tests/Acceptance/admin/events/CreateWithInvalidDataCest.php`

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE events`, logo após `starts_on`:

```sql
    starts_on   DATE NOT NULL,
    ends_on     DATE NOT NULL,
```

Por quê:

- ActiveRecord não cria coluna. `save()` insere todas as chaves de
  `$columns`; coluna ausente -> `Unknown column 'ends_on'`.
- `NOT NULL` sem `DEFAULT`: obrigatoriedade fica no model. Se fosse
  opcional: `DATE NULL`.

Armadilha: testes recriam o schema; o banco de dev não. No fim,
`./run db:populate`, senão o erro só aparece no navegador.

## 2. `app/Models/Event.php`

Cinco pontos.

a) `use` no topo, entre `Model` e `Validations`:

```php
use Core\Database\ActiveRecord\Model;
use DateTimeImmutable;
use Lib\Validations;
```

b) Docblock da classe, depois de `@property string $starts_on`:

```php
 * @property string $ends_on data de término, nunca antes de starts_on
```

Por quê: atributo dinâmico (`__get`/`__set`); sem `@property` o
`phpstan` reclama.

c) `$columns`, depois de `'starts_on',`:

```php
        'ends_on',
```

Por quê: lista que monta `INSERT`/`UPDATE` e libera atribuição.
Esquecer -> `Property ends_on not found`.

d) `validates()`, logo depois do bloco de `starts_on` (linha ~75-77),
antes do `if` de `capacity`:

```php
        Validations::notEmpty('starts_on', $this) &&
            Validations::validDate('starts_on', $this) &&
            $this->validatesStartsOnNotPast();

        Validations::notEmpty('ends_on', $this) &&
            Validations::validDate('ends_on', $this) &&
            $this->validatesEndsOnNotBeforeStartsOn();
```

Por quê:

- Regra entre campos é do model, em `validates()`.
- `&&` em cadeia: só compara se não vazio **e** data válida. Um erro
  por campo, nunca dois contraditórios.

e) Métodos privados novos, depois de `validatesStartsOnNotPast()`
(termina em `Validations::notPastDate('starts_on', $this);`, linha
~98), antes do docblock "P02: na edição, a capacidade...":

```php
    /**
     * A data de término não pode ser anterior à de início (no mesmo dia é permitido).
     * Só compara quando as duas datas são válidas; data inválida já tem o próprio erro.
     */
    private function validatesEndsOnNotBeforeStartsOn(): void
    {
        $start = self::parseDate($this->starts_on);
        $end = self::parseDate($this->ends_on);

        if ($start !== null && $end !== null && $end < $start) {
            $this->addError('ends_on', 'não pode ser anterior à data de início!');
        }
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
    }
```

Por quê:

- Mesmo molde do `validatesStartsOnNotPast()`: método privado chamado
  no fim da cadeia, grava erro com `addError`.
- `parseDate` de `lib/Validations.php` é privado: não dá pra chamar de
  fora. Copie o `createFromFormat('!Y-m-d', ...)`.
- `!` zera a hora (compara só o dia). `format() === $value` rejeita
  `2030-02-31` (que viraria 03/03).
- `null` em qualquer das duas -> não compara. Com `starts_on` inválida,
  o usuário vê só o erro de `starts_on`.
- `$end < $start`: mesmo dia é válido.
- Não copie a regra "não no passado" para `ends_on` (não foi pedida).

## 3. `app/Controllers/Admin/EventsController.php`

Constante `FIELDS` (linha ~14), depois de `'starts_on'`:

```php
    private const FIELDS = ['title', 'description', 'kind', 'modality', 'starts_on', 'ends_on', 'location', 'capacity'];
```

Por quê: allowlist. Só o que está em `FIELDS` sai do POST e chega ao
model (protege contra mass assignment). Esquecer -> model recebe `null`
e `notEmpty` reclama "sem motivo".

## 4. `app/views/admin/events/_form.phtml`

Depois do `</div>` do bloco de `starts_on` (`<?= $feedback('starts_on') ?>`,
linha ~74), antes do bloco de `location`:

```php
    <div class="mb-3">
        <label for="event_ends_on" class="form-label">Data de término</label>
        <input type="date" class="form-control<?= $invalid('ends_on') ?>" id="event_ends_on" name="event[ends_on]"
            value="<?= htmlspecialchars((string) $event->ends_on) ?>">
        <?= $feedback('ends_on') ?>
    </div>
```

Por quê:

- Cópia do campo `starts_on` trocando `id`, `name`, `label`, `value`.
- `name="event[ends_on]"`: chega dentro do array `event`, que o
  controller filtra por `FIELDS`.
- `$invalid()` põe `is-invalid` (borda vermelha); `$feedback()` mostra
  a mensagem embaixo. View burra: só lê `$event->errors(...)`.
- `type="date"` envia `Y-m-d`, o formato que `validDate` espera.
- `htmlspecialchars`: escape (XSS), cobrado na rubrica.

## 5. `app/views/admin/events/index.phtml`

a) `<thead>`, depois de `<th>Data</th>`:

```php
                <th>Término</th>
```

b) `<tbody>`, depois do `<td>` de `starts_on`:

```php
                    <td><?= date('d/m/Y', strtotime($event->starts_on)) ?></td>
                    <td><?= date('d/m/Y', strtotime($event->ends_on)) ?></td>
```

Por quê: view só exibe o que o controller passou. Ordem dos `<td>`
bate com a dos `<th>`. `date(...)` formata pt-BR; saída numérica,
dispensa `htmlspecialchars`.

## 6. `app/views/admin/events/show.phtml`

Dentro do `<dl>`, depois do par `Data`:

```php
    <dt class="col-sm-3">Término</dt>
    <dd class="col-sm-9"><?= date('d/m/Y', strtotime($event->ends_on)) ?></dd>
```

Por quê: mesmo padrão `dt`/`dd` dos outros campos; `show` recebe o
`$event` do controller e só exibe.

## 7. `database/Populate/populate.php`

No `new Event([...])` do `foreach` (linha ~138), depois de
`'starts_on' => ...`:

```php
        'starts_on' => date('Y-m-d', strtotime("+{$daysAhead} days")),
        // Semana acadêmica dura 5 dias; os demais Eventos terminam no mesmo dia.
        'ends_on' => date('Y-m-d', strtotime('+' . ($daysAhead + ($kind === 'academic_week' ? 4 : 0)) . ' days')),
```

Por quê:

- Sem a chave, `save()` falha no `notEmpty` e o banco de dev fica sem
  Eventos.
- Usa as variáveis já destruturadas do `foreach` (`$daysAhead`,
  `$kind`); não mexa no array posicional de dados.
- Na prova basta término = início; aqui a Semana acadêmica termina 4
  dias depois para a coluna ter variação.

Depois de salvar: `./run db:populate`.

## 8. `tests/Unit/Models/Events/EventTest.php`

a) `params()` (linhas ~41-54), depois de `'starts_on' => ...`:

```php
            'ends_on' => date('Y-m-d', strtotime('+31 days')),
```

b) `test_empty_event_should_have_error_in_every_field` e `errorsOf()`:
lista ganha `'ends_on'`; quebre em `$fields` (linha > 130 colunas,
limite do `phpcs.xml`):

```php
        $fields = ['title', 'description', 'kind', 'modality', 'audience', 'starts_on', 'ends_on', 'location', 'capacity'];
        foreach ($fields as $field) {
```

c) `requiredFieldsProvider()`, depois de `'starts_on' => ['starts_on'],`:

```php
            'ends_on' => ['ends_on'],
```

d) Testes novos depois de `test_starts_on_today_should_be_valid`
(antes de `test_invalid_event_should_not_be_saved`):

```php
    public function test_ends_on_equal_to_starts_on_should_be_valid(): void
    {
        $date = date('Y-m-d', strtotime('+20 days'));
        $event = new Event($this->params(['starts_on' => $date, 'ends_on' => $date]));

        $this->assertTrue($event->isValid());
        $this->assertTrue($event->save());
        $this->assertEquals($date, Event::findById($event->id)?->ends_on);
    }

    public function test_ends_on_before_starts_on_should_be_invalid(): void
    {
        $event = new Event($this->params([
            'starts_on' => date('Y-m-d', strtotime('+20 days')),
            'ends_on' => date('Y-m-d', strtotime('+19 days')),
        ]));

        $this->assertFalse($event->isValid());
        $this->assertFalse($event->save());
        $this->assertEquals('não pode ser anterior à data de início!', $event->errors('ends_on'));
        $this->assertNull($event->errors('starts_on'));
        $this->assertCount(0, Event::all());
    }

    public function test_ends_on_should_be_a_valid_date(): void
    {
        foreach (['amanhã', '2030-02-31', '30/12/2030'] as $value) {
            $event = new Event($this->params(['ends_on' => $value]));

            $this->assertFalse($event->isValid(), $value);
            $this->assertEquals('não é uma data válida!', $event->errors('ends_on'), $value);
        }
    }

    public function test_ends_on_should_not_be_compared_with_an_invalid_starts_on(): void
    {
        $event = new Event($this->params(['starts_on' => '2030-02-31', 'ends_on' => '2030-01-01']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não é uma data válida!', $event->errors('starts_on'));
        $this->assertNull($event->errors('ends_on'));
    }
```

Por quê:

- `params()` é a fábrica de Evento válido: sem a chave, todo teste que
  salva quebra por "vazio".
- Provider + form vazio: `ends_on` passa a ser obrigatório.
- Quatro casos: mesmo dia válido (e persiste), um dia antes inválido,
  data inexistente, `starts_on` inválida não gera erro falso em
  `ends_on`.

## 9. `tests/Unit/Models/Events/EnrollmentTest.php`

`setUp` (linhas ~37-49), no `new Event([...])`, depois de `starts_on`:

```php
            'ends_on' => date('Y-m-d', strtotime('+10 days')),
```

Por quê: o `setUp` salva um Evento; sem a chave `save()` retorna
`false` e todos os testes de Inscrição caem.

## 10. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) `validParams()` (linha ~278), depois de `starts_on` (+10 dias):

```php
            'ends_on' => date('Y-m-d', strtotime('+12 days')),
```

b) Testes novos depois de
`test_create_with_invalid_data_renders_form_with_errors_and_values`:

```php
    public function test_create_with_ends_on_before_starts_on_shows_error_below_the_field(): void
    {
        $startsOn = date('Y-m-d', strtotime('+10 days'));
        $endsOn = date('Y-m-d', strtotime('+9 days'));

        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['starts_on' => $startsOn, 'ends_on' => $endsOn])]
        );

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression(
            '/class="form-control is-invalid" id="event_ends_on"[^>]*value="' . $endsOn . '">\s*'
                . '<div class="invalid-feedback">não pode ser anterior à data de início!<\/div>/',
            $response
        );
        $this->assertMatchesRegularExpression(
            '/class="form-control" id="event_starts_on"[^>]*value="' . $startsOn . '"/',
            $response
        );
        $this->assertStringContainsString('value="Oficina de Testes"', $response);
        $this->assertStringContainsString('Existem dados incorretos! Por favor, verifique!', $response);
    }

    public function test_create_saves_ends_on_and_show_displays_it(): void
    {
        $endsOn = date('Y-m-d', strtotime('+12 days'));

        $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['event' => $this->validParams()]);

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals($endsOn, $event->ends_on);

        $show = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $this->assertStringContainsString('Término', $show);
        $this->assertStringContainsString(date('d/m/Y', strtotime('+12 days')), $show);

        $index = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $this->assertStringContainsString(date('d/m/Y', strtotime('+12 days')), $index);
    }
```

Por quê:

- Prova o caminho form -> `FIELDS` -> model -> view.
- Primeiro: erro só em `ends_on`, `starts_on` sem `is-invalid`, valores
  mantidos, flash de erro.
- Segundo: salva e aparece em `show` e `index` (formato `d/m/Y`).

## 11. `tests/Integration/Access/EventsAccessTest.php`

a) `setUp` (linhas ~28-40), no `new Event([...])`, depois de
`starts_on` (+30 dias):

```php
            'ends_on' => date('Y-m-d', strtotime('+30 days')),
```

b) `test_participant_should_not_create_update_or_destroy_events`
(linha ~148), no array `event` do POST, depois de `starts_on`:

```php
            'ends_on' => date('Y-m-d', strtotime('+10 days')),
```

Por quê: teste de acesso bate nas rotas por HTTP. `setUp` precisa de
Evento salvo; o POST precisa de dados válidos para o teste medir só o
bloqueio de acesso.

## 12. `tests/Acceptance/admin/events/BaseEventsCest.php`

a) `seedEvents` (linhas ~84-94), depois de `'starts_on' => ...,`:

```php
                'ends_on' => $this->startsOn($number),
```

b) `fillEventForm`: docblock passa a citar
`starts_on e ends_on (Y-m-d)`; o `if` de `starts_on` vira laço:

```php
        // input[type=date] depende do idioma do navegador ao digitar; definir o valor ISO é determinístico.
        foreach (['starts_on', 'ends_on'] as $field) {
            if (array_key_exists($field, $data)) {
                $page->executeJS(
                    "document.getElementById('event_{$field}').value = arguments[0];",
                    [(string) $data[$field]]
                );
            }
        }
```

c) `validEventData`: `return array_merge(...)` vira `$data = ...` e,
depois do `], $overrides);`:

```php
        $data = array_merge([
            // ... chaves de sempre
        ], $overrides);

        // Sem término informado, o Evento termina no dia em que começa (nunca antes do início).
        $data['ends_on'] ??= $data['starts_on'];

        return $data;
```

Por quê:

- `seedEvents` não confere o `save()`: sem a chave, nenhum Evento é
  gravado e os Cests falham com listagem vazia, sem mensagem.
- Selenium: `type="date"` não aceita `fillField` em pt-BR. Por isso
  `executeJS` define o valor ISO direto.
- `??=` em `validEventData`: Cest que muda só o início (ex.
  `UpdateSuccessfullyCest`, `+90 days`) ganha término válido sozinho.

## 13. `tests/Acceptance/admin/events/CreateSuccessfullyCest.php`

Depois de `Assert::assertSame($data['starts_on'], $event->starts_on);`:

```php
        Assert::assertSame($data['ends_on'], $event->ends_on);
```

Por quê: confere no banco que o valor do navegador chegou.

## 14. `tests/Acceptance/admin/events/CreateWithInvalidDataCest.php`

a) `createWithEmptyForm`, lista de obrigatórios:

```php
        foreach (['title', 'description', 'kind', 'starts_on', 'ends_on', 'location', 'capacity'] as $field) {
```

b) `createWithInvalidDataKeepsValidValues`: preencher `ends_on` válido,
conferir que não fica inválido e que se mantém:

```php
        $this->fillEventForm($page, ['starts_on' => date('Y-m-d', strtotime('-1 day')), 'ends_on' => date('Y-m-d')]);
```

```php
        foreach (['title', 'description', 'kind', 'ends_on', 'location'] as $field) {
            $page->dontSeeElement("#event_{$field}.is-invalid");
        }
```

e, depois de `$page->seeInField('#event_location', 'Laboratório B203');`:

```php
        $page->seeInField('#event_ends_on', date('Y-m-d'));
```

c) Cest novo no fim da classe:

```php
    public function createWithEndsOnBeforeStartsOn(AcceptanceTester $page): void
    {
        $startsOn = date('Y-m-d', strtotime('+10 days'));
        $endsOn = date('Y-m-d', strtotime('+9 days'));

        $page->wantTo('Tentativa de cadastro com término antes do início');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData(['starts_on' => $startsOn, 'ends_on' => $endsOn]));
        $page->click('Salvar');

        $page->see(self::FLASH);
        $page->see('Novo Evento', 'h1');
        $page->seeElement('#event_ends_on.is-invalid');
        $page->see('não pode ser anterior à data de início!', '#event_ends_on ~ .invalid-feedback');
        $page->dontSeeElement('#event_starts_on.is-invalid');
        $page->seeInField('#event_starts_on', $startsOn);
        $page->seeInField('#event_ends_on', $endsOn);
        $page->seeInField('#event_title', 'Oficina de Testes Automatizados');

        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }
```

Por quê: espelha o teste de integração no navegador real. Erro sob o
campo, borda vermelha, valores mantidos, nada gravado.

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # Unit + Integration (o PHPUnit não roda os Cests)
docker compose up -d selenium && sleep 5
./run test:browser      # Acceptance
docker compose stop selenium
./run phpcs
./run phpstan
```

No navegador (`admin@example.com` / `123456`): listagem com coluna
"Término" (Semana acadêmica termina 4 dias depois). "Novo Evento" com
término um dia antes do início -> erro embaixo do campo, demais valores
mantidos.

Commit (inglês): `feat: Add end date to events`.

## Armadilhas (resumo)

- Comparar sem validar antes: com `starts_on` inválida aparece erro
  falso em `ends_on`. Valide cada uma (`validDate`) e só compare as
  duas válidas.
- `parseDate` de `lib/Validations.php` é privado: copie o
  `createFromFormat('!Y-m-d', ...)` no model.
- Sem `!`, `createFromFormat` herda a hora atual; sem conferir
  `format()`, `2030-02-31` vira março.
- Não copie a regra "não no passado" de `starts_on` sem pedido.
- Selenium: `type="date"` não aceita `fillField` em pt-BR; use
  `executeJS`.
- Esquecer `ends_on` no `seedEvents`: listagem vazia sem mensagem.
- Esquecer `./run db:populate`: `Unknown column 'ends_on'` só no
  navegador.
