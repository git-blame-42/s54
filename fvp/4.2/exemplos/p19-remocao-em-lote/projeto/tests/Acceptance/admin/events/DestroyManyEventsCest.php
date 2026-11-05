<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Remoção em lote. Base: Evento 01 (3 inscrições) e Evento 03 (1 inscrição) não podem ser removidos.
 */
class DestroyManyEventsCest extends BaseEventsCest
{
    public function removeSelectedEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Remover vários Eventos marcando os checkboxes');
        $page->amOnPage('/admin/events');

        $page->checkOption("#event_select_{$this->events[2]->id}");
        $page->checkOption("#event_select_{$this->events[4]->id}");
        $page->click('Remover selecionados');

        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('2 evento(s) removido(s) com sucesso!');
        $page->dontSee($this->title(2), 'table');
        $page->dontSee($this->title(4), 'table');
        Assert::assertCount(self::TOTAL_EVENTS - 2, Event::all());
    }

    public function eventsWithEnrollmentsAreKept(AcceptanceTester $page): void
    {
        $page->wantTo('Remover em lote com Eventos que têm inscrições');
        $page->amOnPage('/admin/events');

        $page->checkOption("#event_select_{$this->events[1]->id}");
        $page->checkOption("#event_select_{$this->events[2]->id}");
        $page->checkOption("#event_select_{$this->events[3]->id}");
        $page->click('Remover selecionados');

        $page->see('1 evento(s) removido(s) com sucesso!');
        $page->see("Não é possível remover eventos com inscrições: {$this->title(1)}, {$this->title(3)}!");
        $page->see($this->title(1), 'table');
        $page->dontSee($this->title(2), 'table');
        Assert::assertCount(self::TOTAL_EVENTS - 1, Event::all());
    }

    public function removeWithoutSelection(AcceptanceTester $page): void
    {
        $page->wantTo('Clicar em Remover selecionados sem marcar nada');
        $page->amOnPage('/admin/events');

        $page->click('Remover selecionados');

        $page->see('Selecione ao menos um evento válido para remover!');
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }
}
