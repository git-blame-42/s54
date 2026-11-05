<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\Enrollment;
use App\Models\Event;
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

    public function test_index_remove_button_links_to_the_show_page(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression(
            "#<a [^>]*href=\"/admin/events/{$event->id}\">Remover</a>#",
            $response
        );
        $this->assertStringNotContainsString('value="DELETE"', $response);
    }

    public function test_show_displays_the_confirmation_code_and_the_removal_form(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('id="confirmation_code_value">4321<', $response);
        $this->assertStringContainsString("action=\"/admin/events/{$event->id}\"", $response);
        $this->assertStringContainsString('name="_method" value="DELETE"', $response);
        $this->assertStringContainsString('name="confirmation_code"', $response);
        $this->assertDoesNotMatchRegularExpression('/name="confirmation_code"[^>]*value=/', $response);
    }

    public function test_create_ignores_confirmation_code_sent_by_the_form(): void
    {
        $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['event' => $this->validParams(['confirmation_code' => '42'])]
        );

        $event = Event::findBy(['title' => 'Oficina de Testes']);
        $this->assertNotNull($event);
        $this->assertGreaterThanOrEqual(Event::CODE_MIN, $event->confirmation_code);
        $this->assertLessThanOrEqual(Event::CODE_MAX, $event->confirmation_code);
    }

    public function test_update_keeps_the_confirmation_code(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $this->put(
            action: 'update',
            controllerName: self::CONTROLLER,
            params: [
                'id' => $event->id,
                'event' => $this->validParams(['title' => 'Título Novo', 'confirmation_code' => '1234']),
            ]
        );

        $updated = Event::findById($event->id);
        $this->assertEquals('Título Novo', $updated->title);
        $this->assertSame(4321, $updated->confirmation_code);
    }

    public function test_destroy_with_right_code_removes_event_without_enrollments(): void
    {
        $event = $this->createEvent();

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $event->confirmation_code]
        );

        $this->assertNull(Event::findById($event->id));
        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento removido com sucesso!', $_SESSION['flash']['success']);
    }

    public function test_destroy_with_right_code_keeps_event_with_enrollments(): void
    {
        $event = $this->createEvent();
        $this->enroll($event);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $event->confirmation_code]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals(
            'não é possível remover um evento com inscrições (1)!',
            $_SESSION['flash']['danger']
        );
    }

    public function test_destroy_without_code_keeps_event_and_goes_back_to_show(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->delete(action: 'destroy', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Código incorreto! Tente de novo.', $_SESSION['flash']['danger']);
        $this->assertArrayNotHasKey('success', $_SESSION['flash']);
    }

    public function test_destroy_with_wrong_code_keeps_event_and_goes_back_to_show(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '1234']
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Código incorreto! Tente de novo.', $_SESSION['flash']['danger']);
    }

    public function test_destroy_with_code_as_array_keeps_event(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => ['4321']]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
    }

    public function test_destroy_with_wrong_code_does_not_reveal_enrollments(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);
        $this->enroll($event);

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '1234']
        );

        $this->assertEquals('Código incorreto! Tente de novo.', $_SESSION['flash']['danger']);
    }

    public function test_destroy_with_code_of_another_event_keeps_both(): void
    {
        $event = $this->createEvent(['title' => 'Alvo', 'confirmation_code' => 1111]);
        $this->createEvent(['title' => 'Outro', 'confirmation_code' => 2222]);

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '2222']
        );

        $this->assertCount(2, Event::all());
    }

    public function test_destroy_with_expired_code_keeps_event_with_its_own_message(): void
    {
        $event = $this->createEvent();
        $code = $event->issueConfirmationCode(time() - Event::CODE_TTL_SECONDS - 1);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $code]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Código expirado! Um novo foi gerado.', $_SESSION['flash']['danger']);
    }

    public function test_destroy_with_exhausted_attempts_keeps_event_with_its_own_message(): void
    {
        $event = $this->createEvent();
        $event->update(['confirmation_code_attempts' => Event::CODE_MAX_ATTEMPTS]);

        $response = $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => (string) $event->confirmation_code]
        );

        $this->assertNotNull(Event::findById($event->id));
        $this->assertSame("Location: /admin/events/{$event->id}", $response);
        $this->assertEquals('Tentativas esgotadas! Um novo código foi gerado.', $_SESSION['flash']['danger']);
    }

    public function test_destroy_third_wrong_code_exhausts_the_attempts(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);
        $event->update(['confirmation_code_attempts' => Event::CODE_MAX_ATTEMPTS - 1]);

        $this->delete(
            action: 'destroy',
            controllerName: self::CONTROLLER,
            params: ['id' => $event->id, 'confirmation_code' => '1234']
        );

        $this->assertEquals('Tentativas esgotadas! Um novo código foi gerado.', $_SESSION['flash']['danger']);
        $this->assertSame(Event::CODE_MAX_ATTEMPTS, Event::findById($event->id)->confirmation_code_attempts);
    }

    public function test_show_issues_a_new_code_when_the_old_one_is_exhausted(): void
    {
        $event = $this->createEvent();
        $event->update(['confirmation_code_attempts' => Event::CODE_MAX_ATTEMPTS]);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $found = Event::findById($event->id);
        $this->assertSame(0, $found->confirmation_code_attempts);
        $this->assertStringContainsString("id=\"confirmation_code_value\">{$found->confirmation_code}<", $response);
    }

    public function test_show_issues_a_new_code_when_the_old_one_expired(): void
    {
        $event = $this->createEvent();
        $event->issueConfirmationCode(time() - Event::CODE_TTL_SECONDS - 1);

        $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertGreaterThanOrEqual(time() - 5, Event::findById($event->id)->confirmation_code_issued_at);
    }

    public function test_show_keeps_a_live_code_and_its_attempts(): void
    {
        $event = $this->createEvent(['confirmation_code' => 4321]);
        $event->update(['confirmation_code_attempts' => 1]);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('id="confirmation_code_value">4321<', $response);
        $this->assertSame(1, Event::findById($event->id)->confirmation_code_attempts);
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
