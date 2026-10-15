<?php

namespace Tests\Integration\Controllers\Organizer;

use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    private User $organizer;

    public function setUp(): void
    {
        parent::setUp();
        $this->organizer = new User([
            'name' => 'Organizador',
            'email' => 'organizer@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'organizer'
        ]);
        $this->organizer->save();
    }

    public function test_index_should_render_organizer_dashboard(): void
    {
        Auth::login($this->organizer);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Organizer\DashboardController');

        $this->assertMatchesRegularExpression('/<h1>\s*Painel do Organizador\s*<\/h1>/', $response);
        $this->assertStringContainsString('Olá, Organizador!', $response);
        $this->assertStringContainsString('Organização', $response);
    }
}
