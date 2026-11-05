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

    public function deleteWithWrongCodeKeepsTheEvent(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Remoção do registro com código de confirmação errado (P16)');
        $page->amOnPage('/admin/events');
        $page->click('Remover', $this->rowOf('Evento 02'));

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/delete");
        $code = (int) $page->grabTextFrom('#confirmation_code_value');
        $page->fillField('#confirmation_code', (string) ($code === 9999 ? 1000 : $code + 1));
        $page->click('Remover', 'form');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}/delete");
        $page->see('Código de confirmação inválido! O evento não foi removido.');
        $page->see('Remover Evento', 'h1');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    /**
     * P16: "Remover" da listagem leva à página de confirmação; o código gerado no servidor
     * aparece na tela e é digitado no campo antes de enviar o DELETE.
     */
    private function clickRemove(AcceptanceTester $page, string $title): void
    {
        $page->click('Remover', $this->rowOf($title));

        $page->see('Remover Evento', 'h1');
        $page->see($title);
        $code = $page->grabTextFrom('#confirmation_code_value');
        Assert::assertMatchesRegularExpression('/^\d{4}$/', $code);

        $page->fillField('#confirmation_code', $code);
        $page->click('Remover', 'form');
    }
}
