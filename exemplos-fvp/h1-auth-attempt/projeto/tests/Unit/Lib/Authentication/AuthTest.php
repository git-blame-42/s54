<?php

namespace Tests\Unit\Lib\Authentication;

use Lib\Authentication\Auth;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
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
        parent::setUp();
        $_SESSION = [];
    }

    public function test_login(): void
    {
        Auth::login($this->user);

        $this->assertEquals(1, $_SESSION['user']['id']);
        $this->assertNotNull(User::findById($this->user->id)->last_login_at);
    }

    public function test_user(): void
    {
        Auth::login($this->user);

        $userFromSession = Auth::user();

        $this->assertEquals($this->user->id, $userFromSession->id);
    }

    public function test_check(): void
    {
        Auth::login($this->user);

        $this->assertTrue(Auth::check());
    }

    public function test_logout(): void
    {
        Auth::login($this->user);
        Auth::logout();

        $this->assertFalse(Auth::check());
    }

    public function test_attempt_with_valid_credentials_should_login(): void
    {
        $result = Auth::attempt('fulano@example.com', '123456');

        $this->assertTrue($result);
        $this->assertTrue(Auth::check());
        $this->assertEquals($this->user->id, Auth::user()->id);
    }

    public function test_attempt_with_wrong_password_should_not_login(): void
    {
        $result = Auth::attempt('fulano@example.com', 'senha-errada');

        $this->assertFalse($result);
        $this->assertFalse(Auth::check());
        $this->assertArrayNotHasKey('user', $_SESSION);
    }

    public function test_attempt_with_unknown_email_should_not_login(): void
    {
        $result = Auth::attempt('ninguem@example.com', '123456');

        $this->assertFalse($result);
        $this->assertFalse(Auth::check());
        $this->assertArrayNotHasKey('user', $_SESSION);
    }
}
