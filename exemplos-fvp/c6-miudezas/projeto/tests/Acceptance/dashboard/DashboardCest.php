<?php

namespace Tests\Acceptance\dashboard;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class DashboardCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
    }

    public function accessDashboardWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/dashboard');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function accessDashboardAfterLogin(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/dashboard');

        $page->see('Painel do Usuário', '//h1');
        $page->see('Bem-vindo(a), Fulano!');
        $page->see('Membro desde');
        $page->dontSee('Você é administrador(a).');
    }

    public function accessDashboardAsAdminShowsAdminOnlyContent(AcceptanceTester $page): void
    {
        $admin = new User([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin'
        ]);
        $admin->save();

        $page->login('admin@example.com', '123456');
        $page->amOnPage('/dashboard');

        $page->see('Você é administrador(a).');
        $page->click('Ir para a área administrativa');
        $page->seeCurrentUrlEquals('/admin');
    }
}
