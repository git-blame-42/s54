<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Busca por título, filtro por tipo e ordenação escolhida, juntos e mantidos ao trocar de página.
 */
class SearchEventsCest extends BaseEventsCest
{
    public function searchSortPaginateAndFilter(AcceptanceTester $page): void
    {
        $page->wantTo('Buscar, ordenar e filtrar a listagem, mantendo tudo ao paginar');
        $page->amOnPage('/admin/events');

        // "1" casa o Evento 01 e os Eventos 10 a 16 ("Evento 1" não casaria o 01).
        $page->fillField('#filter_q', '1');
        $page->selectOption('#filter_sort', 'Título');
        $page->selectOption('#filter_dir', 'Decrescente');
        $page->click('Filtrar');
        $page->see('Mostrando 1 - 5 de 8');
        $this->seeTitlesInOrder($page, [16, 15, 14, 13, 12]);

        // Página 2: busca e ordenação continuam na URL e na tela.
        $page->click('2', '.pagination');
        $page->seeInCurrentUrl('/admin/events/page/2?q=1&sort=title&dir=desc');
        $page->see('Mostrando 6 - 8 de 8');
        $this->seeTitlesInOrder($page, [11, 10, 1]);
        $page->see($this->title(1), 'table tbody tr:last-child');
        $page->seeInField('#filter_q', '1');
        $page->seeOptionIsSelected('#filter_sort', 'Título');
        $page->seeOptionIsSelected('#filter_dir', 'Decrescente');

        // Filtro por tipo junto: Palestras são os Eventos 01, 05, 09 e 13; só 13 e 01 casam a busca.
        $page->selectOption('#filter_kind', 'Palestra');
        $page->click('Filtrar');
        $page->see('Mostrando 1 - 2 de 2');
        $this->seeTitlesInOrder($page, [13, 1]);
    }

    public function invalidParamShowsWarningAndUsesDefault(AcceptanceTester $page): void
    {
        $page->wantTo('Ver um aviso quando a listagem recebe um parâmetro inválido');
        $page->amOnPage('/admin/events?kind=party&sort=title&dir=desc');

        $page->see('Tipo inválido.', '.alert-warning');
        // O tipo recusado volta a "Todos os tipos"; a ordenação válida continua valendo.
        $page->seeOptionIsSelected('#filter_kind', 'Todos os tipos');
        $page->see('Mostrando 1 - 5 de 16');
        $page->see($this->title(16), 'table tbody tr:first-child');
    }

    /**
     * Linhas da tabela: exatamente estes Eventos, nesta ordem.
     *
     * @param array<int, int> $numbers
     */
    private function seeTitlesInOrder(AcceptanceTester $page, array $numbers): void
    {
        $page->seeNumberOfElements('table tbody tr', count($numbers));
        foreach ($numbers as $index => $number) {
            $page->see($this->title($number), 'table tbody tr:nth-child(' . ($index + 1) . ')');
        }
    }
}
