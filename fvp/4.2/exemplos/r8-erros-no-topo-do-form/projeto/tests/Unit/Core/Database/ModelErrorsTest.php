<?php

namespace Tests\Unit\Core\Database;

use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use PHPUnit\Framework\TestCase;

class ModelErrorsTest extends TestCase
{
    private function newModel(): Model
    {
        return new class () extends Model {
            protected static array $columns = ['name', 'email'];

            public function validates(): void
            {
                Validations::notEmpty('name', $this);
                Validations::notEmpty('email', $this);
            }
        };
    }

    public function test_all_errors_should_be_empty_without_errors(): void
    {
        $model = $this->newModel();

        $this->assertSame([], $model->allErrors());
    }

    public function test_all_errors_should_return_every_error_in_order(): void
    {
        $model = $this->newModel();

        $this->assertFalse($model->isValid());
        $this->assertSame(
            ['name' => 'não pode ser vazio!', 'email' => 'não pode ser vazio!'],
            $model->allErrors()
        );
    }

    public function test_all_errors_should_be_cleared_by_a_new_validation(): void
    {
        $model = $this->newModel();
        $model->isValid();

        $model->name = 'Fulano'; // @phpstan-ignore-line
        $model->email = 'fulano@example.com'; // @phpstan-ignore-line

        $this->assertTrue($model->isValid());
        $this->assertSame([], $model->allErrors());
    }

    public function test_all_errors_should_include_errors_added_by_hand(): void
    {
        $model = $this->newModel();
        $model->addError('base', 'erro geral');

        $this->assertSame(['base' => 'erro geral'], $model->allErrors());
    }
}
