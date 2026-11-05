<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Filtros por público-alvo (FIND_IN_SET no SET) e "só com vagas" (subconsulta COUNT).
 * Base: Eventos ímpares têm Estudantes e Professores; pares, só Estudantes. Evento 01 está lotado (3/3).
 */
class FilterEventsCest extends BaseEventsCest
{
    public function filterByAudience(AcceptanceTester $page): void
    {
        $page->wantTo('Filtrar a listagem por público-alvo');
        $page->amOnPage('/admin/events');

        $page->selectOption('#filter_audience', 'Professores');
        $page->click('Filtrar');

        $page->seeInCurrentUrl('audience=professors');
        $page->seeOptionIsSelected('#filter_audience', 'Professores');
        // 8 Eventos ímpares, todos na mesma página (com filtro não há paginação).
        $page->seeNumberOfElements('table tbody tr', 8);
        $page->see($this->title(15), 'table');
        $page->dontSee($this->title(2), 'table');
        $page->see('8 evento(s) encontrado(s).');
        $page->dontSeeElement('.pagination');
    }

    public function filterOnlyWithAvailableSpotsAndClear(AcceptanceTester $page): void
    {
        $page->wantTo('Filtrar só Eventos com vagas e limpar o filtro');
        $page->amOnPage('/admin/events');

        $page->checkOption('#filter_available');
        $page->click('Filtrar');

        $page->seeCheckboxIsChecked('#filter_available');
        $page->seeNumberOfElements('table tbody tr', self::TOTAL_EVENTS - 1);
        $page->dontSee($this->title(1), 'table');

        $page->selectOption('#filter_audience', 'Professores');
        $page->click('Filtrar');
        $page->seeNumberOfElements('table tbody tr', 7);

        $page->click('Limpar filtros');
        $page->seeCurrentUrlEquals('/admin/events');
        $page->seeNumberOfElements('table tbody tr', self::PER_PAGE);
        $page->seeElement('.pagination');
    }
}
