<?php

namespace Tests\Integration\Controllers;

use App\Models\User;

class UsersControllerTest extends ControllerTestCase
{
    public function test_new_should_render_sign_up_form(): void
    {
        $response = $this->get(action: 'new', controllerName: 'App\Controllers\UsersController');

        $this->assertMatchesRegularExpression('/<h1>\s*Cadastro\s*<\/h1>/', $response);
        $this->assertStringContainsString('name="user[name]"', $response);
        $this->assertStringContainsString('Cadastrar', $response);
    }

    public function test_create_with_valid_data_should_save_and_redirect_to_login(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: 'App\Controllers\UsersController',
            params: ['user' => [
                'name' => 'Novo Usuário',
                'email' => 'novo@example.com',
                'password' => '123456',
                'password_confirmation' => '123456',
            ]]
        );

        $this->assertStringContainsString('Location: /login', $response);
        $this->assertNotNull(User::findByEmail('novo@example.com'));
        $this->assertCount(1, User::all());
    }

    public function test_create_with_invalid_data_should_render_form_with_errors(): void
    {
        $response = $this->post(
            action: 'create',
            controllerName: 'App\Controllers\UsersController',
            params: ['user' => [
                'name' => '',
                'email' => 'novo@example.com',
                'password' => '123456',
                'password_confirmation' => 'outra',
            ]]
        );

        $this->assertStringNotContainsString('Location:', $response);
        $this->assertStringContainsString('não pode ser vazio!', $response);
        $this->assertStringContainsString('as senhas devem ser idênticas!', $response);
        $this->assertStringContainsString('value="novo@example.com"', $response);
        $this->assertCount(0, User::all());
    }

    public function test_create_should_ignore_role_sent_in_the_request(): void
    {
        $this->post(
            action: 'create',
            controllerName: 'App\Controllers\UsersController',
            params: ['user' => [
                'name' => 'Esperto',
                'email' => 'esperto@example.com',
                'password' => '123456',
                'password_confirmation' => '123456',
                'role' => 'admin',
            ]]
        );

        $user = User::findByEmail('esperto@example.com');

        $this->assertNotNull($user);
        $this->assertEquals('participant', $user->role);
        $this->assertFalse($user->isAdmin());
    }
}
