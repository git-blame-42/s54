<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Filtro da listagem por tipo (`?kind=`), mantido na paginação.
 *
 * A base cria 16 Eventos com os tipos em rodízio: Palestra (01, 05, 09, 13), Oficina (02, 06, 10, 14),
 * Minicurso (03, 07, 11, 15) e Semana acadêmica (04, 08, 12, 16). Cada tipo cabe numa página de 5.
 */
class FilterEventsCest extends BaseEventsCest
{
    private const WORKSHOPS = [2, 6, 10, 14];

    public function filterByKind(AcceptanceTester $page): void
    {
        $page->wantTo('Filtrar a listagem por tipo');
        $page->amOnPage('/admin/events');

        $page->selectOption('#filter_kind', 'Oficina');
        $page->click('Filtrar');

        $page->seeCurrentUrlEquals('/admin/events?kind=workshop');
        $page->seeOptionIsSelected('#filter_kind', 'Oficina');
        $page->seeNumberOfElements('table tbody tr', count(self::WORKSHOPS));
        for ($i = 1; $i <= self::TOTAL_EVENTS; $i++) {
            if (in_array($i, self::WORKSHOPS, true)) {
                $page->see($this->title($i), 'table');
            } else {
                $page->dontSee($this->title($i), 'table');
            }
        }
        $page->see('Mostrando 1 - 4 de 4');
    }

    public function keepFilterOnPageTwo(AcceptanceTester $page): void
    {
        $page->wantTo('Filtrar a listagem por tipo e ir à página 2 com o filtro mantido');

        // Mais duas Oficinas pelo formulário: 6 Oficinas, 2 páginas no filtro.
        $this->createWorkshop($page, 'Oficina Extra 1');
        $this->createWorkshop($page, 'Oficina Extra 2');

        $page->amOnPage('/admin/events?kind=workshop');
        $page->see('Mostrando 1 - 5 de 6');
        $page->see('Oficina Extra 1', 'table');
        $page->dontSee('Oficina Extra 2', 'table');
        $page->seeLink('2', '/admin/events/page/2?kind=workshop');

        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/2?kind=workshop');
        $page->seeOptionIsSelected('#filter_kind', 'Oficina');
        $page->seeNumberOfElements('table tbody tr', 1);
        $page->see('Oficina Extra 2', 'table');
        $page->dontSee($this->title(2), 'table');
        $page->dontSee($this->title(5), 'table');
        $page->see('Mostrando 6 - 6 de 6');

        $page->click('Anterior', '.pagination');
        $page->seeCurrentUrlEquals('/admin/events/page/1?kind=workshop');
        $page->see($this->title(2), 'table');
        $page->dontSee($this->title(1), 'table');
    }

    public function clearFilter(AcceptanceTester $page): void
    {
        $page->wantTo('Voltar a listar todos os tipos');
        $page->amOnPage('/admin/events?kind=workshop');
        $page->dontSee($this->title(1), 'table');

        $page->selectOption('#filter_kind', 'Todos');
        $page->click('Filtrar');

        $page->seeCurrentUrlEquals('/admin/events?kind=');
        $page->see($this->title(1), 'table');
        $page->see($this->title(5), 'table');
        $page->see('Mostrando 1 - 5 de 16');
    }

    /** Cadastra uma Oficina pelo formulário (validEventData já usa o tipo Oficina). */
    private function createWorkshop(AcceptanceTester $page, string $title): void
    {
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $this->validEventData(['title' => $title]));
        $page->click('Salvar');
        $page->see('Evento cadastrado com sucesso!');
    }
}
