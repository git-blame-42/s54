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

    public function loginWithWrongPassword(AcceptanceTester $page): void
    {
        $page->login('fulano@example.com', 'wrong');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Senha incorreta!');
    }

    public function loginWithUnknownEmail(AcceptanceTester $page): void
    {
        $page->login('ninguem@example.com', '123456');

        $page->seeCurrentUrlEquals('/login');
        $page->see('E-mail não cadastrado!');
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

        $page->seeCurrentUrlEquals('/');
        $page->see('Logout realizado com sucesso!');
        $page->see('Login');
    }
}
