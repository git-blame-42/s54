# S4 passo a passo: Testes de borda pedidos pelo professor

Pedido típico: "testes para quantidades negativas, valores não
numéricos, duplicidade, edição não autorizada, atualização sem
alteração". Chance: média-alta. Tempo: 15-25 min.

Exemplo pronto: `fvp/4.2/exemplos/s4-testes-de-borda/` (patch, bundle e
árvore `projeto/`). Pré-requisito: nenhum, parte de `main`. Só testes:
nada em `app/`, `core/`, `lib/` nem no schema.

Ordem = fluxo da requisição, de dentro pra fora: model (unit) ->
controller (integração) -> rota/middleware (acesso por HTTP). Na prova,
prioridade: testes passando, depois `phpcs`/`phpstan`.

## Arquivos, em ordem

1. `tests/Unit/Models/Events/EventTest.php`
2. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
3. `tests/Integration/Access/EventsAccessTest.php`

Depois: `./run test tests/Unit`, `tests/Integration`, `./run phpcs`.

## 1. `tests/Unit/Models/Events/EventTest.php`

Depois de `test_invalid_event_should_not_be_saved` (linha ~235), antes
do comentário `/* ------------------- audience (SET) ------------------- */`.
`use PDOException;` e `use ...\DataProvider;` já estão no topo.

a) Capacidade inválida (negativo, não numérico, vazio), via provider:

```php
    /* ------------------- testes de borda ------------------- */

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidCapacityProvider(): array
    {
        return [
            'negativo' => [-1, 'deve ser maior ou igual a 1!'],
            'negativo em texto' => ['-1', 'deve ser maior ou igual a 1!'],
            'zero' => ['0', 'deve ser maior ou igual a 1!'],
            'texto' => ['abc', 'deve ser um número inteiro!'],
            'decimal' => ['1.5', 'deve ser um número inteiro!'],
            'decimal com vírgula' => ['1,5', 'deve ser um número inteiro!'],
            'notação científica' => ['1e1', 'deve ser um número inteiro!'],
            'zero à esquerda' => ['010', 'deve ser um número inteiro!'],
            'vazio' => ['', 'não pode ser vazio!'],
            'nulo' => [null, 'não pode ser vazio!'],
        ];
    }

    #[DataProvider('invalidCapacityProvider')]
    public function test_capacity_edge_values_should_be_rejected(mixed $capacity, string $message): void
    {
        $event = new Event($this->params(['capacity' => $capacity]));

        $this->assertFalse($event->isValid());
        $this->assertSame($message, $event->errors('capacity'));
        $this->assertCount(1, $this->errorsOf($event));
        $this->assertFalse($event->save());
        $this->assertCount(0, Event::all());
    }
```

Por quê:

- `new Event($this->params([...]))` + `isValid()`: o `createEvent()`
  tem `assertTrue($event->save())` dentro, não serve pra dado inválido.
- Provider: um caso por linha; o nome do caso aparece no `--testdox`.
- Mensagem por curto-circuito `notEmpty && integerMin` (`Event.php`
  ~79): vazio para em "não pode ser vazio!"; o resto vai pro
  `integerMin` (`filter_var` `FILTER_VALIDATE_INT`).
- `assertCount(1, $this->errorsOf($event))`: helper que já existia
  (linha ~128); prova que só `capacity` tem erro.
- `@return array<string, array{mixed, string}>`: sem isso o `phpstan`
  (nível 6) reclama de `array` sem tipo.

b) Comportamento atual que a base aceita ou estoura no banco:

```php
    /**
     * Comportamento atual: o filter_var(FILTER_VALIDATE_INT) aceita espaços nas pontas e o sinal "+".
     */
    public function test_capacity_with_spaces_or_plus_sign_is_accepted(): void
    {
        foreach ([' 10 ', '+5'] as $capacity) {
            $this->assertTrue((new Event($this->params(['capacity' => $capacity])))->isValid(), $capacity);
        }
    }

    /**
     * Comportamento atual (armadilha): o model não tem limite superior e a coluna é INT (máx. 2147483647).
     * O MySQL em modo estrito recusa o valor e o save() lança PDOException em vez de devolver false.
     */
    public function test_capacity_above_the_int_column_limit_passes_validation_but_fails_on_save(): void
    {
        $event = new Event($this->params(['capacity' => '3000000000']));

        $this->assertTrue($event->isValid());

        $this->expectException(PDOException::class);
        $event->save();
    }

    /**
     * Comportamento atual (armadilha): título acima do VARCHAR(255) passa na validação e o MySQL
     * recusa no save() com PDOException ("Data too long").
     */
    public function test_title_above_the_varchar_limit_passes_validation_but_fails_on_save(): void
    {
        $event = new Event($this->params(['title' => str_repeat('a', 256)]));

        $this->assertTrue($event->isValid());

        $this->expectException(PDOException::class);
        $event->save();
    }

    /**
     * Comportamento atual: notEmpty só recusa null e '' (não faz trim).
     */
    public function test_title_with_only_spaces_is_accepted(): void
    {
        $this->assertTrue((new Event($this->params(['title' => '   '])))->isValid());
    }
```

