<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * M14: cada abertura do show do Evento soma uma visualização, exibida no show e na listagem.
 */
class ViewsCountCest extends BaseEventsCest
{
    public function showCountsViews(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Abrir o detalhe do Evento duas vezes e ver o contador subir');
        $page->amOnPage('/admin/events');
        $page->see('0', $this->rowOf('Evento 02') . "//td[@class='views-count']");

        $page->click('Ver', $this->rowOf('Evento 02'));
        $page->see('1', '#event_views_count');

        $page->amOnPage("/admin/events/{$event->id}");
        $page->see('2', '#event_views_count');

        $page->amOnPage('/admin/events');
        $page->see('2', $this->rowOf('Evento 02') . "//td[@class='views-count']");
        Assert::assertEquals(2, Event::findById((int) $event->id)?->views_count);
    }
}
