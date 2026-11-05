<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class ShowParticipantsCest extends BaseEventsCest
{
    public function showListsParticipants(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Visualização do registro (inscritos com nome e e-mail)');
        $page->amOnPage('/admin/events');
        $page->click('Ver', $this->rowOf('Evento 01'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Inscritos', 'h2');
        // Evento 01 da base: os 3 Participantes inscritos.
        for ($i = 1; $i <= 3; $i++) {
            $page->see("Participante {$i}", 'table');
            $page->see("participante{$i}@example.com", 'table');
        }
    }

    public function showWithoutEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Visualização do registro (Evento sem inscritos)');
        $page->amOnPage("/admin/events/{$event->id}");

        $page->see('Inscritos', 'h2');
        $page->see('Nenhuma inscrição.');
    }
}
