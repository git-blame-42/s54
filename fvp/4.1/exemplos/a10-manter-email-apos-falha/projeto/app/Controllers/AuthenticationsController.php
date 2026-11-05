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
        $params = $request->getParam('user', []);
        $email = trim($params['email'] ?? '');
        $password = $params['password'] ?? '';

        $user = $email !== '' ? User::findByEmail($email) : null;

        if ($user && $user->authenticate($password)) {
            Auth::login($user);
            FlashMessage::success('Login realizado com sucesso!');
            $this->redirectTo(route('dashboard.index'));
        } else {
            FlashMessage::danger('E-mail ou senha inválidos!');
            $title = 'Login';
            $this->render('authentications/new', compact('title', 'email'));
        }
    }

    public function destroy(): void
    {
        Auth::logout();
        FlashMessage::success('Logout realizado com sucesso!');
        $this->redirectTo(route('users.login'));
    }
}
