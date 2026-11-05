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

    public function editOwnProfileFromNavbar(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->click('fulano@example.com');
        $page->click('Editar perfil');

        $page->seeCurrentUrlEquals('/profile/edit');
        $page->see('Editar Perfil', '//h1');
        $page->seeInField('user[name]', 'Fulano');

        $page->fillField('user[name]', 'Beltrano');
        $page->fillField('user[email]', 'beltrano@example.com');
        $page->click('Salvar');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Perfil atualizado com sucesso!');
        $page->see('Bem-vindo(a), Beltrano!');
        $page->see('beltrano@example.com', '//nav');
    }

    public function editProfileWithEmptyNameShowsError(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/profile/edit');

        $page->fillField('user[name]', '');
        $page->click('Salvar');

        $page->see('Existem dados incorretos!');
        $page->see('não pode ser vazio!');
        $page->seeInField('user[email]', 'fulano@example.com');
    }
}
