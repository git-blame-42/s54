# P13 passo a passo: Admin inscreve e remove um Participante

Pedido típico: "no detalhe do Evento (área admin), o administrador
escolhe um Participante num select e o inscreve; a lista de inscritos
tem botão para remover. O `user_id` vem do form: valide no model
(existe, é Participante, há vaga, não duplica)". Chance: média-alta.
Tempo: 30-40 min.

Exemplo pronto: `fvp/4.2/exemplos/p13-admin-inscreve-remove-participante/`
(patch, bundle e árvore `projeto/`). Um commit, sobre `main`. Sem
pré-requisito de outro cenário (com P2 na pilha, junte os
`validates()`; ver Armadilhas).

Ordem = fluxo da requisição: model -> rota -> controller -> view ->
testes. Schema não muda. Na prova, prioridade: funcionar no navegador,
depois testar, depois `phpcs`.

## Arquivos, em ordem

1. `app/Models/Enrollment.php`
2. `app/Models/Event.php`
3. `config/routes.php`
4. `app/Controllers/Admin/EnrollmentsController.php` (novo)
5. `app/views/admin/events/show.phtml`
6. `tests/Unit/Models/Events/EnrollmentTest.php`
7. `tests/Unit/Models/Events/EventTest.php`
8. `tests/Integration/Controllers/Admin/EnrollmentsControllerTest.php` (novo)
9. `tests/Integration/Access/EventsAccessTest.php`
10. `tests/Acceptance/admin/events/ManageEnrollmentsCest.php` (novo)

Depois: suíte, `./run phpcs`, `./run phpstan`, `./run db:populate`.

## 1. `app/Models/Enrollment.php`

a) `use`, depois de `use Core\Database\ActiveRecord\Model;`:

```php
use Lib\Validations;
```

b) Depois de `protected static array $columns = ['event_id', 'user_id'];`
e antes de `event()`:

```php
    /**
     * Regras da Inscrição: o usuário existe e é Participante, o Evento existe e tem vaga
     * e o Participante ainda não está inscrito nele. O user_id pode vir de um formulário, então
     * é conferido aqui, e não no controller.
     */
    public function validates(): void
    {
        if (!Validations::notEmpty('event_id', $this) || !Validations::notEmpty('user_id', $this)) {
            return;
        }

        if (!$this->newRecord()) {
            return;
        }

        if (!$this->validatesParticipant()) {
            return;
        }

        $event = $this->validatesEvent();
        if ($event === null) {
            return;
        }

        if (self::exists(['event_id' => $event->id, 'user_id' => $this->user_id])) {
            $this->addError('base', 'o participante já está inscrito neste evento!');
            return;
        }

        if ($event->availableSpots() <= 0) {
            $this->addError('base', 'não há vagas neste evento!');
        }
    }

    /**
     * O user_id precisa ser um número inteiro de um usuário existente com perfil Participante.
     */
    private function validatesParticipant(): bool
    {
        $id = filter_var((string) $this->user_id, FILTER_VALIDATE_INT);
        $user = $id === false ? null : User::findById($id);

        if ($user === null) {
            $this->addError('base', 'o usuário informado não existe!');
            return false;
        }

        if ($user->role !== 'participant') {
            $this->addError('base', 'somente Participantes podem ser inscritos!');
            return false;
        }

        return true;
    }

    private function validatesEvent(): ?Event
    {
        $id = filter_var((string) $this->event_id, FILTER_VALIDATE_INT);
        $event = $id === false ? null : Event::findById($id);

        if ($event === null) {
            $this->addError('base', 'o evento informado não existe!');
        }

        return $event;
    }
```

Por quê:

- `user_id` vem do form: exceção à regra "nunca do form". Toda regra
  que depende dele mora no model; `save()` chama `validates()` e não
  grava com erro.
- Early return: para no primeiro erro, uma mensagem só.
- `newRecord()`: regras só ao criar (Inscrição não é editada).
- `filter_var(..., FILTER_VALIDATE_INT)`, não `(int)`: `'1abc'`
  viraria 1 e inscreveria o usuário 1.
- `(string) $this->user_id`: com `@property int $user_id` o `phpstan`
  acha o `filter_var` "sempre falso" sem o cast.
- `self::exists([...])` com duas chaves: duplicata vira erro de
  validação, não `PDOException` do `UNIQUE`.
