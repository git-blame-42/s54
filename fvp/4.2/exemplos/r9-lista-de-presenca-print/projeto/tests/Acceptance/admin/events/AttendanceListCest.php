<?php

namespace Tests\Acceptance\admin\events;

use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Lista de presença imprimível. Evento 01 da base tem os 3 Participantes inscritos.
 */
class AttendanceListCest extends BaseEventsCest
{
    public function openAttendanceListFromShow(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Abrir a lista de presença a partir do detalhe do Evento');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->click('Lista de presença');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/attendance");
        $page->see('Lista de presença', 'h1');
        $page->see($this->title(1), 'h2');
        $page->see('Assinatura', 'table thead');
        $page->seeNumberOfElements('table.attendance tbody tr', 3);
        foreach ($this->participants as $participant) {
            $page->see($participant->name, 'table.attendance');
            $page->see($participant->email, 'table.attendance');
        }

        // Layout próprio: sem navbar; o botão Imprimir existe (o JS fica em arquivo próprio).
        $page->dontSeeElement('nav.navbar');
        $page->seeElement('#print_button');
        $page->seeInSource('src="/assets/js/print.js"');

        // Troca window.print por um espião para não abrir o diálogo do navegador.
        $page->executeJS('window.printCalled = false; window.print = function () { window.printCalled = true; };');
        $page->click('Imprimir');
        Assert::assertTrue($page->executeJS('return window.printCalled;'));

        $page->click('Voltar');
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
    }

    public function attendanceListOfEventWithoutEnrollments(AcceptanceTester $page): void
    {
        $page->wantTo('Lista de presença de Evento sem inscritos');
        $page->amOnPage("/admin/events/{$this->events[2]->id}/attendance");

        $page->see('Nenhum inscrito neste evento.');
        $page->dontSeeElement('table.attendance');
    }
}
