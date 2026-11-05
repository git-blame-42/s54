<?php

namespace Tests\Acceptance\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class ParticipantCancelEnrollmentCest extends BaseParticipantEventsCest
{
    public function cancelOwnEnrollment(AcceptanceTester $page): void
    {
        $event = $this->events[3];
        Assert::assertTrue($this->participants[1]->isEnrolledIn($event));

        $page->wantTo('Participante cancela a própria inscrição');
        $page->amOnPage("/events/{$event->id}");
        $page->see('Você está inscrito neste evento.');
        $page->see('1/23');

        $page->click('Cancelar inscrição');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Inscrição cancelada!');
        $page->see('0/23');
        $page->see('Inscrever-se');
        $page->dontSee('Cancelar inscrição');
        Assert::assertFalse($this->participants[1]->isEnrolledIn($event));
    }

    public function enrollAndCancelInFullEventFreesTheSpot(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Cancelar inscrição em Evento lotado libera a vaga');
        $page->amOnPage("/events/{$event->id}");
        $page->see('3/3');
        $page->see('Lotado');

        $page->click('Cancelar inscrição');

        $page->see('Inscrição cancelada!');
        $page->see('2/3');
        $page->dontSee('Lotado');
        Assert::assertCount(2, Enrollment::where(['event_id' => $event->id]));

        $page->click('Inscrever-se');

        $page->see('Inscrição realizada com sucesso!');
        $page->see('3/3');
        Assert::assertTrue($this->participants[1]->isEnrolledIn($event));
    }
}
