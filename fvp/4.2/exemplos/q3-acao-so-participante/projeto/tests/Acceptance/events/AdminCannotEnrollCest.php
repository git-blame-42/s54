<?php

namespace Tests\Acceptance\events;

use App\Models\Enrollment;
use PHPUnit\Framework\Assert;
use Tests\Acceptance\admin\events\BaseEventsCest;
use Tests\Support\AcceptanceTester;

/**
 * O Administrador vê a página do Evento na área do Participante, mas sem o botão de inscrição.
 * (BaseEventsCest já faz login como Administrador.)
 */
class AdminCannotEnrollCest extends BaseEventsCest
{
    public function adminSeesNoEnrollButton(AcceptanceTester $page): void
    {
        $event = $this->events[2];

        $page->wantTo('Administrador não vê o botão Inscrever-se');
        $page->amOnPage("/events/{$event->id}");

        $page->see($event->title, 'h1');
        $page->see('Inscrições são feitas pelos participantes.');
        $page->dontSee('Inscrever-se');
        Assert::assertCount(0, Enrollment::where(['event_id' => $event->id]));
    }

    public function participantStillSeesTheEnrollButton(AcceptanceTester $page): void
    {
        $page->wantTo('Participante continua vendo o botão Inscrever-se');
        $page->amOnPage('/logout');
        $page->login($this->participants[2]->email, self::PASSWORD);
        $page->amOnPage("/events/{$this->events[2]->id}");

        $page->see('Inscrever-se');
        $page->dontSee('Inscrições são feitas pelos participantes.');
    }
}
