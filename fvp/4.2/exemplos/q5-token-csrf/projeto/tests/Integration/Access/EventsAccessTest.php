<?php

namespace Tests\Integration\Access;

use App\Models\Event;
use App\Models\User;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rotas admin.events.*: só o Administrador acessa. Não autenticado vai para /login; Participante é
 * barrado e vai para /dashboard.
 */
class EventsAccessTest extends TestCase
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
        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));

        return $client;
    }

    /**
     * As 8 rotas admin.events.* (index, paginate, new, create, show, edit, update, destroy).
     *
     * @return array<string, array{string, string}>
     */
    public static function eventRoutes(): array
    {
        return [
            'admin.events.index' => ['GET', '/admin/events'],
            'admin.events.paginate' => ['GET', '/admin/events/page/2'],
            'admin.events.new' => ['GET', '/admin/events/new'],
            'admin.events.create' => ['POST', '/admin/events'],
            'admin.events.show' => ['GET', '/admin/events/1'],
            'admin.events.edit' => ['GET', '/admin/events/1/edit'],
            'admin.events.update' => ['PUT', '/admin/events/1'],
            'admin.events.destroy' => ['DELETE', '/admin/events/1'],
        ];
    }

    /**
     * PUT e DELETE chegam do navegador como POST com o campo oculto `_method`.
     *
     * @return array<string, array{string, string}>
     */
    public static function methodOverrideRoutes(): array
    {
        return [
            'admin.events.update' => ['PUT', '/admin/events/1'],
            'admin.events.destroy' => ['DELETE', '/admin/events/1'],
        ];
    }

    #[DataProvider('eventRoutes')]
    public function test_events_route_should_redirect_to_login_without_session(string $method, string $uri): void
    {
        $response = $this->newClient()->request($method, $uri);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    #[DataProvider('eventRoutes')]
    public function test_events_route_should_redirect_participant_to_dashboard(string $method, string $uri): void
    {
        $response = $this->loggedClient($this->participant)->request($method, $uri);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));
    }

    #[DataProvider('methodOverrideRoutes')]
    public function test_events_route_should_redirect_with_method_override_without_session(string $method, string $uri): void
    {
        $response = $this->newClient()->post($uri, ['form_params' => ['_method' => $method]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_participant_should_not_create_update_or_destroy_events(): void
    {
        $client = $this->loggedClient($this->participant);
        $uri = "/admin/events/{$this->event->id}";

        $client->post('/admin/events', ['form_params' => ['event' => [
            'title' => 'Evento Invasor',
            'description' => 'Não deve ser criado.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => ['students'],
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Qualquer lugar',
            'capacity' => '5',
        ]]]);
        $client->post($uri, ['form_params' => ['_method' => 'PUT', 'event' => ['title' => 'Título Alterado']]]);
        $client->post($uri, ['form_params' => ['_method' => 'DELETE']]);

        $events = Event::all();
        $this->assertCount(1, $events);
        $this->assertEquals('Palestra de Acesso', $events[0]->title);
    }

    public function test_get_with_method_delete_should_not_destroy_the_event(): void
    {
        $client = $this->loggedClient($this->admin);
        $uri = "/admin/events/{$this->event->id}";

        // Antes, o Request lia _method de $_REQUEST e este GET caía no destroy (link ou <img> removia o Evento).
        $response = $client->get($uri, ['query' => ['_method' => 'DELETE']]);

        // Continua GET: é a rota show.
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Palestra de Acesso', (string) $response->getBody());
        $this->assertNotNull(Event::findById((int) $this->event->id));
    }

    public function test_get_with_method_put_should_not_update_the_event(): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->get("/admin/events/{$this->event->id}", ['query' => [
            '_method' => 'PUT',
            'event' => [
                'title' => 'Alterado por GET',
                'description' => 'Dados válidos: sem a correção, o update gravaria.',
                'kind' => 'lecture',
                'modality' => 'online',
                'audience' => ['students'],
                'starts_on' => date('Y-m-d', strtotime('+30 days')),
                'location' => 'Microsoft Teams',
                'capacity' => '10',
            ],
        ]]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Palestra de Acesso', Event::findById((int) $this->event->id)?->title);
    }

    public function test_post_with_method_delete_only_in_query_string_should_be_not_found(): void
    {
        $client = $this->loggedClient($this->admin);

        // POST /admin/events/{id} sem _method no corpo: não há rota POST nesse caminho, o Router lança 404.
        $response = $client->post("/admin/events/{$this->event->id}?_method=DELETE", ['http_errors' => false]);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertNotNull(Event::findById((int) $this->event->id));
    }

    public function test_admin_should_destroy_event_with_method_delete_in_post_body(): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->post("/admin/events/{$this->event->id}", ['form_params' => [
            '_method' => 'DELETE',
            '_token' => $this->csrfToken($client),
        ]]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/events', $response->getHeaderLine('Location'));
        $this->assertNull(Event::findById((int) $this->event->id));
    }

    /* ------------------- Q5: token CSRF ------------------- */

    /**
     * Lê o token CSRF da sessão do cliente na página do formulário (como o navegador faria).
     */
    private function csrfToken(Client $client): string
    {
        $body = (string) $client->get('/admin/events/new')->getBody();
        $this->assertSame(1, preg_match('/name="_token" value="([0-9a-f]{64})"/', $body, $matches));

        return $matches[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function newEventParams(): array
    {
        return ['event' => [
            'title' => 'Evento via POST',
            'description' => 'Criado no teste de CSRF.',
            'kind' => 'lecture',
            'modality' => 'online',
            'audience' => ['students'],
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Online',
            'capacity' => '5',
        ]];
    }

    /**
     * Rotas que alteram estado, como o navegador as envia (POST, com _method quando é PUT/DELETE).
     *
     * @return array<string, array{string, array<string, string>}>
     */
    public static function stateChangingRoutes(): array
    {
        return [
            'admin.events.create' => ['/admin/events', []],
            'admin.events.update' => ['/admin/events/1', ['_method' => 'PUT']],
            'admin.events.destroy' => ['/admin/events/1', ['_method' => 'DELETE']],
        ];
    }

    /**
     * @param array<string, string> $params
     */
    #[DataProvider('stateChangingRoutes')]
    public function test_admin_request_without_csrf_token_should_be_forbidden(string $uri, array $params): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->post($uri, ['form_params' => $params + $this->newEventParams(), 'http_errors' => false]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertCount(1, Event::all());
        $this->assertEquals('Palestra de Acesso', Event::findById((int) $this->event->id)?->title);
    }

    public function test_admin_request_with_wrong_csrf_token_should_be_forbidden(): void
    {
        $client = $this->loggedClient($this->admin);
        $this->csrfToken($client);

        $response = $client->post("/admin/events/{$this->event->id}", [
            'form_params' => ['_method' => 'DELETE', '_token' => str_repeat('0', 64)],
            'http_errors' => false,
        ]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNotNull(Event::findById((int) $this->event->id));
    }

    public function test_admin_should_create_event_with_csrf_token(): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->post('/admin/events', [
            'form_params' => ['_token' => $this->csrfToken($client)] + $this->newEventParams(),
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertNotNull(Event::findBy(['title' => 'Evento via POST']));
    }

    public function test_csrf_token_from_another_session_should_be_forbidden(): void
    {
        $other = $this->loggedClient($this->admin);
        $token = $this->csrfToken($other);
        $client = $this->loggedClient($this->admin);

        $response = $client->post("/admin/events/{$this->event->id}", [
            'form_params' => ['_method' => 'DELETE', '_token' => $token],
            'http_errors' => false,
        ]);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNotNull(Event::findById((int) $this->event->id));
    }

    public function test_admin_should_access_events_index_and_new(): void
    {
        $client = $this->loggedClient($this->admin);

        $index = $client->get('/admin/events');
        $this->assertEquals(200, $index->getStatusCode());
        $this->assertStringContainsString('Palestra de Acesso', (string) $index->getBody());

        $new = $client->get('/admin/events/new');
        $this->assertEquals(200, $new->getStatusCode());
        $this->assertStringContainsString('Novo Evento', (string) $new->getBody());
    }

    public function test_admin_should_be_redirected_to_index_when_event_does_not_exist(): void
    {
        $client = $this->loggedClient($this->admin);

        $response = $client->get('/admin/events/999999');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/admin/events', $response->getHeaderLine('Location'));
    }
}
