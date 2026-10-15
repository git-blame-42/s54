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

    public function test_authenticate_with_invalid_credentials_should_increment_failed_attempts(): void
    {
        $this->loginWith('wrong');

        $this->assertSame(1, (int) User::findById($this->user->id)->failed_attempts);
    }

    public function test_authenticate_after_max_failed_attempts_should_lock_account(): void
    {
        for ($i = 0; $i < User::MAX_ATTEMPTS; $i++) {
            $this->loginWith('wrong');
        }
        unset($_SESSION['flash']);

        $response = $this->loginWith('123456');

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
        $this->assertSame(1, substr_count($response, 'Location:'));
        $this->assertSame('Conta bloqueada por excesso de tentativas.', $_SESSION['flash']['danger'] ?? null);
    }

    public function test_authenticate_with_valid_credentials_should_reset_failed_attempts(): void
    {
        for ($i = 1; $i < User::MAX_ATTEMPTS; $i++) {
            $this->loginWith('wrong');
        }

        $this->loginWith('123456');

        $this->assertTrue(Auth::check());
        $this->assertSame(0, (int) User::findById($this->user->id)->failed_attempts);
    }

    public function test_destroy_should_logout(): void
    {
        Auth::login($this->user);

        $response = $this->get(action: 'destroy', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
    }

    public function tearDown(): void
    {
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    private function loginWith(string $password): string
    {
        return $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => $password]]
        );
    }
}
