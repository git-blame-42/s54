# P6 passo a passo: Duplicar Evento

Pedido típico: "um botão 'Duplicar' na listagem cria uma cópia do
Evento (título com '(cópia)', data daqui a 7 dias, sem inscrições) e
abre a edição da cópia". Chance: média-alta. Tempo: 20-30 min.

Exemplo pronto: `fvp/4.2/exemplos/p6-duplicar-evento/` (patch, bundle
e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato "um
arquivo por vez, com o motivo". Sem pré-requisito: sai de `main`. Os
testes unitários de `duplicate()` ficam no S3
(`exemplos/s3-teste-unitario-model`, guia `sbs/s3-sbs.md`, empilhado
sobre este); aqui só controller, acesso e aceitação.

Ordem = fluxo da requisição: model -> rota -> controller -> view ->
testes. Schema e populate não mudam. Na prova, prioridade: funcionar no
navegador, depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `app/Models/Event.php`
2. `config/routes.php`
3. `app/Controllers/Admin/EventsController.php`
4. `app/views/admin/events/index.phtml`
5. `tests/Integration/Controllers/Admin/EventsControllerTest.php`
6. `tests/Integration/Access/EventsAccessTest.php`
7. `tests/Acceptance/admin/events/DuplicateEventCest.php` (novo)

Depois: suíte, `./run phpcs`.

## 1. `app/Models/Event.php`

Depois de `validatesCapacityAgainstEnrollments()` e antes de
`public function user(): BelongsTo`:

```php
    /**
     * Cópia do Evento, ainda não salva (o controller chama save()). Copia todas as colunas,
     * acrescenta " (cópia)" ao título e marca a data para daqui a 7 dias. Inscrições não são
     * copiadas: ficam em event_user, e a cópia nasce sem nenhuma.
     */
    public function duplicate(): Event
    {
        $copy = new Event();

        foreach (static::$columns as $column) {
            $copy->$column = $this->$column;
        }

        // title é VARCHAR(255) e " (cópia)" tem 8 caracteres.
        $copy->title = mb_substr($this->title, 0, 247) . ' (cópia)';
        $copy->starts_on = date('Y-m-d', strtotime('+7 days'));

        return $copy;
    }
```

Por quê:

- Cópia é regra do model; controller só salva e escolhe a flash.
- Devolve **sem salvar**: controller decide o que fazer se `save()`
  falhar.
- `new Event()`, não `clone $this`: o clone leva o `id`, continua
  "existente" (`newRecord()` falso) e `save()` faria `UPDATE` no
  original.
- `$attributes` é privado em `Model`: copia via `__get`/`__set`
  (`$this->$column`), coluna a coluna de `$columns`. `id` não está em
  `$columns`, então não vai.
- Inscrições moram em `event_user`: a cópia nasce sem nenhuma, sem
  código extra.
- `user_id` do original (vem no `foreach`). Com Q1, use o admin logado.

Armadilhas:

- Título de 255 + " (cópia)" estoura `VARCHAR(255)`: `mb_substr(...,
  0, 247)` (multibyte por causa do "ó").
- Copiar `starts_on` de Evento passado: a validação "não pode estar no
  passado" recusa a cópia. Por isso `+7 days`.
- Com P1: `$copy->checkin_code = null;` (senão `uniqueness` barra).
  Com P5: `$copy->status = 'scheduled';`.

## 2. `config/routes.php`

Grupo `admin`, depois de `admin.events.destroy`:

```php
        Route::delete('/admin/events/{id}', [AdminEventsController::class, 'destroy'])
            ->name('admin.events.destroy');
        Route::post('/admin/events/{id}/duplicate', [AdminEventsController::class, 'duplicate'])
            ->name('admin.events.duplicate');
```

Por quê:

- Grupo `admin`: middleware barra Participante e sem sessão antes do
  controller.
- `POST` porque cria recurso; sem `_method`. Sufixo `/duplicate` após
  `{id}`: mesma receita de rota com sufixo (O1).

## 3. `app/Controllers/Admin/EventsController.php`

Ação nova depois de `destroy` (antes do docblock de `fill`):

```php
    public function duplicate(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $copy = $event->duplicate();

        if ($copy->save()) {
            FlashMessage::success('Evento duplicado! Revise os dados.');
            $this->redirectTo(route('admin.events.edit', ['id' => $copy->id]));
        } else {
            FlashMessage::danger('Não foi possível duplicar o evento!');
            $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
        }
    }
```

Por quê:

- Molde: `create` (salva e redireciona) + `findEvent`/`eventNotFound`
  com early return.
- Depois do `save()`, `$copy->id` existe: redirect pra edição da
  cópia.
- PRG: redirect nos dois ramos, nunca `render` depois de POST.
- Não passa por `FIELDS`/`fill`: nada vem do form, só o `{id}`.

Armadilha: redirecionar para `edit` sem flash deixa o admin sem saber
que já está na cópia, não no original.

## 4. `app/views/admin/events/index.phtml`

Na coluna de ações, depois do `</form>` de "Remover":

```php
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remover</button>
                        </form>
                        <form class="d-inline"
                              action="<?= route('admin.events.duplicate', ['id' => $event->id]) ?>" method="post">
                            <button type="submit" class="btn btn-sm btn-outline-dark">Duplicar</button>
                        </form>
```

Por quê:

- Ação que cria dado = form `post`, não link.
- URL pelo nome da rota (`route(...)`).

Armadilha: esquecer `method="post"`: link/`GET` não casa a rota.

## 5. `tests/Integration/Controllers/Admin/EventsControllerTest.php`

Depois do último teste de `destroy` (antes do docblock de
`validParams()`). Usa `createEvent()` e `enroll()` que já existem:

```php
    /* ------------------- duplicar ------------------- */

    public function test_index_has_a_duplicate_button(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString("action=\"/admin/events/{$event->id}/duplicate\" method=\"post\"", $response);
        $this->assertStringContainsString('Duplicar', $response);
    }

    public function test_duplicate_creates_a_copy_and_redirects_to_its_edit_form(): void
    {
        $event = $this->createEvent(['title' => 'Oficina Original', 'audience' => ['students', 'staff']]);
        $this->enroll($event);

        $response = $this->post(action: 'duplicate', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertCount(2, Event::all());
        $copy = Event::findBy(['title' => 'Oficina Original (cópia)']);
        $this->assertNotNull($copy);
        $this->assertNotEquals($event->id, $copy->id);
        $this->assertEquals(['students', 'staff'], $copy->audienceList());
        $this->assertEquals(date('Y-m-d', strtotime('+7 days')), $copy->starts_on);
        $this->assertSame(0, $copy->enrollmentsCount());
        $this->assertSame("Location: /admin/events/{$copy->id}/edit", $response);
        $this->assertEquals('Evento duplicado! Revise os dados.', $_SESSION['flash']['success']);
    }

    public function test_duplicate_keeps_the_original_unchanged(): void
    {
        $event = $this->createEvent(['title' => 'Oficina Original']);
        $this->enroll($event);

        $this->post(action: 'duplicate', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $original = Event::findById($event->id);
        $this->assertEquals('Oficina Original', $original->title);
        $this->assertEquals($event->starts_on, $original->starts_on);
        $this->assertSame(1, $original->enrollmentsCount());
    }

    public function test_duplicate_with_unknown_id_redirects_to_index(): void
    {
        $response = $this->post(action: 'duplicate', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
        $this->assertCount(0, Event::all());
    }
```

Por quê:

- `post(...)` chama a ação direto; `redirectTo` vira `Location: ...`.
- `test_duplicate_keeps_the_original_unchanged` pega o erro do
  `clone` (que faria `UPDATE` no original).
- `audienceList()` prova que o `foreach` copiou o `SET`; Inscrição no
  original prova que a cópia nasce com 0.

## 6. `tests/Integration/Access/EventsAccessTest.php`

a) Docblock de `eventRoutes()`:

