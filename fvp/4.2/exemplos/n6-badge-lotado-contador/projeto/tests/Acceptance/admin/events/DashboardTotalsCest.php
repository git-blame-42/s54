<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class DashboardTotalsCest extends BaseEventsCest
{
    public function dashboardShowsTotals(AcceptanceTester $page): void
    {
        $page->wantTo('Painel administrativo com o total de Eventos e de Inscrições');
        $page->amOnPage('/admin');

        $page->see('Painel Administrativo', 'h1');
        $page->see('Eventos', '#total_events');
        $page->see((string) self::TOTAL_EVENTS, '#total_events .card-title');
        // BaseEventsCest: 3 Inscrições no Evento 01 e 1 no Evento 03.
        $page->see('Inscrições', '#total_enrollments');
        $page->see('4', '#total_enrollments .card-title');
    }

    public function fullEventShowsBadgeOnShow(AcceptanceTester $page): void
    {
        $page->wantTo('Detalhes do Evento lotado com o badge "Lotado"');
        $page->amOnPage("/admin/events/{$this->events[1]->id}");
        $page->see('3/3');
        $page->see('Lotado', '.badge');

        $page->amOnPage("/admin/events/{$this->events[2]->id}");
        $page->dontSee('Lotado');
    }
}
