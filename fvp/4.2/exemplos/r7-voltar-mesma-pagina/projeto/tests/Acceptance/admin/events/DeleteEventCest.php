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

        $page->wantTo('Remoção do registro (P03: Evento com Inscrições não pode ser removido)');
        $page->amOnPage('/admin/events');

        $this->clickRemove($page, 'Evento 01');

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('não é possível remover um evento com inscrições (3)!');
        $page->dontSee('Evento removido com sucesso!');
        $page->see('Eventos', 'h1');
        $page->see('Evento 01', 'table');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertSame(3, $event->enrollmentsCount());
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function deleteEventKeepsTheCurrentPage(AcceptanceTester $page): void
    {
        $event = $this->events[7];
        Assert::assertFalse($event->hasEnrollments());

        $page->wantTo('Remoção do registro na página 2 volta para a página 2');
        $page->amOnPage('/admin/events/page/2');
        $page->see('Evento 07', $this->rowOf('Evento 07'));

        $this->clickRemove($page, 'Evento 07');

        $page->seeCurrentUrlEquals('/admin/events/page/2');
        $page->see('Evento removido com sucesso!');
        $page->dontSee('Evento 07', 'table');
        $page->see('Evento 06', 'table');
        $page->see('Evento 11', 'table');
        $page->see(sprintf('Mostrando 6 - 10 de %d', self::TOTAL_EVENTS - 1));
        Assert::assertNull(Event::findById((int) $event->id));
    }

    private function clickRemove(AcceptanceTester $page, string $title): void
    {
        // Remover é um form com _method=DELETE e botão "Remover", sem confirm() (contrato-ui-eventos.md).
        $page->click('Remover', $this->rowOf($title));
    }
}
