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
            'name' => 'User 1',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();
    }

    public function test_index_should_show_first_access_message_on_first_login(): void
    {
        Auth::login($this->user);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Painel do Usuário', $response);
        $this->assertStringContainsString('Este é o seu primeiro acesso.', $response);
        $this->assertStringNotContainsString('Último acesso:', $response);
    }

    public function test_index_should_show_previous_login_date_and_time(): void
    {
        $this->user->update(['last_login_at' => '2026-10-01 08:30:00']);
        Auth::login(User::findById($this->user->id));

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Último acesso: 01/10/2026 08:30', $response);
    }
}
