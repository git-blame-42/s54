<?php

namespace Lib;

/**
 * Código de confirmação de 4 dígitos para ações destrutivas (ex.: remover um Evento).
 *
 * O código é gerado no servidor e guardado em $_SESSION, um por chave (ex.: "event.7"), para que
 * só quem viu a página de confirmação consiga concluir a ação. Estática como FlashMessage; nos
 * testes basta usar o array $_SESSION, sem sessão real.
 */
class ConfirmationCode
{
    public const MIN = 1000;
    public const MAX = 9999;

    private const SESSION_KEY = 'confirmation_codes';

    /**
     * Gera um código novo para a chave (substitui o anterior) e o devolve.
     */
    public static function generate(string $key): int
    {
        $code = random_int(self::MIN, self::MAX);
        $_SESSION[self::SESSION_KEY][$key] = $code;

        return $code;
    }

    public static function current(string $key): ?int
    {
        $code = $_SESSION[self::SESSION_KEY][$key] ?? null;

        return is_int($code) ? $code : null;
    }

    /**
     * true só se existe código para a chave e o valor enviado é igual a ele.
     * Aceita string (vinda do formulário) ou int; qualquer outro tipo é recusado.
     */
    public static function check(string $key, mixed $code): bool
    {
        $expected = self::current($key);

        if ($expected === null || !(is_string($code) || is_int($code))) {
            return false;
        }

        return trim((string) $code) === (string) $expected;
    }

    public static function clear(string $key): void
    {
        unset($_SESSION[self::SESSION_KEY][$key]);
    }
}
