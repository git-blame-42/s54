<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class ShowEventCest extends BaseEventsCest
{
    public function showEvent(AcceptanceTester $page): void
    {
        // Evento 01: Palestra, Sala 1, lotado (3 de 3 vagas).
        $event = $this->events[1];

        $page->wantTo('Visualização do registro');
        $page->amOnPage("/admin/events/{$event->id}");

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see($event->title, 'h1');
        $page->see($event->description);
        $page->see('Palestra');
        $page->see('Sala 1');
        $page->see(date('d/m/Y', (int) strtotime($event->starts_on)));
        $page->see('3/3');
        $page->see('0 disponíveis');
        $page->seeLink('Editar', "/admin/events/{$event->id}/edit");
        $page->seeLink('Voltar', '/admin/events');
    }

    public function showEventNotFound(AcceptanceTester $page): void
    {
        $page->wantTo('Visualização do registro (Evento inexistente)');
        $page->amOnPage('/admin/events/999999');

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento não encontrado!');
        $page->see('Eventos', 'h1');
    }
}
