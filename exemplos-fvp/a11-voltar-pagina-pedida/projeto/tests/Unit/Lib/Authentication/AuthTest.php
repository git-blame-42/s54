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

    public function test_pull_intended_url_should_return_remembered_path_and_clear_it(): void
    {
        Auth::rememberIntendedUrl('/admin');

        $this->assertSame('/admin', Auth::pullIntendedUrl('/dashboard'));
        $this->assertArrayNotHasKey('redirect_to', $_SESSION);
    }

    public function test_pull_intended_url_without_remembered_path_should_return_default(): void
    {
        $this->assertSame('/dashboard', Auth::pullIntendedUrl('/dashboard'));
    }

    public function test_remember_intended_url_should_ignore_external_urls(): void
    {
        foreach (['https://evil.example.com/', '//evil.example.com', '/\\evil.example.com', 'admin'] as $url) {
            Auth::rememberIntendedUrl($url);

            $this->assertArrayNotHasKey('redirect_to', $_SESSION, $url);
        }
    }

    public function test_pull_intended_url_should_ignore_external_url_in_session(): void
    {
        $_SESSION['redirect_to'] = 'https://evil.example.com/';

        $this->assertSame('/dashboard', Auth::pullIntendedUrl('/dashboard'));
        $this->assertArrayNotHasKey('redirect_to', $_SESSION);
    }
}
