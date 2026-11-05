<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Admin;

/**
 * Dublê do middleware Admin: guarda o destino do redirecionamento em vez
 * de enviar o cabeçalho e encerrar o processo com `exit`.
 */
class AdminSpy extends Admin
{
    public ?string $redirectedTo = null;

    protected function redirectTo(string $location): void
    {
        $this->redirectedTo = $location;
    }
}
