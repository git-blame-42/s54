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

    private const CONTROLLER = 'App\Controllers\AuthenticationsController';

    public function tearDown(): void
    {
        unset($_SESSION['pending_user_id'], $_SESSION['flash']);
        parent::tearDown();
    }

    private function submitValidCredentials(): string
    {
        return $this->post(
            action: 'authenticate',
            controllerName: self::CONTROLLER,
            params: ['user' => ['email' => 'fulano@example.com', 'password' => '123456']]
        );
    }

    private function storedCode(): int
    {
        return (int) User::findById($this->user->id)?->login_code;
    }

    public function test_authenticate_with_valid_credentials_should_ask_for_the_code(): void
    {
        $response = $this->submitValidCredentials();

        $this->assertFalse(Auth::check());
        $this->assertSame('Location: /login/code', $response);
        $this->assertEquals($this->user->id, $_SESSION['pending_user_id']);

        $code = $this->storedCode();
        $this->assertGreaterThanOrEqual(1000, $code);
        $this->assertLessThanOrEqual(9999, $code);
        $this->assertEquals("Seu código de acesso é {$code}", $_SESSION['flash']['success']);
    }

    public function test_code_page_renders_form_when_there_is_a_pending_login(): void
    {
        $this->submitValidCredentials();

        $response = $this->get(action: 'code', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Código de acesso\s*<\/h1>/', $response);
        $this->assertStringContainsString('name="code"', $response);
        $this->assertStringContainsString('action="/login/code"', $response);
    }

    public function test_code_page_without_pending_login_redirects_to_login(): void
    {
        $response = $this->get(action: 'code', controllerName: self::CONTROLLER);

        $this->assertSame('Location: /login', $response);
    }

    public function test_verify_code_with_right_code_should_login(): void
    {
        $this->submitValidCredentials();

        $response = $this->post(
            action: 'verifyCode',
            controllerName: self::CONTROLLER,
            params: ['code' => (string) $this->storedCode()]
        );

        $this->assertTrue(Auth::check());
        $this->assertSame('Location: /dashboard', $response);
        $this->assertArrayNotHasKey('pending_user_id', $_SESSION);
        $this->assertEquals('Login realizado com sucesso!', $_SESSION['flash']['success']);
        $this->assertNull(User::findById($this->user->id)?->login_code);
    }

    public function test_verify_code_with_wrong_code_should_not_login(): void
    {
        $this->submitValidCredentials();
        $wrong = (string) ($this->storedCode() === 9999 ? 1000 : $this->storedCode() + 1);

        $response = $this->post(action: 'verifyCode', controllerName: self::CONTROLLER, params: ['code' => $wrong]);

        $this->assertFalse(Auth::check());
        $this->assertSame('Location: /login/code', $response);
        $this->assertEquals('Código inválido!', $_SESSION['flash']['danger']);
        $this->assertEquals($this->user->id, $_SESSION['pending_user_id']);
    }

    public function test_verify_code_with_array_code_should_not_login(): void
    {
        $this->submitValidCredentials();

        $response = $this->post(
            action: 'verifyCode',
            controllerName: self::CONTROLLER,
            params: ['code' => [(string) $this->storedCode()]]
        );

        $this->assertFalse(Auth::check());
        $this->assertSame('Location: /login/code', $response);
    }

    public function test_verify_code_without_pending_login_redirects_to_login(): void
    {
        $response = $this->post(action: 'verifyCode', controllerName: self::CONTROLLER, params: ['code' => '1234']);

        $this->assertFalse(Auth::check());
        $this->assertSame('Location: /login', $response);
    }

    public function test_authenticate_with_invalid_credentials_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => 'wrong']]
        );

        $this->assertFalse(Auth::check());
        $this->assertSame('Location: /login', $response);
        $this->assertArrayNotHasKey('pending_user_id', $_SESSION);
        $this->assertNull(User::findById($this->user->id)?->login_code);
    }

    public function test_destroy_should_logout(): void
    {
        Auth::login($this->user);

        $response = $this->get(action: 'destroy', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
    }
}
