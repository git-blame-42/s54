<?php

namespace Tests\Integration\Controllers\Admin;

use App\Models\User;
use Lib\Authentication\Auth;
use Lib\FlashMessage;
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
        // O tearDown da base limpa só a sessão do usuário; a flash vazaria entre testes.
        unset($_SESSION['flash']);
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

    public function test_update_should_promote_participant_to_admin(): void
    {
        $user = User::findByEmail('participante1@example.com');

        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\Admin\UsersController',
            params: ['id' => $user->id, 'user' => ['role' => 'admin']]
        );

        $this->assertEquals('Location: /admin/users', $response);
        $this->assertTrue(User::findById($user->id)->isAdmin());
        $this->assertEquals(['success' => 'Perfil alterado com sucesso!'], FlashMessage::get());
    }

    public function test_update_should_demote_admin_to_participant(): void
    {
        $user = User::findByEmail('participante2@example.com');
        $user->changeRole('admin');

        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\Admin\UsersController',
            params: ['id' => $user->id, 'user' => ['role' => 'participant']]
        );

        $this->assertEquals('Location: /admin/users', $response);
        $this->assertFalse(User::findById($user->id)->isAdmin());
    }

    public function test_update_should_not_change_the_own_role(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\Admin\UsersController',
            params: ['id' => $this->admin->id, 'user' => ['role' => 'participant']]
        );

        $this->assertEquals('Location: /admin/users', $response);
        $this->assertTrue(User::findById($this->admin->id)->isAdmin());
        $this->assertEquals(['danger' => 'Você não pode alterar o próprio perfil.'], FlashMessage::get());
    }

    public function test_update_should_reject_unknown_role(): void
    {
        $user = User::findByEmail('participante3@example.com');

        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\Admin\UsersController',
            params: ['id' => $user->id, 'user' => ['role' => 'organizer']]
        );

        $this->assertEquals('Location: /admin/users', $response);
        $this->assertSame('participant', User::findById($user->id)->role);
        $this->assertEquals(['danger' => 'Não foi possível alterar o perfil.'], FlashMessage::get());
    }

    public function test_update_should_ignore_unknown_user(): void
    {
        $response = $this->put(
            action: 'update',
            controllerName: 'App\Controllers\Admin\UsersController',
            params: ['id' => 9999, 'user' => ['role' => 'admin']]
        );

        $this->assertEquals('Location: /admin/users', $response);
        $this->assertEquals(['danger' => 'Não foi possível alterar o perfil.'], FlashMessage::get());
    }

    public function test_index_should_not_render_role_form_in_own_row(): void
    {
        $response = $this->get(action: 'index', controllerName: 'App\Controllers\Admin\UsersController');

        $other = User::findByEmail('participante1@example.com');

        $this->assertStringContainsString('action="/admin/users/' . $other->id . '"', $response);
        $this->assertStringNotContainsString('action="/admin/users/' . $this->admin->id . '"', $response);
    }
}
