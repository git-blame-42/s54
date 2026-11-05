<?php

namespace Tests\Acceptance\admin;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class AdminUsersCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $admin = new User([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $admin->save();

        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();

        for ($i = 1; $i <= 10; $i++) {
            (new User([
                // Zero à esquerda: com a listagem ordenada por nome, "10" não vem antes de "2".
                'name' => sprintf('Participante %02d', $i),
                'email' => "participante{$i}@example.com",
                'password' => '123456',
                'password_confirmation' => '123456'
            ]))->save();
        }
    }

    public function accessUsersListWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/admin/users');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function accessUsersListAsCommonUser(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/admin/users');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Acesso restrito a administradores');
    }

    public function listUsersAsAdmin(AcceptanceTester $page): void
    {
        $page->login('admin@example.com', '123456');
        $page->click('Usuários');

        $page->seeCurrentUrlEquals('/admin/users');
        $page->see('Usuários', '//h1');
        $page->see('admin@example.com');
        $page->see('fulano@example.com');
        $page->see('Mostrando 1 - 10 de 12');
    }

    public function navigateToTheSecondPage(AcceptanceTester $page): void
    {
        $page->login('admin@example.com', '123456');
        $page->amOnPage('/admin/users');

        // O link fica na borda inferior da janela do Selenium; conferir o href e
        // navegar evita o "element click intercepted" do Chrome headless.
        $page->seeLink('Próxima', '/admin/users/page/2');
        $page->amOnPage('/admin/users/page/2');

        $page->seeCurrentUrlEquals('/admin/users/page/2');
        $page->see('participante9@example.com');
        $page->see('participante10@example.com');
        $page->dontSee('fulano@example.com');
        $page->see('Mostrando 11 - 12 de 12');
    }
}
