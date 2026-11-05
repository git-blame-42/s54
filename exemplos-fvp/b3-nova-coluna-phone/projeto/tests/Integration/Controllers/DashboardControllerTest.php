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
            'password_confirmation' => '123456',
            'phone' => '(46) 99999-0000'
        ]);
        $this->user->save();
    }

    public function test_index_should_show_user_phone(): void
    {
        Auth::login($this->user);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Telefone: (46) 99999-0000', $response);
    }

    public function test_index_should_show_placeholder_when_phone_is_empty(): void
    {
        $this->user->update(['phone' => null]);
        Auth::login($this->user);

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\DashboardController');

        $this->assertStringContainsString('Telefone: não informado', $response);
    }
}
