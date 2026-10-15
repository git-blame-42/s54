<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\User;
use Lib\Authentication\Auth;
use Tests\Integration\Controllers\ControllerTestCase;

class UsersControllerTest extends ControllerTestCase
{
    private User $admin;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $this->admin->save();

        for ($i = 1; $i <= 12; $i++) {
            (new User([
                'name' => "Participante {$i}",
                'email' => "participante{$i}@example.com",
                'password' => '123456',
                'password_confirmation' => '123456'
            ]))->save();
        }

        Auth::login($this->admin);
    }

    public function test_index_should_list_the_first_page_of_users(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\UsersController');

        $this->assertMatchesRegularExpression('/<h1>\s*Usuários\s*<\/h1>/', $response);
        $this->assertStringContainsString('admin@example.com', $response);
        $this->assertStringContainsString('participante9@example.com', $response);
        $this->assertStringNotContainsString('participante10@example.com', $response);
        $this->assertStringContainsString('Mostrando 1 - 10 de 13', $response);
    }

    public function test_index_should_list_the_requested_page(): void
    {
        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\Admin\UsersController',
            params: ['page' => 2]
        );

        // O e-mail do admin logado aparece na navbar; conferimos um participante da página 1.
        $this->assertStringNotContainsString('participante9@example.com', $response);
        $this->assertStringContainsString('participante10@example.com', $response);
        $this->assertStringContainsString('participante12@example.com', $response);
        $this->assertStringContainsString('Mostrando 11 - 13 de 13', $response);
    }

    public function test_index_should_render_pagination_links_with_the_admin_route(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\UsersController');

        $this->assertStringContainsString('href="/admin/users/page/2"', $response);
    }
}
