<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Botão "Adicionar ao calendário" no show do Evento. O download em si (headers e corpo .ics)
 * é conferido no teste de acesso, porque o Selenium não lê o arquivo baixado.
 */
class ExportICalendarCest extends BaseEventsCest
{
    public function showHasAddToCalendarLink(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Botão Adicionar ao calendário no detalhe do Evento');
        $page->amOnPage("/admin/events/{$event->id}");

        $page->see('Adicionar ao calendário');
        $page->seeLink('Adicionar ao calendário', "/admin/events/{$event->id}/ics");
    }
}
