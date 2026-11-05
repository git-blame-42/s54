<?php

namespace Tests\Unit\Core\Router;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;

/**
 * Middleware de teste: grava o nome da classe em `$calls` quando roda,
 * para os testes conferirem a ordem real de execução.
 */
abstract class RecordingMiddleware implements Middleware
{
    /** @var string[] */
    public static array $calls = [];

    public function handle(Request $request): void
    {
        self::$calls[] = static::class;
    }
}
