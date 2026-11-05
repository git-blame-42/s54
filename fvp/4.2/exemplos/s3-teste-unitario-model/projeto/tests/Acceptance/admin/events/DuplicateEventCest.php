<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * P6: duplicar um Evento pela listagem abre a edição da cópia.
 */
class DuplicateEventCest extends BaseEventsCest
{
    public function duplicateEventOpensTheCopyForEditing(AcceptanceTester $page): void
    {
        $original = $this->events[1];

        $page->wantTo('Duplicar um Evento');
        $page->amOnPage('/admin/events');
        $page->click('Duplicar', $this->rowOf('Evento 01'));

        $copy = Event::findBy(['title' => 'Evento 01 (cópia)']);
        Assert::assertNotNull($copy);
        $page->seeCurrentUrlEquals("/admin/events/{$copy->id}/edit");
        $page->see('Evento duplicado! Revise os dados.');
        $page->see('Editar Evento', 'h1');
        $page->seeInField('#event_title', 'Evento 01 (cópia)');
        $page->seeInField('#event_starts_on', date('Y-m-d', strtotime('+7 days')));
        $page->seeInField('#event_location', $original->location);

        // A cópia nasce sem Inscrições; o original (lotado) continua com as 3.
        Assert::assertSame(0, $copy->enrollmentsCount());
        Assert::assertSame(3, $original->enrollmentsCount());
        Assert::assertCount(self::TOTAL_EVENTS + 1, Event::all());

        $page->amOnPage('/admin/events/page/4');
        $page->see('Evento 01 (cópia)', 'table');
        $page->see('0/3', $this->rowOf('Evento 01 (cópia)'));
    }
}
