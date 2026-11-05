<?php

namespace Tests\Integration\Access;

use App\Models\Comment;
use App\Models\Event;
use App\Models\User;
use GuzzleHttp\Client;
use Tests\TestCase;

/**
 * Rotas events.show e events.comments.create: só para logados (grupo auth). Participante e
 * Administrador acessam.
 */
class CommentsAccessTest extends TestCase
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
            'title' => 'Palestra Comentada',
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

    public function test_event_page_should_redirect_to_login_without_session(): void
    {
        $response = $this->newClient()->get("/events/{$this->event->id}");

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function test_comment_should_redirect_to_login_without_session(): void
    {
        $response = $this->newClient()->post("/events/{$this->event->id}/comments", [
            'form_params' => ['comment' => ['body' => 'Anônimo']],
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
        $this->assertCount(0, Comment::all());
    }

    public function test_participant_should_see_event_page_and_comment(): void
    {
        $client = $this->loggedClient($this->participant);

        $page = $client->get("/events/{$this->event->id}");
        $this->assertEquals(200, $page->getStatusCode());
        $this->assertStringContainsString('Palestra Comentada', (string) $page->getBody());

        $response = $client->post("/events/{$this->event->id}/comments", [
            'form_params' => ['comment' => ['body' => 'Comentário do Participante']],
        ]);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/events/{$this->event->id}", $response->getHeaderLine('Location'));
        $this->assertCount(1, Comment::all());
    }

    public function test_admin_should_comment_and_return_to_admin_page(): void
    {
        $response = $this->loggedClient($this->admin)->post("/events/{$this->event->id}/comments", [
            'form_params' => ['comment' => ['body' => 'Comentário do Administrador']],
        ]);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals("/admin/events/{$this->event->id}", $response->getHeaderLine('Location'));
        $this->assertCount(1, Comment::all());
    }
}
