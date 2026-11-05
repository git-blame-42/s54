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
    }

    public function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        $_SESSION = [];
        Router::reset();
        parent::tearDown();
    }

    public function test_handle_with_participant_should_redirect_to_dashboard(): void
    {
        Auth::login($this->createUser('participant'));
        $middleware = new AdminDouble();

        $middleware->handle($this->request);

        $this->assertEquals('/dashboard', $middleware->redirectedTo);
    }

    public function test_handle_with_participant_should_set_danger_flash_message(): void
    {
        Auth::login($this->createUser('participant'));
        $middleware = new AdminDouble();

        $middleware->handle($this->request);

        $this->assertEquals(['danger' => 'Acesso restrito a administradores'], FlashMessage::get());
    }

    public function test_handle_with_admin_should_not_redirect(): void
    {
        Auth::login($this->createUser('admin'));
        $middleware = new AdminDouble();

        $middleware->handle($this->request);

        $this->assertNull($middleware->redirectedTo);
        $this->assertEmpty(FlashMessage::get());
    }

    public function test_handle_without_session_should_not_redirect(): void
    {
        // Sem sessão quem barra é o Authenticate; o Admin só deixa passar.
        $middleware = new AdminDouble();

        $middleware->handle($this->request);

        $this->assertNull($middleware->redirectedTo);
        $this->assertEmpty(FlashMessage::get());
    }

    private function createUser(string $role): User
    {
        $user = new User([
            'name' => 'User 1',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role
        ]);
        $user->save();

        return $user;
    }
}
