<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Authenticate;

/**
 * Dublê do middleware Authenticate para testes unitários.
 *
 * O `redirectTo` real envia o cabeçalho e encerra o processo com `exit`.
 * Aqui ele só guarda o destino, para o teste conferir se houve redirecionamento.
 */
class AuthenticateDouble extends Authenticate
{
    public ?string $location = null;

    protected function redirectTo(string $location): void
    {
        $this->location = $location;
    }
}
