<?php

namespace Tests\Integration\Access;

use App\Models\Event;
use App\Models\User;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rotas organizer.events.*: só o Organizador entra. Sem sessão vai para /login; Participante e
 * Administrador são barrados e vão para /dashboard. O Organizador também não entra em /admin.
 */
class OrganizerEventsAccessTest extends TestCase
{
    private const PASSWORD = '123456';

    /** @var array<string, User> */
    private array $users = [];
    private Event $event;

    public function setUp(): void
    {
        parent::setUp();

        $this->users['admin'] = $this->createUser('Administrador', 'admin@example.com', 'admin');
        $this->users['participant'] = $this->createUser('Participante', 'participante@example.com', 'participant');
        $this->users['organizer'] = $this->createUser('Organizadora', 'organizador@example.com', 'organizer');

        $this->event = new Event([
            'user_id' => $this->users['organizer']->id,
            'title' => 'Evento da Organizadora',
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
        return new Client(['allow_redirects' => false, 'base_uri' => 'http://web:8080', 'cookies' => true]);
    }

    private function loggedClient(User $user): Client
    {
        $client = $this->newClient();
        $client->post('/login', [
            'form_params' => ['user' => ['email' => $user->email, 'password' => self::PASSWORD]],
        ]);

        return $client;
    }

    /**
     * As 8 rotas organizer.events.* (PUT e DELETE com _method, como o navegador envia).
     *
     * @return array<string, array{string, string, array<string, string>}>
     */
    public static function organizerRoutes(): array
    {
        return [
            'organizer.events.index' => ['GET', '/organizer/events', []],
            'organizer.events.paginate' => ['GET', '/organizer/events/page/2', []],
            'organizer.events.new' => ['GET', '/organizer/events/new', []],
            'organizer.events.create' => ['POST', '/organizer/events', []],
            'organizer.events.show' => ['GET', '/organizer/events/1', []],
            'organizer.events.edit' => ['GET', '/organizer/events/1/edit', []],
            'organizer.events.update' => ['POST', '/organizer/events/1', ['_method' => 'PUT']],
            'organizer.events.destroy' => ['POST', '/organizer/events/1', ['_method' => 'DELETE']],
        ];
    }

    /**
     * @param array<string, string> $form
     */
    #[DataProvider('organizerRoutes')]
    public function test_organizer_route_should_redirect_to_login_without_session(string $method, string $uri, array $form): void
    {
        $response = $this->newClient()->request($method, $uri, ['form_params' => $form]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    /**
     * @param array<string, string> $form
     */
    #[DataProvider('organizerRoutes')]
    public function test_organizer_route_should_redirect_participant_and_admin_to_dashboard(
        string $method,
        string $uri,
        array $form
    ): void {
        foreach (['participant', 'admin'] as $role) {
            $response = $this->loggedClient($this->users[$role])->request($method, $uri, ['form_params' => $form]);

            $this->assertEquals(302, $response->getStatusCode(), $role);
            $this->assertEquals('/dashboard', $response->getHeaderLine('Location'), $role);
        }

        $this->assertEquals('Evento da Organizadora', Event::findById($this->event->id)?->title);
        $this->assertCount(1, Event::all());
    }

    public function test_organizer_should_access_own_events(): void
    {
        $client = $this->loggedClient($this->users['organizer']);

        $index = $client->get('/organizer/events');
        $this->assertEquals(200, $index->getStatusCode());
        $this->assertStringContainsString('Evento da Organizadora', (string) $index->getBody());
        $this->assertStringContainsString('Meus Eventos', (string) $index->getBody());

        $show = $client->get("/organizer/events/{$this->event->id}");
        $this->assertEquals(200, $show->getStatusCode());
    }

    public function test_organizer_should_not_access_admin_area(): void
    {
        $client = $this->loggedClient($this->users['organizer']);

        foreach (['/admin', '/admin/events', "/admin/events/{$this->event->id}"] as $uri) {
            $response = $client->get($uri);

            $this->assertEquals(302, $response->getStatusCode(), $uri);
            $this->assertEquals('/dashboard', $response->getHeaderLine('Location'), $uri);
        }
    }

    public function test_organizer_login_should_redirect_to_organizer_events(): void
    {
        $response = $this->newClient()->post('/login', [
            'form_params' => ['user' => ['email' => 'organizador@example.com', 'password' => self::PASSWORD]],
        ]);

        $this->assertEquals('/organizer/events', $response->getHeaderLine('Location'));
    }
}
