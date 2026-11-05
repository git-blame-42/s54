<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class ChooseResponsibleCest extends BaseEventsCest
{
    public function createEventForAnotherAdmin(AcceptanceTester $page): void
    {
        $other = $this->createUser('Administradora Dois', 'admin2@example.com', 'admin');
        $data = $this->validEventData(['title' => 'Evento da Administradora Dois']);

        $page->wantTo('Cadastrar um Evento escolhendo outro administrador como responsável');
        $page->amOnPage('/admin/events/new');

        // Só administradores no select; o logado vem selecionado.
        $page->seeOptionIsSelected('#event_user_id', 'Administrador');
        $page->dontSee('Participante 1', '#event_user_id');

        $this->fillEventForm($page, $data);
        $page->selectOption('#event_user_id', 'Administradora Dois');
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $page->see('Administradora Dois', '//dt[normalize-space(.)="Responsável"]/following-sibling::dd[1]');

        $event = Event::findBy(['title' => $data['title']]);
        Assert::assertNotNull($event);
        Assert::assertEquals($other->id, $event->user_id);

        // Na edição, o responsável gravado vem selecionado.
        $page->click('Editar');
        $page->seeOptionIsSelected('#event_user_id', 'Administradora Dois');
    }
}
