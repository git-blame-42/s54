<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Q2: Evento passado ou cancelado não pode ser editado nem removido.
 */
class PastEventCest extends BaseEventsCest
{
    public function pastEventHasNoEditOrRemoveButtons(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        // update() não valida: simula um Evento que já aconteceu.
        $event->update(['starts_on' => date('Y-m-d', strtotime('-5 days'))]);

        $page->wantTo('Evento passado sem os botões Editar e Remover');
        $page->amOnPage('/admin/events');

        $page->see('Evento 02', $this->rowOf('Evento 02'));
        $page->dontSee('Editar', $this->rowOf('Evento 02'));
        $page->dontSee('Remover', $this->rowOf('Evento 02'));
        $page->see('Editar', $this->rowOf('Evento 04'));

        $page->click('Ver', $this->rowOf('Evento 02'));
        $page->dontSeeLink('Editar');
    }

    public function pastEventEditUrlRedirectsToShow(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $event->update(['starts_on' => date('Y-m-d', strtotime('-5 days'))]);

        $page->wantTo('URL direta de edição de Evento passado redireciona');
        $page->amOnPage("/admin/events/{$event->id}/edit");

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento passado ou cancelado não pode ser alterado!');
        $page->dontSee('Editar Evento', 'h1');
        Assert::assertNotNull(Event::findById((int) $event->id));
    }

    public function canceledEventHasNoEditOrRemoveButtons(AcceptanceTester $page): void
    {
        $this->events[4]->cancel();

        $page->wantTo('Evento cancelado sem os botões Editar e Remover');
        $page->amOnPage('/admin/events');

        $page->see('Cancelado', $this->rowOf('Evento 04'));
        $page->dontSee('Editar', $this->rowOf('Evento 04'));
        $page->dontSee('Remover', $this->rowOf('Evento 04'));
    }
}
