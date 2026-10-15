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

    public function test_login_should_store_last_activity(): void
    {
        Auth::login($this->user);

        $this->assertEqualsWithDelta(time(), $_SESSION['user']['last_activity'], 1);
    }

    public function test_check_with_recent_activity_should_be_true(): void
    {
        Auth::login($this->user);
        $_SESSION['user']['last_activity'] = time() - Auth::TIMEOUT + 60;

        $this->assertTrue(Auth::check());
    }

    public function test_check_after_timeout_should_be_false_and_clear_session(): void
    {
        Auth::login($this->user);
        $_SESSION['user']['last_activity'] = time() - Auth::TIMEOUT - 1;

        $this->assertFalse(Auth::check());
        $this->assertArrayNotHasKey('user', $_SESSION);
        $this->assertTrue(Auth::wasExpired());
    }

    public function test_check_should_renew_last_activity(): void
    {
        Auth::login($this->user);
        $_SESSION['user']['last_activity'] = time() - 600;

        Auth::check();

        $this->assertEqualsWithDelta(time(), $_SESSION['user']['last_activity'], 1);
    }

    public function test_was_expired_should_be_consumed_after_first_read(): void
    {
        Auth::login($this->user);
        $_SESSION['user']['last_activity'] = time() - Auth::TIMEOUT - 1;
        Auth::check();

        $this->assertTrue(Auth::wasExpired());
        $this->assertFalse(Auth::wasExpired());
    }

    public function test_login_should_clear_expired_flag(): void
    {
        $_SESSION['auth_expired'] = true;

        Auth::login($this->user);

        $this->assertFalse(Auth::wasExpired());
    }

    public function test_logout_should_clear_whole_user_session(): void
    {
        Auth::login($this->user);
        Auth::logout();

        $this->assertArrayNotHasKey('user', $_SESSION);
        $this->assertFalse(Auth::wasExpired());
    }
}
