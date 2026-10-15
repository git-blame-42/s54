<?php

namespace App\Middleware;

use Core\Http\Request;
use Lib\Authentication\Auth;

class Admin extends BaseMiddleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        // Sem usuário logado, quem redireciona é o middleware Authenticate.
        if ($user !== null && !$user->isAdmin()) {
            $this->deny('Acesso restrito a administradores', 'dashboard.index');
        }
    }
}
