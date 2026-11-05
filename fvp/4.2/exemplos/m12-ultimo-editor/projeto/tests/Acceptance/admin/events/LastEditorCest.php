<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class LastEditorCest extends BaseEventsCest
{
    public function showLastEditorAfterUpdate(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Ver quem fez a última edição do Evento');
        $page->amOnPage("/admin/events/{$event->id}");
        $page->dontSee('Última edição por');

        $page->click('Editar');
        $this->fillEventForm($page, ['title' => 'Evento 02 Revisado']);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento atualizado com sucesso!');
        $page->see('Última edição por Administrador', '.last-editor');
        Assert::assertEquals($this->admin->id, Event::findById($event->id)?->updated_by);
    }
}