Por quê:

- Pedido é de testes, não de correção: o teste documenta o que a base
  faz hoje; docblock diz "Comportamento atual". Corrigir é outro
  cenário (M2: `maxLength`/`integerMax`).
- `expectException` vem **antes** da linha que lança; o que vem depois
  do `save()` não roda. Asserts de validação ficam antes.

c) Data inválida, via provider:

```php
    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidStartsOnProvider(): array
    {
        return [
            'dia inexistente' => ['2030-02-30', 'não é uma data válida!'],
            '29/02 fora de ano bissexto' => ['2029-02-29', 'não é uma data válida!'],
            'formato brasileiro' => ['31/12/2030', 'não é uma data válida!'],
            'com hora' => ['2030-12-31 10:00', 'não é uma data válida!'],
            'texto' => ['amanhã', 'não é uma data válida!'],
            'ontem' => [date('Y-m-d', strtotime('-1 day')), 'não pode estar no passado!'],
            'ano passado' => [date('Y-m-d', strtotime('-1 year')), 'não pode estar no passado!'],
        ];
    }

    #[DataProvider('invalidStartsOnProvider')]
    public function test_starts_on_edge_values_should_be_rejected(string $startsOn, string $message): void
    {
        $event = new Event($this->params(['starts_on' => $startsOn]));

        $this->assertFalse($event->isValid());
        $this->assertSame($message, $event->errors('starts_on'));
        $this->assertCount(1, $this->errorsOf($event));
    }
```

Por quê: mesmo molde do item a; datas relativas (`strtotime`) para o
teste não envelhecer.

d) Duplicidade:

```php
    /**
     * Comportamento atual: não há regra de unicidade; dois Eventos iguais são gravados.
     */
    public function test_duplicate_event_is_accepted(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);
        $this->createEvent(['title' => 'Oficina de Git']);

        $this->assertCount(2, Event::where(['title' => 'Oficina de Git']));
    }
```

Por quê: na base não há unicidade; o teste documenta isso. Com M6
aplicado, troque pelo teste de duplicidade do M6 (segundo `save()`
falso).

## 2. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

a) Topo, nos `use`, entre `Lib\Authentication\Auth` e
`Tests\...\ControllerTestCase`:

```php
use Lib\Authentication\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\Controllers\ControllerTestCase;
```

b) Depois de `test_destroy_keeps_event_with_enrollments` (linha ~254),
antes do docblock de `validParams()`. Create com dado ruim: form de
volta, sem 500, nada gravado.

```php
    /* ------------------- testes de borda ------------------- */

    public function test_create_without_a_required_key_renders_form_with_error(): void
    {
        $params = $this->validParams();
        unset($params['title']);

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['event' => $params]);

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression('/id="event_title"[^>]*is-invalid|is-invalid[^>]*id="event_title"/', $response);
        $this->assertStringContainsString('<div class="invalid-feedback">não pode ser vazio!</div>', $response);
    }

    public function test_create_with_negative_capacity_renders_form_with_error(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['capacity' => '-1'])]
        );

        $this->assertCount(0, Event::all());
        $this->assertStringContainsString('<div class="invalid-feedback">deve ser maior ou igual a 1!</div>', $response);
        $this->assertStringContainsString('value="-1"', $response);
    }

    public function test_create_with_non_numeric_capacity_renders_form_with_error(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['capacity' => 'abc'])]
        );

        $this->assertCount(0, Event::all());
        $this->assertStringContainsString('<div class="invalid-feedback">deve ser um número inteiro!</div>', $response);
    }

    public function test_create_with_array_in_a_text_field_does_not_break(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['title' => ['x'], 'capacity' => ['1']])]
        );

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('<div class="invalid-feedback">não pode ser vazio!</div>', $response);
    }

    public function test_create_with_event_param_as_string_renders_form(): void
    {
        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['event' => 'texto']);

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('Existem dados incorretos! Por favor, verifique!', $response);
    }
```

