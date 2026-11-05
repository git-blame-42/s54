<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class AuthenticationsControllerTest extends ControllerTestCase
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
        unset($_SESSION['flash']);
    }

    public function test_render_login_page(): void
    {
        $response = $this->get(action: 'new', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertMatchesRegularExpression('/<h1>\s*Login\s*<\/h1>/', $response);
        $this->assertMatchesRegularExpression('/Entrar/', $response);
    }

    public function test_authenticate_with_valid_credentials_should_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => '123456']]
        );

        $this->assertTrue(Auth::check());
        $this->assertStringContainsString('Location: /dashboard', $response);
    }

    public function test_authenticate_with_invalid_credentials_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => 'wrong']]
        );

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
    }

    public function test_authenticate_without_user_param_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: []
        );

        $this->assertLoginRejected($response);
    }

    public function test_authenticate_without_email_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['password' => '123456']]
        );

        $this->assertLoginRejected($response);
    }

    public function test_authenticate_with_blank_password_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => ' ']]
        );

        $this->assertLoginRejected($response);
    }

    public function test_authenticate_with_non_string_email_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => ['fulano@example.com'], 'password' => '123456']]
        );

        $this->assertLoginRejected($response);
    }

    public function test_destroy_should_logout(): void
    {
        Auth::login($this->user);

        $response = $this->get(action: 'destroy', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
    }

    private function assertLoginRejected(string $response): void
    {
        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
        $this->assertSame(['danger' => 'E-mail ou senha inválidos!'], FlashMessage::get());
    }
}
