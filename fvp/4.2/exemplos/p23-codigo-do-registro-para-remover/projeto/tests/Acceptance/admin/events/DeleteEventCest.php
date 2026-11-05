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

    public function deleteWithWrongCodeGoesBackToShow(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $wrong = $event->confirmation_code === 1000 ? '1001' : '1000';

        $page->wantTo('Remoção com código de confirmação errado');
        $page->amOnPage('/admin/events');
        $page->click('Remover', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->fillField('#confirmation_code', $wrong);
        $page->click('Confirmar remoção');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Código de confirmação incorreto!');
        $page->dontSee('Evento removido com sucesso!');
        $page->see((string) $event->confirmation_code, '#confirmation_code_value');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    /**
     * "Remover" na listagem leva ao show do Evento; lá o código aparece em #confirmation_code_value e é
     * digitado em #confirmation_code antes de "Confirmar remoção" (form com _method=DELETE).
     */
    private function clickRemove(AcceptanceTester $page, string $title): void
    {
        $page->click('Remover', $this->rowOf($title));
        $page->see($title, 'h1');

        $code = $page->grabTextFrom('#confirmation_code_value');
        $page->fillField('#confirmation_code', $code);
        $page->click('Confirmar remoção');
    }
}
