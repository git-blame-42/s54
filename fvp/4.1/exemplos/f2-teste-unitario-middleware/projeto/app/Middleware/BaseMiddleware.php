<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Lib\FlashMessage;

/**
 * Classe base dos middlewares da aplicação.
 *
 * Centraliza o que `Authenticate` e `Admin` faziam em duplicidade:
 * registrar uma mensagem flash de erro e redirecionar para uma rota
 * nomeada. Cada middleware concreto só decide QUANDO negar o acesso;
 * o COMO negar fica aqui.
 */
abstract class BaseMiddleware implements Middleware
{
    /**
     * Nega o acesso: grava a mensagem de erro e redireciona para a rota.
     */
    protected function deny(string $message, string $routeName): void
    {
        FlashMessage::danger($message);
        $this->redirectTo(route($routeName));
    }

    protected function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
