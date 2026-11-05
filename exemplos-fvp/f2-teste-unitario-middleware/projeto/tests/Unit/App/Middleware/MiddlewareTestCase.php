<?php

namespace Tests\Unit\App\Middleware;

use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Tests\TestCase;

/**
 * Base dos testes de middleware.
 *
 * Carrega as rotas (o `route()` do `deny` precisa delas fora do nginx),
 * monta uma requisição GET e limpa a sessão antes e depois de cada teste.
 * Estende `Tests\TestCase` porque `Auth::check()` consulta a tabela `users`.
 */
abstract class MiddlewareTestCase extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SESSION = [];
    }

    public function tearDown(): void
    {
        Router::reset();
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        $_SESSION = [];
        parent::tearDown();
    }

    protected function request(): Request
    {
        return new Request();
    }

    protected function createUser(string $role): User
    {
        $user = new User([
            'name' => 'User ' . $role,
            'email' => $role . '@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }
}
