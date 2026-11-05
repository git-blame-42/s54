<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class DeleteEventCest extends BaseEventsCest
{
    public function deleteEventWithoutEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        Assert::assertFalse($event->hasEnrollments());

        $page->wantTo('Remoção do registro');
        $page->amOnPage('/admin/events');
        $page->see('Evento 02', $this->rowOf('Evento 02'));

        $this->clickRemove($page, 'Evento 02');

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento removido com sucesso!');
        $page->see('Eventos', 'h1');
        $page->dontSee('Evento 02', 'table');
        Assert::assertNull(Event::findById((int) $event->id));
        Assert::assertCount(self::TOTAL_EVENTS - 1, Event::all());
    }

    public function deleteEventWithEnrollmentsIsBlocked(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        Assert::assertTrue($event->hasEnrollments());

        $page->wantTo('Remoção do registro (P03: Evento com Inscrições não mostra Remover)');
        $page->amOnPage('/admin/events');

        // Sem botão na tela; a recusa do servidor a um DELETE direto fica no EventsControllerTest.
        $page->dontSeeElement($this->rowOf('Evento 01') . "//button[contains(., 'Remover')]");
        $page->see('Com inscrições', $this->rowOf('Evento 01'));
        $page->seeElement($this->rowOf('Evento 02') . "//button[contains(., 'Remover')]");
        $page->see('Evento 01', 'table');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertSame(3, $event->enrollmentsCount());
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    private function clickRemove(AcceptanceTester $page, string $title): void
    {
        // Remover é um form com _method=DELETE e botão "Remover", sem confirm() (contrato-ui-eventos.md).
        $page->click('Remover', $this->rowOf($title));
    }
}
