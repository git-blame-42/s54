<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\EventChange;
use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class EventsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\Admin\EventsController';

    private User $admin;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $this->admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $this->admin->save();
        Auth::login($this->admin);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_index_lists_five_events_per_page(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertMatchesRegularExpression('/<h1>\s*Eventos\s*<\/h1>/', $page1);
        $this->assertStringContainsString('Novo Evento', $page1);
        $this->assertStringContainsString('Evento 1', $page1);
        $this->assertStringContainsString('Evento 5', $page1);
        $this->assertStringNotContainsString('Evento 6', $page1);
        $this->assertStringContainsString('/admin/events/page/2', $page1);
        $this->assertStringContainsString('Evento 6', $page2);
        $this->assertStringContainsString('Evento 7', $page2);
        $this->assertStringNotContainsString('Evento 5', $page2);
    }

    public function test_index_shows_date_and_enrollments_over_capacity(): void
    {
        $event = $this->createEvent(['capacity' => 10, 'starts_on' => date('Y-m-d', strtotime('+10 days'))]);
        $this->enroll($event);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString(date('d/m/Y', strtotime('+10 days')), $response);
        $this->assertStringContainsString('1/10', $response);
    }

    public function test_new_renders_form(): void
    {
        $response = $this->get(action: 'new', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('novalidate', $response);
        $this->assertStringContainsString('name="event[title]"', $response);
        $this->assertStringContainsString('id="event_kind"', $response);
        $this->assertStringContainsString('id="event_modality_online"', $response);
        $this->assertStringContainsString('id="event_audience_students"', $response);
        $this->assertStringContainsString('type="date"', $response);
        $this->assertStringContainsString('Salvar', $response);
        $this->assertStringNotContainsString('name="_method"', $response);
    }

    public function test_create_with_valid_data_saves_event_for_logged_admin(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['audience' => ['students', 'staff']])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertEquals($this->admin->id, $event->user_id);
        $this->assertEquals(['students', 'staff'], $event->audienceList());
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento cadastrado com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_create_ignores_user_id_sent_by_the_form(): void
    {
        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['user_id' => '999'])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertEquals($this->admin->id, $event->user_id);
    }

    public function test_create_with_invalid_data_renders_form_with_errors_and_values(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams([
                'title' => '',
                'location' => 'Bloco B',
                'kind' => 'workshop',
                'modality' => 'hybrid',
                'audience' => ['community'],
            ])]
        );

        $this->assertCount(0, Event::all());
        $this->assertMatchesRegularExpression('/<h1>\s*Novo Evento\s*<\/h1>/', $response);
        $this->assertMatchesRegularExpression('/id="event_title"[^>]*is-invalid|is-invalid[^>]*id="event_title"/', $response);
        $this->assertStringContainsString('<div class="invalid-feedback">não pode ser vazio!</div>', $response);
        $this->assertStringContainsString('value="Bloco B"', $response);
        $this->assertMatchesRegularExpression('/<option value="workshop" selected>/', $response);
        $this->assertMatchesRegularExpression('/id="event_modality_hybrid"[^>]*checked/', $response);
        $this->assertMatchesRegularExpression('/id="event_audience_community"[^>]*checked/', $response);
        $this->assertDoesNotMatchRegularExpression('/id="event_audience_students"[^>]*checked/', $response);
        $this->assertStringContainsString('Existem dados incorretos! Por favor, verifique!', $response);
    }

    public function test_create_without_audience_marks_audience_field(): void
    {
        $params = $this->validParams();
        unset($params['audience']);

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['event' => $params]);

        $this->assertCount(0, Event::all());
        $this->assertStringContainsString('invalid-feedback d-block', $response);
    }

    public function test_show_renders_event(): void
    {
        $event = $this->createEvent(['title' => 'Palestra Final', 'capacity' => 4]);
        $this->enroll($event);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('Palestra Final', $response);
        $this->assertStringContainsString('1/4', $response);
        $this->assertStringContainsString("/admin/events/{$event->id}/edit", $response);
    }

    public function test_show_with_unknown_id_redirects_to_index(): void
    {
        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    public function test_edit_renders_form_filled(): void
    {
        $event = $this->createEvent([
            'title' => 'Para Editar',
            'modality' => 'online',
            'audience' => ['professors', 'staff'],
        ]);

        $response = $this->get(action: 'edit', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertMatchesRegularExpression('/<h1>\s*Editar Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('value="Para Editar"', $response);
        $this->assertStringContainsString("action=\"/admin/events/{$event->id}\"", $response);
        $this->assertStringContainsString('name="_method" value="PUT"', $response);
        $this->assertMatchesRegularExpression('/id="event_modality_online"[^>]*checked/', $response);
        $this->assertMatchesRegularExpression('/id="event_audience_professors"[^>]*checked/', $response);
        $this->assertMatchesRegularExpression('/id="event_audience_staff"[^>]*checked/', $response);
        $this->assertDoesNotMatchRegularExpression('/id="event_audience_students"[^>]*checked/', $response);
    }

    public function test_update_with_valid_data_changes_event(): void
    {
        $event = $this->createEvent();

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Título Novo', 'capacity' => '40'])]
        );

        $updated = Event::findById($event->id);
        $this->assertEquals('Título Novo', $updated->title);
        $this->assertEquals(40, $updated->capacity);
        $this->assertEquals($this->admin->id, $updated->user_id);
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Evento atualizado com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_update_with_invalid_data_renders_form_and_keeps_record(): void
    {
        $event = $this->createEvent(['title' => 'Original']);

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Mudou', 'capacity' => '0'])]
        );

        $this->assertEquals('Original', Event::findById($event->id)->title);
        $this->assertMatchesRegularExpression('/<h1>\s*Editar Evento\s*<\/h1>/', $response);
        $this->assertStringContainsString('value="Mudou"', $response);
        $this->assertStringContainsString('invalid-feedback', $response);
        $this->assertStringContainsString('Existem dados incorretos! Por favor, verifique!', $response);
    }

    public function test_update_capacity_below_enrollments_is_rejected(): void
    {
        $event = $this->createEvent(['capacity' => 5]);
        $this->enroll($event);
        $this->enroll($event, 'outra@example.com');

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['capacity' => '1'])]
        );

        $this->assertEquals(5, Event::findById($event->id)->capacity);
        $this->assertStringContainsString('não pode ser menor que o total de inscrições (2)!', $response);
    }

    public function test_update_records_history_with_the_session_user(): void
    {
        $event = $this->createEvent(['title' => 'Antes', 'capacity' => '20']);
        $editor = new User([
            'name' => 'Editora da Sessão',
            'email' => 'editora@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $editor->save();
        Auth::login($editor);

        // user_id no request (dentro e fora de event[]) não pode virar o autor do histórico.
        $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: [
                'id' => $event->id,
                'user_id' => $this->admin->id,
                'event' => $this->validParams(['title' => 'Depois', 'user_id' => (string) $this->admin->id]),
            ]
        );

        $this->assertEquals('Evento atualizado com sucesso!', $_SESSION['flash']['success']);
        $history = EventChange::forEvent($event->id);
        $this->assertCount(1, $history);
        $this->assertSame('title', $history[0]->field);
        $this->assertSame('Antes', $history[0]->old_value);
        $this->assertSame('Depois', $history[0]->new_value);
        $this->assertEquals($editor->id, $history[0]->user_id);
        $this->assertEquals($this->admin->id, Event::findById($event->id)?->user_id);
    }

    public function test_update_without_changes_records_no_history(): void
    {
        $event = $this->createEvent();

        $response = $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams()]
        );

        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertSame([], EventChange::forEvent($event->id));
    }

    public function test_update_with_invalid_data_records_no_history(): void
    {
        $event = $this->createEvent();

        $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'event' => $this->validParams(['title' => 'Mudou', 'capacity' => '0'])]
        );

        $this->assertSame([], EventChange::forEvent($event->id));
    }

    public function test_show_lists_history_newest_first_and_escaped(): void
    {
        $event = $this->createEvent();
        $this->recordChange($event, 'title', 'Velho', '<b>Novo</b>', '2026-03-01 10:00:00');
        $this->recordChange($event, 'kind', 'lecture', 'workshop', '2026-03-02 11:30:00');

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('Histórico de alterações', $response);
        $this->assertMatchesRegularExpression(
            '/<td>Título<\/td>\s*<td>Velho<\/td>\s*<td>&lt;b&gt;Novo&lt;\/b&gt;<\/td>\s*<td>Admin<\/td>/',
            $response
        );
        $this->assertStringNotContainsString('<b>Novo</b>', $response);
        $this->assertMatchesRegularExpression('/<td>Tipo<\/td>\s*<td>Palestra<\/td>\s*<td>Oficina<\/td>/', $response);
        $this->assertStringContainsString('02/03/2026 11:30', $response);
        $this->assertStringContainsString('01/03/2026 10:00', $response);
        $this->assertSame(2, substr_count($response, 'class="event-change"'));

        // Mais recente primeiro: a mudança de Tipo (dia 02) vem antes da de Título (dia 01).
        $this->assertLessThan(strpos($response, '01/03/2026 10:00'), strpos($response, '02/03/2026 11:30'));
    }

    public function test_show_without_history_says_so(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('Nenhuma alteração registrada.', $response);
        $this->assertStringNotContainsString('class="event-change"', $response);
    }

    public function test_destroy_removes_event_without_enrollments(): void
    {
        $event = $this->createEvent();

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNull(Event::findById($event->id));
        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento removido com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_destroy_keeps_event_with_enrollments(): void
    {
        $event = $this->createEvent();
        $this->enroll($event);

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals(
            'não é possível remover um evento com inscrições (1)!',
            $_SESSION['flash']['danger']
        );
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validParams(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Oficina de Testes',
            'description' => 'Aprenda a testar.',
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => ['students'],
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Bloco A',
            'capacity' => '20',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = []): Event
    {
        $params = array_merge($this->validParams(), $overrides);
        $audience = $params['audience'];
        unset($params['audience']);

        $event = new Event($params + ['user_id' => $this->admin->id]);
        $event->setAudienceList($audience);
        $event->save();

        return $event;
    }

    private function recordChange(Event $event, string $field, string $old, string $new, string $createdAt): void
    {
        $change = new EventChange([
            'event_id' => $event->id,
            'user_id' => $this->admin->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'created_at' => $createdAt,
        ]);
        $this->assertTrue($change->save());
    }

    private function enroll(Event $event, string $email = 'participante@example.com'): void
    {
        $user = User::findByEmail($email) ?? new User([
            'name' => 'Participante',
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();

        (new Enrollment(['event_id' => $event->id, 'user_id' => $user->id]))->save();
    }
}
