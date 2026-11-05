<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Título único na mesma data: "Evento 01" já existe na data startsOn(1).
 */
class CreateDuplicateEventCest extends BaseEventsCest
{
    public function createWithSameTitleAndDate(AcceptanceTester $page): void
    {
        $page->wantTo('Tentativa de cadastro com título e data de um Evento existente');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData([
            'title' => $this->title(1),
            'starts_on' => $this->startsOn(1),
        ]));
        $page->click('Salvar');

        $page->see('Existem dados incorretos! Por favor, verifique!');
        $page->seeElement('#event_title.is-invalid');
        $page->see('já existe um registro com esse dado', '#event_title ~ .invalid-feedback');
        $page->seeInField('#event_title', $this->title(1));
        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function createWithSameTitleOnAnotherDate(AcceptanceTester $page): void
    {
        $page->wantTo('Cadastro com o título de um Evento existente em outra data');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData([
            'title' => $this->title(1),
            'starts_on' => $this->startsOn(2),
        ]));
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        Assert::assertCount(self::TOTAL_EVENTS + 1, Event::all());
    }
}
