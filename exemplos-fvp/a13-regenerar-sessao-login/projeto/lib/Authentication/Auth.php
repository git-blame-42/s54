<?php

namespace Lib\Authentication;

use App\Models\User;

class Auth
{
    public static function login(User $user): void
    {
        // Novo id de sessão no login: um id obtido antes (session fixation) deixa de valer.
        self::regenerateSessionId();

        $user->registerLogin();
        $_SESSION['user']['id'] = $user->id;
    }

    public static function user(): ?User
    {
        if (isset($_SESSION['user']['id'])) {
            $id = $_SESSION['user']['id'];
            return User::findById($id);
        }

        return null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']['id']) && self::user() !== null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);

        // Sem session_destroy(): o flash "Logout realizado" ainda precisa da sessão.
        self::regenerateSessionId();
    }

    private static function regenerateSessionId(): void
    {
        // Nos testes (CLI) não há sessão nativa ativa; sem este if, session_regenerate_id gera warning.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
