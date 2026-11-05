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

    public function loginAsksForTheCodeAfterThePassword(AcceptanceTester $page): void
    {
        $page->wantTo('Ver a tela do código depois da senha certa, sem estar logado');
        $page->submitCredentials('fulano@example.com', '123456');

        $page->seeCurrentUrlEquals('/login/code');
        $page->see('Código de acesso', 'h1');
        $page->see('Seu código de acesso é', '.alert-success');

        $page->amOnPage('/dashboard');
        $page->seeCurrentUrlEquals('/login');
    }

    public function loginWithWrongCodeThenRightCode(AcceptanceTester $page): void
    {
        $page->wantTo('Errar o código uma vez e acertar na segunda');
        $page->submitCredentials('fulano@example.com', '123456');
        $code = $page->grabLoginCode();
        $wrong = $code === '9999' ? '1000' : (string) ((int) $code + 1);

        $page->fillField('code', $wrong);
        $page->click('Confirmar');
        $page->seeCurrentUrlEquals('/login/code');
        $page->see('Código inválido!', '.alert-danger');

        $page->fillField('code', $code);
        $page->click('Confirmar');
        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Login realizado com sucesso!');
    }

    public function codePageWithoutPasswordRedirectsToLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/login/code');

        $page->seeCurrentUrlEquals('/login');
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
