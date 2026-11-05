<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class DashboardControllerTest extends ControllerTestCase
{
    public function test_index_escapes_admin_name(): void
    {
        $admin = new User([
            'name' => '<b>Ana</b>',
            'email' => 'ana@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $admin->save();
        Auth::login($admin);

        $html = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\DashboardController');

        $this->assertStringContainsString('Olá, &lt;b&gt;Ana&lt;/b&gt;!', $html);
        $this->assertStringNotContainsString('<b>Ana</b>', $html);
    }
}
