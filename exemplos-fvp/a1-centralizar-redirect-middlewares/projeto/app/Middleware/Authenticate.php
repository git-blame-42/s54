<?php

namespace App\Middleware;

use Core\Http\Request;
use Lib\Authentication\Auth;

class Authenticate extends BaseMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            $this->deny('Você deve estar logado para acessar essa página', 'users.login');
        }
    }
}
