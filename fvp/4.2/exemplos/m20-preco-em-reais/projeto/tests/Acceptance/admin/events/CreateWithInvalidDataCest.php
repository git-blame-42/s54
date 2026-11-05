<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class CreateWithInvalidDataCest extends BaseEventsCest
{
    private const FLASH = 'Existem dados incorretos! Por favor, verifique!';

    public function createWithEmptyForm(AcceptanceTester $page): void
    {
        $page->wantTo('Tentativa de cadastro com dados incorretos (formulário vazio)');
        $page->amOnPage('/admin/events/new');
        $page->see('Novo Evento', 'h1');

        $page->click('Salvar');

        $page->see(self::FLASH);
        $page->see('Novo Evento', 'h1');
        foreach (['title', 'description', 'kind', 'starts_on', 'location', 'capacity', 'price'] as $field) {
            $page->seeElement("#event_{$field}.is-invalid");
            $page->see('não pode ser vazio!', "#event_{$field} ~ .invalid-feedback");
        }
        $page->seeElement('input[name="event[modality]"].is-invalid');
        $page->seeElement('input[name="event[audience][]"].is-invalid');

        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function createWithInvalidDataKeepsValidValues(AcceptanceTester $page): void
    {
        $page->wantTo('Tentativa de cadastro com dados incorretos (valores válidos são mantidos)');
        $page->amOnPage('/admin/events/new');

        $page->fillField('#event_title', 'Oficina de Testes Automatizados');
        $page->fillField('#event_description', 'Oficina prática de testes de aceitação.');
        $page->selectOption('#event_kind', Event::KINDS['workshop']);
        $page->checkOption('#event_modality_online');
        $page->checkOption('#event_audience_students');
        $page->checkOption('#event_audience_staff');
        $page->fillField('#event_location', 'Laboratório B203');
        $page->fillField('#event_capacity', '0');
        $page->fillField('#event_price', '10,5');
        $this->fillEventForm($page, ['starts_on' => date('Y-m-d', strtotime('-1 day'))]);
        $page->click('Salvar');

        $page->see(self::FLASH);
        $page->see('Novo Evento', 'h1');

        // Apenas os campos incorretos recebem erro...
        $page->seeElement('#event_starts_on.is-invalid');
        $page->see('não pode estar no passado!', '#event_starts_on ~ .invalid-feedback');
        $page->seeElement('#event_capacity.is-invalid');
        $page->see('deve ser maior ou igual a 1!', '#event_capacity ~ .invalid-feedback');
        foreach (['title', 'description', 'kind', 'location', 'price'] as $field) {
            $page->dontSeeElement("#event_{$field}.is-invalid");
        }
        $page->dontSeeElement('input[name="event[modality]"].is-invalid');
        $page->dontSeeElement('input[name="event[audience][]"].is-invalid');

        // ...e os valores enviados continuam no formulário.
        $page->seeInField('#event_title', 'Oficina de Testes Automatizados');
        $page->seeInField('#event_description', 'Oficina prática de testes de aceitação.');
        $page->seeOptionIsSelected('#event_kind', Event::KINDS['workshop']);
        $page->seeCheckboxIsChecked('#event_modality_online');
        $page->dontSeeCheckboxIsChecked('#event_modality_in_person');
        $page->seeCheckboxIsChecked('#event_audience_students');
        $page->seeCheckboxIsChecked('#event_audience_staff');
        $page->dontSeeCheckboxIsChecked('#event_audience_professors');
        $page->seeInField('#event_location', 'Laboratório B203');
        $page->seeInField('#event_price', '10,50');

        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }

    public function createWithInvalidPriceShowsErrorBelowTheField(AcceptanceTester $page): void
    {
        $page->wantTo('Tentativa de cadastro com preço inválido (notação científica)');
        $page->amOnPage('/admin/events/new');

        $this->fillEventForm($page, $this->validEventData(['price' => '1e3']));
        $page->click('Salvar');

        $page->see(self::FLASH);
        $page->seeElement('#event_price.is-invalid');
        $page->see('deve ser um valor entre 0 e 999999,99, como 10,50!', '#event_price ~ .invalid-feedback');
        $page->seeInField('#event_price', '1e3');

        Assert::assertCount(self::TOTAL_EVENTS, Event::all());
    }
}
