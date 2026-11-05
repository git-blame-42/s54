# P2 passo a passo: Inscrição do Participante (`POST /events/{id}/enrollments`)

Pedido típico: "o Participante logado se inscreve em um Evento, se
houver vaga e se ainda não estiver inscrito; botão 'Inscrever-se' no
detalhe do Evento e flash com sucesso ou motivo da recusa". Chance:
média-alta. Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p2-inscricao-participante/` (patch,
bundle e árvore `projeto/`). Este arquivo é o mesmo conteúdo em formato
"um arquivo por vez, com o motivo".

Pré-requisito: O2 (`exemplos/o2-eventos-para-participante`, commit
`feat: Add events listing and details for participants`). O patch traz
os 2 commits (O2 + P2); aqui só o do P2. O O2 cria
`app/Controllers/EventsController.php`, `app/views/events/show.phtml`,
as rotas `events.index`/`events.show` e os testes de Participante que
este cenário estende (guia: `sbs/o2-sbs.md`).

Ordem = fluxo da requisição: model -> rota -> controller -> view ->
testes. Schema não muda (`event_user` com `UNIQUE (event_id, user_id)`
já existe). Na prova, prioridade: funcionar no navegador, depois
testar, depois `phpcs`.

## Arquivos, em ordem

1. `app/Models/Enrollment.php`
2. `app/Models/User.php`
3. `config/routes.php`
4. `app/Controllers/EnrollmentsController.php` (novo)
5. `app/views/events/show.phtml`
6. `tests/Unit/Models/Events/EnrollmentTest.php`
7. `tests/Unit/Models/Users/UserTest.php`
8. `tests/Integration/Controllers/EnrollmentsControllerTest.php` (novo)
9. `tests/Integration/Controllers/EventsControllerTest.php`
10. `tests/Integration/Access/ParticipantEventsAccessTest.php`
11. `tests/Acceptance/events/BaseParticipantEventsCest.php`
12. `tests/Acceptance/events/ParticipantEnrollCest.php` (novo)

Depois: `./run composer dump-autoload`, suíte, `./run phpcs`.

## 1. `app/Models/Enrollment.php`

a) Import, depois de `use Core\Database\ActiveRecord\Model;`:

```php
use Lib\Validations;
```

b) Depois de `protected static array $columns = ['event_id', 'user_id'];`
e antes de `public function event(): BelongsTo`:

```php
    public function validates(): void
    {
        if (!Validations::notEmpty('event_id', $this) || !Validations::notEmpty('user_id', $this)) {
            return;
        }

        if (!Validations::uniqueness(['event_id', 'user_id'], $this)) {
            $this->addError('base', 'você já está inscrito neste evento!');
            return;
        }

        $this->validatesAvailableSpots();
    }

    /**
     * Só ao criar: o Evento precisa existir e ter vaga (availableSpots() > 0).
     */
    private function validatesAvailableSpots(): void
    {
        if (!$this->newRecord()) {
            return;
        }

        $event = Event::findById((int) $this->event_id);

        if ($event === null) {
            $this->addError('event_id', 'evento não encontrado!');
        } elseif ($event->availableSpots() <= 0) {
            $this->addError('base', 'não há vagas neste evento!');
        }
    }
