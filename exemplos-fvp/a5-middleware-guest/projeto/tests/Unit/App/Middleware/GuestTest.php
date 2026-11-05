<?php

namespace Tests\Unit\App\Middleware;

use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Lib\Authentication\Auth;
use Tests\TestCase;

class GuestTest extends TestCase
{
    private User $user;
    private Request $request;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/login';
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

    public function test_handle_should_let_visitor_through_without_session(): void
    {
        $middleware = new GuestDouble();

        $middleware->handle($this->request);

        $this->assertNull($middleware->redirectedTo);
    }

    public function test_handle_should_redirect_logged_user_to_dashboard(): void
    {
        Auth::login($this->user);
        $middleware = new GuestDouble();

        $middleware->handle($this->request);

        $this->assertEquals('/dashboard', $middleware->redirectedTo);
    }
}
