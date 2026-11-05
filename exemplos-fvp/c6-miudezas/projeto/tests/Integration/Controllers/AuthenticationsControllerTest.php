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
    }

    public function tearDown(): void
    {
        // O ControllerTestCase limpa só $_SESSION['user']; o flash vazaria para o próximo teste.
        unset($_SESSION['flash']);
        parent::tearDown();
    }

    public function test_render_login_page(): void
    {
        $response = $this->get(action: 'new', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertMatchesRegularExpression('/<h1>\s*Login\s*<\/h1>/', $response);
        $this->assertMatchesRegularExpression('/Entrar/', $response);
    }

    public function test_render_login_page_should_use_page_title_in_document_title(): void
    {
        $response = $this->get(action: 'new', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertStringContainsString('<title>Login - Eventos do Câmpus</title>', $response);
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

    public function test_authenticate_with_wrong_password_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'fulano@example.com', 'password' => 'wrong']]
        );

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
        $this->assertEquals(['danger' => 'Senha incorreta!'], FlashMessage::get());
    }

    public function test_authenticate_with_unknown_email_should_not_login(): void
    {
        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'ninguem@example.com', 'password' => '123456']]
        );

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /login', $response);
        $this->assertEquals(['danger' => 'E-mail não cadastrado!'], FlashMessage::get());
    }

    public function test_destroy_should_logout(): void
    {
        Auth::login($this->user);

        $response = $this->get(action: 'destroy', controllerName: 'App\Controllers\AuthenticationsController');

        $this->assertFalse(Auth::check());
        $this->assertStringContainsString('Location: /', $response);
        $this->assertStringNotContainsString('Location: /login', $response);
    }
}
