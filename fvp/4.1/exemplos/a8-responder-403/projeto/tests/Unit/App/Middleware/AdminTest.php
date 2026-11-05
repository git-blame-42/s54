<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Admin;
use App\Models\User;
use Core\Exceptions\HTTPException;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Tests\TestCase;

class AdminTest extends TestCase
{
    private Admin $middleware;
    private Request $request;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin';

        $this->middleware = new Admin();
        $this->request = new Request();
    }

    public function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        parent::tearDown();
    }

    public function test_handle_should_throw_403_for_common_user(): void
    {
        Auth::login($this->createUser('participant'));

        try {
            $this->middleware->handle($this->request);
            $this->fail('Esperava HTTPException para usuário sem perfil admin');
        } catch (HTTPException $e) {
            $this->assertEquals(403, $e->getStatusCode());
            $this->assertEquals('Acesso restrito a administradores', $e->getMessage());
        }
    }

    public function test_handle_should_allow_admin_user(): void
    {
        Auth::login($this->createUser('admin'));

        $this->middleware->handle($this->request);

        $this->assertTrue(Auth::check());
    }

    public function test_handle_should_ignore_request_without_session(): void
    {
        $this->middleware->handle($this->request);

        $this->assertFalse(Auth::check());
    }

    private function createUser(string $role): User
    {
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }
}