- `availableSpots()` (`Event`, base): a regra de vagas já existe;
  reaproveite.
- Erros em `base`: a tela mostra na flash, não embaixo de um campo.

## 2. `app/Models/Event.php`

a) `use`, depois de `use Core\Database\ActiveRecord\BelongsTo;`:

```php
use Core\Database\ActiveRecord\HasMany;
```

b) Depois do método `user(): BelongsTo` (o dono), antes de
`enrollmentsCount()`:

```php
    /**
     * Inscrições (linhas em event_user) deste Evento.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'event_id');
    }

    /**
     * Participantes que ainda não estão inscritos neste Evento (opções do select do show).
     *
     * @return array<User>
     */
    public function enrollableParticipants(): array
    {
        $enrolled = [];
        foreach ($this->enrollments()->get() as $enrollment) {
            if ($enrollment instanceof Enrollment) {
                $enrolled[] = (int) $enrollment->user_id;
            }
        }

        return array_values(array_filter(
            User::where(['role' => 'participant']),
            fn(User $user) => !in_array((int) $user->id, $enrolled, true)
        ));
    }
```

Por quê:

- `enrollments()` (`HasMany`), não `participants()`: a lista precisa do
  **id da Inscrição** para o botão remover.
- `enrollableParticipants()`: consulta no model, view só itera. Select
  sem admin e sem quem já está inscrito.
- `instanceof Enrollment`: `get()` devolve `array<Model>`; o `phpstan`
  precisa do tipo para ler `user_id`.
- Select filtrado é UX; a validação do model continua barrando POST
  feito na mão.

## 3. `config/routes.php`

a) `use`, junto dos outros controllers admin:

```php
use App\Controllers\Admin\EnrollmentsController as AdminEnrollmentsController;
```

b) No grupo `admin`, depois de `admin.events.destroy`:

```php
        Route::delete('/admin/events/{id}', [AdminEventsController::class, 'destroy'])
            ->name('admin.events.destroy');

        // Inscrições do Evento (recurso aninhado): o Administrador inscreve e remove Participantes pelo show.
        Route::post('/admin/events/{event_id}/enrollments', [AdminEnrollmentsController::class, 'create'])
            ->name('admin.events.enrollments.create');
        Route::delete('/admin/events/{event_id}/enrollments/{id}', [AdminEnrollmentsController::class, 'destroy'])
            ->name('admin.events.enrollments.destroy');
```

Por quê:

- Recurso aninhado RESTful (`create`/`destroy`); verbo na URI
  (`/enroll`) a rubrica não aceita.
- Grupo `admin`: middleware barra Participante.
- `as AdminEnrollmentsController`: pode existir um
  `EnrollmentsController` do Participante (P2, se aplicado).
- `{event_id}`, não `{eventId}`: `core/Router/Route.php:90` só aceita
  `[a-z,_]`. Com maiúscula vira texto literal e a rota nunca casa, sem
  erro.
- Parâmetros da rota entram por último no `Request` (`array_merge`):
  `event_id`/`id` no corpo do POST não ganham da URL.

## 4. `app/Controllers/Admin/EnrollmentsController.php` (novo)

```php
<?php

namespace App\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

/**
 * Inscrições de um Evento gerenciadas pelo Administrador a partir do show do Evento.
 */
class EnrollmentsController extends Controller
{
    public function create(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        $params = $request->getParam('enrollment', []);
        $userId = is_array($params) && is_scalar($params['user_id'] ?? null) ? (string) $params['user_id'] : null;

        // As regras (usuário existe, é Participante, há vaga, sem duplicidade) ficam em Enrollment::validates().
        $enrollment = new Enrollment(['event_id' => $event->id, 'user_id' => $userId]);

        if ($enrollment->save()) {
            FlashMessage::success('Participante inscrito com sucesso!');
        } else {
            FlashMessage::danger($enrollment->errors('base') ?? 'Selecione um Participante!');
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }

    public function destroy(Request $request): void
    {
        $event = $this->findEvent($request);
        if ($event === null) {
            $this->eventNotFound();
            return;
        }

        // Busca pelo id da Inscrição E pelo Evento da rota: não remove Inscrição de outro Evento.
        $id = $this->routeId($request, 'id');
        $enrollment = $id === null ? null : Enrollment::findBy(['id' => $id, 'event_id' => $event->id]);

        if ($enrollment !== null && $enrollment->destroy()) {
            FlashMessage::success('Inscrição removida com sucesso!');
        } else {
            FlashMessage::danger('Inscrição não encontrada!');
        }

        $this->redirectTo(route('admin.events.show', ['id' => $event->id]));
    }

    private function findEvent(Request $request): ?Event
    {
        $id = $this->routeId($request, 'event_id');

        return $id === null ? null : Event::findById($id);
    }

    /**
     * Id vindo da rota só se for formado apenas por dígitos: '1abc' não vira 1, como faria o (int).
     */
    private function routeId(Request $request, string $key): ?int
    {
        $value = $request->getParam($key);
        $digits = is_scalar($value) ? (string) $value : '';

        return ctype_digit($digits) ? (int) $digits : null;
    }

    private function eventNotFound(): void
    {
        FlashMessage::danger('Evento não encontrado!');
        $this->redirectTo(route('admin.events.index'));
    }
}
```

