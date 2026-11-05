<?php

namespace Tests\Integration\Controllers;

use App\Models\User;
use Lib\Authentication\Auth;

class HomeControllerTest extends ControllerTestCase
{
    public function test_render_home_page(): void
    {
        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\HomeController'
        );

        $this->assertMatchesRegularExpression('/<h1>\s*Home Page\s*<\/h1>/', $response);
        $this->assertMatchesRegularExpression('/TSI3D Framework Template/', $response);
    }

    public function test_index_when_authenticated_should_redirect_to_dashboard(): void
    {
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
        Auth::login($user);

        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\HomeController'
        );

        $this->assertStringContainsString('Location: /dashboard', $response);
        $this->assertStringNotContainsString('TSI3D Framework Template', $response);
    }
}
