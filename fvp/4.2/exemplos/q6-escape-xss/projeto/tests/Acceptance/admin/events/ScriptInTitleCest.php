<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Título com HTML aparece como texto em todas as telas e nada executa: se o `<script>` rodasse, o
 * `alert` abriria um diálogo e o próximo passo do Selenium falharia.
 */
class ScriptInTitleCest extends BaseEventsCest
{
    private const SCRIPT = '<script>alert(1)</script>';

    public function scriptInTitleIsShownAsText(AcceptanceTester $page): void
    {
        $page->wantTo('Cadastrar Evento com <script> no título e ver o texto literal');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $this->validEventData(['title' => self::SCRIPT]));
        $page->click('Salvar');

        // O banco guarda o texto como foi digitado; o escape é só na saída.
        $event = Event::findBy(['title' => self::SCRIPT]);
        Assert::assertInstanceOf(Event::class, $event);

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cadastrado com sucesso!');
        $page->see(self::SCRIPT, 'h1');
        $page->see(self::SCRIPT, 'dl');

        $page->amOnPage('/admin/events/page/4');
        $page->see(self::SCRIPT, 'table');

        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->seeInField('#event_title', self::SCRIPT);
    }
}
