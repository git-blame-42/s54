<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Listagem com duas tabelas paginadas na mesma tela: "Próximos" (parâmetro `page`) e
 * "Passados" (parâmetro `past_page`). Trocar de página numa tabela não mexe na outra.
 */
class TwoPaginatorsCest extends BaseEventsCest
{
    private const UPCOMING = '#upcoming-events';
    private const PAST = '#past-events';

    /** Eventos 01 a 07 vão para o passado: Passados = 01-05 / 06-07; Próximos = 08-12 / 13-16. */
    private function movePastEvents(): void
    {
        for ($number = 1; $number <= 7; $number++) {
            $this->events[$number]->update(['starts_on' => date('Y-m-d', (int) strtotime("-{$number} days"))]);
        }
    }

    /**
     * Confere as linhas de uma tabela: exatamente os Eventos de $first a $last.
     */
    private function seeRows(AcceptanceTester $page, string $section, int $first, int $last): void
    {
        $page->seeNumberOfElements("{$section} table tbody tr", $last - $first + 1);
        $page->see($this->title($first), $section);
        $page->see($this->title($last), $section);
        if ($first > 1) {
            $page->dontSee($this->title($first - 1), $section);
        }
        if ($last < self::TOTAL_EVENTS) {
            $page->dontSee($this->title($last + 1), $section);
        }
    }

    public function paginateEachTableIndependently(AcceptanceTester $page): void
    {
        $page->wantTo('Paginar Próximos e Passados de forma independente na mesma tela');
        $this->movePastEvents();
        $page->amOnPage('/admin/events');

        $page->see('Próximos', self::UPCOMING . ' h2');
        $page->see('Passados', self::PAST . ' h2');
        $this->seeRows($page, self::UPCOMING, 8, 12);
        $page->see('Mostrando 1 - 5 de 9', self::UPCOMING);
        $this->seeRows($page, self::PAST, 1, 5);
        $page->see('Mostrando 1 - 5 de 7', self::PAST);

        // Página 2 de Passados: Próximos continua na página 1.
        $page->click('2', self::PAST . ' .pagination');
        $page->seeCurrentUrlEquals('/admin/events?past_page=2');
        $this->seeRows($page, self::PAST, 6, 7);
        $this->seeRows($page, self::UPCOMING, 8, 12);

        // "Próxima" de Próximos: Passados continua na página 2.
        $page->click('Próxima', self::UPCOMING . ' .pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2?past_page=2');
        $this->seeRows($page, self::UPCOMING, 13, 16);
        $this->seeRows($page, self::PAST, 6, 7);

        // "Anterior" de Passados: Próximos continua na página 2.
        $page->click('Anterior', self::PAST . ' .pagination');
        $page->seeCurrentUrlEquals('/admin/events?page=2&past_page=1');
        $this->seeRows($page, self::PAST, 1, 5);
        $this->seeRows($page, self::UPCOMING, 13, 16);
    }

    public function noPastEventsShowsEmptyMessage(AcceptanceTester $page): void
    {
        $page->wantTo('Ver a tabela de Passados vazia quando todos os Eventos são futuros');
        $page->amOnPage('/admin/events');

        $page->see('Nenhum evento passado.', self::PAST);
        $page->dontSeeElement(self::PAST . ' table');
        $page->dontSeeElement(self::PAST . ' .pagination');
        $page->seeNumberOfElements('.pagination', 1);
        $page->see('Mostrando 1 - 5 de 16', self::UPCOMING);
    }
}
