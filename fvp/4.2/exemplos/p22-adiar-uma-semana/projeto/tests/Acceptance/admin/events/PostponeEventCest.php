<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class PostponeEventCest extends BaseEventsCest
{
    public function postponeEventOneWeek(AcceptanceTester $page): void
    {
        $event = $this->events[16];
        $newDate = date('Y-m-d', (int) strtotime($event->starts_on . ' +7 days'));

        $page->wantTo('Adiar o Evento uma semana pelo botão do detalhe');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->see('Evento 16', 'h1');
        $page->see(date('d/m/Y', (int) strtotime($event->starts_on)));

        $page->click('Adiar uma semana');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento adiado para ' . date('d/m/Y', (int) strtotime($newDate)) . '!');
        $page->see(date('d/m/Y', (int) strtotime($newDate)), 'dl');

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertSame($newDate, $stored->starts_on);
        Assert::assertEquals($event->capacity, $stored->capacity);
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function postponeFullEventKeepsEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        Assert::assertSame(3, $event->enrollmentsCount());

        $page->wantTo('Adiar um Evento lotado mantém as Inscrições');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->click('Adiar uma semana');

        $page->see('Evento adiado para');
        $page->see('3/3', 'dl');
        Assert::assertSame(3, $event->enrollmentsCount());
    }
}
