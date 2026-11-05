<?php

namespace Lib;

class StringUtils
{
    public static function camelToSnakeCase($string)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    public static function lowerSnakeToCamelCase($string)
    {
        $camel_case = preg_replace_callback(
            '/_([a-zA-Z])/',
            fn ($match) => strtoupper($match[1]),
            $string
        );

        return $camel_case;
    }

    public static function snakeToCamelCase($string)
    {
        return ucfirst(self::lowerSnakeToCamelCase($string));
    }

    /** Letras acentuadas => equivalente sem acento (tabela fixa: não depende do locale do servidor). */
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y', 'ÿ' => 'y',
    ];

    /**
     * Texto => slug para URL: minúsculas, sem acentos, só [a-z0-9] separados por um hífen.
     * Ex.: "Semana Acadêmica: Edição 2026!" => "semana-academica-edicao-2026". Pode devolver ''.
     */
    public static function slugify(string $text): string
    {
        $text = strtr(mb_strtolower($text, 'UTF-8'), self::ACCENTS);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';

        return trim($text, '-');
    }
}