```php
     * As 9 rotas admin.events.* (index, paginate, new, create, show, edit, update, destroy, duplicate).
```

b) Em `eventRoutes()` (só nele; **não** em `methodOverrideRoutes()`,
que é para `PUT`/`DELETE` via `_method`), depois de
`'admin.events.destroy' => [...]`:

```php
            'admin.events.duplicate' => ['POST', '/admin/events/1/duplicate'],
```

c) Testes novos, depois do teste que tenta criar/alterar/remover como
Participante (termina com `'Palestra de Acesso'`):

```php
    public function test_participant_should_not_duplicate_events(): void
    {
        $this->loggedClient($this->participant)->post("/admin/events/{$this->event->id}/duplicate");

        $this->assertCount(1, Event::all());
    }

    public function test_admin_should_duplicate_an_event(): void
    {
        $response = $this->loggedClient($this->admin)->post("/admin/events/{$this->event->id}/duplicate");

        $copy = Event::findBy(['title' => 'Palestra de Acesso (cópia)']);
        $this->assertNotNull($copy);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/admin/events/{$copy->id}/edit", $response->getHeaderLine('Location'));
    }
```

Por quê: `eventRoutes()` alimenta os testes existentes (sem sessão ->
`/login`, Participante -> `/dashboard`). Os dois novos batem por HTTP
real e provam o middleware `admin`.

