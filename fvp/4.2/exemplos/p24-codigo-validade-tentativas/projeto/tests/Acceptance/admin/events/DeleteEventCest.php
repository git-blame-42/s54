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
        $page->see('Código incorreto! Tente de novo.');
        $page->dontSee('Evento removido com sucesso!');
        $page->see((string) $event->confirmation_code, '#confirmation_code_value');
        Assert::assertNotNull(Event::findById((int) $event->id));
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function threeWrongCodesGiveANewCode(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $wrong = $event->confirmation_code === 1000 ? '1001' : '1000';

        $page->wantTo('Código de confirmação esgotado depois de 3 tentativas erradas');
        $page->amOnPage("/admin/events/{$event->id}");

        foreach (['Código incorreto! Tente de novo.', 'Código incorreto! Tente de novo.'] as $message) {
            $page->fillField('#confirmation_code', $wrong);
            $page->click('Confirmar remoção');
            $page->see($message);
        }
        $page->fillField('#confirmation_code', $wrong);
        $page->click('Confirmar remoção');
        $page->see('Tentativas esgotadas! Um novo código foi gerado.');

        // A tela já mostra o código novo, com as tentativas zeradas.
        $found = Event::findById((int) $event->id);
        Assert::assertNotNull($found);
        Assert::assertSame(0, $found->confirmation_code_attempts);
        $page->see((string) $found->confirmation_code, '#confirmation_code_value');

        $page->fillField('#confirmation_code', $page->grabTextFrom('#confirmation_code_value'));
        $page->click('Confirmar remoção');
        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Evento removido com sucesso!');
        Assert::assertNull(Event::findById((int) $event->id));
    }

    public function expiredCodeGivesANewCode(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Código de confirmação expirado');
        $page->amOnPage("/admin/events/{$event->id}");
        $code = $page->grabTextFrom('#confirmation_code_value');
        $event->update(['confirmation_code_issued_at' => time() - Event::CODE_TTL_SECONDS - 1]);

        $page->fillField('#confirmation_code', $code);
        $page->click('Confirmar remoção');
        $page->see('Código expirado! Um novo foi gerado.');
        Assert::assertNotNull(Event::findById((int) $event->id));

        $page->fillField('#confirmation_code', $page->grabTextFrom('#confirmation_code_value'));
        $page->click('Confirmar remoção');
        $page->see('Evento removido com sucesso!');
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
