<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class ShowEventBySlugCest extends BaseEventsCest
{
    public function openEventBySlugFromTheAdminShow(AcceptanceTester $page): void
    {
        $page->wantTo('Abrir o Evento pelo endereço com slug gerado a partir do título');
        $page->amOnPage('/admin/events');
        $page->seeLink('evento-01', '/events/evento-01');

        $page->click('Ver', $this->rowOf('Evento 01'));
        $page->seeLink('/events/evento-01');
        $page->click('/events/evento-01');

        $page->seeCurrentUrlEquals('/events/evento-01');
        $page->see('Evento 01', 'h1');
        $page->see('Sala 1');
        $page->see('0 de 3');
    }

    public function repeatedTitleGetsSuffix(AcceptanceTester $page): void
    {
        $page->wantTo('Cadastrar Evento com título repetido e receber slug com sufixo');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $this->validEventData(['title' => 'Evento 02']));
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $page->seeLink('/events/evento-02-2');

        $event = Event::findBySlug('evento-02-2');
        Assert::assertNotNull($event);
        Assert::assertSame('Evento 02', $event->title);
    }
}
