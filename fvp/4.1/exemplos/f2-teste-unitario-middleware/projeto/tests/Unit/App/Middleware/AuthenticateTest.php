<?php

namespace Tests\Unit\App\Middleware;

use Lib\Authentication\Auth;
use Lib\FlashMessage;

class AuthenticateTest extends MiddlewareTestCase
{
    public function test_handle_without_session_should_redirect_to_login_with_flash(): void
    {
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request());

        $this->assertEquals('/login', $middleware->location);
        $this->assertEquals(
            ['danger' => 'Você deve estar logado para acessar essa página'],
            FlashMessage::get()
        );
    }

    public function test_handle_with_participant_session_should_not_redirect(): void
    {
        Auth::login($this->createUser('participant'));
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request());

        $this->assertNull($middleware->location);
        $this->assertEquals([], FlashMessage::get());
    }

    public function test_handle_with_admin_session_should_not_redirect(): void
    {
        Auth::login($this->createUser('admin'));
        $middleware = new AuthenticateDouble();

        $middleware->handle($this->request());

        $this->assertNull($middleware->location);
        $this->assertEquals([], FlashMessage::get());
    }
}
