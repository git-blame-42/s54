<?php

namespace Lib\Authentication;

use App\Models\User;

class Auth
{
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

    /* ------------------- Login por código (FVP B11) ------------------- */

    /**
     * Registra na sessão que ESTE navegador pediu um código para este Usuário.
     * É a oitava verificação do login por código: quem não pediu o código aqui não
     * chega nem ao formulário de verificação. Só o id vai para a sessão; o código
     * (em hash) fica no banco, no model User. Toda escrita em $_SESSION mora nesta
     * classe, como login() e logout().
     * Próximo passo: tests/Unit/Lib/Authentication/AuthTest.php; depois config/routes.php.
     */
    public static function startLoginCode(User $user): void
    {
        $_SESSION['login_code']['user_id'] = $user->id;
    }

    /**
     * O Usuário do pedido pendente neste navegador, ou null se não há pedido.
     *
     * @phpstan-impure lê a sessão; o resultado muda depois de startLoginCode()
     */
    public static function loginCodeUser(): ?User
    {
        $id = $_SESSION['login_code']['user_id'] ?? null;

        return $id === null ? null : User::findById((int) $id);
    }

    /** Encerra o pedido: no sucesso do login e quando o código morre. */
    public static function clearLoginCode(): void
    {
        unset($_SESSION['login_code']);
    }
}
