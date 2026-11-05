<?php

namespace Tests\Acceptance\admin\events;

use App\Models\EventChange;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Histórico de alterações no show do Evento: editar pela Área Administrativa grava uma linha
 * por campo alterado, com o Administrador logado como autor.
 */
class EventHistoryCest extends BaseEventsCest
{
    public function editAndSeeHistoryOnShow(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Editar um Evento e ver o histórico de alterações no show');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->see('Histórico de alterações', 'h2');
        $page->see('Nenhuma alteração registrada.', '#history');

        $page->click('Editar');
        $this->fillEventForm($page, ['title' => 'Evento 02 <i>Revisto</i>', 'capacity' => '30']);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento atualizado com sucesso!', '.alert-success');
        $page->dontSee('Nenhuma alteração registrada.');
        $page->seeNumberOfElements('#history tr.event-change', 2);

        $titleRow = "//section[@id='history']//tr[td[1][normalize-space(.)='Título']]";
        $page->see('Evento 02', "{$titleRow}/td[2]");
        $page->see('Evento 02 <i>Revisto</i>', "{$titleRow}/td[3]");
        $page->see('Administrador', "{$titleRow}/td[4]");
        $page->dontSeeElement('#history i');

        $capacityRow = "//section[@id='history']//tr[td[1][normalize-space(.)='Vagas']]";
        $page->see((string) $event->capacity, "{$capacityRow}/td[2]");
        $page->see('30', "{$capacityRow}/td[3]");

        Assert::assertCount(2, EventChange::forEvent($event->id));
    }

    public function saveWithoutChangesAddsNoHistory(AcceptanceTester $page): void
    {
        $event = $this->events[3];

        $page->wantTo('Salvar sem mudar nada não acrescenta linhas ao histórico');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $this->fillEventForm($page, ['location' => 'Auditório Novo']);
        $page->click('Salvar');
        $page->seeNumberOfElements('#history tr.event-change', 1);

        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->click('Salvar');

        $page->see('Evento atualizado com sucesso!', '.alert-success');
        $page->seeNumberOfElements('#history tr.event-change', 1);
        $page->see('Auditório Novo', '#history');
        Assert::assertCount(1, EventChange::forEvent($event->id));
    }
}
