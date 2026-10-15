<?php

namespace App\Controllers;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class ProfileController extends Controller
{
    public function edit(): void
    {
        $user = $this->current_user;
        $title = 'Editar Perfil';
        $this->render('profile/edit', compact('user', 'title'));
    }

    public function update(Request $request): void
    {
        $params = $request->getParam('user', []);
        if (!is_array($params)) {
            $params = [];
        }

        // Cópia separada: se a validação falhar, a navbar continua mostrando os dados salvos.
        $user = User::findById($this->current_user->id);
        $user->name = $params['name'] ?? '';
        $user->email = $params['email'] ?? '';

        if ($user->save()) {
            FlashMessage::success('Perfil atualizado com sucesso!');
            $this->redirectTo(route('dashboard.index'));
        } else {
            FlashMessage::danger('Existem dados incorretos!');
            $title = 'Editar Perfil';
            $this->render('profile/edit', compact('user', 'title'));
        }
    }
}
