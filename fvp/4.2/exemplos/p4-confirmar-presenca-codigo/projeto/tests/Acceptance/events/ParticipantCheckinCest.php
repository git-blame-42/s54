<?php

namespace Tests\Acceptance\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class ParticipantCheckinCest extends BaseParticipantEventsCest
{
    public function confirmPresenceWithTheCheckinCode(AcceptanceTester $page): void
    {
        // Evento 03: o Participante 1 já está inscrito (BaseEventsCest).
        $event = Event::findById((int) $this->events[3]->id);
        Assert::assertNotNull($event);
        $code = (int) $event->checkin_code;
        $wrong = $code === 9999 ? 1000 : $code + 1;

        $page->wantTo('Confirmação de presença com o código de check-in (errado e depois certo)');
        $page->amOnPage("/events/{$event->id}");
        $page->see('Você está inscrito neste evento.');
        $page->dontSee((string) $code);

        $page->fillField('#checkin_code', (string) $wrong);
        $page->click('Confirmar presença');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('código inválido!');
        $page->seeElement('#checkin_code');
        Assert::assertSame(0, $event->confirmedCount());

        $page->fillField('#checkin_code', (string) $code);
        $page->click('Confirmar presença');

        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Presença confirmada!');
        $page->see('Presença confirmada em', '#checkin_confirmed');
        $page->dontSeeElement('#checkin_code');
        Assert::assertSame(1, $event->confirmedCount());
    }

    public function notEnrolledParticipantSeesNoCheckinForm(AcceptanceTester $page): void
    {
        $page->wantTo('Participante não inscrito não vê o formulário de check-in');
        $page->amOnPage("/events/{$this->events[2]->id}");

        $page->see('Inscrever-se');
        $page->dontSeeElement('#checkin_code');
    }
}
