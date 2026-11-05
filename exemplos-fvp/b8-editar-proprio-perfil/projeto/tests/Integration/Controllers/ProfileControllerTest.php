<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

class ProfileControllerTest extends ControllerTestCase
{
    private User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();

        $other = new User([
            'name' => 'Ciclano',
            'email' => 'ciclano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $other->save();

        Auth::login($this->user);
    }

    public function test_edit_should_render_form_with_current_values(): void
    {
        $response = $this->get(action: 'edit', controllerName: 'App\Controllers\ProfileController');

        $this->assertMatchesRegularExpression('/<h1>\s*Editar Perfil\s*<\/h1>/', $response);
        $this->assertStringContainsString('value="Fulano"', $response);
        $this->assertStringContainsString('value="fulano@example.com"', $response);
        $this->assertStringContainsString('name="_method" value="PUT"', $response);
        $this->assertStringContainsString('novalidate', $response);
    }

    public function test_update_with_valid_data_should_save_and_redirect_to_dashboard(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\ProfileController',
            params: ['user' => ['name' => 'Novo Nome', 'email' => 'novo@example.com']]
        );

        $this->assertStringContainsString('Location: /dashboard', $response);

        $saved = User::findById($this->user->id);
        $this->assertNotNull($saved);
        $this->assertEquals('Novo Nome', $saved->name);
        $this->assertEquals('novo@example.com', $saved->email);
    }

    public function test_update_with_email_of_another_user_should_render_uniqueness_error(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\ProfileController',
            params: ['user' => ['name' => 'Fulano', 'email' => 'ciclano@example.com']]
        );

        $this->assertStringNotContainsString('Location:', $response);
        $this->assertStringContainsString('já existe um registro com esse dado', $response);
        $this->assertStringContainsString('value="ciclano@example.com"', $response);

        $saved = User::findById($this->user->id);
        $this->assertNotNull($saved);
        $this->assertEquals('fulano@example.com', $saved->email);
    }

    public function test_update_with_empty_name_should_render_errors_keeping_values(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\ProfileController',
            params: ['user' => ['name' => '', 'email' => 'outro@example.com']]
        );

        $this->assertStringNotContainsString('Location:', $response);
        $this->assertStringContainsString('não pode ser vazio!', $response);
        $this->assertStringContainsString('value="outro@example.com"', $response);

        $saved = User::findById($this->user->id);
        $this->assertNotNull($saved);
        $this->assertEquals('Fulano', $saved->name);
    }
}
