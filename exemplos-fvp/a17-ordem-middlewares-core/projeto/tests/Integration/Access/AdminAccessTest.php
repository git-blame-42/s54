<?php

namespace Tests\Integration\Access;

use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Tests\TestCase;

/**
 * Acesso HTTP real a /admin. A rota está no grupo 'admin' aninhado em
 * 'auth': o Authenticate tem de rodar antes do Admin.
 */
class AdminAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'allow_redirects' => false,
            'base_uri' => 'http://web:8080',
            'cookies' => new CookieJar(),
        ]);

        $this->createUser('Admin', 'admin@example.com', 'admin');
        $this->createUser('Fulano', 'fulano@example.com', 'participant');
    }

    public function test_visitor_should_be_redirected_to_login_with_login_message(): void
    {
        $response = $this->client->get('/admin');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));

        $body = (string) $this->client->get('/login')->getBody();
        $this->assertStringContainsString('Você deve estar logado para acessar essa página', $body);
        $this->assertStringNotContainsString('Acesso restrito a administradores', $body);
    }

    public function test_participant_should_be_redirected_to_dashboard_with_restricted_message(): void
    {
        $this->login('fulano@example.com');

        $response = $this->client->get('/admin');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));

        $body = (string) $this->client->get('/dashboard')->getBody();
        $this->assertStringContainsString('Acesso restrito a administradores', $body);
    }

    public function test_admin_should_access_admin_area(): void
    {
        $this->login('admin@example.com');

        $response = $this->client->get('/admin');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Olá, Admin!', (string) $response->getBody());
    }

    private function createUser(string $name, string $email, string $role): void
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();
    }

    private function login(string $email): void
    {
        $response = $this->client->post('/login', [
            'form_params' => ['user' => ['email' => $email, 'password' => '123456']],
        ]);

        $this->assertEquals('/dashboard', $response->getHeaderLine('Location'));
    }
}
