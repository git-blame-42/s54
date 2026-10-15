<?php

namespace Tests\Unit\Lib\Validations;

use PHPUnit\Framework\TestCase;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;

class ValidationsTest extends TestCase
{
    public function test_not_empty(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['name'];
        };

        $this->assertFalse(Validations::notEmpty('name', $model));

        $model->name = 'Diego'; // @phpstan-ignore-line
        $this->assertTrue(Validations::notEmpty('name', $model));
    }

    public function test_password_confirmation(): void
    {
        $model = new class () extends Model {
            protected ?string $password = null;
            protected ?string $password_confirmation = null;
        };

        $model->password = '123456'; // @phpstan-ignore-line
        $model->password_confirmation = 'wrong'; // @phpstan-ignore-line

        $this->assertFalse(Validations::passwordConfirmation($model));

        $model->password_confirmation = '123456'; // @phpstan-ignore-line
        $this->assertTrue(Validations::passwordConfirmation($model));
    }

    public function test_min_length_should_reject_short_value(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['password'];
        };

        $model->password = '123'; // @phpstan-ignore-line

        $this->assertFalse(Validations::minLength('password', 6, $model));
        $this->assertEquals('deve ter pelo menos 6 caracteres!', $model->errors('password'));
    }

    public function test_min_length_should_accept_value_with_min_or_more_chars(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['password'];
        };

        $model->password = '123456'; // @phpstan-ignore-line
        $this->assertTrue(Validations::minLength('password', 6, $model));

        $model->password = '1234567';
        $this->assertTrue(Validations::minLength('password', 6, $model));
    }

    public function test_min_length_should_ignore_null_value(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['password'];
        };

        $this->assertTrue(Validations::minLength('password', 6, $model));
    }
}
