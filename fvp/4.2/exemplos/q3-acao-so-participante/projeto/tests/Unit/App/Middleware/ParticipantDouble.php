<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Participant;

/**
 * Dublê do middleware Participant: guarda o destino do redirect em vez de enviar o header e dar exit.
 */
class ParticipantDouble extends Participant
{
    public ?string $location = null;

    protected function redirectTo(string $location): void
    {
        $this->location = $location;
    }
}
