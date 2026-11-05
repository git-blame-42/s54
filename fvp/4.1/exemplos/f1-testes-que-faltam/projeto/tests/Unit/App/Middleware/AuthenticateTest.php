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

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/dashboard';
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

    public function test_handle_without_session_should_redirect_to_login(): void
    {
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request);

        $this->assertEquals('/login', $middleware->redirectedTo);
    }

    public function test_handle_without_session_should_set_danger_flash_message(): void
    {
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request);

        $this->assertEquals(
            ['danger' => 'Você deve estar logado para acessar essa página'],
            FlashMessage::get()
        );
    }

    public function test_handle_with_session_should_not_redirect(): void
    {
        $user = new User([
            'name' => 'User 1',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
        Auth::login($user);
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request);

        $this->assertNull($middleware->redirectedTo);
        $this->assertEmpty(FlashMessage::get());
    }
}
