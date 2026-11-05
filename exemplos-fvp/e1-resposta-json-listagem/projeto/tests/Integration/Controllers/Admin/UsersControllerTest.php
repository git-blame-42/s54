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

    public function test_index_should_return_json_when_client_accepts_json(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\UsersController');
        $json = json_decode($response, true);

        $this->assertIsArray($json);
        $this->assertArrayHasKey('users', $json);
        $this->assertArrayHasKey('pagination', $json);
        $this->assertCount(10, $json['users']);
        $this->assertEquals('admin@example.com', $json['users'][0]['email']);
        $this->assertEquals(['id', 'name', 'email', 'role', 'last_login_at'], array_keys($json['users'][0]));
        $this->assertEquals(1, $json['pagination']['page']);
        $this->assertEquals(2, $json['pagination']['total_of_pages']);
        $this->assertEquals(13, $json['pagination']['total_of_registers']);
        $this->assertStringNotContainsString('<table', $response);
    }

    public function test_index_should_return_json_for_the_requested_page(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';

        $response = $this->get(
            action: 'index',
            controllerName: 'App\Controllers\Admin\UsersController',
            params: ['page' => 2]
        );
        $json = json_decode($response, true);

        $this->assertCount(3, $json['users']);
        $this->assertEquals('participante10@example.com', $json['users'][0]['email']);
        $this->assertEquals(2, $json['pagination']['page']);
    }

    public function test_index_should_render_html_without_json_accept_header(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\UsersController');

        $this->assertNull(json_decode($response, true));
        $this->assertStringContainsString('<table', $response);
    }
}