Por quê:

- Pasta/namespace `Admin`: mesmo padrão do `Admin\EventsController`.
- `event_id` sai da **rota**; o do form é ignorado (teste cobre).
- Controller só filtra a forma do payload (`enrollment[user_id]`
  escalar ou `null`) e mostra a mensagem. Regra nenhuma aqui.
- Select vazio: `notEmpty('user_id')` falha, `errors('base')` é `null`
  -> "Selecione um Participante!".
- PRG: sempre `redirectTo` para o `show`, sucesso ou erro (flash).
- `findBy(['id' => ..., 'event_id' => ...])`: sem o `event_id`, trocar o
  número na URL remove Inscrição de outro Evento.
- `routeId()` com `ctype_digit`: `.../enrollments/1abc` não remove a
  Inscrição 1. `ctype_digit` só recebe string (com `int` é depreciado
  no PHP 8.1), daí o `(string)`; no teste de controller os ids chegam
  como `int`.
- `return` depois do `eventNotFound()`: no teste de controller o
  `redirectTo` só imprime `Location:` e o método seguiria.

## 5. `app/views/admin/events/show.phtml`

Depois do `</dl>` (fim do bloco "Vagas") e antes dos botões
"Editar"/"Voltar":

```php
<?php
$enrollments = $event->enrollments()->get();
$enrollable = $event->enrollableParticipants();
?>
<h2 class="h5">Inscritos</h2>
<?php if (empty($enrollments)) : ?>
    <p>Nenhuma inscrição.</p>
<?php else : ?>
    <table class="table table-sm" id="enrollments">
        <thead>
            <tr>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($enrollments as $enrollment) : ?>
                <?php $participant = $enrollment->user()->get(); ?>
                <tr>
                    <td><?= htmlspecialchars($participant->name) ?></td>
                    <td><?= htmlspecialchars($participant->email) ?></td>
                    <td>
                        <form class="d-inline" method="post"
                              action="<?= route('admin.events.enrollments.destroy', [
                                  'event_id' => $event->id,
                                  'id' => $enrollment->id,
                              ]) ?>">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="submit" class="btn btn-sm btn-outline-danger">Remover</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (empty($enrollable)) : ?>
    <p>Todos os Participantes já estão inscritos.</p>
<?php else : ?>
    <form class="row g-2 align-items-end mb-4" method="post"
          action="<?= route('admin.events.enrollments.create', ['event_id' => $event->id]) ?>">
        <div class="col-auto">
            <label for="enrollment_user_id" class="form-label">Inscrever Participante</label>
            <select class="form-select" id="enrollment_user_id" name="enrollment[user_id]">
                <option value="">Selecione...</option>
                <?php foreach ($enrollable as $user) : ?>
                    <?php $label = "{$user->name} ({$user->email})"; ?>
                    <option value="<?= (int) $user->id ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-success">Inscrever</button>
        </div>
    </form>
<?php endif; ?>
```

Por quê:

- View burra: só chama métodos do model (`enrollments()`,
  `enrollableParticipants()`, `user()` do `Enrollment`). Nada de SQL.
- `_method=DELETE`: HTML só tem GET/POST; o framework troca o verbo.
  Sem ele, POST em `.../enrollments/{id}` dá 404.
- `route(..., ['event_id' => ..., 'id' => ...])`: chaves = nomes dos
  parâmetros da rota.
- `name="enrollment[user_id]"`: chega como array `enrollment`; o
  controller lê só `user_id`.
