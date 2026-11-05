<?php

namespace Lib;

/**
 * Montagem de arquivos iCalendar (.ics, RFC 5545) a partir de linhas "NOME:valor".
 *
 * - escape(): texto de SUMMARY/DESCRIPTION/LOCATION (\ ; , e quebras de linha).
 * - fold(): linhas com mais de 75 octetos continuam na linha seguinte, que começa com espaço,
 *   sem partir caracteres UTF-8 ao meio.
 * - build(): junta as linhas com CRLF (obrigatório no formato), inclusive no fim.
 */
class ICalendar
{
    public const EOL = "\r\n";
    private const MAX_OCTETS = 75;

    public static function escape(string $text): string
    {
        $text = str_replace(['\\', ';', ','], ['\\\\', '\;', '\\,'], $text);

        return str_replace(["\r\n", "\r", "\n"], '\\n', $text);
    }

    public static function fold(string $line): string
    {
        $parts = [];
        $limit = self::MAX_OCTETS;

        while (strlen($line) > $limit) {
            // mb_strcut corta em octetos sem quebrar um caractere multibyte.
            $part = mb_strcut($line, 0, $limit, 'UTF-8');
            $parts[] = $part;
            $line = substr($line, strlen($part));
            // As linhas de continuação começam com um espaço, que conta nos 75.
            $limit = self::MAX_OCTETS - 1;
        }
        $parts[] = $line;

        return implode(self::EOL . ' ', $parts);
    }

    /**
     * @param array<int, string> $lines
     */
    public static function build(array $lines): string
    {
        return implode(self::EOL, array_map([self::class, 'fold'], $lines)) . self::EOL;
    }
}
