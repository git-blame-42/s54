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

    public function test_max_length(): void
    {
        $model = new class () extends Model {
            protected static array $columns = ['body'];
        };

        $model->body = str_repeat('á', 500); // @phpstan-ignore-line
        $this->assertTrue(Validations::maxLength('body', $model, 500));
        $this->assertNull($model->errors('body'));

        $model->body = str_repeat('á', 501);
        $this->assertFalse(Validations::maxLength('body', $model, 500));
        $this->assertEquals('deve ter no máximo 500 caracteres!', $model->errors('body'));
    }
}
