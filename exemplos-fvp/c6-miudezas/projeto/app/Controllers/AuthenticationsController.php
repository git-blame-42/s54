<?php

namespace App\Controllers;

use App\Models\User;
use Core\Http\Controllers\Controller;
use Core\Http\Request;
use Lib\Authentication\Auth;
use Lib\FlashMessage;

class AuthenticationsController extends Controller
{
    public function new(): void
    {
        $title = 'Login';
        $this->render('authentications/new', compact('title'));
    }

    public function authenticate(Request $request): void
    {
        $params = $request->getParam('user');
        $user = User::findByEmail($params['email']);

        if ($user === null) {
            FlashMessage::danger('E-mail não cadastrado!');
            $this->redirectTo(route('users.login'));
            return;
        }

        if (!$user->authenticate($params['password'])) {
            FlashMessage::danger('Senha incorreta!');
            $this->redirectTo(route('users.login'));
            return;
        }

        Auth::login($user);
        FlashMessage::success('Login realizado com sucesso!');
        $this->redirectTo(route('dashboard.index'));
    }

    public function destroy(): void
    {
        Auth::logout();
        FlashMessage::success('Logout realizado com sucesso!');
        $this->redirectTo(route('root'));
    }
}
