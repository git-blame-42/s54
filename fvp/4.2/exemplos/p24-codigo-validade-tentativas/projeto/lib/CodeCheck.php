<?php

namespace Lib;

/**
 * Resultado da conferência de um código de confirmação, com a mensagem mostrada ao usuário.
 */
enum CodeCheck
{
    case Ok;
    case Wrong;
    case Expired;
    case Exhausted;

    public function message(): string
    {
        return match ($this) {
            self::Ok => 'Código confirmado!',
            self::Wrong => 'Código incorreto! Tente de novo.',
            self::Expired => 'Código expirado! Um novo foi gerado.',
            self::Exhausted => 'Tentativas esgotadas! Um novo código foi gerado.',
        };
    }
}
