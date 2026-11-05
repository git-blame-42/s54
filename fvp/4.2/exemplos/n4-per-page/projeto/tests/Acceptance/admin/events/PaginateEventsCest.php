<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

class PaginateEventsCest extends BaseEventsCest
{
    public function paginateEvents(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros');
        $page->amOnPage('/admin/events');

        // Página 1: Eventos 01 a 10.
        $this->seeEventsOfPage($page, 1, self::PER_PAGE);
        $page->see('Mostrando 1 - 10 de 16');
        $page->seeElement('.pagination');
        // Anterior, 1, 2, Próxima.
        $page->seeNumberOfElements('.pagination .page-item', 4);

        // Página 2 (última) por "Próxima": Eventos 11 a 16.
        $page->click('Próxima', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2, self::PER_PAGE);
        $page->see('Mostrando 11 - 16 de 16');

        // Volta por "Anterior".
        $page->click('Anterior', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/1');
        $this->seeEventsOfPage($page, 1, self::PER_PAGE);

        // Página 2 pelo número.
        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2, self::PER_PAGE);
    }

    public function openPageTwoDirectly(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (acesso direto à página 2)');

        $page->amOnPage('/admin/events/page/2');
        $this->seeEventsOfPage($page, 2, self::PER_PAGE);
        $page->dontSee($this->title(1));
    }

    public function choosePerPage(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (usuário escolhe 5 por página)');
        $page->amOnPage('/admin/events');

        $page->click('5', '.per-page');
        $page->seeCurrentUrlEquals('/admin/events?per_page=5');
        $this->seeEventsOfPage($page, 1, 5);
        $page->see('Mostrando 1 - 5 de 16');
        // Anterior, 1, 2, 3, 4, Próxima.
        $page->seeNumberOfElements('.pagination .page-item', 6);

        // Os links de página mantêm o per_page.
        $page->click('Próxima', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2?per_page=5');
        $this->seeEventsOfPage($page, 2, 5);
        $page->see('Mostrando 6 - 10 de 16');

        $page->click('4', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/4?per_page=5');
        $this->seeEventsOfPage($page, 4, 5);

        // 20 por página: tudo numa página só.
        $page->click('20', '.per-page');
        $page->seeCurrentUrlEquals('/admin/events?per_page=20');
        $this->seeEventsOfPage($page, 1, 20);
        $page->see('Mostrando 1 - 16 de 16');
    }

    public function invalidPerPageFallsBackToDefault(AcceptanceTester $page): void
    {
        $page->wantTo('Paginação dos registros (per_page inválido usa o padrão)');

        $page->amOnPage('/admin/events?per_page=0');
        $this->seeEventsOfPage($page, 1, self::PER_PAGE);
        $page->see('Mostrando 1 - 10 de 16');
    }

    /** Confere que a página mostra exatamente os Eventos dela (ordem de data). */
    private function seeEventsOfPage(AcceptanceTester $page, int $number, int $perPage): void
    {
        $first = ($number - 1) * $perPage + 1;
        $last = min($number * $perPage, self::TOTAL_EVENTS);

        $page->see('Eventos', 'h1');
        $page->seeNumberOfElements('table tbody tr', $last - $first + 1);
        for ($i = 1; $i <= self::TOTAL_EVENTS; $i++) {
            if ($i >= $first && $i <= $last) {
                $page->see($this->title($i), 'table');
            } else {
                $page->dontSee($this->title($i), 'table');
            }
        }
    }
}
