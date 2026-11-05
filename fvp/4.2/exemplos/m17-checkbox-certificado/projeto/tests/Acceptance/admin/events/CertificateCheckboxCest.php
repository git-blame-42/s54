<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Checkbox "Emite certificado": marcado grava 1, desmarcado grava 0 (o checkbox desmarcado não vai no
 * POST; o hidden com value=0 antes dele garante o envio).
 */
class CertificateCheckboxCest extends BaseEventsCest
{
    public function createEventWithCertificate(AcceptanceTester $page): void
    {
        $data = $this->validEventData(['has_certificate' => true]);

        $page->wantTo('Cadastrar Evento marcando "Emite certificado"');
        $page->amOnPage('/admin/events/new');
        $page->dontSeeCheckboxIsChecked('#event_has_certificate');
        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $page->see('Sim', '.certificate');

        $stored = Event::findBy(['title' => $data['title']]);
        Assert::assertInstanceOf(Event::class, $stored);
        Assert::assertSame(1, $stored->has_certificate);
    }

    public function createEventWithoutCertificate(AcceptanceTester $page): void
    {
        $data = $this->validEventData();

        $page->wantTo('Cadastrar Evento com "Emite certificado" desmarcado');
        $page->amOnPage('/admin/events/new');
        $this->fillEventForm($page, $data);
        $page->click('Salvar');

        $page->see('Evento cadastrado com sucesso!');
        $page->see('Não', '.certificate');

        $stored = Event::findBy(['title' => $data['title']]);
        Assert::assertInstanceOf(Event::class, $stored);
        Assert::assertSame(0, $stored->has_certificate);
    }

    public function uncheckCertificateOnEdit(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Desmarcar "Emite certificado" ao editar um Evento');
        $page->amOnPage('/admin/events');
        $page->see('Sim', $this->rowOf('Evento 02'));
        $page->see('Não', $this->rowOf('Evento 01'));

        $page->click('Editar', $this->rowOf('Evento 02'));
        $page->seeCheckboxIsChecked('#event_has_certificate');
        $this->fillEventForm($page, ['has_certificate' => false]);
        $page->click('Salvar');

        $page->seeCurrentUrlEquals("/admin/events/{$event->id}");
        $page->see('Evento atualizado com sucesso!');
        $page->see('Não', '.certificate');

        $stored = Event::findById((int) $event->id);
        Assert::assertInstanceOf(Event::class, $stored);
        Assert::assertSame(0, $stored->has_certificate);
    }
}
