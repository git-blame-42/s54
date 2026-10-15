<?php

namespace Tests\Unit\App\Middleware;

use Lib\Authentication\Auth;
use Lib\FlashMessage;

class AdminTest extends MiddlewareTestCase
{
    public function test_handle_without_session_should_not_redirect(): void
    {
        // Sem sessão, quem nega o acesso é o Authenticate, que roda no mesmo grupo de rotas.
        $middleware = new AdminDouble();

        $middleware->handle($this->request());

        $this->assertNull($middleware->location);
        $this->assertEquals([], FlashMessage::get());
    }

    public function test_handle_with_participant_session_should_redirect_to_dashboard_with_flash(): void
    {
        Auth::login($this->createUser('participant'));
        $middleware = new AdminDouble();

        $middleware->handle($this->request());

        $this->assertEquals('/dashboard', $middleware->location);
        $this->assertEquals(
            ['danger' => 'Acesso restrito a administradores'],
            FlashMessage::get()
        );
    }

    public function test_handle_with_admin_session_should_not_redirect(): void
    {
        Auth::login($this->createUser('admin'));
        $middleware = new AdminDouble();

        $middleware->handle($this->request());

        $this->assertNull($middleware->location);
        $this->assertEquals([], FlashMessage::get());
    }
}
