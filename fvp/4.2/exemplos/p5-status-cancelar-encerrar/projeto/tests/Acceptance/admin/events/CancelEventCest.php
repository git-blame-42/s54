<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Situação do Evento (Agendado, Cancelado, Encerrado), com cancelar pela listagem ou pelo show e encerrar pelo show.
 */
class CancelEventCest extends BaseEventsCest
{
    public function cancelScheduledEvent(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Cancelar um Evento agendado');
        $page->amOnPage('/admin/events');
        $page->see('Agendado', $this->rowOf('Evento 02'));
        $page->click('Ver', $this->rowOf('Evento 02'));
        $page->see('Agendado', '#event_status');

        $page->click('Cancelar Evento');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cancelado!');
        $page->see('Cancelado', '#event_status');
        $page->dontSee('Cancelar Evento');
        $page->dontSee('Encerrar Evento');
        Assert::assertSame('canceled', Event::findById((int) $event->id)?->status);

        $page->amOnPage('/admin/events');
        $page->see('Cancelado', $this->rowOf('Evento 02'));
        $page->see('Agendado', $this->rowOf('Evento 03'));
    }

    public function cancelEventFromTheListing(AcceptanceTester $page): void
    {
        $event = $this->events[4];

        $page->wantTo('Cancelar um Evento pela listagem');
        $page->amOnPage('/admin/events');
        $page->click('Cancelar Evento', $this->rowOf('Evento 04'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cancelado!');
        $page->see('Cancelado', '#event_status');
    }

    public function canceledEventCannotBeEdited(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $event->cancel();

        $page->wantTo('Evento cancelado não aceita edição');
        $page->amOnPage("/admin/events/{$event->id}/edit");

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cancelado não pode ser editado!');
        $page->dontSee('Editar Evento', 'h1');
    }

    public function finishScheduledEvent(AcceptanceTester $page): void
    {
        $event = $this->events[3];

        $page->wantTo('Encerrar um Evento agendado');
        $page->amOnPage("/admin/events/{$event->id}");

        $page->click('Encerrar Evento');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento encerrado!');
        $page->see('Encerrado', '#event_status');
        Assert::assertSame('finished', Event::findById((int) $event->id)?->status);
    }
}
