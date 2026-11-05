<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Enrollment;
use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Remover virou arquivar (soft delete): o Evento some da listagem, mas continua no banco com deleted_at.
 */
class DeleteEventCest extends BaseEventsCest
{
    public function archiveEventWithoutEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        Assert::assertFalse($event->hasEnrollments());

        $page->wantTo('Arquivamento do registro (some da listagem, continua no banco)');
        $page->amOnPage('/admin/events');
        $page->see('Evento 02', $this->rowOf('Evento 02'));

        $this->clickArchive($page, 'Evento 02');

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento arquivado com sucesso!');
        $page->see('Eventos', 'h1');
        $page->dontSee('Evento 02', 'table');
        $page->see('Mostrando 1 - 5 de ' . (self::TOTAL_EVENTS - 1));

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertTrue($stored->isArchived());
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
        Assert::assertCount(self::TOTAL_EVENTS - 1, Event::active());
    }

    public function archiveEventWithEnrollmentsKeepsThem(AcceptanceTester $page): void
    {
        $event = $this->events[1];
        Assert::assertSame(3, $event->enrollmentsCount());

        $page->wantTo('Arquivamento de Evento com Inscrições (as Inscrições continuam)');
        $page->amOnPage('/admin/events');

        $this->clickArchive($page, 'Evento 01');

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento arquivado com sucesso!');
        $page->dontSee('Evento 01', 'table');

        Assert::assertTrue(Event::findById((int) $event->id)?->isArchived());
        Assert::assertCount(3, Enrollment::where(['event_id' => $event->id]));
    }

    public function restoreArchivedEvent(AcceptanceTester $page): void
    {
        $page->wantTo('Listagem de arquivados e restauração do Evento');
        $page->amOnPage('/admin/events');
        $this->clickArchive($page, 'Evento 03');

        $page->click('Arquivados');
        $page->seeCurrentUrlEquals('/admin/events/archived');
        $page->see('Eventos arquivados', 'h1');
        $page->see('Evento 03', $this->rowOf('Evento 03'));
        $page->dontSee('Evento 02', 'table');

        $page->click('Restaurar', $this->rowOf('Evento 03'));

        $page->seeCurrentUrlEquals('/admin/events/archived');
        $page->see('Evento restaurado com sucesso!');
        $page->see('Nenhum evento arquivado.');

        $page->amOnPage('/admin/events');
        $page->see('Evento 03', 'table');
        Assert::assertFalse(Event::findById((int) $this->events[3]->id)?->isArchived());
    }

    private function clickArchive(AcceptanceTester $page, string $title): void
    {
        // Arquivar é um form com _method=DELETE e botão "Arquivar", sem confirm() (contrato-ui-eventos.md).
        $page->click('Arquivar', $this->rowOf($title));
    }
}
