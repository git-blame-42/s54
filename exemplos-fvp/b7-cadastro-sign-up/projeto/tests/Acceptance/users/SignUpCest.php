<?php

namespace Tests\Acceptance\users;

use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class SignUpCest extends BaseAcceptanceCest
{
    public function signUpWithValidDataAndLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/login');
        $page->click('Cadastre-se');
        $page->seeCurrentUrlEquals('/users/new');
        $page->see('Cadastro', '//h1');

        $page->fillField('user[name]', 'Novo Usuário');
        $page->fillField('user[email]', 'novo@example.com');
        $page->fillField('user[password]', '123456');
        $page->fillField('user[password_confirmation]', '123456');
        $page->click('Cadastrar');

        $page->seeCurrentUrlEquals('/login');
        $page->see('Cadastro realizado! Faça login.');

        $page->login('novo@example.com', '123456');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Bem-vindo(a), Novo Usuário!');
    }

    public function signUpWithInvalidDataShowsErrors(AcceptanceTester $page): void
    {
        $page->amOnPage('/users/new');

        $page->fillField('user[email]', 'novo@example.com');
        $page->fillField('user[password]', '123456');
        $page->fillField('user[password_confirmation]', 'outra');
        $page->click('Cadastrar');

        $page->seeCurrentUrlEquals('/users');
        $page->see('Existem dados incorretos!');
        $page->see('não pode ser vazio!');
        $page->see('as senhas devem ser idênticas!');
        $page->seeInField('user[email]', 'novo@example.com');
    }
}
