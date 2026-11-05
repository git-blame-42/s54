<?php

namespace Tests\Unit\Lib;

use Lib\ICalendar;
use PHPUnit\Framework\TestCase;

class ICalendarTest extends TestCase
{
    public function test_escape_should_escape_backslash_semicolon_and_comma(): void
    {
        $this->assertSame('Bloco D\\, Sala 1\; térreo \\\\ fundos', ICalendar::escape('Bloco D, Sala 1; térreo \\ fundos'));
    }

    public function test_escape_should_turn_line_breaks_into_backslash_n(): void
    {
        $this->assertSame('linha 1\\nlinha 2\\nlinha 3\\nlinha 4', ICalendar::escape("linha 1\r\nlinha 2\nlinha 3\rlinha 4"));
    }

    public function test_escape_should_keep_plain_text(): void
    {
        $this->assertSame('Oficina de Git: ramos e PRs', ICalendar::escape('Oficina de Git: ramos e PRs'));
    }

    public function test_fold_should_keep_short_lines(): void
    {
        $line = 'SUMMARY:' . str_repeat('a', 67);

        $this->assertSame($line, ICalendar::fold($line));
    }

    public function test_fold_should_split_long_lines_at_75_octets(): void
    {
        $line = 'DESCRIPTION:' . str_repeat('a', 200);

        $parts = explode("\r\n", ICalendar::fold($line));

        $this->assertSame(75, strlen($parts[0]));
        foreach (array_slice($parts, 1) as $part) {
            $this->assertStringStartsWith(' ', $part);
            $this->assertLessThanOrEqual(75, strlen($part));
        }
        $this->assertSame($line, $parts[0] . implode('', array_map(fn(string $p) => substr($p, 1), array_slice($parts, 1))));
    }

    public function test_fold_should_not_split_a_multibyte_character(): void
    {
        $line = 'SUMMARY:' . str_repeat('ç', 60);

        foreach (explode("\r\n", ICalendar::fold($line)) as $part) {
            $this->assertTrue(mb_check_encoding($part, 'UTF-8'), $part);
            $this->assertLessThanOrEqual(75, strlen($part));
        }
    }

    public function test_build_should_join_lines_with_crlf_including_the_last_one(): void
    {
        $ics = ICalendar::build(['BEGIN:VCALENDAR', 'END:VCALENDAR']);

        $this->assertSame("BEGIN:VCALENDAR\r\nEND:VCALENDAR\r\n", $ics);
    }
}
