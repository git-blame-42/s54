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

    public function test_create_does_not_enroll_a_logged_admin(): void
    {
        Auth::login($this->admin);
        $event = $this->createEvent();

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]);

        // O controller é chamado direto (sem o middleware da rota): quem barra aqui é o model.
        $this->assertSame("Location: /events/{$event->id}", $response);
        $this->assertEquals('administradores não podem se inscrever em eventos!', $_SESSION['flash']['danger']);
        $this->assertFalse($this->admin->isEnrolledIn($event));
        $this->assertCount(0, Enrollment::all());
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
