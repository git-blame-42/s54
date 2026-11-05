<?php

namespace Tests\Acceptance\home;

use App\Models\User;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class HomeIndexCest extends BaseAcceptanceCest
{
    public function seeHomePage(AcceptanceTester $page): void
    {
        $page->amOnPage('/');
        $page->see('Home Page', '//h1');
        $page->seeInTitle('Home Page - Eventos do Câmpus');
    }

    public function authenticatedUserIsRedirectedToDashboard(AcceptanceTester $page): void
    {
        $user = new User([
            'name' => 'Fulano',
            'email' => 'fulano@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $user->save();

        $page->login('fulano@example.com', '123456');
        $page->amOnPage('/');

        $page->seeCurrentUrlEquals('/dashboard');
        $page->see('Painel do Usuário', '//h1');
    }
}
