<?php

namespace Lib;

/**
 * Leitura de configurações do .env (gravadas em $_ENV pelo EnvLoader).
 */
class Config
{
    /**
     * Inteiro da chave $key entre $min e $max. O valor do .env é sempre string ("" para chave vazia):
     * só aceita dígitos; ausente, vazio, não numérico ou fora da faixa devolve $default.
     */
    public static function int(string $key, int $default, int $min, int $max): int
    {
        $value = $_ENV[$key] ?? null;

        if (!is_string($value) || !ctype_digit($value)) {
            return $default;
        }

        $number = (int) $value;

        return ($number >= $min && $number <= $max) ? $number : $default;
    }
}
