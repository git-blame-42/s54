<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class Authenticate implements Middleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            $message = Auth::wasExpired()
                ? 'Sua sessão expirou. Faça login novamente.'
                : 'Você deve estar logado para acessar essa página';

            FlashMessage::danger($message);
            $this->redirectTo(route('users.login'));
        }
    }

    protected function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
