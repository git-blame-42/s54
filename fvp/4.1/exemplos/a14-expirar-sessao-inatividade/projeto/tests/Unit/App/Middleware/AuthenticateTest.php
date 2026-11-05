<?php

namespace Tests\Unit\App\Middleware;

use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Lib\Authentication\Auth;
use Lib\FlashMessage;
use Tests\TestCase;

class AuthenticateTest extends TestCase
{
    private Request $request;
    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $this->request = new Request();

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
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);
        $_SESSION = [];
        Router::reset();
        parent::tearDown();
    }

    public function test_handle_with_recent_activity_should_not_redirect(): void
    {
        Auth::login($this->user);
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request);

        $this->assertNull($middleware->redirectedTo);
        $this->assertEmpty(FlashMessage::get());
    }

    public function test_handle_after_timeout_should_redirect_with_expired_message(): void
    {
        Auth::login($this->user);
        $_SESSION['user']['last_activity'] = time() - Auth::TIMEOUT - 1;
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request);

        $this->assertEquals('/login', $middleware->redirectedTo);
        $this->assertEquals(['danger' => 'Sua sessão expirou. Faça login novamente.'], FlashMessage::get());
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function test_handle_without_session_should_redirect_with_login_message(): void
    {
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request);

        $this->assertEquals('/login', $middleware->redirectedTo);
        $this->assertEquals(['danger' => 'Você deve estar logado para acessar essa página'], FlashMessage::get());
    }
}
