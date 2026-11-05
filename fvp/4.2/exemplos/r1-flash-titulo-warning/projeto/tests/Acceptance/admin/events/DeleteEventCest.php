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
        $page->see('Evento "Evento 02" removido com sucesso!', '.alert-success');
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
        $page->see(
            'Evento "Evento 01" não removido: não é possível remover um evento com inscrições (3)!',
            '.alert-warning'
        );
        $page->dontSee('removido com sucesso!');
        $page->see('Eventos', 'h1');
        $page->see('Evento 01', 'table');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertSame(3, $event->enrollmentsCount());
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function flashWithSpecialCharactersInTitleIsEscaped(AcceptanceTester $page): void
    {
        $title = 'A & "B" <b>x</b>';

        $page->wantTo('Remover Evento com título especial e ver o título literal no flash');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $this->validEventData(['title' => $title]));
        $page->click('Salvar');
        $page->see("Evento \"{$title}\" cadastrado com sucesso!", '.alert-success');

        $page->amOnPage('/admin/events/page/4');
        $page->click('Remover', "//table//tr[contains(normalize-space(.), 'A & \"B\"')]");

        // Escapado uma vez: o texto aparece literal, sem &amp; nem negrito.
        $page->see("Evento \"{$title}\" removido com sucesso!", '.alert-success');
        $page->dontSeeElement('.alert b');
        $page->dontSee('&amp;');
        Assert::assertNull(Event::findBy(['title' => $title]));
    }

    private function clickRemove(AcceptanceTester $page, string $title): void
    {
        // Remover é um form com _method=DELETE e botão "Remover", sem confirm() (contrato-ui-eventos.md).
        $page->click('Remover', $this->rowOf($title));
    }
}
