<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Core\Database\Database;
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

    public function test_authenticate_admin_should_redirect_to_admin(): void
    {
        $admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => User::ROLE_ADMIN
        ]);
        $admin->save();

        $response = $this->post(
            action: 'authenticate',
            controllerName: 'App\Controllers\AuthenticationsController',
            params: ['user' => ['email' => 'admin@example.com', 'password' => '123456']]
        );

        $this->assertTrue(Auth::check());
        $this->assertStringContainsString('Location: /admin', $response);
        $this->assertStringNotContainsString('Location: /dashboard', $response);
    }

    public function test_authenticate_with_unknown_role_should_fail_without_login(): void
    {
        // Simula um perfil novo no banco ainda sem rota inicial (o ENUM não aceitaria 'ghost').
        Database::exec("ALTER TABLE users MODIFY role VARCHAR(20) NOT NULL DEFAULT 'participant'");
        Database::exec("UPDATE users SET role = 'ghost' WHERE email = 'fulano@example.com'");

        try {
            $this->post(
                action: 'authenticate',
                controllerName: 'App\Controllers\AuthenticationsController',
                params: ['user' => ['email' => 'fulano@example.com', 'password' => '123456']]
            );
            $this->fail('Era esperada DomainException para perfil desconhecido.');
        } catch (\DomainException $e) {
            $this->assertSame('Perfil desconhecido: ghost', $e->getMessage());
        }

        $this->assertFalse(Auth::check());
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
