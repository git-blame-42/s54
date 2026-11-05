<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

/**
 * Ações do login por código (FVP B11), chamadas direto (sem HTTP nem middleware): o
 * redirectTo da base vira "echo Location: ...", por isso as ações têm return depois
 * dele e o teste compara a string inteira.
 * Próximo passo: tests/Integration/Access/LoginCodesAccessTest.php.
 */
class LoginCodesControllerTest extends ControllerTestCase
{
    private const CONTROLLER = 'App\Controllers\LoginCodesController';

    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION['flash'] = [];
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
        unset($_SESSION['flash']);
        unset($_SESSION['login_code']);
        parent::tearDown();
    }

    public function test_new_should_render_the_email_form(): void
    {
        $response = $this->get(action: 'new', controllerName: self::CONTROLLER);

        $this->assertMatchesRegularExpression('/<h1>\s*Entrar com código\s*<\/h1>/', $response);
        $this->assertStringContainsString('name="login_code[email]"', $response);
        $this->assertStringContainsString('Receber código', $response);
    }

    public function test_create_should_generate_a_code_and_redirect_to_verify(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['login_code' => ['email' => 'fulano@example.com']]
        );

        $this->assertSame('Location: /login/code/verify', $response);
        $this->assertEquals($this->user->id, Auth::loginCodeUser()?->id);
        $this->assertTrue(User::findById($this->user->id)->hasPendingLoginCode());
        $this->assertMatchesRegularExpression('/\d{4}/', (string) $_SESSION['flash']['success']);
        $this->assertFalse(Auth::check());
    }

    public function test_create_with_unknown_email_should_redirect_back(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: self::CONTROLLER,
            params: ['login_code' => ['email' => 'ninguem@example.com']]
        );

        $this->assertSame('Location: /login/code', $response);
        $this->assertNull(Auth::loginCodeUser());
        $this->assertEquals('E-mail não cadastrado!', $_SESSION['flash']['danger']);
    }

    public function test_authenticate_with_the_right_code_should_login(): void
    {
        $code = $this->user->generateLoginCode();
        Auth::startLoginCode($this->user);

        $response = $this->post(
            action: 'authenticate',
            controllerName: self::CONTROLLER,
            params: ['login_code' => ['code' => $code]]
        );

        $this->assertTrue(Auth::check());
        $this->assertNull(Auth::loginCodeUser());
        $this->assertSame('Location: /dashboard', $response);
    }

    public function test_authenticate_with_a_wrong_code_should_not_login(): void
    {
        $code = $this->user->generateLoginCode();
        Auth::startLoginCode($this->user);

        $response = $this->post(
            action: 'authenticate',
            controllerName: self::CONTROLLER,
            params: ['login_code' => ['code' => $code === '1000' ? '1001' : '1000']]
        );

        $this->assertFalse(Auth::check());
        $this->assertSame('Location: /login/code/verify', $response);
        $this->assertEquals('Código inválido!', $_SESSION['flash']['danger']);
    }

    public function test_authenticate_without_a_pending_request_should_redirect_to_new(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: self::CONTROLLER,
            params: ['login_code' => ['code' => '1234']]
        );

        $this->assertFalse(Auth::check());
        $this->assertSame('Location: /login/code', $response);
    }
}
