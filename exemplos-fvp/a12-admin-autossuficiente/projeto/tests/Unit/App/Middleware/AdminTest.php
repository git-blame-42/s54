<?php

namespace Tests\Unit\App\Middleware;

use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Lib\Authentication\Auth;
use Lib\FlashMessage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    private AdminSpy $middleware;
    private Request $request;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin';
        $this->request = new Request();
        $this->middleware = new AdminSpy();
    }

    public function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        $_SESSION = [];
        Router::reset();
        parent::tearDown();
    }

    public function test_handle_without_session_should_redirect_to_login(): void
    {
        $this->middleware->handle($this->request);

        $this->assertEquals('/login', $this->middleware->redirectedTo);
        $this->assertEquals(
            ['danger' => 'Você deve estar logado para acessar essa página'],
            FlashMessage::get()
        );
    }

    public function test_handle_with_participant_should_redirect_to_dashboard(): void
    {
        Auth::login($this->createUser('fulano@example.com', 'participant'));

        $this->middleware->handle($this->request);

        $this->assertEquals('/dashboard', $this->middleware->redirectedTo);
        $this->assertEquals(['danger' => 'Acesso restrito a administradores'], FlashMessage::get());
    }

    public function test_handle_with_admin_should_let_request_pass(): void
    {
        Auth::login($this->createUser('admin@example.com', 'admin'));

        $this->middleware->handle($this->request);

        $this->assertNull($this->middleware->redirectedTo);
        $this->assertEmpty(FlashMessage::get());
    }

    private function createUser(string $email, string $role): User
    {
        $user = new User([
            'name' => 'Usuário',
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }
}
