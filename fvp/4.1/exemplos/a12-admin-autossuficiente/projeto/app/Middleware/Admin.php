<?php

namespace App\Middleware;

use Core\Http\Request;
use Lib\Authentication\Auth;

/**
 * Libera a rota só para usuários com perfil admin.
 *
 * É autossuficiente: não depende de o `Authenticate` rodar antes. O
 * `RouteWrapperMiddleware::group` adiciona os middlewares do grupo
 * interno primeiro, então em `/admin` a ordem real é
 * `[Admin, Authenticate]`. Por isso o `Admin` trata sozinho o caso sem
 * usuário logado e pode proteger uma rota mesmo fora do grupo `auth`.
 */
class Admin extends BaseMiddleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        if ($user === null) {
            $this->deny('Você deve estar logado para acessar essa página', 'users.login');
            return;
        }

        if (!$user->isAdmin()) {
            $this->deny('Acesso restrito a administradores', 'dashboard.index');
        }
    }
}
