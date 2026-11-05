<?php

namespace Tests\Acceptance\admin\events;

use App\Models\Event;
use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

/**
 * Evento 01 da base tem 3 Inscrições (3/3): a data e o tipo não podem mudar.
 */
class UpdateEventWithEnrollmentsCest extends BaseEventsCest
{
    public function startsOnIsLockedAndRejectedWithEnrollments(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Tentar mudar a data de um Evento que já tem inscritos');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->see('A data não pode mudar: o Evento já tem 3 inscrito(s).');
        $page->seeElement('#event_starts_on[readonly]');
        $page->seeElement('#event_kind[disabled]');
        $page->see('O tipo não pode mudar: o Evento já tem inscritos.');

        // O readonly é só aviso: forçar o valor pelo navegador não passa pelo model.
        $this->fillEventForm($page, ['starts_on' => date('Y-m-d', strtotime('+90 days'))]);
        $page->click('Salvar');

        $page->see('Existem dados incorretos! Por favor, verifique!');
        $page->seeElement('#event_starts_on.is-invalid');
        $page->see('não pode mudar: já há inscritos!', '#event_starts_on ~ .invalid-feedback');
        Assert::assertSame($event->starts_on, Event::findById($event->id)->starts_on);
    }

    public function eventWithEnrollmentsCanBeEditedKeepingTheDate(AcceptanceTester $page): void
    {
        $event = $this->events[1];

        $page->wantTo('Editar o título de um Evento com inscritos, sem mudar a data');
        $page->amOnPage("/admin/events/{$event->id}/edit");
        $page->fillField('#event_title', 'Evento 01 Revisado');
        $page->click('Salvar');

        $page->see('Evento atualizado com sucesso!');
        $stored = Event::findById($event->id);
        Assert::assertSame('Evento 01 Revisado', $stored->title);
        // O tipo foi enviado pelo campo oculto e continua o mesmo.
        Assert::assertSame($event->kind, $stored->kind);
    }
}
