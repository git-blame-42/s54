<?php

namespace Tests\Acceptance\events;

use Tests\Acceptance\admin\events\BaseEventsCest;
use Tests\Support\AcceptanceTester;

/**
 * Base dos cenários da área do Participante (/events): mesmos dados de BaseEventsCest (16 Eventos,
 * Evento 01 lotado 3/3, Evento 03 com uma Inscrição do Participante 1), mas a sessão é do
 * Participante 1 (participante1@example.com) em vez do Administrador.
 */
class BaseParticipantEventsCest extends BaseEventsCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);

        $page->amOnPage('/logout');
        $page->login($this->participants[1]->email, self::PASSWORD);
    }
}
