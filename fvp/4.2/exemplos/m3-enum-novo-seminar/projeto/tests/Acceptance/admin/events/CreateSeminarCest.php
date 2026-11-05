<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class CreateSeminarCest extends BaseEventsCest
{
    public function createEventWithSeminarKind(AcceptanceTester $page): void
    {
        $data = $this->validEventData(['title' => 'Seminário de Pesquisa', 'kind' => Event::KINDS['seminar']]);

        $page->wantTo('Cadastro de Evento com o tipo novo Seminário');
        $page->amOnPage('/admin/events/new');
        $page->see('Seminário', '#event_kind');

        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $page->see('Seminário de Pesquisa', 'h1');
        $page->see('Seminário', 'dd');

        $event = Event::findBy(['title' => 'Seminário de Pesquisa']);
        Assert::assertNotNull($event);
        Assert::assertSame('seminar', $event->kind);
    }
}
