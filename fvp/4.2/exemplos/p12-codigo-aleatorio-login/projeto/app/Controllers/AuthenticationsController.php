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

        if ($user && $user->authenticate($params['password'])) {
            // P12: a senha certa ainda não loga; o login termina em verifyCode, com o código.
            $code = $user->generateLoginCode();
            $_SESSION['pending_user_id'] = $user->id;
            // Simula o envio do código (não há e-mail no projeto): o backend o devolve no flash.
            FlashMessage::success("Seu código de acesso é {$code}");
            $this->redirectTo(route('users.code'));
        } else {
            FlashMessage::danger('E-mail ou senha inválidos!');
            $this->redirectTo(route('users.login'));
        }
    }

    public function code(): void
    {
        if ($this->pendingUser() === null) {
            $this->redirectTo(route('users.login'));
            return;
        }

        $title = 'Código de acesso';
        $this->render('authentications/code', compact('title'));
    }

    public function verifyCode(Request $request): void
    {
        $user = $this->pendingUser();
        if ($user === null) {
            $this->redirectTo(route('users.login'));
            return;
        }

        $code = $request->getParam('code');
        $code = is_string($code) ? trim($code) : '';

        if ($user->verifyLoginCode($code)) {
            unset($_SESSION['pending_user_id']);
            Auth::login($user);
            FlashMessage::success('Login realizado com sucesso!');
            $this->redirectTo(route('dashboard.index'));
        } else {
            FlashMessage::danger('Código inválido!');
            $this->redirectTo(route('users.code'));
        }
    }

    public function destroy(): void
    {
        Auth::logout();
        FlashMessage::success('Logout realizado com sucesso!');
        $this->redirectTo(route('users.login'));
    }

    /**
     * Usuário que acertou a senha e ainda não informou o código (null se não houver).
     */
    private function pendingUser(): ?User
    {
        $id = $_SESSION['pending_user_id'] ?? null;

        return is_int($id) || is_string($id) ? User::findById((int) $id) : null;
    }
}