```

Por quê:

- Regra de negócio mora no model. `save()` chama `isValid()` ->
  `validates()`; populate, testes e controller passam pela mesma regra.
- `uniqueness` aceita array: checa o par `event_id` + `user_id`. Vira
  mensagem pro usuário em vez de `PDOException` (500) do `UNIQUE`.
- `uniqueness` grava erro em `event_id`/`user_id` ("já existe um
  registro..."); a flash lê `base`, por isso o `addError('base', ...)`.
- Vagas só em `newRecord()`: depois de gravada, a própria Inscrição já
  conta em `availableSpots()`.
- `Event` está no mesmo namespace (`App\Models`): sem `use`.

Armadilha: corrida do mesmo usuário (dois POSTs juntos) passa os dois
pela validação; o `UNIQUE` lança `PDOException` no segundo. Se pedirem,
`try/catch (PDOException)` + `addError('base', ...)` como no
`safeDestroy()` de `Event`.

## 2. `app/Models/User.php`

Depois do método `events()` (Eventos criados pelo admin):

```php
    /**
     * Inscrições deste usuário (Participante) em Eventos.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'user_id');
    }

    public function isEnrolledIn(Event $event): bool
    {
        if ($this->newRecord() || $event->newRecord()) {
            return false;
        }

        return Enrollment::exists(['event_id' => $event->id, 'user_id' => $this->id]);
    }
```

Por quê:

- `hasMany` = relação 1xN do framework, cópia de `events()`.
  `events()` continua sendo "criados por"; não reaproveite.
- `isEnrolledIn` deixa a view perguntar "inscrito?" sem SQL fora do
  model (view burra).
- `exists([...])` com duas condições usa `where`, que junta várias com `AND`.
- Guarda de `newRecord()`: sem `id` não há o que consultar.

## 3. `config/routes.php`

a) Import, depois de `use App\Controllers\DashboardController;`:

```php
use App\Controllers\EnrollmentsController;
```

b) No grupo `auth`, depois de `events.show` e antes do grupo `admin`:

```php
    Route::get('/events/{id}', [EventsController::class, 'show'])->name('events.show');

    // Inscrição do usuário logado no Evento.
    Route::post('/events/{id}/enrollments', [EnrollmentsController::class, 'create'])
        ->name('events.enrollments.create');
```

Por quê:

- Grupo `auth`: middleware barra sem sessão (302 `/login`) antes do
  controller.
- RESTful: recurso aninhado `enrollments` + `create` (P3 faz o
  `destroy`). `/events/{id}/enroll` funciona, mas é verbo na URI e a
  rubrica critica.
- `POST` porque cria recurso; sem `_method`.

## 4. `app/Controllers/EnrollmentsController.php` (novo)

```php
<?php

namespace App\Controllers;

use App\Models\Enrollment;
use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Inscrição do usuário logado em um Evento (área do Participante).
 */
class EnrollmentsController extends Controller
{
    public function create(Request $request): void
    {
        $event = Event::findById((int) $request->getParam('id'));
        if ($event === null) {
            FlashMessage::danger('Evento não encontrado!');
            $this->redirectTo(route('events.index'));
            return;
        }

        $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $this->currentUser()?->id]);

        if ($enrollment->save()) {
            FlashMessage::success('Inscrição realizada com sucesso!');
        } else {
            FlashMessage::danger($enrollment->errors('base') ?? 'Não foi possível realizar a inscrição!');
        }

        $this->redirectTo(route('events.show', ['id' => $event->id]));
    }
}
```

Por quê:

- `{id}` da rota chega por `$request->getParam('id')`.
- `user_id` sempre de `currentUser()`, nunca do form: senão o usuário
  inscreve outro (mass assignment). Há teste para isso.
- Controller só pergunta ao model (`save()`) e escolhe a flash; motivo
  da recusa vem de `errors('base')`.
- PRG: sempre `redirectTo` para `events.show` (O2), nunca `render`
  depois de POST.
- Classe nova: namespace = pasta (PSR-4); `./run composer
  dump-autoload` se não achar.

Armadilha: `redirectTo` sem `return` no "não encontrado": no teste de
controller ele só ecoa `Location:` e o método seguiria.

## 5. `app/views/events/show.phtml`

a) Dentro do `<dd>` de vagas, depois de
`(<?= $event->availableSpots() ?> disponíveis)`:

```php
        <?php if ($event->availableSpots() === 0) : ?>
            <span class="badge text-bg-danger">Lotado</span>
        <?php endif; ?>
```

b) Depois do `</dl>`, antes do link "Voltar":

```php
<?php if ($this->currentUser()?->isEnrolledIn($event)) : ?>
    <p class="text-success">Você está inscrito neste evento.</p>
<?php else : ?>
    <form class="d-inline" action="<?= route('events.enrollments.create', ['id' => $event->id]) ?>" method="post">
        <button type="submit" class="btn btn-primary">Inscrever-se</button>
    </form>
