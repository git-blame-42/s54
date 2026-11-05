<?php

namespace Tests\Acceptance\events;

use Tests\Support\AcceptanceTester;

class ParticipantMyEventsCest extends BaseParticipantEventsCest
{
    public function listOnlyMyEventsFromNavbar(AcceptanceTester $page): void
    {
        $page->wantTo('"Meus eventos" lista só as Inscrições do Participante logado');
        $page->amOnPage('/dashboard');
        $page->click('Meus eventos', 'nav');

        $page->seeCurrentUrlEquals('/enrollments');
        $page->see('Meus eventos', 'h1');
        $page->see('Evento 01', 'table');
        $page->see('Evento 03', 'table');
        $page->see('Sala 3', $this->rowOf('Evento 03'));
        $page->see(date('d/m/Y', strtotime($this->startsOn(3))), $this->rowOf('Evento 03'));
        $page->dontSee('Evento 02', 'table');
        $page->seeNumberOfElements('table tbody tr', 2);

        $page->click('Ver', $this->rowOf('Evento 03'));
        $page->seeCurrentUrlEquals("/events/{$this->events[3]->id}");
        $page->see('Você está inscrito neste evento.');
    }

    public function newEnrollmentShowsUpInMyEvents(AcceptanceTester $page): void
    {
        $this->loginAsNewParticipant($page, 'novo@example.com');

        $page->wantTo('Inscrição nova aparece em "Meus eventos"');
        $page->amOnPage('/enrollments');
        $page->see('Você não está inscrito em nenhum evento.');

        $page->amOnPage("/events/{$this->events[2]->id}");
        $page->click('Inscrever-se');
        $page->click('Meus eventos', 'nav');

        $page->see('Evento 02', 'table');
        $page->seeNumberOfElements('table tbody tr', 1);
    }
}