## 7. `tests/Acceptance/admin/events/DuplicateEventCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Duplicar um Evento pela listagem abre a edição da cópia.
 */
class DuplicateEventCest extends BaseEventsCest
{
    public function duplicateEventOpensTheCopyForEditing(AcceptanceTester $page): void
    {
        $original = $this->events[1];

        $page->wantTo('Duplicar um Evento');
        $page->amOnPage('/admin/events');
        $page->click('Duplicar', $this->rowOf('Evento 01'));

        $copy = Event::findBy(['title' => 'Evento 01 (cópia)']);
        Assert::assertNotNull($copy);
        $page->seeCurrentUrlEquals("/admin/events/{$copy->id}/edit");
        $page->see('Evento duplicado! Revise os dados.');
        $page->see('Editar Evento', 'h1');
        $page->seeInField('#event_title', 'Evento 01 (cópia)');
        $page->seeInField('#event_starts_on', date('Y-m-d', strtotime('+7 days')));
        $page->seeInField('#event_location', $original->location);

        // A cópia nasce sem Inscrições; o original (lotado) continua com as 3.
        Assert::assertSame(0, $copy->enrollmentsCount());
        Assert::assertSame(3, $original->enrollmentsCount());
        Assert::assertCount(self::TOTAL_EVENTS + 1, Event::all());

        $page->amOnPage('/admin/events/page/4');
        $page->see('Evento 01 (cópia)', 'table');
        $page->see('0/3', $this->rowOf('Evento 01 (cópia)'));
    }
}
```

Por quê: navegador real (Selenium). Duplica o Evento 01 (lotado 3/3),
cai na edição com campos preenchidos, cópia com 0/3 e listagem com 17
(`TOTAL_EVENTS + 1`, vem do `BaseEventsCest`).

## Fechamento

```sh
./run test tests/Integration/Controllers/Admin/EventsControllerTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
```

Schema e populate não mudam. No navegador (admin): "Duplicar" numa
linha -> edição aberta com "(cópia)" no título, data daqui a 7 dias e
flash "Evento duplicado! Revise os dados."; listagem com 17 Eventos; a
cópia com 0 inscritos.

Commit (inglês): `feat: Duplicate an Event from the listing`.

## Armadilhas (resumo)

- `clone $this`: copia `id`, `newRecord()` falso, `save()` vira
  `UPDATE` do original. Use `new Event()`.
- `$attributes` é privado em `Model`: copie via `$this->$column`.
- Título 255 + " (cópia)" estoura `VARCHAR(255)`: `mb_substr(..., 0,
  247)`.
- Copiar `starts_on` de Evento passado: validação recusa. `+7 days`.
- `user_id`: mantenha o original, salvo pedido contrário (com Q1, o
  logado).
- Redirect para `edit` sem flash: usuário não sabe que foi criado.
- Form sem `method="post"`: não casa a rota.
- Rota POST entra em `eventRoutes()`, não em `methodOverrideRoutes()`.