<?php endif; ?>
<a class="btn btn-secondary" href="<?= route('events.index') ?>">Voltar</a>
```

Por quê:

- View só exibe; a pergunta "inscrito?" é do model (`isEnrolledIn`).
- `route('events.enrollments.create', [...])`: URL pelo nome da rota,
  não à mão.
- Botão aparece mesmo lotado: quem decide é o model, a flash explica
  ("não há vagas"). Facilita P9 (lista de espera).
- Form `method="post"`: link `GET` não casa a rota.

## 6. `tests/Unit/Models/Events/EnrollmentTest.php`

Troque `test_the_same_participant_cannot_enroll_twice_in_an_event`
(antes esperava `PDOException`) e acrescente os testes abaixo. `User`,
`PDOException` já estão importados.

```php
    public function test_the_same_participant_cannot_enroll_twice_in_an_event(): void
    {
        $params = ['event_id' => $this->event->id, 'user_id' => $this->participant->id];
        $this->assertTrue((new Enrollment($params))->save());

        $duplicate = new Enrollment($params);

        $this->assertFalse($duplicate->save());
        $this->assertEquals('você já está inscrito neste evento!', $duplicate->errors('base'));
        $this->assertCount(1, Enrollment::all());
    }

    public function test_the_unique_index_still_blocks_duplicates_without_validation(): void
    {
        $params = ['event_id' => $this->event->id, 'user_id' => $this->participant->id];
        (new Enrollment($params))->save();

        $this->expectException(PDOException::class);

        $duplicate = new class ($params) extends Enrollment {
            public function validates(): void
            {
            }
        };
        $duplicate->save();
    }

    public function test_enrollment_is_rejected_when_the_event_is_full(): void
    {
        $this->event->update(['capacity' => 1]);
        $other = $this->createParticipant('outro@example.com');
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $other->id]))->save();

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('não há vagas neste evento!', $enrollment->errors('base'));
        $this->assertCount(1, Enrollment::all());
    }

    public function test_enrollment_takes_the_last_available_spot(): void
    {
        $this->event->update(['capacity' => 1]);

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $this->assertSame(0, $this->event->availableSpots());
    }

    public function test_enrollment_requires_an_existing_event_and_a_user(): void
    {
        $unknownEvent = new Enrollment(['event_id' => 9999, 'user_id' => $this->participant->id]);
        $withoutUser = new Enrollment(['event_id' => $this->event->id, 'user_id' => null]);

        $this->assertFalse($unknownEvent->save());
        $this->assertEquals('evento não encontrado!', $unknownEvent->errors('event_id'));
        $this->assertFalse($withoutUser->save());
        $this->assertNotNull($withoutUser->errors('user_id'));
        $this->assertCount(0, Enrollment::all());
    }

    private function createParticipant(string $email): User
    {
        $user = new User([
            'name' => 'Outro Participante',
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $user->save();

        return $user;
    }
```

Por quê:

- Com a validação, a duplicata não chega ao banco: `save()` falso, não
  exceção. O teste antigo passa a falhar; atualize.
- Classe anônima com `validates()` vazio prova que o `UNIQUE` do schema
  continua como última barreira.
- `update(['capacity' => 1])` grava sem validar: monta o lotado rápido.
- Cobertura: duplicata, lotado, última vaga, Evento inexistente, sem
  `user_id`.

## 7. `tests/Unit/Models/Users/UserTest.php`

a) Imports, antes de `use App\Models\User;`:

```php
use App\Models\Enrollment;
use App\Models\Event;
```

b) Teste novo, no fim da classe (depois de
`test_update_should_not_change_the_password`):

```php
    public function test_enrollments_and_is_enrolled_in_should_reflect_the_user_enrollments(): void
    {
        $event = new Event([
            'user_id' => $this->user->id,
            'title' => 'Oficina',
            'description' => 'Descrição',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => 5,
        ]);
        $event->save();
        (new Enrollment(['event_id' => $event->id, 'user_id' => $this->user2->id]))->save();

        $this->assertCount(1, $this->user2->enrollments()->get());
        $this->assertCount(0, $this->user->enrollments()->get());
        $this->assertTrue($this->user2->isEnrolledIn($event));
        $this->assertFalse($this->user->isEnrolledIn($event));
        $this->assertFalse($this->user2->isEnrolledIn(new Event()));
    }
```

Por quê: método novo no model = teste unitário. `enrollments()->get()`
executa o `HasMany`; `new Event()` cobre a guarda de `newRecord()`.

## 8. `tests/Integration/Controllers/EnrollmentsControllerTest.php` (novo)

```php
<?php

namespace Tests\Integration\Controllers;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;

/**
 * Inscrição do Participante logado (POST /events/{id}/enrollments).
 */
class EnrollmentsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\EnrollmentsController';

    private User $admin;
    private User $participant;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Participante', 'participante@example.com', 'participant');
        Auth::login($this->participant);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_create_enrolls_the_logged_user(): void
    {
        $event = $this->createEvent(['capacity' => 2]);

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame("Location: /events/{$event->id}", $response);
        $this->assertEquals('Inscrição realizada com sucesso!', $_SESSION['flash']['success']);
        $this->assertTrue($this->participant->isEnrolledIn($event));
        $this->assertSame(1, $event->availableSpots());
    }

    public function test_create_ignores_user_id_sent_by_the_form(): void
    {
        $event = $this->createEvent();

        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'user_id' => $this->admin->id]
        );

        $this->assertTrue($this->participant->isEnrolledIn($event));
        $this->assertFalse($this->admin->isEnrolledIn($event));
    }

    public function test_create_twice_keeps_a_single_enrollment(): void
    {
        $event = $this->createEvent();
        $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame("Location: /events/{$event->id}", $response);
        $this->assertEquals(
            'você já está inscrito neste evento!',
            $_SESSION['flash']['danger']
        );
        $this->assertCount(1, Enrollment::where(['event_id' => $event->id]));
    }

    public function test_create_in_a_full_event_is_rejected(): void
    {
        $event = $this->createEvent(['capacity' => 1]);
        $other = $this->createUser('Outro', 'outro@example.com', 'participant');
        (new Enrollment(['event_id' => $event->id, 'user_id' => $other->id]))->save();

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertSame("Location: /events/{$event->id}", $response);
        $this->assertEquals(
            'não há vagas neste evento!',
            $_SESSION['flash']['danger']
        );
        $this->assertFalse($this->participant->isEnrolledIn($event));
    }

    public function test_create_with_unknown_event_redirects_to_index(): void
    {
        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
        $this->assertCount(0, Enrollment::all());
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = []): Event
    {
        $event = new Event(array_merge([
            'user_id' => $this->admin->id,
            'title' => 'Oficina de PHP',
            'description' => 'Oficina prática.',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Laboratório B203',
            'capacity' => 20,
        ], $overrides));
        $event->save();

        return $event;
    }
}
```

Por quê:

- `ControllerTestCase` chama a ação direto; `redirectTo` vira o texto
  `Location: ...` devolvido. Compara exato.
- `Auth::login($this->participant)` no `setUp`: `currentUser()` precisa
  de sessão.
- Teste do `user_id` no form prova a allowlist (só `currentUser()`).

Armadilha: zerar `$_SESSION['flash'] = []` no meio de um teste faz o
PHPStan inferir `array{}` e acusar `offsetAccess.notFound` depois.
Zere só no `setUp`.

## 9. `tests/Integration/Controllers/EventsControllerTest.php`

Teste do O2 (área do Participante, `App\Controllers\EventsController`).
Depois de `test_show_renders_event_without_admin_actions`:

```php
    public function test_show_offers_enroll_button_when_not_enrolled(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString("action=\"/events/{$event->id}/enrollments\"", $response);
        $this->assertStringContainsString('Inscrever-se', $response);
        $this->assertStringNotContainsString('Você está inscrito neste evento.', $response);
    }

    public function test_show_tells_when_already_enrolled(): void
    {
        $event = $this->createEvent();
        (new Enrollment(['event_id' => $event->id, 'user_id' => $this->participant->id]))->save();

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('Você está inscrito neste evento.', $response);
        $this->assertStringNotContainsString('Inscrever-se', $response);
    }

    public function test_show_marks_full_event(): void
    {
        $full = $this->createEvent(['capacity' => 1]);
        (new Enrollment(['event_id' => $full->id, 'user_id' => $this->admin->id]))->save();
        $open = $this->createEvent(['capacity' => 2]);

        $fullPage = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $full->id]);
        $openPage = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $open->id]);

        $this->assertStringContainsString('Lotado', $fullPage);
        $this->assertStringNotContainsString('Lotado', $openPage);
    }
