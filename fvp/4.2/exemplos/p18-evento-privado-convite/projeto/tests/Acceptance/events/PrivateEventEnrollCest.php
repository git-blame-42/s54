<?php

namespace Tests\Acceptance\events;

use PHPUnit\Framework\Assert;
use Tests\Support\AcceptanceTester;

class PrivateEventEnrollCest extends BaseParticipantEventsCest
{
    public function enrollInPrivateEventWithInviteCode(AcceptanceTester $page): void
    {
        $event = $this->events[2];
        $event->is_private = 1;
        // Código fixo (o save() só gera quando vazio): um sorteado poderia coincidir com o ano da data.
        $event->invite_code = 4321;
        Assert::assertTrue($event->save());
        $code = '4321';
        $wrong = '4322';

        $page->wantTo('Inscrever-se em Evento privado com o código de convite');
        $page->amOnPage("/events/{$event->id}");
        $page->see('Evento privado: informe o código de convite');
        $page->dontSee($code);

        // Sem código.
        $page->click('Inscrever-se');
        $page->see('código de convite inválido!');

        // Código errado.
        $page->fillField('#invite_code', $wrong);
        $page->click('Inscrever-se');
        $page->see('código de convite inválido!');
        Assert::assertFalse($this->participants[1]->isEnrolledIn($event));

        // Código certo.
        $page->fillField('#invite_code', $code);
        $page->click('Inscrever-se');
        $page->seeCurrentUrlEquals("/events/{$event->id}");
        $page->see('Inscrição realizada com sucesso!');
        $page->see('Você está inscrito neste evento.');
        Assert::assertTrue($this->participants[1]->isEnrolledIn($event));
    }
}
