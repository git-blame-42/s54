<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

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
    }

    public function test_index_should_escape_user_name(): void
    {
        $this->user->update(['name' => '<b>x</b>']);
        Auth::login($this->user);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Bem-vindo(a), &lt;b&gt;x&lt;/b&gt;!', $response);
        $this->assertStringNotContainsString('<b>x</b>', $response);
    }

    public function test_index_should_escape_user_email_in_navbar(): void
    {
        $this->user->update(['email' => '<i>fulano</i>@example.com']);
        Auth::login($this->user);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('&lt;i&gt;fulano&lt;/i&gt;@example.com', $response);
        $this->assertStringNotContainsString('<i>fulano</i>', $response);
    }

    public function test_index_should_escape_flash_message(): void
    {
        Auth::login($this->user);
        FlashMessage::danger('<script>alert(1)</script>');

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response);
    }

    public function test_admin_index_should_escape_admin_name(): void
    {
        $this->user->update(['name' => '<b>x</b>', 'role' => 'admin']);
        Auth::login($this->user);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\DashboardController');

        $this->assertStringContainsString('Olá, &lt;b&gt;x&lt;/b&gt;!', $response);
        $this->assertStringNotContainsString('<b>x</b>', $response);
    }
}
