<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

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
    }

    public function tearDown(): void
    {
        // ControllerTestCase::tearDown só limpa $_SESSION['user'].
        unset($_SESSION['redirect_to']);
        parent::tearDown();
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

    public function test_authenticate_should_redirect_to_requested_page(): void
    {
        $_SESSION['redirect_to'] = '/admin';

        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => '123456']]
        );

        $this->assertStringContainsString('Location: /admin', $response);
        $this->assertArrayNotHasKey('redirect_to', $_SESSION);
    }

    public function test_authenticate_with_external_requested_page_should_redirect_to_dashboard(): void
    {
        $_SESSION['redirect_to'] = 'https://evil.example.com/';

        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => '123456']]
        );

        $this->assertStringContainsString('Location: /dashboard', $response);
        $this->assertStringNotContainsString('evil.example.com', $response);
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

    public function test_destroy_should_logout(): void
    {
        Auth::login($this->user);

        $response = $this->get(action: 'destroy', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
    }
}
