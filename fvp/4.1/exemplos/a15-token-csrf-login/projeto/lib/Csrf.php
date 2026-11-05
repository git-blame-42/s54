<?php

namespace Lib;

/**
 * Token CSRF por sessão: gerado uma vez, enviado em campo oculto `_token`
 * e conferido no POST com comparação em tempo constante.
 */
class Csrf
{
    public static function token(): string
    {
        $_SESSION['_token'] ??= bin2hex(random_bytes(32));
        return $_SESSION['_token'];
    }

    /** Aceita mixed porque o valor vem do Request (pode ser array ou nulo). */
    public static function check(mixed $token): bool
    {
        return is_string($token) && isset($_SESSION['_token'])
            && hash_equals($_SESSION['_token'], $token);
    }
}
