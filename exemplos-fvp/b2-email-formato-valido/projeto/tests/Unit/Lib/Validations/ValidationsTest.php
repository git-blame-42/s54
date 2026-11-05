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

    public function test_email_should_accept_valid_address(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['email'];
        };

        $model->email = 'fulano@example.com'; // @phpstan-ignore-line
        $this->assertTrue(Validations::email('email', $model));
    }

    public function test_email_should_reject_invalid_address(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['email'];
        };

        $model->email = 'nao-e-email'; // @phpstan-ignore-line
        $this->assertFalse(Validations::email('email', $model));
        $this->assertEquals('deve ser um e-mail válido!', $model->errors('email'));
    }

    public function test_email_should_ignore_empty_value(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['email'];
        };

        $this->assertTrue(Validations::email('email', $model));

        $model->email = ''; // @phpstan-ignore-line
        $this->assertTrue(Validations::email('email', $model));
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
}
