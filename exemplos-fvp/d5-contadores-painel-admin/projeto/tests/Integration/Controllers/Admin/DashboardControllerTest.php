<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    public function test_index_should_show_user_counters(): void
    {
        $admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $admin->save();

        foreach (['fulano', 'beltrano'] as $name) {
            (new User([
                'name' => ucfirst($name),
                'email' => "{$name}@example.com",
                'password' => '123456',
                'password_confirmation' => '123456',
            ]))->save();
        }

        Auth::login($admin);

        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\Admin\DashboardController'
        );

        $this->assertStringContainsString('Usuários cadastrados: 3', $response);
        $this->assertStringContainsString('Administradores: 1', $response);
        $this->assertStringContainsString('Participantes: 2', $response);
    }
}
