<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class PrivateEventCest extends BaseEventsCest
{
    public function createPrivateEventAndSeeInviteCode(AcceptanceTester $page): void
    {
        $page->wantTo('Cadastrar um Evento privado e ver o código de convite');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData(['title' => 'Oficina Privada']));
        $page->checkOption('#event_is_private');
        $page->click('Salvar');

        $event = Event::findBy(['title' => 'Oficina Privada']);
        Assert::assertNotNull($event);
        Assert::assertTrue($event->isPrivate());
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see("Privado (código de convite: {$event->invite_code})", '.visibility');
    }

    public function publicEventHasNoInviteCode(AcceptanceTester $page): void
    {
        $page->wantTo('Evento público não tem código de convite');
        $page->amOnPage("/admin/events/{$this->events[2]->id}");

        $page->see('Público', '.visibility');
        $page->dontSee('código de convite');
    }
}
