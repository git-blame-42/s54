<?php

namespace Lib\Authentication;

use App\Models\User;

class Auth
{
    /** Tempo máximo de inatividade, em segundos, antes de a sessão expirar. */
    public const TIMEOUT = 1800;

    public static function login(User $user): void
    {
        $user->registerLogin();
        unset($_SESSION['auth_expired']);
        $_SESSION['user']['id'] = $user->id;
        $_SESSION['user']['last_activity'] = time();
    }

    public static function user(): ?User
    {
        if (!isset($_SESSION['user']['id'])) {
            return null;
        }

        $lastActivity = $_SESSION['user']['last_activity'] ?? time();

        if (time() - $lastActivity > self::TIMEOUT) {
            self::logout();
            $_SESSION['auth_expired'] = true;
            return null;
        }

        $_SESSION['user']['last_activity'] = time();

        return User::findById($_SESSION['user']['id']);
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Informa se a última sessão terminou por inatividade e consome o aviso.
     */
    public static function wasExpired(): bool
    {
        $expired = $_SESSION['auth_expired'] ?? false;
        unset($_SESSION['auth_expired']);

        return $expired === true;
    }

    public static function logout(): void
    {
        unset($_SESSION['user']);
    }
}
