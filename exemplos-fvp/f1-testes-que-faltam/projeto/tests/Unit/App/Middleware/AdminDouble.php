<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\Admin;

/**
 * Dublê de teste: captura o destino do redirecionamento em vez de chamar
 * header() + exit, no mesmo espírito do redirectTo sobrescrito em
 * ControllerTestCase.
 */
class AdminDouble extends Admin
{
    public ?string $redirectedTo = null;

    protected function redirectTo(string $location): void
    {
        $this->redirectedTo = $location;
    }
}
