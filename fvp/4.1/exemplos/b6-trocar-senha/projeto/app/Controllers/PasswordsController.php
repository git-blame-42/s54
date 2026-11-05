<?php

namespace App\Controllers;

use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class PasswordsController extends Controller
{
    public function edit(): void
    {
        $user = $this->current_user;
        $title = 'Trocar senha';
        $this->render('passwords/edit', compact('user', 'title'));
    }

    public function update(Request $request): void
    {
        $params = $request->getParam('user', []);
        $user = $this->current_user;

        $changed = $user !== null && $user->changePassword(
            $params['password'] ?? '',
            $params['password_confirmation'] ?? ''
        );

        if ($changed) {
            FlashMessage::success('Senha alterada com sucesso!');
            $this->redirectTo(route('dashboard.index'));
        } else {
            FlashMessage::danger('Não foi possível alterar a senha.');
            $title = 'Trocar senha';
            $this->render('passwords/edit', compact('user', 'title'));
        }
    }
}
