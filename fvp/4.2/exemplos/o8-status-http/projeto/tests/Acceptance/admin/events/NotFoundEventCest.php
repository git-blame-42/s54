<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Evento inexistente mostra a página "Evento não encontrado" no próprio endereço (404, sem
 * redirect). O status é conferido por HTTP em EventsAccessTest; o Selenium não lê status.
 */
class NotFoundEventCest extends BaseEventsCest
{
    public function unknownEventShowsNotFoundPage(AcceptanceTester $page): void
    {
        $page->wantTo('Abrir um Evento inexistente e ver a página de não encontrado');
        $page->amOnPage('/admin/events/999999');

        $page->seeCurrentUrlEquals('/admin/events/999999');
        $page->see('Evento não encontrado', 'h1');
        $page->see('O evento procurado não existe ou foi removido.');

        $page->click('Voltar para a lista de eventos');
        $page->seeCurrentUrlEquals('/admin/events');
    }
}
