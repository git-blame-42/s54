<?php

namespace App\Controllers;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\FlashMessage;

class UsersController extends Controller
{
    public function new(): void
    {
        $user = new User();
        $title = 'Cadastro';
        $this->render('users/new', compact('user', 'title'));
    }

    public function create(Request $request): void
    {
        $params = $request->getParam('user', []);

        // Lista explícita de campos: nunca `new User($params)`, senão um
        // `user[role]=admin` enviado no POST criaria um administrador.
        $user = new User([
            'name' => $params['name'] ?? null,
            'email' => $params['email'] ?? null,
            'password' => $params['password'] ?? null,
            'password_confirmation' => $params['password_confirmation'] ?? null,
        ]);

        if ($user->save()) {
            FlashMessage::success('Cadastro realizado! Faça login.');
            $this->redirectTo(route('users.login'));
        } else {
            FlashMessage::danger('Existem dados incorretos!');
            $title = 'Cadastro';
            $this->render('users/new', compact('user', 'title'));
        }
    }
}