```

Por quê: prova os três ramos da view (botão, "inscrito", badge
"Lotado") lendo o HTML renderizado.

## 10. `tests/Integration/Access/ParticipantEventsAccessTest.php`

a) Import, antes de `use App\Models\Event;`:

```php
use App\Models\Enrollment;
```

b) No fim da classe, depois de
`test_unknown_event_should_redirect_participant_to_events`:

```php
    public function test_enroll_should_redirect_to_login_without_session(): void
    {
        $response = $this->newClient()->post("/events/{$this->event->id}/enrollments");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_participant_should_enroll_and_return_to_the_event(): void
    {
        $response = $this->loggedClient($this->participant)->post("/events/{$this->event->id}/enrollments");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/events/{$this->event->id}", $response->getHeaderLine('Location'));
        $this->assertTrue($this->participant->isEnrolledIn($this->event));
    }
```

Por quê: teste de acesso sobe o servidor e bate por HTTP (Guzzle).
Prova o middleware `auth` (sem sessão -> `/login`) e a rota real.

## 11. `tests/Acceptance/events/BaseParticipantEventsCest.php`

a) Import, depois de `namespace`:

```php
use App\Models\User;
```

b) Depois do `_before`:

```php
    /**
     * Troca a sessão para um Participante novo, sem nenhuma Inscrição.
     */
    protected function loginAsNewParticipant(AcceptanceTester $page, string $email): User
    {
        $user = $this->createUser('Participante Novo', $email, 'participant');

        $page->amOnPage('/logout');
        $page->login($email, self::PASSWORD);

        return $user;
    }
```

Por quê: o Participante 1 do seed já está inscrito no Evento 01
(lotado) e receberia "já está inscrito". Para ver "não há vagas" precisa
de alguém sem Inscrição. `createUser` vem do `BaseEventsCest`.

## 12. `tests/Acceptance/events/ParticipantEnrollCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class ParticipantEnrollCest extends BaseParticipantEventsCest
{
    public function enrollInEventWithSpots(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Inscrição do Participante em Evento com vagas');
        $page->amOnPage("/events/{$event->id}");
        $page->see('0/22');

        $page->click('Inscrever-se');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Inscrição realizada com sucesso!');
        $page->see('1/22');
        $page->see('Você está inscrito neste evento.');
        $page->dontSee('Inscrever-se');
        Assert::assertTrue($this->participants[1]->isEnrolledIn($event));
    }

    public function alreadyEnrolledParticipantSeesNoButton(AcceptanceTester $page): void
    {
        $page->wantTo('Participante já inscrito não vê o botão de inscrição');
        $page->amOnPage("/events/{$this->events[3]->id}");

        $page->see('Você está inscrito neste evento.');
        $page->dontSee('Inscrever-se');
    }

    public function enrollInFullEventIsRejected(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        $user = $this->loginAsNewParticipant($page, 'novo@example.com');

        $page->wantTo('Inscrição recusada em Evento lotado');
        $page->amOnPage("/events/{$event->id}");
        $page->see('3/3');
        $page->see('Lotado');

        $page->click('Inscrever-se');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('não há vagas neste evento!');
        $page->see('3/3');
        Assert::assertFalse($user->isEnrolledIn($event));
        Assert::assertCount(3, Enrollment::where(['event_id' => $event->id]));
    }
}
```

Por quê: navegador real (Selenium). Três caminhos: com vaga, já
inscrito (Evento 03 do seed), lotado (Evento 01, 3/3).

## Fechamento

```sh
./run composer dump-autoload -o --strict-psr   # classe nova
./run test tests/Unit/Models/Events/EnrollmentTest.php
./run test tests/Integration/Controllers/EnrollmentsControllerTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
./run db:populate       # populate passa pela validação nova
```

Schema não muda. No navegador: `fernanda@example.com` / `123456`,
"Eventos", "Ver" no Evento 2, "Inscrever-se" -> "Inscrição realizada
com sucesso!". De novo: botão some, "Você está inscrito". Evento 1
(3/3, "Lotado") -> "não há vagas neste evento!". `user@example.com` já
está no 1 e no 2 (veria "já está inscrito").

Commit (inglês): `feat: Allow participants to enroll in events`.

## Armadilhas (resumo)

- `user_id` vindo do form: nunca; sempre `currentUser()->id`.
- `EnrollmentTest` antigo espera `PDOException` na duplicata; com a
  validação passa a falhar. Atualize.
- `uniqueness` marca erro em `event_id`/`user_id`; flash lê `base`:
  `addError('base', ...)` explícito.
- Vagas validadas na edição contam a própria Inscrição: só em
  `newRecord()`.
- Concorrência: vaga a mais por corrida é aceitável, saiba dizer;
  duplicata do mesmo usuário em corrida -> `PDOException` do `UNIQUE`
  (trate como `safeDestroy`, se pedirem).
- `enrollmentsCount()`/`safeDestroy()` já contam Inscrições: Evento
  com inscritos continua não removível.
- Verbo na URI (`/enroll`) funciona, mas rubrica cobra RESTful
  (`enrollments` + `create`).
- `redirectTo` sem `return` no "não encontrado" segue executando no
  teste.
- Admin também consegue se inscrever (rota só `auth`); decida com o
  professor.
