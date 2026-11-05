<?php

namespace Tests\Acceptance\admin\events;

use Tests\Support\AcceptanceTester;

/**
 * Filtro da listagem por período (de/até), mantido na paginação.
 * No BaseEventsCest o "Evento NN" começa em hoje + (13 + NN) dias.
 */
class FilterEventsByPeriodCest extends BaseEventsCest
{
    public function filterByPeriodAndGoToPage2(AcceptanceTester $page): void
    {
        $from = $this->startsOn(2);
        $to = $this->startsOn(13);

        $page->wantTo('Filtrar a listagem por período e ir à página 2 mantendo o filtro');
        $page->amOnPage('/admin/events');
        $this->setDate($page, 'filter_from', $from);
        $this->setDate($page, 'filter_to', $to);
        $page->click('Filtrar');

        $page->seeCurrentUrlEquals("/admin/events?from={$from}&to={$to}");
        $page->see('Mostrando 1 - 5 de 12');
        $page->dontSee('Evento 01', 'table');
        $page->see('Evento 02', 'table');
        $page->see('Evento 06', 'table');
        $page->seeInField('#filter_from', $from);
        $page->seeInField('#filter_to', $to);

        $page->click('2', '.pagination');
        $page->seeCurrentUrlEquals("/admin/events/page/2?from={$from}&to={$to}");
        $page->see('Mostrando 6 - 10 de 12');
        $page->see('Evento 07', 'table');
        $page->see('Evento 11', 'table');

        $page->click('Próxima', '.pagination');
        $page->seeCurrentUrlEquals("/admin/events/page/3?from={$from}&to={$to}");
        $page->see('Evento 12', 'table');
        $page->see('Evento 13', 'table');
        $page->dontSee('Evento 14', 'table');

        $page->click('Limpar');
        $page->seeCurrentUrlEquals('/admin/events');
        $page->see('Mostrando 1 - 5 de 16');
    }

    public function invalidDateShowsErrorAndUnfilteredList(AcceptanceTester $page): void
    {
        $page->wantTo('Data inválida no filtro mostra erro e a listagem sem filtro');
        $page->amOnPage('/admin/events?from=2026-13-40&to=');

        $page->see('Data inicial inválida no filtro! A listagem abaixo está sem filtro.');
        $page->see('Mostrando 1 - 5 de 16');
        $page->see('Evento 01', 'table');
    }

    public function periodWithFromAfterToShowsError(AcceptanceTester $page): void
    {
        $page->wantTo('Data inicial depois da final é acusada');
        $page->amOnPage('/admin/events');
        $this->setDate($page, 'filter_from', $this->startsOn(10));
        $this->setDate($page, 'filter_to', $this->startsOn(5));
        $page->click('Filtrar');

        $page->see('A data inicial do filtro não pode ser depois da data final!');
        $page->see('Mostrando 1 - 5 de 16');
    }

    private function setDate(AcceptanceTester $page, string $id, string $value): void
    {
        // input[type=date] depende do idioma do navegador ao digitar; definir o valor ISO é determinístico.
        $page->executeJS('document.getElementById(arguments[0]).value = arguments[1];', [$id, $value]);
    }
}
