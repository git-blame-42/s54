<?php

namespace Tests\Acceptance\authentications;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class LoginCest extends BaseAcceptanceCest
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

    public function loginWithInvalidCredentials(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', 'wrong');

        $page->seeCurrentUrlEquals('/login');
        $page->see('E-mail ou senha inválidos!');
    }

    public function loginWithValidCredentials(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Login realizado com sucesso!');
        $page->see('fulano@example.com');
    }

    public function loginWithTamperedCsrfToken(AcceptanceTester $page): void
    {
        $page->amOnPage('/login');
        $page->executeJS("document.querySelector('input[name=_token]').value = 'wrong';");
        $page->fillField('user[email]', 'fulano@example.com');
        $page->fillField('user[password]', '123456');
        $page->click('Entrar');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Sessão expirada, tente de novo.');
    }

    public function logout(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');
        $page->logout();

        $page->seeCurrentUrlEquals('/login');
        $page->see('Logout realizado com sucesso!');
        $page->see('Login');
    }
}
