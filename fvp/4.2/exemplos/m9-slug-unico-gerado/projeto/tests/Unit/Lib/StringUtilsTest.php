<?php

namespace Tests\Unit\Lib;

use Lib\StringUtils;
use PHPUnit\Framework\TestCase;

class StringUtilsTest extends TestCase
{
    public function test_camel_to_snake_case(): void
    {
        $this->assertEquals('my_test_string', StringUtils::camelToSnakeCase('myTestString'));
        $this->assertEquals('another_test_string', StringUtils::camelToSnakeCase('AnotherTestString'));
        $this->assertEquals('test', StringUtils::camelToSnakeCase('Test'));
    }

    public function test_lower_snake_to_camel_case(): void
    {
        $this->assertEquals('myTestString', StringUtils::lowerSnakeToCamelCase('my_test_string'));
        $this->assertEquals('anotherTestString', StringUtils::lowerSnakeToCamelCase('another_test_string'));
        $this->assertEquals('test', StringUtils::lowerSnakeToCamelCase('test'));
    }

    public function test_snake_to_camel_case(): void
    {
        $this->assertEquals('MyTestString', StringUtils::snakeToCamelCase('my_test_string'));
        $this->assertEquals('AnotherTestString', StringUtils::snakeToCamelCase('another_test_string'));
        $this->assertEquals('Test', StringUtils::snakeToCamelCase('test'));
    }

    public function test_slugify_should_lowercase_and_join_words_with_hyphens(): void
    {
        $this->assertEquals('oficina-de-git', StringUtils::slugify('Oficina de Git'));
        $this->assertEquals('oficina-de-git', StringUtils::slugify('  Oficina   de   Git  '));
    }

    public function test_slugify_should_remove_accents(): void
    {
        $this->assertEquals('semana-academica-de-informatica', StringUtils::slugify('Semana Acadêmica de Informática'));
        $this->assertEquals('acao-cancao-pao-ja-voce', StringUtils::slugify('AÇÃO Canção Pão Já Você'));
        $this->assertEquals('uber-curso-espanol', StringUtils::slugify('Über Curso Español'));
    }

    public function test_slugify_should_turn_symbols_into_single_hyphens(): void
    {
        $this->assertEquals('palestra-ia-no-ensino-2026', StringUtils::slugify('Palestra: IA no Ensino (2026)!'));
        $this->assertEquals('c-php-sql', StringUtils::slugify('C++ / PHP & SQL'));
        $this->assertEquals('a-b', StringUtils::slugify('---a___b---'));
    }

    public function test_slugify_should_return_empty_string_without_letters_or_digits(): void
    {
        $this->assertSame('', StringUtils::slugify('!!! ???'));
        $this->assertSame('', StringUtils::slugify(''));
    }
}
