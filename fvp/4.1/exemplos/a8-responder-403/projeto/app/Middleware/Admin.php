<?php

namespace App\Middleware;

use Core\Exceptions\HTTPException;
use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;

class Admin implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        // Sem usuário logado, quem redireciona é o middleware Authenticate.
        // Logado sem perfil admin: resposta 403 (Forbidden), sem redirecionar.
        // O ErrorsHandler converte a HTTPException no status HTTP da resposta.
        if ($user !== null && !$user->isAdmin()) {
            throw new HTTPException('Acesso restrito a administradores', 403);
        }
    }
}
