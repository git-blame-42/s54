<?php

namespace Tests\Unit\Core\Errors;

use Core\Errors\ErrorsHandler;
use Core\Exceptions\HTTPException;
use ErrorException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ErrorsHandlerTest extends TestCase
{
    private mixed $originalEnv;

    public function setUp(): void
    {
        $this->originalEnv = $_ENV['APP_ENV'] ?? null;
    }

    public function tearDown(): void
    {
        if ($this->originalEnv === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $this->originalEnv;
        }
    }

    public function test_view_for_not_found_outside_development_is_404_page(): void
    {
        $e = new HTTPException('URI /x not found.', 404);

        $this->assertSame('errors/404', ErrorsHandler::viewFor($e, 'production'));
        $this->assertSame('errors/404', ErrorsHandler::viewFor($e, 'testing'));
    }

    public function test_view_for_other_errors_outside_development_is_500_page(): void
    {
        $this->assertSame('errors/500', ErrorsHandler::viewFor(new RuntimeException('falhou'), 'production'));
        $this->assertSame('errors/500', ErrorsHandler::viewFor(new \Error('erro fatal'), 'production'));
        $this->assertSame('errors/500', ErrorsHandler::viewFor(new ErrorException('warning'), 'production'));
        $this->assertSame('errors/500', ErrorsHandler::viewFor(new HTTPException('teapot', 418), 'production'));
    }

    public function test_view_for_in_development_is_null_to_keep_stack_trace(): void
    {
        $this->assertNull(ErrorsHandler::viewFor(new HTTPException('URI /x not found.', 404), 'development'));
        $this->assertNull(ErrorsHandler::viewFor(new RuntimeException('falhou'), 'development'));
    }

    public function test_current_env_reads_dot_env_values(): void
    {
        $_ENV['APP_ENV'] = 'development';

        $this->assertSame('development', ErrorsHandler::currentEnv());
    }

    public function test_current_env_defaults_to_production_when_missing(): void
    {
        unset($_ENV['APP_ENV']);
        $this->assertSame('production', ErrorsHandler::currentEnv());

        $_ENV['APP_ENV'] = '';
        $this->assertSame('production', ErrorsHandler::currentEnv());
    }

    public function test_friendly_404_page_has_no_technical_details(): void
    {
        $html = ErrorsHandler::friendlyPage('errors/404');

        $this->assertStringContainsString('Página não encontrada', $html);
        $this->assertStringContainsString('<a href="/"', $html);
        $this->assertStringContainsString('Voltar ao início', $html);
        $this->assertStringNotContainsString('Stack Trace', $html);
        $this->assertStringNotContainsString('.php', $html);
    }

    public function test_friendly_500_page_has_no_technical_details(): void
    {
        $html = ErrorsHandler::friendlyPage('errors/500');

        $this->assertStringContainsString('Erro interno', $html);
        $this->assertStringContainsString('<a href="/"', $html);
        $this->assertStringNotContainsString('Stack Trace', $html);
        $this->assertStringNotContainsString('.php', $html);
    }
}
