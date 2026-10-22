<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class Admin implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        // O core garante que, no grupo 'admin' aninhado em 'auth', o
        // Authenticate roda antes. Mesmo assim o Admin não depende dessa
        // ordem: sem usuário logado ele não decide nada e deixa o
        // Authenticate redirecionar para o login.
        if ($user !== null && !$user->isAdmin()) {
            FlashMessage::danger('Acesso restrito a administradores');
            $this->redirectTo(route('dashboard.index'));
        }
    }

    private function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
