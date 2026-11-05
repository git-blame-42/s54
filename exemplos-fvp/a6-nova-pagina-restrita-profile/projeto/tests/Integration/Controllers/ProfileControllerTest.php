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
        Auth::login($this->user);
    }

    public function test_show_should_render_current_user_data(): void
    {
        $response = $this->get(action: 'show', controllerName: 'App\Controllers\ProfileController');

        $this->assertMatchesRegularExpression('/<h1>\s*Meu Perfil\s*<\/h1>/', $response);
        $this->assertStringContainsString('Nome: Fulano', $response);
        $this->assertStringContainsString('E-mail: fulano@example.com', $response);
        $this->assertStringContainsString('Perfil: Participante', $response);
    }

    public function test_show_should_render_last_login(): void
    {
        $response = $this->get(action: 'show', controllerName: 'App\Controllers\ProfileController');

        $this->assertStringContainsString('Último acesso:', $response);
        $this->assertStringNotContainsString('primeiro acesso', $response);
    }
}
