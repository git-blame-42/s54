<?php

namespace Lib\Authentication;

use App\Models\User;

class Auth
{
    public static function login(User $user): void
    {
        // Guarda o acesso anterior antes de registrar o atual: depois do
        // registerLogin(), last_login_at passa a ser o login de agora.
        $_SESSION['user']['previous_login_at'] = $user->last_login_at;

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

    /**
     * Data/hora do acesso anterior ao login atual, ou null no primeiro acesso.
     */
    public static function previousLoginAt(): ?string
    {
        return $_SESSION['user']['previous_login_at'] ?? null;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']['id']);
        unset($_SESSION['user']['previous_login_at']);
    }
}
