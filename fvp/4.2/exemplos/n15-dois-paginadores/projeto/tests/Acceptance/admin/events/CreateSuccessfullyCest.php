<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class CreateSuccessfullyCest extends BaseEventsCest
{
    public function createEventSuccessfully(AcceptanceTester $page): void
    {
        $data = $this->validEventData();

        $page->wantTo('Tentativa de cadastro bem-sucedida');
        $page->amOnPage('/admin/events');
        $page->click('Novo Evento');
        $page->seeCurrentUrlEquals('/admin/events/new');
        $page->see('Novo Evento', 'h1');

        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        // O redirect vai para o show do Evento novo, com o flash e os dados à vista.
        $event = Event::findBy(['title' => $data['title']]);
        Assert::assertNotNull($event);
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cadastrado com sucesso!');
        $page->see($data['title'], 'h1');
        $page->see($data['location']);

        // "Próximos" vem em ordem de data: o Evento novo (amanhã) abre a 1ª página; 17 Eventos.
        $page->amOnPage('/admin/events');
        $page->see($data['title'], '#upcoming-events table tbody tr:first-child');
        $page->see('Mostrando 1 - 5 de 17');

        Assert::assertCount(self::TOTAL_EVENTS + 1, Event::all());
        Assert::assertEquals($this->admin->id, $event->user_id);
        Assert::assertSame($data['description'], $event->description);
        Assert::assertSame('workshop', $event->kind);
        Assert::assertSame('online', $event->modality);
        Assert::assertSame(['students', 'staff'], $event->audienceList());
        Assert::assertSame($data['starts_on'], $event->starts_on);
        Assert::assertSame($data['location'], $event->location);
        Assert::assertEquals(40, $event->capacity);
        Assert::assertSame(0, $event->enrollmentsCount());
    }
}
