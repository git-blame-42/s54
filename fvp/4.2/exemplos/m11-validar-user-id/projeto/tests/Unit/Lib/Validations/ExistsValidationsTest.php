<?php

namespace Tests\Unit\Lib\Validations;

use App\Models\User;
use Core\Database\ActiveRecord\Model;
use Lib\Validations;
use Tests\TestCase;

/**
 * Validations::exists: o atributo deve ser o id de um registro do model informado (aqui, User).
 */
class ExistsValidationsTest extends TestCase
{
    private Model $model;
    private User $admin;
    private User $participant;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = new class () extends Model {
            protected static array $columns = ['user_id'];
        };

        $this->admin = $this->createUser('admin@example.com', 'admin');
        $this->participant = $this->createUser('participante@example.com', 'participant');
    }

    private function createUser(string $email, string $role): User
    {
        $user = new User([
            'name' => 'Usuário',
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => $role,
        ]);
        $user->save();

        return $user;
    }

    public function test_exists_should_accept_an_existing_id(): void
    {
        $this->model->user_id = $this->admin->id; // @phpstan-ignore-line

        $this->assertTrue(Validations::exists('user_id', User::class, $this->model));
        $this->assertNull($this->model->errors('user_id'));
    }

    public function test_exists_should_accept_a_numeric_string_id(): void
    {
        $this->model->user_id = (string) $this->admin->id; // @phpstan-ignore-line

        $this->assertTrue(Validations::exists('user_id', User::class, $this->model));
    }

    public function test_exists_should_reject_an_unknown_id(): void
    {
        $this->model->user_id = 9999; // @phpstan-ignore-line

        $this->assertFalse(Validations::exists('user_id', User::class, $this->model));
        $this->assertEquals('não existe!', $this->model->errors('user_id'));
    }

    public function test_exists_should_reject_null_text_and_array_values(): void
    {
        $this->assertFalse(Validations::exists('user_id', User::class, $this->model));

        $this->model->user_id = 'abc'; // @phpstan-ignore-line
        $this->assertFalse(Validations::exists('user_id', User::class, $this->model));

        $this->model->user_id = [$this->admin->id]; // @phpstan-ignore-line
        $this->assertFalse(Validations::exists('user_id', User::class, $this->model));
    }

    public function test_exists_should_apply_extra_conditions(): void
    {
        $this->model->user_id = $this->participant->id; // @phpstan-ignore-line
        $this->assertFalse(Validations::exists('user_id', User::class, $this->model, ['role' => 'admin']));

        $this->model->user_id = $this->admin->id; // @phpstan-ignore-line
        $this->assertTrue(Validations::exists('user_id', User::class, $this->model, ['role' => 'admin']));
    }
}
