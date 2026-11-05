<?php

namespace Tests\Integration\Controllers;

use App\Models\Event;
use App\Models\User;
use Lib\Authentication\Auth;

class EventsControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\EventsController';

    private Event $event;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];

        $admin = $this->createUser('Admin', 'admin@example.com', 'admin');
        $this->event = new Event([
            'user_id' => $admin->id,
            'title' => 'Oficina de Acessibilidade Web',
            'description' => 'HTML semântico e leitores de tela.',
            'kind' => 'workshop',
            'modality' => 'in_person',
            'audience' => 'students',
            'starts_on' => date('Y-m-d', strtotime('+10 days')),
            'location' => 'Laboratório B204',
            'capacity' => 24,
        ]);
        $this->event->save();

        Auth::login($this->createUser('Participante', 'participante@example.com', 'participant'));
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
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

    public function test_show_by_slug_renders_the_event(): void
    {
        $response = $this->get(
            action: 'show',
            controllerName: self::CONTROLLER,
            params: ['slug' => 'oficina-de-acessibilidade-web']
        );

        $this->assertMatchesRegularExpression('/<h1>\s*Oficina de Acessibilidade Web\s*<\/h1>/', $response);
        $this->assertStringContainsString('Laboratório B204', $response);
        $this->assertStringContainsString(date('d/m/Y', strtotime('+10 days')), $response);
        $this->assertStringContainsString('24 de 24', $response);
        $this->assertStringNotContainsString('/edit', $response);
    }

    public function test_show_with_unknown_slug_redirects_to_dashboard(): void
    {
        $response = $this->get(action: 'show', controllerName: self::CONTROLLER, params: ['slug' => 'nao-existe']);

        $this->assertSame('Location: /dashboard', $response);
        $this->assertEquals('Evento não encontrado!', $_SESSION['flash']['danger']);
    }
}
