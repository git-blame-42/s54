<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Abas Próximos/Passados (`?scope=upcoming|past`). Os Eventos da base são todos futuros; aqui o
 * Evento 02 é movido para ontem com update(), que não valida (o formulário recusaria a data).
 */
class ScopeEventsCest extends BaseEventsCest
{
    public function upcomingAndPastTabs(AcceptanceTester $page): void
    {
        $this->events[2]->update(['starts_on' => date('Y-m-d', strtotime('-1 day'))]);

        $page->wantTo('Ver só os Eventos próximos e só os passados');
        $page->amOnPage('/admin/events');

        $page->click('Próximos', '.events-scope');
        $page->seeCurrentUrlEquals('/admin/events?scope=upcoming');
        // Sem paginação: os 15 futuros numa página só.
        $page->seeNumberOfElements('table tbody tr', self::TOTAL_EVENTS - 1);
        $page->see($this->title(1), 'table');
        $page->see($this->title(16), 'table');
        $page->dontSee($this->title(2), 'table');
        $page->dontSeeElement('.pagination');

        $page->click('Passados', '.events-scope');
        $page->seeCurrentUrlEquals('/admin/events?scope=past');
        $page->seeNumberOfElements('table tbody tr', 1);
        $page->see($this->title(2), 'table');
        $page->see(date('d/m/Y', (int) strtotime('-1 day')), 'table');

        $page->click('Todos', '.events-scope');
        $page->seeCurrentUrlEquals('/admin/events');
        $page->seeElement('.pagination');
        $page->see('Mostrando 1 - 5 de 16');
    }

    public function pastTabWithoutEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Aba de Eventos passados vazia');
        $page->amOnPage('/admin/events?scope=past');

        $page->see('Nenhum evento passado.');
        $page->dontSeeElement('table');
    }
}
