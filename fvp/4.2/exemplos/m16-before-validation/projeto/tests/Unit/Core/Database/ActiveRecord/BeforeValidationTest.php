<?php

namespace Tests\Unit\Core\Database\ActiveRecord;

use PHPUnit\Framework\TestCase;

/**
 * Gancho Model::beforeValidation(): roda dentro de isValid(), antes de validates().
 * Não usa banco: isValid() só chama os dois métodos.
 */
class BeforeValidationTest extends TestCase
{
    public function test_is_valid_should_call_before_validation_before_validates(): void
    {
        $model = new TrimmingModel(['name' => 'Ana']);

        $this->assertTrue($model->isValid());
        $this->assertSame(['beforeValidation', 'validates'], $model->calls);
    }

    public function test_hook_should_run_on_every_is_valid_call(): void
    {
        $model = new TrimmingModel(['name' => 'Ana']);

        $model->isValid();
        $model->isValid();

        $this->assertSame(['beforeValidation', 'validates', 'beforeValidation', 'validates'], $model->calls);
    }

    public function test_validates_should_see_the_normalized_value(): void
    {
        $model = new TrimmingModel(['name' => "   \t\n"]);

        $this->assertFalse($model->isValid());
        $this->assertSame('não pode ser vazio!', $model->errors('name'));
        $this->assertSame('', $model->name);
    }

    public function test_normalized_value_should_stay_on_the_object(): void
    {
        $model = new TrimmingModel(['name' => '  Ana  ']);

        $this->assertTrue($model->isValid());
        $this->assertSame('Ana', $model->name);
    }

    public function test_hook_should_not_touch_null(): void
    {
        $model = new TrimmingModel();

        $this->assertFalse($model->isValid());
        $this->assertNull($model->name);
        $this->assertSame('não pode ser vazio!', $model->errors('name'));
    }

    public function test_default_hook_should_change_nothing(): void
    {
        $model = new PlainModel(['name' => '   ']);

        $this->assertTrue($model->isValid());
        $this->assertSame('   ', $model->name);
    }
}
