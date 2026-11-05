<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    public function test_index_should_show_counters_and_users_ordered_by_name(): void
    {
        $admin = $this->createUser('Zuleica', 'zuleica@example.com', 'admin');
        $this->createUser('Mário', 'mario@example.com', 'participant');
        $this->createUser('Beatriz', 'beatriz@example.com', 'participant');

        Auth::login($admin);

        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\Admin\DashboardController'
        );

        $this->assertStringContainsString('Administradores: 1', $response);
        $this->assertStringContainsString('Participantes: 2', $response);
        $this->assertMatchesRegularExpression('/Beatriz.*Mário.*Zuleica/s', $response);
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role
        ]);
        $user->save();

        return $user;
    }
}
