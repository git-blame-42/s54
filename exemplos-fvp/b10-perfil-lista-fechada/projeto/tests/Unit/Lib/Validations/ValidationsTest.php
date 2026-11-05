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

    public function test_inclusion_should_accept_allowed_value(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['role'];
        };

        $model->role = 'admin'; // @phpstan-ignore-line
        $this->assertTrue(Validations::inclusion('role', ['admin', 'participant'], $model));
        $this->assertFalse($model->hasErrors());
    }

    public function test_inclusion_should_reject_value_outside_the_list(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['role'];
        };

        $model->role = 'ghost'; // @phpstan-ignore-line
        $this->assertFalse(Validations::inclusion('role', ['admin', 'participant'], $model));
        $this->assertEquals('não é um valor válido!', $model->errors('role'));
    }

    public function test_inclusion_should_reject_null_and_empty_string(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['role'];
        };

        $this->assertFalse(Validations::inclusion('role', ['admin'], $model));

        $model->role = ''; // @phpstan-ignore-line
        $this->assertFalse(Validations::inclusion('role', ['admin'], $model));
        $this->assertEquals('não é um valor válido!', $model->errors('role'));
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
