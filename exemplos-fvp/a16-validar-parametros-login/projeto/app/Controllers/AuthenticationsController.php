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
        $credentials = $this->credentials($request->getParam('user'));

        if ($credentials === null) {
            FlashMessage::danger('E-mail ou senha inválidos!');
            $this->redirectTo(route('users.login'));
            return;
        }

        $user = User::findByEmail($credentials['email']);

        if ($user && $user->authenticate($credentials['password'])) {
            Auth::login($user);
            FlashMessage::success('Login realizado com sucesso!');
            $this->redirectTo(route('dashboard.index'));
        } else {
            FlashMessage::danger('E-mail ou senha inválidos!');
            $this->redirectTo(route('users.login'));
        }
    }

    public function destroy(): void
    {
        Auth::logout();
        FlashMessage::success('Logout realizado com sucesso!');
        $this->redirectTo(route('users.login'));
    }

    /**
     * O formulário precisa enviar user[email] e user[password] como strings
     * não vazias. Qualquer outra forma (chave ausente, null, array, só espaços)
     * é recusada aqui, antes de consultar o model.
     *
     * @return array{email: string, password: string}|null
     */
    private function credentials(mixed $params): ?array
    {
        if (!is_array($params)) {
            return null;
        }

        foreach (['email', 'password'] as $field) {
            if (!isset($params[$field]) || !is_string($params[$field]) || trim($params[$field]) === '') {
                return null;
            }
        }

        return ['email' => $params['email'], 'password' => $params['password']];
    }
}
