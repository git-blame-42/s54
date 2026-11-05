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
