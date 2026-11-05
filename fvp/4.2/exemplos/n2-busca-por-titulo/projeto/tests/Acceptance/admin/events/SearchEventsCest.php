<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class SearchEventsCest extends BaseEventsCest
{
    public function searchByTitle(AcceptanceTester $page): void
    {
        $page->wantTo('Buscar Eventos por parte do título');
        $page->amOnPage('/admin/events');

        // "Evento 1" casa com Evento 10 a 16 (7 Eventos), não com "Evento 01".
        $page->fillField('#search_q', 'Evento 1');
        $page->click('Buscar');

        $page->seeCurrentUrlEquals('/admin/events?q=Evento+1');
        $page->seeInField('#search_q', 'Evento 1');
        $page->seeNumberOfElements('table tbody tr', 7);
        for ($i = 1; $i <= self::TOTAL_EVENTS; $i++) {
            if ($i >= 10) {
                $page->see($this->title($i), 'table');
            } else {
                $page->dontSee($this->title($i), 'table');
            }
        }
        // Com busca, sem paginação.
        $page->dontSeeElement('.pagination');
    }

    public function searchWithoutResults(AcceptanceTester $page): void
    {
        $page->wantTo('Buscar Eventos sem resultado');
        $page->amOnPage('/admin/events?q=Inexistente');

        $page->see('Nenhum evento encontrado para "Inexistente".');
        $page->dontSeeElement('table');
    }

    public function emptySearchShowsPaginatedListing(AcceptanceTester $page): void
    {
        $page->wantTo('Busca vazia mostra a listagem normal');
        $page->amOnPage('/admin/events');

        $page->fillField('#search_q', '   ');
        $page->click('Buscar');

        $page->seeNumberOfElements('table tbody tr', self::PER_PAGE);
        $page->see($this->title(1), 'table');
        $page->seeElement('.pagination');
        $page->see('Mostrando 1 - 5 de 16');
    }
}
