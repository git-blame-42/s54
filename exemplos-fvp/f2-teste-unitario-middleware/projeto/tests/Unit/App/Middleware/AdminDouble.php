<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Admin;

/**
 * Dublê do middleware Admin para testes unitários.
 *
 * O `redirectTo` real envia o cabeçalho e encerra o processo com `exit`.
 * Aqui ele só guarda o destino, para o teste conferir se houve redirecionamento.
 */
class AdminDouble extends Admin
{
    public ?string $location = null;

    protected function redirectTo(string $location): void
    {
        $this->location = $location;
    }
}
