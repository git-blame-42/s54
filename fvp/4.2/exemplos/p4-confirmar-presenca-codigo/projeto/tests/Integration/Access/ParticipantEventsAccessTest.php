<?php

namespace Tests\Integration\Access;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rotas events.* (área do usuário): qualquer usuário autenticado acessa; sem sessão vai para /login.
 */
class ParticipantEventsAccessTest extends TestCase
{
    private const PASSWORD = '123456';

    private User $admin;
    private User $participant;
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->participant = $this->createUser('Participante', 'participante@example.com', 'participant');

        $this->event = new Event([
            'user_id' => $this->admin->id,
            'title' => 'Palestra de Acesso',
            'description' => 'Evento usado nos testes de acesso.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Microsoft Teams',
            'capacity' => 10,
        ]);
        $this->event->save();
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    private function newClient(): Client
    {
        return new Client([
            'allow_redirects' => false,
            'base_uri' => 'http://web:8080',
            'cookies' => true,
        ]);
    }

    private function loggedClient(User $user): Client
    {
        $client = $this->newClient();
        $response = $client->post('/login', [
            'form_params' => ['user' => ['email' => $user->email, 'password' => self::PASSWORD]],
        ]);

        $this->assertEquals(302, $response->getStatusCode());

        return $client;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function participantRoutes(): array
    {
        return [
            'events.index' => ['/events'],
            'events.paginate' => ['/events/page/1'],
            'events.show' => ['/events/1'],
        ];
    }

    #[DataProvider('participantRoutes')]
    public function test_events_route_should_redirect_to_login_without_session(string $uri): void
    {
        $response = $this->newClient()->get($uri);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    #[DataProvider('participantRoutes')]
    public function test_participant_should_access_events_route(string $uri): void
    {
        $response = $this->loggedClient($this->participant)->get($uri);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Palestra de Acesso', (string) $response->getBody());
    }

    public function test_admin_should_also_access_participant_events(): void
    {
        $response = $this->loggedClient($this->admin)->get('/events');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Palestra de Acesso', (string) $response->getBody());
    }

    public function test_navbar_should_link_participant_to_events(): void
    {
        $body = (string) $this->loggedClient($this->participant)->get('/dashboard')->getBody();

        $this->assertStringContainsString('href="/events"', $body);
        $this->assertStringNotContainsString('href="/admin/events"', $body);
    }

    public function test_unknown_event_should_redirect_participant_to_events(): void
    {
        $response = $this->loggedClient($this->participant)->get('/events/999999');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/events', $response->getHeaderLine('Location'));
    }

    public function test_enroll_should_redirect_to_login_without_session(): void
    {
        $response = $this->newClient()->post("/events/{$this->event->id}/enrollments");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        $this->assertCount(0, Enrollment::all());
    }

    public function test_checkin_should_redirect_to_login_without_session(): void
    {
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]))->save();

        $response = $this->newClient()->post(
            "/events/{$this->event->id}/checkin",
            ['form_params' => ['code' => (string) Event::findById((int) $this->event->id)?->checkin_code]]
        );

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        $this->assertSame(0, $this->event->confirmedCount());
    }

    public function test_participant_should_confirm_presence_over_http(): void
    {
        (new Enrollment(['event_id' => $this->event->id, 'user_id' => $this->participant->id]))->save();
        $code = (string) Event::findById((int) $this->event->id)?->checkin_code;

        $response = $this->loggedClient($this->participant)->post(
            "/events/{$this->event->id}/checkin",
            ['form_params' => ['code' => $code]]
        );

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/events/{$this->event->id}", $response->getHeaderLine('Location'));
        $this->assertSame(1, $this->event->confirmedCount());
    }

    public function test_participant_should_enroll_and_return_to_the_event(): void
    {
        $response = $this->loggedClient($this->participant)->post("/events/{$this->event->id}/enrollments");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/events/{$this->event->id}", $response->getHeaderLine('Location'));
        $this->assertTrue($this->participant->isEnrolledIn($this->event));
    }
}
