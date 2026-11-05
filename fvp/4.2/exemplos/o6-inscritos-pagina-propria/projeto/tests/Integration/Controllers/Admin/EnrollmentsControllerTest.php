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

    private User $admin;
    private Event $event;

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

        $this->event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Oficina de Testes',
            'description' => 'Aprenda a testar.',
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Bloco A',
            'capacity' => 20,
        ]);
        $this->event->save();
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_index_lists_first_five_participants_with_email_and_date(): void
    {
        $this->enrollParticipants($this->event, 7);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['id' => $this->event->id]);

        $this->assertMatchesRegularExpression('/<h1>\s*Inscritos: Oficina de Testes\s*<\/h1>/', $response);
        $this->assertStringContainsString('7 inscrito(s) de 20 vagas.', $response);
        $this->assertStringContainsString('Participante 1', $response);
        $this->assertStringContainsString('participante5@example.com', $response);
        $this->assertStringNotContainsString('Participante 6', $response);
        $this->assertStringContainsString(date('d/m/Y'), $response);
        $this->assertStringContainsString("href=\"/admin/events/{$this->event->id}/enrollments/page/2\"", $response);
        $this->assertStringContainsString('Mostrando 1 - 5 de 7', $response);
    }

    public function test_page_two_lists_remaining_participants(): void
    {
        $this->enrollParticipants($this->event, 7);

        $response = $this->get(
            action: 'index',
            controllerName: self::CONTROLLER,
            params: ['id' => $this->event->id, 'page' => '2']
        );

        $this->assertStringContainsString('Participante 6', $response);
        $this->assertStringContainsString('participante7@example.com', $response);
        $this->assertStringNotContainsString('Participante 5<', $response);
        $this->assertStringContainsString("href=\"/admin/events/{$this->event->id}/enrollments/page/1\"", $response);
        $this->assertStringContainsString('Mostrando 6 - 7 de 7', $response);
    }

    public function test_index_does_not_list_participants_of_other_events(): void
    {
        $other = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Outro Evento',
            'description' => 'Outro.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+20 days')),
            'location' => 'Online',
            'capacity' => 5,
        ]);
        $other->save();
        $this->enrollParticipants($other, 2);

        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['id' => $this->event->id]);

        $this->assertStringNotContainsString('participante1@example.com', $response);
        $this->assertStringContainsString('Nenhum inscrito neste evento.', $response);
    }

    public function test_index_of_event_without_enrollments_shows_message(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['id' => $this->event->id]);

        $this->assertStringContainsString('0 inscrito(s) de 20 vagas.', $response);
        $this->assertStringContainsString('Nenhum inscrito neste evento.', $response);
        $this->assertStringNotContainsString('<table', $response);
        $this->assertStringContainsString("href=\"/admin/events/{$this->event->id}\"", $response);
    }

    public function test_index_with_unknown_event_redirects_to_events_index(): void
    {
        $response = $this->get(action: 'index', controllerName: self::CONTROLLER, params: ['id' => 999999]);

        $this->assertSame('Location: /admin/events', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }

    private function enrollParticipants(Event $event, int $total): void
    {
        for ($number = 1; $number <= $total; $number++) {
            $email = "participante{$number}@example.com";
            $user = User::findByEmail($email);

            if ($user === null) {
                $user = new User([
                    'name' => "Participante {$number}",
                    'email' => $email,
                    'password' => '123456',
                    'password_confirmation' => '123456'
                ]);
                $user->save();
            }

            (new Enrollment(['event_id' => $event->id, 'user_id' => $user->id]))->save();
        }
    }
}
