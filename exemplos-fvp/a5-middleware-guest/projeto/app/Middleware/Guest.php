<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;

/**
 * Middleware para rotas de visitante (login). Quem já está logado
 * não precisa ver a tela de login: vai direto para o painel.
 */
class Guest implements Middleware
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            $this->redirectTo(route('dashboard.index'));
        }
    }

    protected function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
