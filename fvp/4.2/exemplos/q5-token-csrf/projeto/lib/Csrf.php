<?php

namespace Lib;

/**
 * Token CSRF por sessão.
 *
 * token() gera 32 bytes aleatórios (64 caracteres hex) na primeira chamada e guarda em
 * $_SESSION['_token']; as chamadas seguintes devolvem o mesmo valor. check() compara com
 * hash_equals (tempo constante).
 */
class Csrf
{
    public const FIELD = '_token';

    public static function token(): string
    {
        $token = $_SESSION[self::FIELD] ?? null;

        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::FIELD] = $token;
        }

        return $token;
    }

    /**
     * true só se há token na sessão e o valor enviado é uma string igual a ele.
     */
    public static function check(mixed $token): bool
    {
        $expected = $_SESSION[self::FIELD] ?? null;

        if (!is_string($expected) || $expected === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($expected, $token);
    }
}
