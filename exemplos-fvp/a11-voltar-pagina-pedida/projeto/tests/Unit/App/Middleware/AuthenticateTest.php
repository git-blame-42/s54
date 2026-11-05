<?php

namespace Tests\Unit\App\Middleware;

use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Lib\Authentication\Auth;
use Tests\TestCase;

class AuthenticateTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');
    }

    public function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
        Router::reset();
        parent::tearDown();
    }

    private function request(string $method, string $uri): Request
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;

        return new Request();
    }

    public function test_get_without_login_should_remember_requested_page_and_redirect_to_login(): void
    {
        $middleware = new AuthenticateSpy();

        $middleware->handle($this->request('GET', '/admin'));

        $this->assertSame('/login', $middleware->redirectedTo);
        $this->assertSame('/admin', $_SESSION['redirect_to']);
    }

    public function test_post_without_login_should_not_remember_requested_page(): void
    {
        $middleware = new AuthenticateSpy();

        $middleware->handle($this->request('POST', '/dashboard'));

        $this->assertSame('/login', $middleware->redirectedTo);
        $this->assertArrayNotHasKey('redirect_to', $_SESSION);
    }

    public function test_logged_in_user_should_pass_without_redirect(): void
    {
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
        Auth::login($user);
        $middleware = new AuthenticateSpy();

        $middleware->handle($this->request('GET', '/dashboard'));

        $this->assertNull($middleware->redirectedTo);
        $this->assertArrayNotHasKey('redirect_to', $_SESSION);
    }
}
