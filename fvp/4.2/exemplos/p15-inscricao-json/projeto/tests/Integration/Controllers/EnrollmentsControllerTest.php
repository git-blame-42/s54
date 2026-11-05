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
        // http_response_code() vale no CLI e o status fica no processo do PHPUnit: zera a cada teste.
        http_response_code(200);

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

    /* ------------------- resposta JSON ------------------- */

    public function test_create_with_json_accept_returns_the_enrollment_without_flash(): void
    {
        $event = $this->createEvent(['capacity' => 3]);
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]);
        $json = $this->decode($response);

        $enrollment = Enrollment::findBy(['event_id' => $event->id, 'user_id' => $this->participant->id]);
        $this->assertNotNull($enrollment);
        $this->assertTrue($json['ok']);
        $this->assertEquals('Inscrição realizada com sucesso!', $json['message']);
        $this->assertEquals(
            ['id' => $enrollment->id, 'event_id' => $event->id, 'user_id' => $this->participant->id],
            $json['enrollment']
        );
        $this->assertSame(1, $json['enrollments_count']);
        $this->assertSame(3, $json['capacity']);
        $this->assertSame(2, $json['available_spots']);
        $this->assertArrayNotHasKey('errors', $json);
        $this->assertSame(201, http_response_code());
        $this->assertStringNotContainsString('Location:', $response);
        $this->assertEmpty($_SESSION['flash']);
    }

    public function test_create_twice_with_json_accept_returns_errors(): void
    {
        $event = $this->createEvent();
        (new Enrollment(['event_id' => $event->id, 'user_id' => $this->participant->id]))->save();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $json = $this->decode($this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]));

        $this->assertFalse($json['ok']);
        $this->assertEquals('você já está inscrito neste evento!', $json['message']);
        $this->assertEquals(['você já está inscrito neste evento!'], $json['errors']);
        $this->assertArrayNotHasKey('enrollment', $json);
        $this->assertSame(422, http_response_code());
        $this->assertCount(1, Enrollment::all());
        $this->assertEmpty($_SESSION['flash']);
    }

    public function test_create_in_a_full_event_with_json_accept_returns_errors(): void
    {
        $event = $this->createEvent(['capacity' => 1]);
        $other = $this->createUser('Outro', 'outro@example.com', 'participant');
        (new Enrollment(['event_id' => $event->id, 'user_id' => $other->id]))->save();
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $json = $this->decode($this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $event->id]));

        $this->assertFalse($json['ok']);
        $this->assertEquals(['não há vagas neste evento!'], $json['errors']);
        $this->assertSame(0, $json['available_spots']);
        $this->assertFalse($this->participant->isEnrolledIn($event));
    }

    public function test_create_with_json_accept_and_unknown_event_returns_error(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $json = $this->decode($this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => 9999]));

        $this->assertFalse($json['ok']);
        $this->assertEquals(['Evento não encontrado!'], $json['errors']);
        $this->assertEquals('Evento não encontrado!', $json['message']);
        $this->assertArrayNotHasKey('capacity', $json);
        $this->assertSame(404, http_response_code());
        $this->assertEmpty($_SESSION['flash']);
    }

    public function test_create_with_non_numeric_id_is_treated_as_unknown_event(): void
    {
        $event = $this->createEvent();

        foreach (['abc', "{$event->id}x", '-1', ''] as $id) {
            $_SESSION['flash'] = [];
            $response = $this->post(action: 'create', controllerName: self::CONTROLLER, params: ['id' => $id]);

            $this->assertSame('Location: /events', $response, "id '{$id}'");
        }
        $this->assertCount(0, Enrollment::all());
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $response): array
    {
        $json = json_decode($response, true);
        $this->assertIsArray($json, "Resposta não é JSON: {$response}");

        return $json;
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
