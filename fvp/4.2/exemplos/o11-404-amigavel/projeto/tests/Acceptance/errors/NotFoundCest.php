<?php

namespace Tests\Acceptance\errors;

use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

/**
 * O navegador de teste usa a porta 8080 (php_test, APP_ENV=testing): fora de development a rota
 * inexistente mostra a página amigável, sem stack trace.
 */
class NotFoundCest extends BaseAcceptanceCest
{
    public function seeFriendlyNotFoundPage(AcceptanceTester $page): void
    {
        $page->amOnPage('/rota-que-nao-existe');

        $page->see('Página não encontrada', '//h1');
        $page->see('404', '#error_status');
        $page->dontSee('Stack Trace');
        $page->dontSee('Router.php');

        $page->click('Voltar ao início');
        $page->seeCurrentUrlEquals('/');
        $page->see('Home Page', '//h1');
    }
}
