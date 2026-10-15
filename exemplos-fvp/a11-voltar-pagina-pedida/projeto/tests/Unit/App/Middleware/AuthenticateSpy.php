<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Authenticate;

/**
 * Dublê do middleware Authenticate: guarda o destino do redirect em vez de
 * enviar o header e chamar exit, que encerrariam o PHPUnit.
 */
class AuthenticateSpy extends Authenticate
{
    public ?string $redirectedTo = null;

    protected function redirectTo(string $location): void
    {
        $this->redirectedTo = $location;
    }
}
