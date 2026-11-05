<?php

namespace Tests\Acceptance\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class ParticipantEnrollCest extends BaseParticipantEventsCest
{
    public function enrollInEventWithSpots(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Inscrição do Participante em Evento com vagas');
        $page->amOnPage("/events/{$event->id}");
        $page->see('0/22');

        $page->click('Inscrever-se');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Inscrição realizada com sucesso!');
        $page->see('1/22');
        $page->see('Você está inscrito neste evento.');
        $page->dontSee('Inscrever-se');
        Assert::assertTrue($this->participants[1]->isEnrolledIn($event));
    }

    public function alreadyEnrolledParticipantSeesNoButton(AcceptanceTester $page): void
    {
        $page->wantTo('Participante já inscrito não vê o botão de inscrição');
        $page->amOnPage("/events/{$this->events[3]->id}");

        $page->see('Você está inscrito neste evento.');
        $page->dontSee('Inscrever-se');
    }

    public function enrollInFullEventGoesToWaitingList(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        $user = $this->loginAsNewParticipant($page, 'novo@example.com');

        $page->wantTo('Inscrição em Evento lotado entra na lista de espera');
        $page->amOnPage("/events/{$event->id}");
        $page->see('3/3');
        $page->see('Lotado');
        $page->dontSee('Inscrever-se');

        $page->click('Entrar na lista de espera');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Evento lotado: você entrou na lista de espera (posição 1).');
        $page->see('Você está na lista de espera (posição 1).');
        $page->see('Lista de espera: 1');
        $page->see('3/3');
        Assert::assertTrue($user->enrollmentIn($event)?->isWaiting());
        Assert::assertSame(3, $event->enrollmentsCount());
        Assert::assertCount(4, Enrollment::where(['event_id' => $event->id]));
    }
}
