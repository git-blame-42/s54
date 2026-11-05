<?php

namespace Tests\Unit\Lib;

use Lib\ConfirmationCode;
use PHPUnit\Framework\TestCase;

class ConfirmationCodeTest extends TestCase
{
    public function setUp(): void
    {
        unset($_SESSION['confirmation_codes']);
    }

    public function tearDown(): void
    {
        unset($_SESSION['confirmation_codes']);
    }

    public function test_generate_should_return_a_four_digit_code(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $code = ConfirmationCode::generate('event.1');

            $this->assertGreaterThanOrEqual(1000, $code);
            $this->assertLessThanOrEqual(9999, $code);
        }
    }

    public function test_generate_should_store_the_code_in_the_session(): void
    {
        $code = ConfirmationCode::generate('event.1');

        $this->assertSame($code, ConfirmationCode::current('event.1'));
        $this->assertSame($code, $_SESSION['confirmation_codes']['event.1']);
    }

    public function test_current_should_be_null_without_a_code(): void
    {
        $this->assertNull(ConfirmationCode::current('event.1'));
    }

    public function test_each_key_should_have_its_own_code(): void
    {
        $first = ConfirmationCode::generate('event.1');
        ConfirmationCode::generate('event.2');

        $this->assertSame($first, ConfirmationCode::current('event.1'));
        $this->assertTrue(ConfirmationCode::check('event.1', (string) $first));
        $this->assertNotNull(ConfirmationCode::current('event.2'));
    }

    public function test_check_should_accept_the_right_code_as_string_or_int(): void
    {
        $code = ConfirmationCode::generate('event.1');

        $this->assertTrue(ConfirmationCode::check('event.1', (string) $code));
        $this->assertTrue(ConfirmationCode::check('event.1', " {$code} "));
        $this->assertTrue(ConfirmationCode::check('event.1', $code));
    }

    public function test_check_should_reject_a_wrong_code(): void
    {
        $code = ConfirmationCode::generate('event.1');
        $wrong = $code === 9999 ? 1000 : $code + 1;

        $this->assertFalse(ConfirmationCode::check('event.1', (string) $wrong));
        $this->assertFalse(ConfirmationCode::check('event.1', ''));
        $this->assertFalse(ConfirmationCode::check('event.1', null));
        $this->assertFalse(ConfirmationCode::check('event.1', [(string) $code]));
    }

    public function test_check_should_reject_the_code_of_another_key(): void
    {
        $code = ConfirmationCode::generate('event.1');

        $this->assertFalse(ConfirmationCode::check('event.2', (string) $code));
    }

    public function test_check_without_a_generated_code_should_be_false(): void
    {
        $this->assertFalse(ConfirmationCode::check('event.1', '1234'));
    }

    public function test_generate_again_should_replace_the_previous_code(): void
    {
        ConfirmationCode::generate('event.1');
        $second = ConfirmationCode::generate('event.1');

        $this->assertSame($second, ConfirmationCode::current('event.1'));
    }

    public function test_clear_should_remove_only_the_given_key(): void
    {
        $code = ConfirmationCode::generate('event.1');
        ConfirmationCode::generate('event.2');

        ConfirmationCode::clear('event.2');

        $this->assertNull(ConfirmationCode::current('event.2'));
        $this->assertFalse(ConfirmationCode::check('event.2', '1234'));
        $this->assertTrue(ConfirmationCode::check('event.1', (string) $code));
    }
}
