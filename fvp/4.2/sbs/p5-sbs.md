# P5 passo a passo: Status do Evento: cancelar e encerrar

Pedido típico: "o administrador pode cancelar um Evento; cancelado
aparece com destaque, não aceita inscrição nem edição. Acrescente
'encerrar'. Todo Evento novo nasce agendado". Chance: alta. Tempo:
25-35 min.

Exemplo pronto: `fvp/4.2/exemplos/p5-status-cancelar-encerrar/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo". Sem pré-requisito: sai de `main`.

Ordem = fluxo da requisição: banco -> model -> rota -> controller ->
views -> testes. Populate não muda (construtor dá o padrão). Na prova,
prioridade: funcionar no navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `database/schema.sql`
2. `app/Models/Event.php`
3. `config/routes.php`
4. `app/Controllers/Admin/EventsController.php`
5. `app/views/admin/events/index.phtml`
6. `app/views/admin/events/show.phtml`
7. `tests/Unit/Models/Events/EventTest.php`
8. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
9. `tests/Integration/Access/EventsAccessTest.php`
10. `tests/Acceptance/admin/events/CancelEventCest.php` (novo)

Depois: `./run db:populate`, suíte, `./run phpcs`.

## 1. `database/schema.sql`

Em `CREATE TABLE events`, logo após `capacity`:

```sql
    capacity    INT NOT NULL,
    -- Situação do Evento: agendado (padrão), cancelado ou encerrado.
    status      ENUM('scheduled', 'canceled', 'finished') NOT NULL DEFAULT 'scheduled',
```

Por quê:

- ActiveRecord não cria coluna. `save()` insere todas as chaves de
  `$columns`; coluna ausente -> `Unknown column`.
- `ENUM` restringe no banco; a lista igual vai pro model
  (`STATUSES` + `inclusion`).

Armadilha: o `DEFAULT 'scheduled'` nunca é usado: `save()` manda todas
as colunas, inclusive `NULL`, e o `NOT NULL` recusa. O padrão vai no
construtor do model (passo 2c). No fim, `./run db:populate`.

## 2. `app/Models/Event.php`

Seis pontos.

a) Docblock da classe, depois de `@property int|string $capacity`:

```php
 * @property string $status um dos valores de STATUSES; muda só por cancel() e finish()
```

Por quê: atributo dinâmico (`__get`/`__set`); sem `@property` o
`phpstan` reclama.

b) Constante, depois de `AUDIENCES` e antes de `$table`; e `'status'`
no fim de `$columns`:

```php
    /** Valores do ENUM `status`, com o rótulo exibido na listagem e no show. */
    public const STATUSES = [
        'scheduled' => 'Agendado',
        'canceled' => 'Cancelado',
        'finished' => 'Encerrado',
    ];
```

```php
        'capacity',
        'status',
    ];
```

Por quê:

- Chave = valor do banco (inglês); valor = rótulo da tela (pt-BR).
  Mesmo padrão de `KINDS`/`MODALITIES`.
- `$columns` monta `INSERT`/`UPDATE`; sem ele `status` nunca vai ao
  banco.

c) Construtor, logo depois de `$columns` (cópia do de
`app/Models/User.php`, que faz o mesmo com `role`):

```php
    /**
     * Model::save() grava todas as colunas, então o DEFAULT 'scheduled' do schema não vale:
     * um Evento novo já nasce com status 'scheduled' no objeto.
     *
     * @param array<string, mixed> $params
     */
    public function __construct($params = [])
    {
        parent::__construct($params);

        if ($this->status === null) {
            $this->status = 'scheduled';
        }
    }
```

Por quê:

- Padrão no objeto: populate, testes e Cests continuam sem passar
  `status`.
- `parent::__construct` primeiro: é ele que preenche os atributos.

Armadilha: `$this->status ??= 'scheduled'` não funciona. `Model` não
define `__isset`; `??` vê sempre "não definido" e sobrescreve o valor
vindo do banco. Use `=== null`.

d) `validates()`, no fim do método, depois do bloco `if` de `capacity`:

```php
        if (Validations::notEmpty('capacity', $this) && Validations::integerMin('capacity', $this, 1)) {
            $this->validatesCapacityAgainstEnrollments();
        }

        Validations::notEmpty('status', $this) &&
            Validations::inclusion('status', $this, array_keys(self::STATUSES));
    }
