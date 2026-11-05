<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Local obrigatório só para presencial e híbrido; online exige link.
 */
class CreateByModalityCest extends BaseEventsCest
{
    public function onlineEventRequiresLinkButNotLocation(AcceptanceTester $page): void
    {
        $page->wantTo('Cadastrar Evento online: link obrigatório, local opcional');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData([
            'title' => 'Live de Boas-Vindas',
            'modality' => 'online',
            'location' => '',
            'link' => '',
        ]));
        $page->click('Salvar');

        $page->see('Existem dados incorretos! Por favor, verifique!');
        $page->seeElement('#event_link.is-invalid');
        $page->see('não pode ser vazio!', '#event_link ~ .invalid-feedback');
        $page->dontSeeElement('#event_location.is-invalid');

        $page->fillField('#event_link', 'https://meet.example.com/boas-vindas');
        $page->click('Salvar');

        $event = Event::findBy(['title' => 'Live de Boas-Vindas']);
        Assert::assertNotNull($event);
        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cadastrado com sucesso!');
        $page->see('https://meet.example.com/boas-vindas');
        Assert::assertEmpty($event->location);
    }

    public function inPersonEventRequiresLocation(AcceptanceTester $page): void
    {
        $page->wantTo('Cadastrar Evento presencial sem local');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData([
            'modality' => 'in_person',
            'location' => '',
            'link' => '',
        ]));
        $page->click('Salvar');

        $page->seeElement('#event_location.is-invalid');
        $page->see('não pode ser vazio!', '#event_location ~ .invalid-feedback');
        $page->dontSeeElement('#event_link.is-invalid');
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }
}
