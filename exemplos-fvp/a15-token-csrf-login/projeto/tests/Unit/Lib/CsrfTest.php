<?php

namespace Tests\Unit\Lib;

use Lib\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    public function tearDown(): void
    {
        unset($_SESSION['_token']);
    }

    public function test_token_should_be_generated_and_stored_in_session(): void
    {
        $token = Csrf::token();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $_SESSION['_token']);
    }

    public function test_token_should_be_stable_within_session(): void
    {
        $this->assertSame(Csrf::token(), Csrf::token());
    }

    public function test_check_with_session_token_should_return_true(): void
    {
        $token = Csrf::token();

        $this->assertTrue(Csrf::check($token));
    }

    public function test_check_with_wrong_token_should_return_false(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::check('wrong'));
    }

    public function test_check_without_token_should_return_false(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::check(null));
        $this->assertFalse(Csrf::check(''));
        $this->assertFalse(Csrf::check(['array']));
    }

    public function test_check_without_session_token_should_return_false(): void
    {
        $this->assertFalse(Csrf::check('abc'));
    }
}
