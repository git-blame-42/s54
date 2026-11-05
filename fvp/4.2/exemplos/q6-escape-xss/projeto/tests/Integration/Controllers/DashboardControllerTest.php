<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

class DashboardControllerTest extends ControllerTestCase
{
    public function test_index_escapes_user_name(): void
    {
        $user = new User([
            'name' => '<b>Ana</b>',
            'email' => 'ana@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
        ]);
        $user->save();
        Auth::login($user);

        $html = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Bem-vindo(a), &lt;b&gt;Ana&lt;/b&gt;!', $html);
        $this->assertStringNotContainsString('<b>Ana</b>', $html);
    }
}