- `<option>` numa linha só (`Nome (e-mail)`): o `selectOption` do
  Selenium compara texto exato.
- `htmlspecialchars` em nome/e-mail (XSS; teste cobre `<b>`); `(int)`
  no id.
- Uma seção "Inscritos" só: se O3 estiver aplicado, fique com esta
  (tem o botão remover).

## 6. `tests/Unit/Models/Events/EnrollmentTest.php`

a) `use`, depois de `use PDOException;`:

```php
use PHPUnit\Framework\Attributes\DataProvider;
```

b) Substitua o corpo de
`test_the_same_participant_cannot_enroll_twice_in_an_event` (antes
esperava `PDOException`) e acrescente os testes novos e o helper no
fim da classe:

```php
    public function test_the_same_participant_cannot_enroll_twice_in_an_event(): void
    {
        $params = ['event_id' => $this->event->id, 'user_id' => $this->participant->id];
        $this->assertTrue((new Enrollment($params))->save());

        $duplicate = new Enrollment($params);

        $this->assertFalse($duplicate->save());
        $this->assertEquals('o participante já está inscrito neste evento!', $duplicate->errors('base'));
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

    public function test_enrollment_should_be_rejected_when_the_event_is_full(): void
    {
        $this->event->update(['capacity' => 1]);
        $other = $this->createParticipant('outro@example.com');
        $this->assertTrue((new Enrollment(['event_id' => $this->event->id, 'user_id' => $other->id]))->save());

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('não há vagas neste evento!', $enrollment->errors('base'));
        $this->assertCount(1, Enrollment::all());
    }

    public function test_enrollment_should_take_the_last_available_spot(): void
    {
        $this->event->update(['capacity' => 1]);

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);

        $this->assertTrue($enrollment->save());
        $this->assertSame(0, $this->event->availableSpots());
    }

    public function test_only_participants_can_be_enrolled(): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('somente Participantes podem ser inscritos!', $enrollment->errors('base'));
        $this->assertCount(0, Enrollment::all());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidUserIds(): array
    {
        return [
            'inexistente' => [9999],
            'texto' => ['abc'],
            'número com sufixo' => ['1abc'],
        ];
    }

    #[DataProvider('invalidUserIds')]
    public function test_user_must_exist(mixed $userId): void
    {
        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $userId]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('o usuário informado não existe!', $enrollment->errors('base'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_event_must_exist(): void
    {
        $enrollment = new Enrollment(['event_id' => 9999, 'user_id' => $this->participant->id]);

        $this->assertFalse($enrollment->save());
        $this->assertEquals('o evento informado não existe!', $enrollment->errors('base'));
    }

    public function test_event_and_user_are_required(): void
    {
        $withoutUser = new Enrollment(['event_id' => $this->event->id, 'user_id' => '']);
        $withoutEvent = new Enrollment(['event_id' => null, 'user_id' => $this->participant->id]);

        $this->assertFalse($withoutUser->save());
        $this->assertEquals('não pode ser vazio!', $withoutUser->errors('user_id'));
        $this->assertNull($withoutUser->errors('base'));
        $this->assertFalse($withoutEvent->save());
        $this->assertEquals('não pode ser vazio!', $withoutEvent->errors('event_id'));
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

- Teste antigo da duplicata esperava `PDOException`; com a validação
  `save()` só devolve `false`. Troque.
- `UNIQUE` do banco continua testado: classe anônima com `validates()`
  vazio pula a validação e bate no índice.
- Um teste por regra: vazio, usuário (inexistente, `'abc'`, `'1abc'`),
  admin, Evento inexistente, duplicata, lotado, última vaga.
- `createParticipant` sem `role`: o default do `User` é Participante.

## 7. `tests/Unit/Models/Events/EventTest.php`

Depois do último teste de `availableSpots` (`assertEquals(3,
$event->availableSpots())`), antes do bloco `starts_on`:

```php
    public function test_enrollments_should_return_only_the_events_enrollments(): void
    {
        $event = $this->createEvent();
        $other = $this->createEvent(['title' => 'Outro evento']);
        $this->enroll($event, 2);
        $this->enroll($other, 1);

        $enrollments = $event->enrollments()->get();

        $this->assertCount(2, $enrollments);
        foreach ($enrollments as $enrollment) {
            $this->assertInstanceOf(Enrollment::class, $enrollment);
            $this->assertEquals($event->id, $enrollment->event_id);
        }
    }

    public function test_enrollable_participants_should_skip_admins_and_enrolled_users(): void
    {
        $event = $this->createEvent();
        $enrolled = $this->createUser('Inscrita', 'inscrita@example.com');
        $free = $this->createUser('Livre', 'livre@example.com');
        (new Enrollment(['event_id' => $event->id, 'user_id' => $enrolled->id]))->save();

        $participants = $event->enrollableParticipants();

        $this->assertCount(1, $participants);
        $this->assertEquals($free->id, $participants[0]->id);
    }
