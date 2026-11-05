<?php

namespace App\Middleware;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

/**
 * Q3: ações exclusivas do Participante (ex.: inscrever-se). O Administrador é barrado e vai
 * para a área administrativa. Espelho do middleware Admin.
 */
class Participant implements Middleware
{
    public function handle(Request $request): void
    {
        $user = Auth::user();

        // Sem usuário logado, quem redireciona é o middleware Authenticate.
        if ($user !== null && $user->isAdmin()) {
            FlashMessage::danger('Ação disponível apenas para participantes!');
            $this->redirectTo(route('admin.dashboard.index'));
        }
    }

    /**
     * protected (e não private como no Admin) para o teste unitário trocar o exit por um registro.
     */
    protected function redirectTo(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
