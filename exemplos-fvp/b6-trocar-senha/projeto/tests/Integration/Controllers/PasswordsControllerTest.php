<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

class PasswordsControllerTest extends ControllerTestCase
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
        Auth::login($this->user);
    }

    public function test_edit_should_render_the_change_password_form(): void
    {
        $response = $this->get(action: 'edit', controllerName: 'App\Controllers\PasswordsController');

        $this->assertMatchesRegularExpression('/<h1>\s*Trocar senha\s*<\/h1>/', $response);
        $this->assertStringContainsString('user[password_confirmation]', $response);
    }

    public function test_update_with_valid_password_should_redirect_to_dashboard(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\PasswordsController',
            params: ['user' => ['password' => '654321', 'password_confirmation' => '654321']]
        );

        $this->assertStringContainsString('Location: /dashboard', $response);
        $this->assertTrue(User::findById($this->user->id)->authenticate('654321'));
        $this->assertFalse(User::findById($this->user->id)->authenticate('123456'));
    }

    public function test_update_with_mismatched_confirmation_should_render_the_error(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\PasswordsController',
            params: ['user' => ['password' => '654321', 'password_confirmation' => 'outra']]
        );

        $this->assertStringContainsString('as senhas devem ser idênticas!', $response);
        $this->assertTrue(User::findById($this->user->id)->authenticate('123456'));
    }

    public function test_update_with_empty_password_should_render_the_error(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\PasswordsController',
            params: ['user' => ['password' => '', 'password_confirmation' => '']]
        );

        $this->assertStringContainsString('não pode ser vazio!', $response);
        $this->assertTrue(User::findById($this->user->id)->authenticate('123456'));
    }
}