```

Por quê: cobre os dois métodos novos do `Event`. `enroll()` e
`createUser()` já são helpers do `EventTest`.

## 8. `tests/Integration/Controllers/Admin/EnrollmentsControllerTest.php` (novo)

```php
<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class EnrollmentsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\Admin\EnrollmentsController';
    private const EVENTS_CONTROLLER = 'App\Controllers\Admin\EventsController';

    private User $admin;
    private User $participant;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $this->admin = $this->createUser('Admin', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Ana Souza', 'ana@example.com');
        $this->event = $this->createEvent('Oficina de PHP', 5);

        Auth::login($this->admin);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_create_enrolls_the_selected_participant(): void
    {
        $response = $this->createEnrollment($this->event->id, $this->participant->id);

        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Participante inscrito com sucesso!', $this->flash('success'));
        $this->assertTrue(Enrollment::exists(['event_id' => $this->event->id, 'user_id' => $this->participant->id]));
    }

    public function test_create_ignores_event_id_sent_by_the_form(): void
    {
        $other = $this->createEvent('Outro Evento', 5);

        $this->post(action: 'create', controllerName: self::CONTROLLER, params: [
            'enrollment' => ['user_id' => $this->participant->id, 'event_id' => $other->id],
            'event_id' => $this->event->id,
        ]);

        $this->assertSame(1, $this->event->enrollmentsCount());
        $this->assertSame(0, $other->enrollmentsCount());
    }

    public function test_create_twice_does_not_duplicate(): void
    {
        $this->createEnrollment($this->event->id, $this->participant->id);
        $this->clearFlash();

        $response = $this->createEnrollment($this->event->id, $this->participant->id);

        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('o participante já está inscrito neste evento!', $this->flash('danger'));
        $this->assertSame(1, $this->event->enrollmentsCount());
    }

    public function test_create_rejects_admin_user(): void
    {
        $this->createEnrollment($this->event->id, $this->admin->id);

        $this->assertEquals('somente Participantes podem ser inscritos!', $this->flash('danger'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_create_rejects_unknown_user(): void
    {
        $this->createEnrollment($this->event->id, 9999);

        $this->assertEquals('o usuário informado não existe!', $this->flash('danger'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_create_rejects_when_event_is_full(): void
    {
        $full = $this->createEvent('Evento Lotado', 1);
        $this->createEnrollment($full->id, $this->createUser('Bruno', 'bruno@example.com')->id);
        $this->clearFlash();

        $this->createEnrollment($full->id, $this->participant->id);

        $this->assertEquals('não há vagas neste evento!', $this->flash('danger'));
        $this->assertSame(1, $full->enrollmentsCount());
    }

    public function test_create_without_user_asks_to_select_a_participant(): void
    {
        $this->post(action: 'create', controllerName: self::CONTROLLER, params: [
            'event_id' => $this->event->id,
        ]);

        $this->assertEquals('Selecione um Participante!', $this->flash('danger'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_create_rejects_non_scalar_user_id(): void
    {
        $this->post(action: 'create', controllerName: self::CONTROLLER, params: [
            'event_id' => $this->event->id,
            'enrollment' => ['user_id' => [$this->participant->id]],
        ]);

        $this->assertEquals('Selecione um Participante!', $this->flash('danger'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_create_with_unknown_event_redirects_to_index(): void
    {
        $response = $this->createEnrollment(9999, $this->participant->id);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $this->flash('danger'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_destroy_removes_the_enrollment(): void
    {
        $this->createEnrollment($this->event->id, $this->participant->id);
        $enrollment = Enrollment::findBy(['event_id' => $this->event->id]);
        $this->assertNotNull($enrollment);
        $this->clearFlash();

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: [
            'event_id' => $this->event->id,
            'id' => $enrollment->id,
        ]);

        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Inscrição removida com sucesso!', $this->flash('success'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_destroy_does_not_remove_enrollment_of_another_event(): void
    {
        $other = $this->createEvent('Outro Evento', 5);
        $this->createEnrollment($other->id, $this->participant->id);
        $enrollment = Enrollment::findBy(['event_id' => $other->id]);
        $this->assertNotNull($enrollment);
        $this->clearFlash();

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: [
            'event_id' => $this->event->id,
            'id' => $enrollment->id,
        ]);

        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Inscrição não encontrada!', $this->flash('danger'));
        $this->assertCount(1, Enrollment::all());
    }

    public function test_destroy_with_unknown_event_redirects_to_index(): void
    {
        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: [
            'event_id' => 9999,
            'id' => 1,
        ]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $this->flash('danger'));
    }

    public function test_create_with_non_numeric_event_id_redirects_to_index(): void
    {
        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: [
            'event_id' => "{$this->event->id}abc",
            'enrollment' => ['user_id' => (string) $this->participant->id],
        ]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $this->flash('danger'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_destroy_with_non_numeric_id_keeps_the_enrollment(): void
    {
        $this->createEnrollment($this->event->id, $this->participant->id);
        $enrollment = Enrollment::findBy(['event_id' => $this->event->id]);
        $this->assertNotNull($enrollment);
        $this->clearFlash();

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: [
            'event_id' => (string) $this->event->id,
            'id' => "{$enrollment->id}abc",
        ]);

        $this->assertSame("Location: /admin/events/{$this->event->id}", $response);
        $this->assertEquals('Inscrição não encontrada!', $this->flash('danger'));
        $this->assertCount(1, Enrollment::all());
    }

    public function test_show_lists_enrolled_and_offers_only_free_participants(): void
    {
        $this->createUser('Bruno <b>Lima</b>', 'bruno@example.com');
        $this->createEnrollment($this->event->id, $this->participant->id);
        $enrollment = Enrollment::findBy(['event_id' => $this->event->id]);
        $this->assertNotNull($enrollment);

        $response = $this->get(action: 'show', controllerName: self::EVENTS_CONTROLLER, params: [
            'id' => $this->event->id,
        ]);

        $this->assertStringContainsString('Inscritos', $response);
        $this->assertStringContainsString('ana@example.com', $response);
        $this->assertStringContainsString(
            "/admin/events/{$this->event->id}/enrollments/{$enrollment->id}",
            $response
        );
        $this->assertStringContainsString('name="_method" value="DELETE"', $response);
        $this->assertStringContainsString("action=\"/admin/events/{$this->event->id}/enrollments\"", $response);
        $this->assertStringContainsString('name="enrollment[user_id]"', $response);
        $this->assertStringContainsString('Bruno &lt;b&gt;Lima&lt;/b&gt; (bruno@example.com)', $response);
        $this->assertStringNotContainsString('<b>Lima</b>', $response);
        $this->assertStringNotContainsString('Ana Souza (ana@example.com)', $response);
        $this->assertStringNotContainsString('Admin (admin@example.com)', $response);
    }

    public function test_show_without_enrollments_says_so(): void
    {
        $response = $this->get(action: 'show', controllerName: self::EVENTS_CONTROLLER, params: [
            'id' => $this->event->id,
        ]);

        $this->assertStringContainsString('Nenhuma inscrição.', $response);
        $this->assertStringContainsString('Ana Souza (ana@example.com)', $response);
    }

    private function flash(string $type): ?string
    {
        return $_SESSION['flash'][$type] ?? null;
    }

    private function clearFlash(): void
    {
        $_SESSION['flash'] = [];
    }

    private function createEnrollment(int $eventId, int $userId): string
    {
        return $this->post(action: 'create', controllerName: self::CONTROLLER, params: [
            'event_id' => $eventId,
            'enrollment' => ['user_id' => (string) $userId],
        ]);
    }

    private function createUser(string $name, string $email, string $role = 'participant'): User
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

    private function createEvent(string $title, int $capacity): Event
    {
        $event = new Event([
            'user_id' => $this->admin->id,
            'title' => $title,
            'description' => 'Descrição do evento.',
            'kind' => 'workshop',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => $capacity,
        ]);
        $event->save();

        return $event;
    }
}
```

Por quê:

- Teste de controller chama a ação com `Request` montado; o
  `redirectTo` devolve `Location: ...` como string, e a flash fica em
  `$_SESSION['flash']`.
- Cobre cada mensagem do model chegando na flash `danger`, o
  `event_id` do form ignorado, `user_id` array, ids `'1abc'` e o
  `destroy` que não remove Inscrição de outro Evento.
- `show` é do `Admin\EventsController` (`EVENTS_CONTROLLER`): prova a
  view (lista, form `DELETE`, select, escape).
- `flash()` como helper: zerar `$_SESSION['flash'] = []` no meio do
  teste faz o `phpstan` achar que a chave não existe.
- `clearFlash()` entre duas chamadas: a flash da primeira não vaza
  para o assert da segunda. À parte: o `ControllerTestCase` reusa o
  mesmo `Request` e `addParams` acumula.

## 9. `tests/Integration/Access/EventsAccessTest.php`

a) `use`, antes de `use App\Models\Event;`:

```php
use App\Models\Enrollment;
```

b) Docblock e array de `eventRoutes()`, depois de `admin.events.destroy`:

```php
    /**
     * As 10 rotas admin.events.* (index, paginate, new, create, show, edit, update, destroy,
     * enrollments.create e enrollments.destroy).
     *
     * @return array<string, array{string, string}>
     */
```

```php
            'admin.events.destroy' => ['DELETE', '/admin/events/1'],
            'admin.events.enrollments.create' => ['POST', '/admin/events/1/enrollments'],
            'admin.events.enrollments.destroy' => ['DELETE', '/admin/events/1/enrollments/1'],
```

c) `methodOverrideRoutes()`, depois de `admin.events.destroy`:

```php
            'admin.events.enrollments.destroy' => ['DELETE', '/admin/events/1/enrollments/1'],
```

d) Testes novos, antes de `test_admin_should_access_events_index_and_new`:

```php
    public function test_participant_should_not_enroll_or_remove_enrollments(): void
    {
        $client = $this->loggedClient($this->participant);
        $uri = "/admin/events/{$this->event->id}/enrollments";

        $client->post($uri, ['form_params' => ['enrollment' => ['user_id' => $this->participant->id]]]);
        $this->assertCount(0, Enrollment::all());

        $enrollment = new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $this->assertTrue($enrollment->save());

        $client->post("{$uri}/{$enrollment->id}", ['form_params' => ['_method' => 'DELETE']]);
        $this->assertCount(1, Enrollment::all());
    }

    public function test_admin_should_enroll_and_remove_participant(): void
    {
        $client = $this->loggedClient($this->admin);
        $uri = "/admin/events/{$this->event->id}/enrollments";

        $create = $client->post($uri, ['form_params' => ['enrollment' => ['user_id' => $this->participant->id]]]);
        $this->assertEquals(302, $create->getStatusCode());
        $this->assertEquals("/admin/events/{$this->event->id}", $create->getHeaderLine('Location'));

        $enrollment = Enrollment::findBy(['event_id' => $this->event->id, 'user_id' => $this->participant->id]);
        $this->assertNotNull($enrollment);

        $destroy = $client->post("{$uri}/{$enrollment->id}", ['form_params' => ['_method' => 'DELETE']]);
        $this->assertEquals(302, $destroy->getStatusCode());
        $this->assertEquals("/admin/events/{$this->event->id}", $destroy->getHeaderLine('Location'));
        $this->assertCount(0, Enrollment::all());
    }
```

Por quê:

- `eventRoutes()` alimenta os testes "sem sessão -> `/login`" e
  "Participante -> `/dashboard`" de toda rota admin: só incluir a rota
  já testa o middleware.
- `methodOverrideRoutes()`: confere que POST + `_method=DELETE` vira
  DELETE de verdade.
- Testes por HTTP provam middleware `admin` e o fluxo completo.

## 10. `tests/Acceptance/admin/events/ManageEnrollmentsCest.php` (novo)

```php
<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * O Administrador inscreve e remove Participantes pelo show do Evento.
 */
class ManageEnrollmentsCest extends BaseEventsCest
{
    public function enrollAndRemoveParticipant(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Inscrever e remover um Participante pelo show do Evento');
        $page->amOnPage('/admin/events');
        $page->click('Ver', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Nenhuma inscrição.');
        $page->dontSeeElement('#enrollments');

        $page->selectOption('#enrollment_user_id', 'Participante 2 (participante2@example.com)');
        $page->click('Inscrever');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Participante inscrito com sucesso!');
        $page->see('participante2@example.com', '#enrollments');
        $page->see('1/' . $event->capacity);
        $page->dontSeeElement('#enrollment_user_id option[value="' . $this->participants[2]->id . '"]');
        Assert::assertSame(1, $event->enrollmentsCount());

        $page->click('Remover', "//table[@id='enrollments']//tr[contains(., 'participante2@example.com')]");

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Inscrição removida com sucesso!');
        $page->see('Nenhuma inscrição.');
        $page->seeElement('#enrollment_user_id option[value="' . $this->participants[2]->id . '"]');
        Assert::assertCount(0, Enrollment::where(['event_id' => $event->id]));
    }

    public function enrollInFullEventIsRejected(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        $this->createUser('Participante 4', 'participante4@example.com', 'participant');

        $page->wantTo('Inscrição recusada em Evento lotado');
        $page->amOnPage("/admin/events/{$event->id}");

        $page->see('3/3');
        $page->selectOption('#enrollment_user_id', 'Participante 4 (participante4@example.com)');
        $page->click('Inscrever');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('não há vagas neste evento!');
        $page->dontSee('participante4@example.com', '#enrollments');
        Assert::assertSame(3, $event->enrollmentsCount());
    }

    public function adminIsNotOfferedAndFullListSaysSo(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Select só com Participantes não inscritos');
        $page->amOnPage("/admin/events/{$event->id}");

        for ($i = 1; $i <= 3; $i++) {
            $page->see("participante{$i}@example.com", '#enrollments');
        }
        $page->dontSeeElement('#enrollment_user_id');
        $page->see('Todos os Participantes já estão inscritos.');
    }
}
```

Por quê:

- `BaseEventsCest` já semeia 16 Eventos, Evento 01 lotado (3/3) com os
  3 Participantes, Evento 02 vazio, sessão de admin.
- Três cenários: inscrever + remover (Evento 02), lotado recusa (Evento
  01) e select sem os já inscritos.
- `selectOption` usa o texto exato do `<option>`.

## Fechamento

```sh
./run test tests/Unit/Models/Events/EnrollmentTest.php
./run test tests/Unit/Models/Events/EventTest.php
./run test tests/Integration/Controllers/Admin/EnrollmentsControllerTest.php
./run test tests/Integration/Access/EventsAccessTest.php
./run test              # suíte toda
./run phpcs
./run phpstan
docker compose up -d selenium && sleep 5
./run test:browser
docker compose stop selenium
./run db:populate       # schema não muda; confere que o seed passa na validação nova
```

No navegador: `admin@example.com` / `123456`, abrir `/admin/events/2`
(sem inscritos), escolher Participante, "Inscrever" -> aparece em
"Inscritos" e some do select; "Remover" desfaz. No Evento 1 (3/3),
inscrever `fernanda@example.com` -> "não há vagas neste evento!".

Commit (inglês):
`feat: Admin enrolls and removes participants from event show`.

## Armadilhas (resumo)

- `user_id` do form é a exceção à regra "nunca do form": toda
  validação no model; teste cobre admin e inexistente.
- `{eventId}` não casa (`Route.php:90` aceita só `[a-z,_]`): vira texto
  literal, rota nunca casa, sem erro. Use `{event_id}`; no controller,
  `getParam('event_id')` (o `id` é a Inscrição).
- `findBy` com `id` **e** `event_id`: senão, troca de `id` na URL remove
  Inscrição de outro Evento.
- `(int)` em id vindo de fora: `'1abc'` vira 1. Use `filter_var` /
  `ctype_digit`.
- Form de remover sem `_method=DELETE`: 404.
- `EnrollmentTest` da base esperava `PDOException` na duplicata:
  troque por `save()` falso e mantenha o `UNIQUE` com classe anônima.
- Seed (populate, `BaseEventsCest`, `EventTest`) que inscreve admin ou
  passa da capacidade quebra com a validação nova; o populate lança
  `RuntimeException` se `save()` falhar.
- `redirectTo` sem `return` depois do "não encontrado": no teste o
  método seguiria.
- `ControllerTestCase` reusa o `Request`: `addParams` acumula entre
  chamadas no mesmo teste.
- `select` com centenas de usuários: aceitável; cite a busca (N2) como
  evolução.
- Com P2 na pilha: um `validates()` só para os dois fluxos; não duplique
  regra no controller; ajuste a mensagem de duplicata.
