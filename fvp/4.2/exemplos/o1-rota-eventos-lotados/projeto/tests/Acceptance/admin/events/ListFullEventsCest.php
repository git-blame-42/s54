<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class ListFullEventsCest extends BaseEventsCest
{
    public function listOnlyFullEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Listagem dos Eventos lotados (sem vagas)');
        $page->amOnPage('/admin/events');

        $page->click('Eventos lotados');

        $page->seeCurrentUrlEquals('/admin/events/full');
        $page->see('Eventos lotados', 'h1');
        // Na base só o Evento 01 está lotado (3/3); o Evento 03 tem Inscrição, mas ainda tem vagas.
        $page->seeNumberOfElements('table tbody tr', 1);
        $page->see('3/3', $this->rowOf('Evento 01'));
        $page->dontSee('Evento 02', 'table');
        $page->dontSee('Evento 03', 'table');

        $page->click('Voltar');
        $page->seeCurrentUrlEquals('/admin/events');
    }
}
