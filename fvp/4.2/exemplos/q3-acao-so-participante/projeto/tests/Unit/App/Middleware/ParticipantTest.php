<?php

namespace Tests\Unit\App\Middleware;

use App\Models\User;
use Core\Constants\Constants;
use Core\Http\Request;
use Core\Router\Router;
use Lib\Authentication\Auth;
use Lib\FlashMessage;
use Tests\TestCase;

class ParticipantTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        // route() precisa das rotas carregadas.
        Router::reset();
        require Constants::rootPath()->join('config/routes.php');

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/events/1/enrollments';
        unset($_SESSION['user'], $_SESSION['flash']);
    }

    public function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_SESSION['user'], $_SESSION['flash']);
        Router::reset();
        parent::tearDown();
    }

    private function createUser(string $role): User
    {
        $user = new User([
            'name' => 'Usuário',
            'email' => "{$role}@example.com",
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    public function test_handle_with_admin_session_should_redirect_to_admin_with_flash(): void
    {
        Auth::login($this->createUser('admin'));
        $middleware = new ParticipantDouble();

        $middleware->handle(new Request());

        $this->assertSame('/admin', $middleware->location);
        $this->assertSame(['danger' => 'Ação disponível apenas para participantes!'], FlashMessage::get());
    }

    public function test_handle_with_participant_session_should_not_redirect(): void
    {
        Auth::login($this->createUser('participant'));
        $middleware = new ParticipantDouble();

        $middleware->handle(new Request());

        $this->assertNull($middleware->location);
        $this->assertSame([], FlashMessage::get());
    }

    public function test_handle_without_session_should_not_redirect(): void
    {
        $middleware = new ParticipantDouble();

        $middleware->handle(new Request());

        // Sem sessão o Participant deixa passar. Ele roda ANTES do Authenticate (o grupo interno
        // roda primeiro), e é o Authenticate, em seguida, que manda para /login.
        $this->assertNull($middleware->location);
        $this->assertSame([], FlashMessage::get());
    }
}
