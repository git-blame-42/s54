<?php

namespace Tests\Unit\Lib;

use Lib\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    private const KEY = 'CONFIG_TEST_NUMBER';

    public function tearDown(): void
    {
        unset($_ENV[self::KEY]);
    }

    public function test_int_should_return_the_default_without_the_key(): void
    {
        $this->assertSame(5, Config::int(self::KEY, 5, 1, 100));
    }

    public function test_int_should_convert_digits_within_the_range(): void
    {
        foreach (['1' => 1, '10' => 10, '100' => 100, '007' => 7] as $value => $expected) {
            $_ENV[self::KEY] = (string) $value;

            $this->assertSame($expected, Config::int(self::KEY, 5, 1, 100), (string) $value);
        }
    }

    #[DataProvider('invalidValuesProvider')]
    public function test_int_should_return_the_default_for_invalid_values(mixed $value): void
    {
        $_ENV[self::KEY] = $value;

        $this->assertSame(5, Config::int(self::KEY, 5, 1, 100));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidValuesProvider(): array
    {
        return [
            'zero' => ['0'],
            'abaixo do mínimo' => ['0000'],
            'acima do máximo' => ['101'],
            'texto' => ['abc'],
            'negativo' => ['-5'],
            'decimal' => ['2.5'],
            'com espaço' => [' 10'],
            'vazio' => [''],
            'inteiro, não string' => [10],
            'booleano' => [true],
        ];
    }
}
