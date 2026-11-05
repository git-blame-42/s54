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

        $inactive = new User([
            'name' => 'Inativo',
            'email' => 'inativo@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'active' => 0
        ]);
        $inactive->save();
    }

    public function loginWithInvalidCredentials(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', 'wrong');

        $page->seeCurrentUrlEquals('/login');
        $page->see('E-mail ou senha inválidos!');
    }

    public function loginWithInactiveUser(AcceptanceTester $page): void
    {
        $page->login('inativo@example.com', '123456');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Usuário inativo. Procure a administração.');
    }

    public function loginWithValidCredentials(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', '123456');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Login realizado com sucesso!');
        $page->see('fulano@example.com');
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
