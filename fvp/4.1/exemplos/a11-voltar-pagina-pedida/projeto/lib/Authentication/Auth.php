<?php

namespace Lib\Authentication;

use App\Models\User;

class Auth
{
    private const INTENDED_URL_KEY = 'redirect_to';

    public static function login(User $user): void
    {
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
        unset($_SESSION['user']['id']);
    }

    /**
     * Guarda a página que o visitante pediu antes de ser mandado para o login.
     * Só aceita caminho interno (ex.: "/admin"); URL externa é ignorada.
     */
    public static function rememberIntendedUrl(string $url): void
    {
        if (self::isInternalPath($url)) {
            $_SESSION[self::INTENDED_URL_KEY] = $url;
        }
    }

    /**
     * Devolve a página guardada (ou o padrão) e limpa a sessão.
     */
    public static function pullIntendedUrl(string $default): string
    {
        $url = $_SESSION[self::INTENDED_URL_KEY] ?? null;
        unset($_SESSION[self::INTENDED_URL_KEY]);

        return is_string($url) && self::isInternalPath($url) ? $url : $default;
    }

    /**
     * Caminho interno começa com uma única "/". "//host" e "/\\host" são
     * tratados pelo navegador como outro domínio, então ficam de fora.
     */
    private static function isInternalPath(string $url): bool
    {
        return str_starts_with($url, '/')
            && !str_starts_with($url, '//')
            && !str_starts_with($url, '/\\')
            && !preg_match('/[\x00-\x1F\x7F]/', $url);
    }
}
