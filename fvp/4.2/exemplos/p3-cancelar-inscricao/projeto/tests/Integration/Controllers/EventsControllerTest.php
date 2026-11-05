<?php

namespace Tests\Integration\Controllers;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;

/**
 * Eventos na área do Participante (/events): listagem paginada e detalhe, sem ações de administração.
 */
class EventsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\EventsController';

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

    public function test_index_lists_five_events_per_page_without_admin_actions(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->createEvent(['title' => "Evento {$i}"]);
        }

        $page1 = $this->get(action: 'index', controllerName: self::CONTROLLER);
        $page2 = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['page' => 2]);

        $this->assertMatchesRegularExpression('/<h1>\s*Eventos\s*<\/h1>/', $page1);
        $this->assertStringContainsString('Evento 1', $page1);
        $this->assertStringContainsString('Evento 5', $page1);
        $this->assertStringNotContainsString('Evento 6', $page1);
        $this->assertStringContainsString('href="/events/page/2"', $page1);
        $this->assertStringContainsString('Evento 6', $page2);
        $this->assertStringNotContainsString('Evento 5', $page2);

        $this->assertStringNotContainsString('Novo Evento', $page1);
        $this->assertStringNotContainsString('Editar', $page1);
        $this->assertStringNotContainsString('Remover', $page1);
        $this->assertStringNotContainsString('/admin/events', $page1);
    }

    public function test_index_shows_enrollments_over_capacity_and_link_to_show(): void
    {
        $event = $this->createEvent(['capacity' => 10]);
        (new Enrollment(['event_id' => $event->id, 'user_id' => $this->participant->id]))->save();

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER);

        $this->assertStringContainsString('1/10', $response);
        $this->assertStringContainsString("href=\"/events/{$event->id}\"", $response);
    }

    public function test_show_renders_event_without_admin_actions(): void
    {
        $event = $this->createEvent(['title' => 'Palestra Final', 'capacity' => 4]);

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertMatchesRegularExpression('/<h1>\s*Palestra Final\s*<\/h1>/', $response);
        $this->assertStringContainsString('0/4', $response);
        $this->assertStringContainsString('(4 disponíveis)', $response);
        $this->assertStringContainsString('href="/events"', $response);
        $this->assertStringNotContainsString('Editar', $response);
        $this->assertStringNotContainsString('/admin/events', $response);
    }

    public function test_show_offers_enroll_button_when_not_enrolled(): void
    {
        $event = $this->createEvent();

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString("action=\"/events/{$event->id}/enrollments\"", $response);
        $this->assertStringContainsString('Inscrever-se', $response);
        $this->assertStringNotContainsString('Você está inscrito neste evento.', $response);
        $this->assertStringNotContainsString('Cancelar inscrição', $response);
    }

    public function test_show_tells_when_already_enrolled(): void
    {
        $event = $this->createEvent();
        (new Enrollment(['event_id' => $event->id, 'user_id' => $this->participant->id]))->save();

        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        $this->assertStringContainsString('Você está inscrito neste evento.', $response);
        $this->assertStringNotContainsString('Inscrever-se', $response);
        $this->assertStringContainsString("action=\"/events/{$event->id}/enrollments\"", $response);
        $this->assertStringContainsString('name="_method" value="DELETE"', $response);
        $this->assertStringContainsString('Cancelar inscrição', $response);
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

    public function test_show_with_unknown_id_redirects_to_index(): void
    {
        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['id' => 9999]);

        $this->assertSame('Location: /events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
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
