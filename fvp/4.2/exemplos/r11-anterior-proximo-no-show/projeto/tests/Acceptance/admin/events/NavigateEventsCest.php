<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class NavigateEventsCest extends BaseEventsCest
{
    public function navigateToNextAndBack(AcceptanceTester $page): void
    {
        $first = $this->events[1];
        $second = $this->events[2];

        $page->wantTo('Navegar do Evento 01 para o 02 com "Próximo" e voltar com "Anterior"');
        $page->amOnPage("/admin/events/{$first->id}");
        $page->see('Evento 01', 'h1');
        // Evento 01 é o mais próximo: sem "Anterior".
        $page->dontSeeElement('#previous_event');

        $page->click('#next_event');
        $page->seeCurrentUrlEquals("/admin/events/{$second->id}");
        $page->see('Evento 02', 'h1');

        $page->click('#previous_event');
        $page->seeCurrentUrlEquals("/admin/events/{$first->id}");
        $page->see('Evento 01', 'h1');
    }

    public function lastEventHasNoNextLink(AcceptanceTester $page): void
    {
        $last = $this->events[self::TOTAL_EVENTS];

        $page->wantTo('O último Evento não mostra "Próximo"');
        $page->amOnPage("/admin/events/{$last->id}");
        $page->see($this->title(self::TOTAL_EVENTS), 'h1');
        $page->dontSeeElement('#next_event');
        $page->seeElement('#previous_event');
    }
}
