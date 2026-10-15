<?php

namespace Tests\Integration\Access;

use App\Models\User;
use GuzzleHttp\Client;
use Tests\TestCase;

/**
 * Acessa /admin com sessão de verdade (cookie do Guzzle) para provar o
 * status HTTP que o middleware Admin devolve a cada perfil.
 */
class AdminAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'allow_redirects' => false,
            'http_errors' => false,
            'cookies' => true,
            'base_uri' => 'http://web:8080',
        ]);
    }

    public function test_admin_route_should_respond_403_for_common_user(): void
    {
        $this->createUserAndLogin('participant');

        $response = $this->client->get('/admin');

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Acesso restrito a administradores', (string) $response->getBody());
    }

    public function test_admin_route_should_respond_200_for_admin(): void
    {
        $this->createUserAndLogin('admin');

        $response = $this->client->get('/admin');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Painel Administrativo', (string) $response->getBody());
    }

    private function createUserAndLogin(string $role): void
    {
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        $this->client->post('/login', [
            'form_params' => ['user' => ['email' => 'fulano@example.com', 'password' => '123456']],
        ]);
    }
}
