<?php

namespace Tests\Acceptance\passwords;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class ChangePasswordCest extends BaseAcceptanceCest
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

    public function accessChangePasswordWithoutLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/password/edit');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Você deve estar logado para acessar essa página');
    }

    public function changePasswordAndLoginWithTheNewOne(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/password/edit');
        $page->see('Trocar senha', '//h1');

        $page->fillField('user[password]', '654321');
        $page->fillField('user[password_confirmation]', '654321');
        $page->click('Salvar');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Senha alterada com sucesso!');

        $page->logout();
        $page->login('fulano@example.com', '654321');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Login realizado com sucesso!');
    }

    public function changePasswordWithMismatchedConfirmation(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/password/edit');

        $page->fillField('user[password]', '654321');
        $page->fillField('user[password_confirmation]', 'outra');
        $page->click('Salvar');

        $page->seeCurrentUrlEquals('/password');
        $page->see('Não foi possível alterar a senha.');
        $page->see('as senhas devem ser idênticas!');
    }
}