Por quê:

- Integração chama a ação com `Request` montado e lê o HTML: prova
  form -> `FIELDS` -> model -> view com erro embaixo do campo.
- `value="-1"`: o form volta com o que o usuário digitou.
- `event` como string e array em campo de texto: o controller
  (`EventsController.php` ~118-121) já trata, sem 500. O teste prova.

c) Ids forjados (mass assignment), logo abaixo:

```php
    public function test_create_ignores_id_sent_by_the_form(): void
    {
        $existing = $this->createEvent(['title' => 'Já Existente']);

        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['id' => (string) $existing->id])]
        );

        $this->assertCount(2, Event::all());
        $this->assertEquals('Já Existente', Event::findById($existing->id)->title);
    }

    public function test_update_ignores_user_id_and_id_sent_by_the_form(): void
    {
        $other = new User([
            'name' => 'Outro Admin',
            'email' => 'outro@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $other->save();
        $event = $this->createEvent();
        $another = $this->createEvent(['title' => 'Outro Evento']);

        $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams([
                'title' => 'Título Novo',
                'user_id' => (string) $other->id,
                'id' => (string) $another->id,
            ])]
        );

        $updated = Event::findById($event->id);
        $this->assertEquals('Título Novo', $updated->title);
        $this->assertEquals($this->admin->id, $updated->user_id);
        $this->assertEquals('Outro Evento', Event::findById($another->id)->title);
    }
```

Por quê:

- `FIELDS` no controller é allowlist: `id` e `user_id` do POST não
  chegam ao model. Confira **no banco**, não só pelo `Location`.
- "Edição não autorizada" na base = id/dono forjado e Participante
  (arquivo 3). Não há regra de dono entre admins (isso é Q1).

d) Update sem alteração e update parcial:

```php
    public function test_update_without_changes_redirects_to_show_and_keeps_the_record(): void
    {
        $event = $this->createEvent();
        $before = Event::findById($event->id);

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams()]
        );

        $after = Event::findById($event->id);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento atualizado com sucesso!', $_SESSION['flash']['success']);
        foreach (['title', 'description', 'kind', 'modality', 'audience', 'starts_on', 'location', 'capacity'] as $field) {
            $this->assertEquals($before->$field, $after->$field, $field);
        }
    }

    /**
     * Comportamento atual: o update copia TODOS os campos do form; o que não vier fica nulo e a validação recusa.
     */
    public function test_update_with_only_some_fields_is_rejected_and_keeps_the_record(): void
    {
        $event = $this->createEvent(['title' => 'Original']);

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => ['title' => 'Só o título']]
        );

        $this->assertEquals('Original', Event::findById($event->id)->title);
        $this->assertMatchesRegularExpression('/<h1>\s*Editar Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('Existem dados incorretos! Por favor, verifique!', $response);
    }
```

Por quê:

- Sem alteração: PRG normal (`Location` do `show` + flash) e todos os
  campos iguais. `test_update_with_valid_data_changes_event` só
  confere 3 campos; o `foreach` pega campo esquecido no `fill`.
- Parcial: o `fill` (`EventsController.php` ~116-130) copia todos os
  `FIELDS`; o que falta vira nulo e a validação recusa.

e) Id inexistente em `edit`/`update`/`destroy`:

```php
    /**
     * @return array<string, array{string}>
     */
    public static function actionsWithIdProvider(): array
    {
        return [
            'edit' => ['edit'],
            'update' => ['update'],
            'destroy' => ['destroy'],
        ];
    }

    #[DataProvider('actionsWithIdProvider')]
    public function test_action_with_unknown_id_redirects_to_index(string $action): void
    {
        $this->createEvent();

        $params = ['id' => 9999, 'event' => $this->validParams(['title' => 'Não deve gravar'])];
        $response = match ($action) {
            'edit' => $this->get(action: $action, controllerName: self::CONTROLLER, params: $params),
            'update' => $this->put(action: $action, controllerName: self::CONTROLLER, params: $params),
            default => $this->delete(action: $action, controllerName: self::CONTROLLER, params: $params),
        };

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
        $this->assertCount(1, Event::all());
        $this->assertNull(Event::findBy(['title' => 'Não deve gravar']));
    }
```

