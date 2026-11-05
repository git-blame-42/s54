<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Ordenar a listagem clicando no cabeçalho. Base: "Evento 01".."Evento 16", datas crescentes,
 * vagas 3 (Evento 01) e 20 + N (demais).
 */
class SortEventsCest extends BaseEventsCest
{
    public function sortByTitleClickingTheHeaderAndKeepItOnPage2(AcceptanceTester $page): void
    {
        $page->wantTo('Ordenar por título clicando no cabeçalho e manter a ordem na página 2');
        $page->amOnPage('/admin/events');
        $page->see('Data ▲', 'thead');

        $page->click('Título', 'thead');
        $page->seeCurrentUrlEquals('/admin/events?sort=title&dir=asc');
        $page->see('Título ▲', 'thead');
        $page->see($this->title(1), 'table tbody tr:first-child');

        // Clicar de novo na coluna ativa inverte a direção.
        $page->click('Título ▲', 'thead');
        $page->seeCurrentUrlEquals('/admin/events?sort=title&dir=desc');
        $page->see('Título ▼', 'thead');
        $page->see($this->title(16), 'table tbody tr:first-child');
        $page->dontSee($this->title(11), 'table');

        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2?sort=title&dir=desc');
        $page->see('Título ▼', 'thead');
        $page->see($this->title(11), 'table tbody tr:first-child');
        $page->see($this->title(7), 'table tbody tr:last-child');
    }

    public function sortByCapacity(AcceptanceTester $page): void
    {
        $page->wantTo('Ordenar por vagas');
        $page->amOnPage('/admin/events');

        $page->click('Vagas', 'thead');
        $page->see('Vagas ▲', 'thead');
        $page->see($this->title(1), 'table tbody tr:first-child');

        $page->click('Vagas ▲', 'thead');
        $page->see('Vagas ▼', 'thead');
        $page->see($this->title(16), 'table tbody tr:first-child');
    }

    public function invalidSortFallsBackToDate(AcceptanceTester $page): void
    {
        $page->wantTo('Ordenação inválida na URL cai no padrão (data crescente)');
        $page->amOnPage('/admin/events?sort=password&dir=sideways');

        $page->see('Data ▲', 'thead');
        $page->see($this->title(1), 'table tbody tr:first-child');
    }
}
