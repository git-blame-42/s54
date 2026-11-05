<?php

namespace Tests\Unit\App\Middleware;

use App\Middleware\VerifyCsrf;

/**
 * Dublê do VerifyCsrf: registra a recusa em vez de responder 403 e dar exit.
 */
class VerifyCsrfDouble extends VerifyCsrf
{
    public bool $denied = false;

    protected function deny(): void
    {
        $this->denied = true;
    }
}