Por quê:

- Na base só o `show` tinha teste de id inexistente; `edit`, `update`
  e `destroy` (`EventsController.php` ~67-71, ~79-83, ~98-102) não.
- `match` escolhe o verbo; os três passam o mesmo `$params` (o `edit`
  ignora `event`, tudo bem).
- `$_SESSION['flash']` sobrevive entre testes; o `setUp` já zera, não
  remova.

## 3. `tests/Integration/Access/EventsAccessTest.php`

Depois de `test_events_route_should_redirect_with_method_override_without_session`
(linha ~136), antes de `test_participant_should_not_create_update_or_destroy_events`.
Provider `methodOverrideRoutes` e `use DataProvider` já existem.

```php
    #[DataProvider('methodOverrideRoutes')]
    public function test_events_route_should_redirect_participant_with_method_override(string $method, string $uri): void
    {
        $response = $this->loggedClient($this->participant)->post($uri, ['form_params' => ['_method' => $method]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));
    }

    public function test_participant_should_be_redirected_to_dashboard_even_for_an_unknown_event(): void
    {
        $response = $this->loggedClient($this->participant)->get('/admin/events/999999/edit');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));
    }

    public function test_participant_should_not_update_or_destroy_with_method_override(): void
    {
        $client = $this->loggedClient($this->participant);
        $uri = "/admin/events/{$this->event->id}";

        $client->post($uri, ['form_params' => ['_method' => 'PUT', 'event' => [
            'title' => 'Título Alterado',
            'description' => 'Descrição alterada.',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => ['students'],
            'starts_on' => date('Y-m-d', strtotime('+20 days')),
            'location' => 'Outro lugar',
            'capacity' => '50',
        ]]]);
        $client->post($uri, ['form_params' => ['_method' => 'DELETE']]);

        $event = Event::findById($this->event->id);
        $this->assertNotNull($event);
        $this->assertEquals('Palestra de Acesso', $event->title);
        $this->assertEquals(10, $event->capacity);
    }
```

Por quê:

- Teste de acesso sobe o servidor e bate por HTTP (Guzzle): passa pela
  rota e pelo middleware de admin, que o teste de controller pula.
- PUT/DELETE chegam como POST + `_method`. Faltava o caso com sessão
  de Participante (sem sessão já existia).
- Evento inexistente: middleware barra **antes** de procurar o Evento
  -> `/dashboard`, não "Evento não encontrado!".
- Último teste confere o banco depois: Evento intacto, não só 302.

## Fechamento

```sh
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

Esperado: Unit 181, Integration 61 (base 159 / 45). Schema não mudou,
sem `db:populate`.

Prova de que os testes testam algo: troque `1` por `0` no
`integerMin('capacity', ...)` de `Event::validates()`; os casos `'0'`
falham. Desfaça com `git checkout app/`.

Sem navegador: nada de view mudou.

Commit (inglês): `test: Add edge case tests for events`.

## Armadilhas (resumo)

- `integerMin` aceita `'10'`, `10`, `' 10 '` e `'+5'`; rejeita `'1.5'`,
  `'1e1'` e `'010'` (`filter_var` `FILTER_VALIDATE_INT`).
- `notEmpty` não faz `trim`: título só com espaços passa. Se pedirem
  correção, `trim` no model, não no controller.
- `capacity` > 2147483647 e título > 255: passam na validação e o
  `save()` lança `PDOException` (500 no form). Correção: M2.
- Mensagens exatas, com `!`.
- `errors()` guarda uma mensagem por campo, a última vence: confira
  mensagem exata e total de campos com erro.
- `createEvent` do `EventsControllerTest` (~289-300) ignora o retorno
  de `save()`: se `validParams()` ficar inválido, `id` nulo e falha
  longe da causa. Ponha `$this->assertTrue($event->save())`.
- Duplicidade só é recusada com M6; não autorizado entre admins só com
  Q1. Na base, teste Participante e ids forjados.
- `expectException` antes da linha que lança.
- Provider sem `@return array<string, array{...}>`: `phpstan` falha.
- Depois de M2/M5/M6, ajuste os testes que documentam o comportamento
  atual (título 256, duplicado, payload do PUT sem `link`).
