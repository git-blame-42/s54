<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $admin->save();
        Auth::login($admin);
    }

    public function test_index_should_render_admin_dashboard_title(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\DashboardController');

        $this->assertMatchesRegularExpression('/<h1>\s*Painel Administrativo\s*<\/h1>/', $response);
    }

    public function test_index_should_greet_admin_by_name(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\DashboardController');

        $this->assertStringContainsString('Olá, Admin!', $response);
    }
}
