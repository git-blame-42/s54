<?php

namespace Tests\Unit\Lib;

use Lib\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    public function setUp(): void
    {
        unset($_SESSION['_token']);
    }

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
        $this->assertTrue(Csrf::check(Csrf::token()));
    }

    public function test_check_with_wrong_token_should_return_false(): void
    {
        $token = Csrf::token();

        $this->assertFalse(Csrf::check(strrev($token)));
        $this->assertFalse(Csrf::check(substr($token, 0, 63)));
    }

    public function test_check_without_token_should_return_false(): void
    {
        Csrf::token();

        $this->assertFalse(Csrf::check(null));
        $this->assertFalse(Csrf::check(''));
        $this->assertFalse(Csrf::check([Csrf::token()]));
    }

    public function test_check_without_session_token_should_return_false(): void
    {
        $this->assertFalse(Csrf::check(''));
        $this->assertFalse(Csrf::check('abc'));
    }

    public function test_csrf_field_should_render_a_hidden_input_with_the_token(): void
    {
        $this->assertSame(
            '<input type="hidden" name="_token" value="' . Csrf::token() . '">',
            csrf_field()
        );
    }
}
