<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

class DashboardControllerTest extends ControllerTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
        Auth::login($user);
    }

    public function test_index_should_render_user_dashboard_title(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertMatchesRegularExpression('/<h1>\s*Painel do Usuário\s*<\/h1>/', $response);
    }

    public function test_index_should_greet_logged_user_by_name(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Bem-vindo(a), Fulano!', $response);
    }
}
