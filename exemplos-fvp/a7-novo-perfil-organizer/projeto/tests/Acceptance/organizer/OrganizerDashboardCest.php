<?php

namespace Tests\Acceptance\organizer;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class OrganizerDashboardCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $organizer = new User([
            'name' => 'Organizador',
            'email' => 'organizer@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'organizer'
        ]);
        $organizer->save();

        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();
    }

    public function accessOrganizerWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/organizer');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function accessOrganizerAsCommonUser(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/organizer');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Acesso restrito a organizadores');
    }

    public function accessOrganizerAsOrganizer(AcceptanceTester $page): void
    {
        $page->login('organizer@example.com', '123456');
        $page->amOnPage('/organizer');

        $page->see('Painel do Organizador', '//h1');
        $page->see('Olá, Organizador!');
        $page->see('Organização');
    }

    public function organizerCannotAccessAdmin(AcceptanceTester $page): void
    {
        $page->login('organizer@example.com', '123456');
        $page->amOnPage('/admin');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Acesso restrito a administradores');
    }
}