```

Por quê: validação no model. `&&` = curto-circuito (não duplica erro).
`inclusion` com `array_keys` = mesma lista do `ENUM`.

e) Logo depois de `validates()`, consultas e rótulo:

```php
    public function isCanceled(): bool
    {
        return $this->status === 'canceled';
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
```

f) Em seguida, as transições:

```php
    /**
     * Cancela o Evento. Só um Evento agendado pode ser cancelado; senão retorna false
     * e registra o motivo em errors('base'). Usa save(), então passa por validates().
     */
    public function cancel(): bool
    {
        if ($this->status !== 'scheduled') {
            $this->addError('base', 'só um evento agendado pode ser cancelado!');
            return false;
        }

        $this->status = 'canceled';

        return $this->save();
    }

    /**
     * Encerra o Evento manualmente (variante "encerrar"). Mesma regra de cancel().
     */
    public function finish(): bool
    {
        if ($this->status !== 'scheduled') {
            $this->addError('base', 'só um evento agendado pode ser encerrado!');
            return false;
        }

        $this->status = 'finished';

        return $this->save();
    }
```

Por quê:

- Regra de transição no model; controller só pergunta e escolhe a
  flash. Mesma forma do `safeDestroy()`.
- Motivo em `errors('base')`: erro do objeto, não de um campo.
- `statusLabel()` tira o lookup da view (view burra).

Armadilha: `cancel()` chama `save()`, que roda `validates()` inteiro:
Evento com capacidade abaixo das inscrições ou data inválida não
cancela. Se o professor quiser cancelar mesmo assim, use
`$this->update(['status' => 'canceled'])` (não valida) e explique.

## 3. `config/routes.php`

Grupo `admin`, depois de `admin.events.destroy`:

```php
        Route::delete('/admin/events/{id}', [AdminEventsController::class, 'destroy'])
            ->name('admin.events.destroy');
        Route::put('/admin/events/{id}/cancel', [AdminEventsController::class, 'cancel'])
            ->name('admin.events.cancel');
        Route::put('/admin/events/{id}/finish', [AdminEventsController::class, 'finish'])
            ->name('admin.events.finish');
```

Por quê:

- Grupo `admin`: middleware barra Participante (-> `/dashboard`) e sem
  sessão (-> `/login`) antes do controller.
- `PUT`: altera recurso existente (RESTful). No HTML vai como `POST` +
  `_method=PUT`, igual ao `update`.
- Ordem não importa (`GET /admin/events/{id}` só casa `GET`), mas
  mantenha junto das outras.

## 4. `app/Controllers/Admin/EventsController.php`

a) `edit`, depois do bloco `eventNotFound()`, antes de
`$title = 'Editar Evento';`:

```php
        if ($event->isCanceled()) {
            $this->canceledEventNotEditable($event);
            return;
        }
```

b) `update`, mesmo bloco, logo depois do `eventNotFound()` e antes de
`$this->fill($event, $request);`:

```php
        if ($event->isCanceled()) {
            $this->canceledEventNotEditable($event);
            return;
        }
        $this->fill($event, $request);
```

c) Ações novas, depois de `destroy` (antes do docblock de `fill`):

```php
    public function cancel(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        if ($event->cancel()) {
            FlashMessage::success('Evento cancelado!');
        } else {
            FlashMessage::danger($event->errors('base') ?? 'Não foi possível cancelar o evento!');
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }

    public function finish(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        if ($event->finish()) {
            FlashMessage::success('Evento encerrado!');
        } else {
            FlashMessage::danger($event->errors('base') ?? 'Não foi possível encerrar o evento!');
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }
```

d) Helper privado, depois de `findEvent` e antes de `eventNotFound`:

```php
    private function canceledEventNotEditable(Event $event): void
    {
        FlashMessage::danger('Evento cancelado não pode ser editado!');
        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }
```

Por quê:

- Molde: `destroy` (ação curta com flash) + `findEvent`/`eventNotFound`.
- PRG: sempre `redirectTo` para o `show`.
- Bloqueio em `edit` **e** `update`: só esconder o link não basta, o
  `PUT` pode vir direto.
- Early return igual ao `eventNotFound()`.

Armadilha: `status` **não** entra em `FIELDS`. Com ele lá (ou num
`<select>` do form), qualquer admin "descancela" pela edição. Só as
ações mudam o status (há teste).

## 5. `app/views/admin/events/index.phtml`

a) Troque o `<td>` do título por um com badge:

```php
                    <td>
                        <?= htmlspecialchars($event->title) ?>
                        <span class="badge text-bg-secondary"><?= htmlspecialchars($event->statusLabel()) ?></span>
                    </td>
```

b) Na coluna de ações, depois do `</form>` de "Remover":

```php
                        <?php if ($event->status === 'scheduled') : ?>
                            <form class="d-inline"
                                  action="<?= route('admin.events.cancel', ['id' => $event->id]) ?>" method="post">
                                <input type="hidden" name="_method" value="PUT">
                                <button type="submit" class="btn btn-sm btn-outline-warning">Cancelar Evento</button>
                            </form>
                        <?php endif; ?>
```

Por quê:

- View só exibe; rótulo vem de `statusLabel()`.
- Botão de ação que muda dado = form `post`, nunca link `GET`.
- "Cancelar Evento", não "Cancelar": o form já tem um link "Cancelar"
  (voltar sem salvar).

Armadilha: sem o `<input type="hidden" name="_method" value="PUT">` o
navegador manda `POST`, não casa a rota `PUT` e cai no 404.

## 6. `app/views/admin/events/show.phtml`

a) Dentro do `<dl>`, depois do `</dd>` de vagas:

```php
    <dt class="col-sm-3">Situação</dt>
    <dd class="col-sm-9">
        <span id="event_status" class="badge text-bg-secondary"><?= htmlspecialchars($event->statusLabel()) ?></span>
    </dd>
```

b) Depois do link "Editar", antes de "Voltar":

```php
<?php if ($event->status === 'scheduled') : ?>
    <form class="d-inline" action="<?= route('admin.events.cancel', ['id' => $event->id]) ?>" method="post">
        <input type="hidden" name="_method" value="PUT">
        <button type="submit" class="btn btn-outline-warning">Cancelar Evento</button>
    </form>
    <form class="d-inline" action="<?= route('admin.events.finish', ['id' => $event->id]) ?>" method="post">
        <input type="hidden" name="_method" value="PUT">
        <button type="submit" class="btn btn-outline-secondary">Encerrar Evento</button>
    </form>
<?php endif; ?>
```

Por quê: `id="event_status"` dá seletor estável pro Cest. Botões só em
agendado (o model recusaria de qualquer jeito).

## 7. `tests/Unit/Models/Events/EventTest.php`

Depois de `test_audience_should_survive_a_round_trip_in_the_database`
(antes do bloco `save / update / destroy / busca`). Usa `params()`,
`createEvent()` e `enroll()` que já existem:

```php
    /* ------------------- status (cancelar / encerrar) ------------------- */

    public function test_new_event_should_be_scheduled(): void
    {
        $event = new Event($this->params());

        $this->assertEquals('scheduled', $event->status);
        $this->assertEquals('Agendado', $event->statusLabel());

        $this->assertTrue($event->save());
        $this->assertEquals('scheduled', Event::findById($event->id)->status);
    }

    public function test_status_should_accept_every_enum_value(): void
    {
        foreach (array_keys(Event::STATUSES) as $status) {
            $this->assertTrue((new Event($this->params(['status' => $status])))->isValid(), $status);
        }
    }

    public function test_status_outside_enum_should_be_invalid(): void
    {
        $event = new Event($this->params(['status' => 'postponed']));

        $this->assertFalse($event->isValid());
        $this->assertEquals('não é uma opção válida!', $event->errors('status'));
    }

    public function test_cancel_should_cancel_a_scheduled_event(): void
    {
        $event = $this->createEvent();

        $this->assertTrue($event->cancel());

        $this->assertTrue($event->isCanceled());
        $this->assertEquals('Cancelado', $event->statusLabel());
        $this->assertEquals('canceled', Event::findById($event->id)->status);
    }

    public function test_cancel_should_fail_for_an_already_canceled_event(): void
    {
        $event = $this->createEvent(['status' => 'canceled']);

        $this->assertFalse($event->cancel());
        $this->assertEquals('só um evento agendado pode ser cancelado!', $event->errors('base'));
    }

    public function test_cancel_should_fail_for_a_finished_event(): void
    {
        $event = $this->createEvent(['status' => 'finished']);

        $this->assertFalse($event->cancel());
        $this->assertEquals('só um evento agendado pode ser cancelado!', $event->errors('base'));
        $this->assertEquals('finished', Event::findById($event->id)->status);
    }

    public function test_finish_should_finish_a_scheduled_event(): void
    {
        $event = $this->createEvent();

        $this->assertTrue($event->finish());

        $this->assertTrue($event->isFinished());
        $this->assertEquals('finished', Event::findById($event->id)->status);
    }

    public function test_finish_should_fail_for_a_canceled_event(): void
    {
        $event = $this->createEvent(['status' => 'canceled']);

        $this->assertFalse($event->finish());
        $this->assertEquals('só um evento agendado pode ser encerrado!', $event->errors('base'));
        $this->assertEquals('canceled', Event::findById($event->id)->status);
    }

    public function test_cancel_should_not_save_an_invalid_event(): void
    {
        $event = $this->createEvent(['capacity' => 5]);
        $this->enroll($event, 3);
        $event->capacity = 2;

        $this->assertFalse($event->cancel());
        $this->assertEquals('scheduled', Event::findById($event->id)->status);
    }
```

Por quê:

- Padrão no construtor, `inclusion`, transições válidas e inválidas.
- Último teste documenta a armadilha: `cancel()` passa por
  `validates()`.
- Mensagem de `inclusion` vem de `lib/Validations.php`; copie exata.

## 8. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois do último teste de `destroy` (antes do docblock de
`validParams()`). Usa `createEvent()`, `validParams()`, `put()`/`get()`
do `ControllerTestCase`:

```php
    /* ------------------- status ------------------- */

    public function test_index_and_show_display_the_status_badge(): void
    {
        $event = $this->createEvent(['title' => 'Evento Cancelado', 'status' => 'canceled']);
        $this->createEvent(['title' => 'Evento Agendado']);

        $index = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $show = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertMatchesRegularExpression('/Evento Cancelado\s*<span class="badge text-bg-secondary">Cancelado/', $index);
        $this->assertMatchesRegularExpression('/Evento Agendado\s*<span class="badge text-bg-secondary">Agendado/', $index);
        $this->assertStringNotContainsString("/admin/events/{$event->id}/cancel", $index);
        $this->assertStringContainsString('Situação', $show);
        $this->assertMatchesRegularExpression('/id="event_status"[^>]*>Cancelado</', $show);
        $this->assertStringNotContainsString('Cancelar Evento', $show);
    }

    public function test_scheduled_event_has_cancel_and_finish_buttons(): void
    {
        $event = $this->createEvent();

        $index = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $show = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString("action=\"/admin/events/{$event->id}/cancel\" method=\"post\"", $index);
        $this->assertStringContainsString('Cancelar Evento', $index);
        $this->assertStringContainsString("action=\"/admin/events/{$event->id}/cancel\" method=\"post\"", $show);
        $this->assertStringContainsString("action=\"/admin/events/{$event->id}/finish\" method=\"post\"", $show);
        $this->assertStringContainsString('name="_method" value="PUT"', $show);
        $this->assertStringContainsString('Encerrar Evento', $show);
    }

    public function test_cancel_twice_is_rejected(): void
    {
        $event = $this->createEvent();
        $this->put(action: 'cancel', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $response = $this->put(action: 'cancel', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertEquals('canceled', Event::findById($event->id)->status);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('só um evento agendado pode ser cancelado!', $_SESSION['flash']['danger']);
    }

    public function test_cancel_cancels_the_event(): void
    {
        $event = $this->createEvent();

        $response = $this->put(action: 'cancel', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertEquals('canceled', Event::findById($event->id)->status);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento cancelado!', $_SESSION['flash']['success']);
    }

    public function test_cancel_of_a_finished_event_is_rejected(): void
    {
        $event = $this->createEvent(['status' => 'finished']);

        $response = $this->put(action: 'cancel', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertEquals('finished', Event::findById($event->id)->status);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('só um evento agendado pode ser cancelado!', $_SESSION['flash']['danger']);
    }

    public function test_cancel_with_unknown_id_redirects_to_index(): void
    {
        $response = $this->put(action: 'cancel', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_finish_finishes_the_event(): void
    {
        $event = $this->createEvent();

        $response = $this->put(action: 'finish', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertEquals('finished', Event::findById($event->id)->status);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento encerrado!', $_SESSION['flash']['success']);
    }

    public function test_edit_of_a_canceled_event_redirects_to_show(): void
    {
        $event = $this->createEvent(['status' => 'canceled']);

        $response = $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento cancelado não pode ser editado!', $_SESSION['flash']['danger']);
    }

    public function test_update_of_a_canceled_event_keeps_the_record(): void
    {
        $event = $this->createEvent(['title' => 'Original', 'status' => 'canceled']);

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Mudou'])]
        );

        $this->assertEquals('Original', Event::findById($event->id)->title);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento cancelado não pode ser editado!', $_SESSION['flash']['danger']);
    }

    public function test_update_ignores_status_sent_by_the_form(): void
    {
        $event = $this->createEvent();

        $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['status' => 'canceled'])]
        );

        $this->assertEquals('scheduled', Event::findById($event->id)->status);
    }
```

Por quê:

- `put(...)` chama a ação direto; `redirectTo` vira `Location: ...`.
  Comparação exata (texto da flash também).
- Último teste prova que `status` fora de `FIELDS` é ignorado no form.

## 9. `tests/Integration/Access/EventsAccessTest.php`

a) Docblock de `eventRoutes()`:

```php
     * As 10 rotas admin.events.* (index, paginate, new, create, show, edit, update, destroy, cancel, finish).
```

b) Em `eventRoutes()` **e** em `methodOverrideRoutes()`, depois de
`'admin.events.destroy' => [...]`:

```php
            'admin.events.cancel' => ['PUT', '/admin/events/1/cancel'],
            'admin.events.finish' => ['PUT', '/admin/events/1/finish'],
```

c) Testes novos, depois do teste que tenta criar/alterar/remover como
Participante (termina com `'Palestra de Acesso'`):

```php
    public function test_participant_should_not_cancel_or_finish_events(): void
    {
        $client = $this->loggedClient($this->participant);

        $client->post("/admin/events/{$this->event->id}/cancel", ['form_params' => ['_method' => 'PUT']]);
        $client->post("/admin/events/{$this->event->id}/finish", ['form_params' => ['_method' => 'PUT']]);

        $this->assertEquals('scheduled', Event::findById($this->event->id)->status);
    }

    public function test_admin_should_cancel_an_event(): void
    {
        $response = $this->loggedClient($this->admin)
            ->post("/admin/events/{$this->event->id}/cancel", ['form_params' => ['_method' => 'PUT']]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/admin/events/{$this->event->id}", $response->getHeaderLine('Location'));
        $this->assertEquals('canceled', Event::findById($this->event->id)->status);
    }
```

Por quê:

- Os providers alimentam os testes já existentes: sem sessão ->
  `/login`, Participante -> `/dashboard`. Rota nova entra nos dois.
- `methodOverrideRoutes()`: o navegador manda `POST` + `_method`;
  prova o caminho real.

## 10. `tests/Acceptance/admin/events/CancelEventCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Situação do Evento (Agendado, Cancelado, Encerrado), com cancelar pela listagem ou pelo show e encerrar pelo show.
 */
class CancelEventCest extends BaseEventsCest
{
    public function cancelScheduledEvent(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Cancelar um Evento agendado');
        $page->amOnPage('/admin/events');
        $page->see('Agendado', $this->rowOf('Evento 02'));
        $page->click('Ver', $this->rowOf('Evento 02'));
        $page->see('Agendado', '#event_status');

        $page->click('Cancelar Evento');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cancelado!');
        $page->see('Cancelado', '#event_status');
        $page->dontSee('Cancelar Evento');
        $page->dontSee('Encerrar Evento');
        Assert::assertSame('canceled', Event::findById((int) $event->id)?->status);

        $page->amOnPage('/admin/events');
        $page->see('Cancelado', $this->rowOf('Evento 02'));
        $page->see('Agendado', $this->rowOf('Evento 03'));
    }

    public function cancelEventFromTheListing(AcceptanceTester $page): void
    {
        $event = $this->events[4];

        $page->wantTo('Cancelar um Evento pela listagem');
        $page->amOnPage('/admin/events');
        $page->click('Cancelar Evento', $this->rowOf('Evento 04'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cancelado!');
        $page->see('Cancelado', '#event_status');
    }

    public function canceledEventCannotBeEdited(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $event->cancel();

        $page->wantTo('Evento cancelado não aceita edição');
        $page->amOnPage("/admin/events/{$event->id}/edit");

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cancelado não pode ser editado!');
        $page->dontSee('Editar Evento', 'h1');
    }

    public function finishScheduledEvent(AcceptanceTester $page): void
    {
        $event = $this->events[3];

        $page->wantTo('Encerrar um Evento agendado');
        $page->amOnPage("/admin/events/{$event->id}");

        $page->click('Encerrar Evento');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento encerrado!');
        $page->see('Encerrado', '#event_status');
        Assert::assertSame('finished', Event::findById((int) $event->id)?->status);
    }
}
```

Por quê: navegador real (Selenium). `rowOf(...)` (do `BaseEventsCest`)
limita o clique à linha certa da tabela. Cobre show, listagem, bloqueio
de edição e encerrar.

## Fechamento

```sh
./run db:populate
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

No navegador (admin): listagem mostra badge "Agendado"; "Cancelar
Evento" num Evento sem inscrições -> "Evento cancelado!", badge
"Cancelado", botões somem; abrir `/admin/events/{id}/edit` dele ->
volta ao show com "Evento cancelado não pode ser editado!". "Encerrar
Evento" noutro -> "Encerrado".

Commit (inglês): `feat: Add Event status with cancel and finish actions`.

## Armadilhas (resumo)

- Confiar no `DEFAULT` do schema: `save()` manda `NULL`; padrão no
  construtor.
- `??=` no construtor sobrescreve o valor do banco (sem `__isset`);
  use `=== null`.
- `status` em `FIELDS` ou no form: admin "descancela" pela edição.
- Esquecer `_method=PUT` no form: 404.
- `cancel()` sem checar `scheduled` deixa cancelar encerrado.
- `cancel()` passa por `validates()`: Evento inválido não cancela;
  alternativa `update(['status' => 'canceled'])`.
- `finished` por data: `isFinished()` comparando `starts_on <
  date('Y-m-d')` sem gravar, ou tarefa `./run` que atualiza.
- Com P2 aplicado: `Enrollment::validates()` deve recusar inscrição em
  cancelado (`addError('base', 'evento cancelado!')`). Não está no
  exemplo.
- Flash e `Location` comparados por texto exato nos testes.
