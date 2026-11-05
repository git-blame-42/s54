<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Vagas ilimitadas: o campo Vagas em branco grava capacity NULL, e a tela mostra "ilimitadas"
 * em vez de "0" ou "0 disponíveis".
 */
class UnlimitedCapacityCest extends BaseEventsCest
{
    public function createEventWithUnlimitedCapacity(AcceptanceTester $page): void
    {
        $data = $this->validEventData(['capacity' => '']);

        $page->wantTo('Cadastrar Evento com vagas ilimitadas (campo Vagas em branco)');
        $page->amOnPage('/admin/events/new');
        $page->see('Deixe em branco para vagas ilimitadas.');

        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $event = Event::findBy(['title' => $data['title']]);
        Assert::assertNotNull($event);
        Assert::assertNull($event->capacity);

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento cadastrado com sucesso!');
        $page->see('0 (vagas ilimitadas)');
        $page->dontSee('disponíveis');

        // O Evento novo vai para o fim da listagem (17º, 4ª página).
        $page->amOnPage('/admin/events/page/4');
        $page->see('0 (ilimitadas)', $this->rowOf($data['title']));
    }

    public function fullEventCanBecomeUnlimited(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Tirar o limite de vagas de um Evento lotado');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->seeInField('#event_capacity', '3');

        $this->fillEventForm($page, ['capacity' => '']);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento atualizado com sucesso!');
        $page->see('3 (vagas ilimitadas)');
        $page->dontSee('disponíveis');

        $stored = Event::findById((int) $event->id);
        Assert::assertNotNull($stored);
        Assert::assertNull($stored->capacity);

        $page->amOnPage('/admin/events');
        $page->see('3 (ilimitadas)', $this->rowOf('Evento 01'));

        // De volta à edição, o campo abre em branco (não "0").
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->seeInField('#event_capacity', '');
    }
}
