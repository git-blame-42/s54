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

    public function test_previous_login_at_should_be_null_on_first_login(): void
    {
        Auth::login($this->user);

        $this->assertNull(Auth::previousLoginAt());
    }

    public function test_previous_login_at_should_return_the_last_login_before_this_one(): void
    {
        $this->user->update(['last_login_at' => '2026-10-01 08:30:00']);

        Auth::login(User::findById($this->user->id));

        $this->assertEquals('2026-10-01 08:30:00', Auth::previousLoginAt());
        $this->assertNotEquals('2026-10-01 08:30:00', User::findById($this->user->id)->last_login_at);
    }

    public function test_logout_should_clear_previous_login_at(): void
    {
        $this->user->update(['last_login_at' => '2026-10-01 08:30:00']);
        Auth::login(User::findById($this->user->id));

        Auth::logout();

        $this->assertNull(Auth::previousLoginAt());
    }
}
