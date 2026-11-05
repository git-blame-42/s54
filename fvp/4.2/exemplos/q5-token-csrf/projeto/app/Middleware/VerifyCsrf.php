<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Csrf;

/**
 * Requisições que alteram estado (POST, PUT, DELETE) precisam do campo _token igual ao da
 * sessão. GET passa direto. Sem token ou com token errado: 403.
 */
class VerifyCsrf implements Middleware
{
    private const SAFE_METHODS = ['GET', 'HEAD'];

    public function handle(Request $request): void
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return;
        }

        // Só do corpo do POST (como o _method, que também só vale no corpo): token na query string vaza em log e Referer.
        if (!Csrf::check($_POST[Csrf::FIELD] ?? null)) {
            $this->deny();
        }
    }

    /**
     * protected para o teste unitário trocar o exit por um registro (dublê).
     */
    protected function deny(): void
    {
        http_response_code(403);
        echo '<h1>403 - Formulário expirado ou inválido</h1>'
            . '<p>Volte à página anterior, recarregue e envie de novo.</p>';
        exit;
    }
}
