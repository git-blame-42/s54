<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Core\Database\Database;
use Lib\Authentication\Auth;

class DashboardControllerTest extends ControllerTestCase
{
    /**
     * @param array<string, mixed> $attributes
     */
    private function createUser(array $attributes = []): User
    {
        $user = new User(array_merge([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ], $attributes));
        $user->save();

        return $user;
    }

    public function test_index_as_admin_should_show_admin_only_content(): void
    {
        Auth::login($this->createUser(['email' => 'admin@example.com', 'role' => 'admin']));

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Bem-vindo(a), Fulano!', $response);
        $this->assertStringContainsString('Você é administrador(a).', $response);
        $this->assertStringContainsString('Ir para a área administrativa', $response);
    }

    public function test_index_as_regular_user_should_hide_admin_only_content(): void
    {
        Auth::login($this->createUser());

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Bem-vindo(a), Fulano!', $response);
        $this->assertStringNotContainsString('Você é administrador(a).', $response);
    }

    public function test_index_should_show_member_since_date(): void
    {
        $user = $this->createUser();
        Database::exec("UPDATE users SET created_at = '2024-03-15 10:30:00' WHERE id = {$user->id}");
        Auth::login($user);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Membro desde 15/03/2024.', $response);
    }
}
