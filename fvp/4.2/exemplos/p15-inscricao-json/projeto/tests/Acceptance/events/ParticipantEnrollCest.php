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

        // O form vai por fetch() (enrollments.js): espera a resposta em vez de assumir o recarregamento.
        $page->waitForText('Inscrição realizada com sucesso!', 5);
        $page->seeCurrentUrlEquals("/events/{$event->id}");
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

    public function enrollInFullEventIsRejected(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        $user = $this->loginAsNewParticipant($page, 'novo@example.com');

        $page->wantTo('Inscrição recusada em Evento lotado');
        $page->amOnPage("/events/{$event->id}");
        $page->see('3/3');
        $page->see('Lotado');

        $page->click('Inscrever-se');

        $page->waitForText('não há vagas neste evento!', 5);
        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('3/3');
        Assert::assertFalse($user->isEnrolledIn($event));
        Assert::assertCount(3, Enrollment::where(['event_id' => $event->id]));
    }
}
