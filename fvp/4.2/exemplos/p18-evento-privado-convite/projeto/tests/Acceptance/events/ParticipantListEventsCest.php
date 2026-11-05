<?php

namespace Tests\Acceptance\events;

use Tests\Support\AcceptanceTester;

class ParticipantListEventsCest extends BaseParticipantEventsCest
{
    public function listEventsFromNavbarWithoutAdminActions(AcceptanceTester $page): void
    {
        $page->wantTo('Listagem de Eventos para o Participante');
        $page->amOnPage('/dashboard');
        $page->click('Eventos', 'nav');

        $page->seeCurrentUrlEquals('/events');
        $page->see('Eventos', 'h1');
        $page->see('Evento 01', 'table');
        $page->see('3/3', $this->rowOf('Evento 01'));
        $page->dontSee('Evento 06', 'table');
        $page->see('Mostrando 1 - 5 de 16');
        $page->dontSee('Novo Evento');
        $page->dontSee('Editar');
        $page->dontSee('Remover');
        $page->dontSee('Administração', 'nav');

        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/events/page/2');
        $page->see('Evento 06', 'table');
        $page->dontSee('Evento 01', 'table');
    }

    public function showEventDetails(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Detalhe do Evento para o Participante');
        $page->amOnPage('/events');
        $page->click('Ver', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Evento 02', 'h1');
        $page->see('Descrição do Evento 02.');
        $page->see('Sala 2');
        $page->see('0/22');
        $page->dontSee('Editar');

        $page->click('Voltar');
        $page->seeCurrentUrlEquals('/events');
    }

    public function adminAreaStaysBlocked(AcceptanceTester $page): void
    {
        $page->wantTo('Participante continua sem acesso à listagem administrativa');
        $page->amOnPage('/admin/events');

        $page->seeCurrentUrlEquals('/dashboard');
    }
}
