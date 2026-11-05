<?php

namespace Tests\Acceptance\profile;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class ProfileCest extends BaseAcceptanceCest
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

    public function accessProfileWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/profile');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function accessProfileAfterLogin(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/profile');

        $page->see('Meu Perfil', '//h1');
        $page->see('Nome: Fulano');
        $page->see('E-mail: fulano@example.com');
    }

    public function navbarLinksToProfile(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->click('fulano@example.com');
        $page->click('Perfil');

        $page->seeCurrentUrlEquals('/profile');
        $page->see('Meu Perfil', '//h1');
    }
}
