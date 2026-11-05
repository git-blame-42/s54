<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

class DashboardControllerTest extends ControllerTestCase
{
    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();
        Auth::login($this->user);
    }

    public function test_index_should_render_user_dashboard(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertMatchesRegularExpression('/<h1>\s*Painel do Usuário\s*<\/h1>/', $response);
        $this->assertStringContainsString('Bem-vindo(a), Fulano!', $response);
    }

    public function test_navbar_should_show_user_name_instead_of_email(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertMatchesRegularExpression('/dropdown-toggle[^>]*>\s*Fulano\s*</', $response);
        $this->assertStringNotContainsString('fulano@example.com', $response);
    }
}
