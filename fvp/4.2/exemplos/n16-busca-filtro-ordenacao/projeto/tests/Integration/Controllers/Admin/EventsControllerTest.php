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

    public function test_index_keeps_search_kind_and_sort_in_links(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Oficina {$i}"]);
        }
        $this->createEvent(['title' => 'Oficina X', 'kind' => 'lecture']);
        $this->createEvent(['title' => 'Palestra 1']);

        $page1 = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['q' => 'Oficina', 'kind' => 'workshop', 'sort' => 'title', 'dir' => 'desc']
        );

        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $page1);
        $this->assertStringNotContainsString('Oficina X', $page1);
        $this->assertStringNotContainsString('Palestra 1', $page1);
        $this->assertLessThan(strpos($page1, 'Oficina 3'), strpos($page1, 'Oficina 7'));
        $this->assertStringContainsString(
            '/admin/events/page/2?q=Oficina&kind=workshop&sort=title&dir=desc',
            $page1
        );
        // O formulário volta preenchido.
        $this->assertStringContainsString('value="Oficina"', $page1);
        $this->assertMatchesRegularExpression('/<option value="workshop"\s+selected>/', $page1);
        $this->assertMatchesRegularExpression('/<option value="title"\s+selected>/', $page1);
        $this->assertMatchesRegularExpression('/<option value="desc"\s+selected>/', $page1);
    }

    public function test_index_second_page_keeps_search_kind_and_sort(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Oficina {$i}"]);
        }
        $this->createEvent(['title' => 'Oficina X', 'kind' => 'lecture']);

        $page2 = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['q' => 'Oficina', 'kind' => 'workshop', 'sort' => 'title', 'dir' => 'desc', 'page' => '2']
        );

        $this->assertStringContainsString('Mostrando 6 - 7 de 7', $page2);
        $this->assertLessThan(strpos($page2, 'Oficina 1'), strpos($page2, 'Oficina 2'));
        $this->assertStringNotContainsString('Oficina 3', $page2);
        $this->assertStringContainsString(
            '/admin/events/page/1?q=Oficina&kind=workshop&sort=title&dir=desc',
            $page2
        );
    }

    public function test_index_with_invalid_sort_shows_warning_and_registration_order(): void
    {
        $this->createEvent(['title' => 'Bravo']);
        $this->createEvent(['title' => 'Alfa']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['sort' => 'description']);

        $this->assertStringContainsString(
            '<div class="alert alert-warning" role="alert">Ordenação inválida.</div>',
            $response
        );
        $this->assertLessThan(strpos($response, 'Alfa'), strpos($response, 'Bravo'));
        $this->assertMatchesRegularExpression('/<option value="id"\s+selected>/', $response);
        $this->assertStringNotContainsString('sort=description', $response);
    }

    public function test_index_shows_every_invalid_param_and_keeps_the_valid_ones(): void
    {
        $this->createEvent(['title' => 'Oficina de Git', 'kind' => 'workshop']);
        $this->createEvent(['title' => 'Palestra de Git', 'kind' => 'lecture']);
        $this->createEvent(['title' => 'Oficina de Docker', 'kind' => 'workshop']);

        $response = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['q' => 'Git', 'kind' => 'party', 'sort' => 'title', 'dir' => ['desc']]
        );

        $this->assertStringContainsString('Tipo inválido.', $response);
        $this->assertStringContainsString('Direção inválida.', $response);
        $this->assertSame(2, substr_count($response, 'alert-warning'));
        $this->assertStringNotContainsString('party', $response);
        // Busca e ordenação válidas continuam valendo; tipo e direção voltam ao padrão.
        $this->assertLessThan(strpos($response, 'Palestra de Git'), strpos($response, 'Oficina de Git'));
        $this->assertStringNotContainsString('Oficina de Docker', $response);
    }

    public function test_index_without_params_keeps_registration_order_and_clean_links(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->createEvent(['title' => "Evento {$i}", 'starts_on' => date('Y-m-d', strtotime('+' . (20 - $i) . ' days'))]);
        }

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertLessThan(strpos($response, 'Evento 5'), strpos($response, 'Evento 1'));
        $this->assertStringContainsString('href="/admin/events/page/2"', $response);
        $this->assertStringNotContainsString('alert-warning', $response);
    }

    public function test_index_search_escapes_the_term_and_shows_message_without_results(): void
    {
        $this->createEvent(['title' => 'Oficina de Git']);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['q' => '<b>"x"</b>']);

        $this->assertStringContainsString('Nenhum evento encontrado.', $response);
        $this->assertStringContainsString('value="&lt;b&gt;&quot;x&quot;&lt;/b&gt;"', $response);
        $this->assertStringNotContainsString('<b>"x"</b>', $response);
        $this->assertStringNotContainsString('Oficina de Git', $response);
    }

    public function test_index_page_param_must_be_a_positive_integer(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $text = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => '2abc']);
        $beyond = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => '9']);

        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $text);
        $this->assertStringContainsString('Mostrando 6 - 7 de 7', $beyond);
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
