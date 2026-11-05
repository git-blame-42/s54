<?php

namespace Core\Errors;

use Core\Constants\Constants;
use Core\Exceptions\HTTPException;
use ErrorException;
use Throwable;

class ErrorsHandler
{
    public static function init(): void
    {
        new self();
    }

    private function __construct()
    {
        ob_start(); // Started capturing the buffer instead of sending it directly to the requester.
        set_exception_handler($this->exceptionHandler());
        set_error_handler($this->errorHandler());
    }

    private static function exceptionHandler(): callable
    {
        return function ($e) {
            ob_end_clean(); // Discard the buffered output

            // APP_ENV é lido aqui, e não no init(): o bootstrap chama init() antes do EnvLoader::init().
            $view = self::viewFor($e, self::currentEnv());
            if ($view !== null) {
                self::sendFriendlyPage($view);
                return;
            }

            if ($e instanceof HTTPException) {
                header('HTTP/1.1 ' . $e->getStatusCode() . ' ' . $e->getMessage());
            } else {
                header('HTTP/1.1 500 Internal Server Error');
            }

            echo <<<HTML
                <h1>{$e->getMessage()}</h1>
                <pre>
                Uncaught exception class: {get_class($e)}
                </pre>
                Message: <strong>{$e->getMessage()}</strong><br>
                File: {$e->getFile()}<br>
                Line: {$e->getLine()}<br>
                <br>
                Stack Trace: <br>
                <pre>
                    {$e->getTraceAsString()}
                </pre>
                HTML;
        };
    }

    private static function errorHandler(): callable
    {
        return function ($errorNumber, $errorStr, $file, $line) {
            ob_end_clean(); // Discard the buffered output

            $view = self::viewFor(new ErrorException($errorStr, 0, $errorNumber, $file, $line), self::currentEnv());
            if ($view !== null) {
                self::sendFriendlyPage($view);
                exit();
            }

            header('HTTP/1.1 500 Internal Server Error');

            switch ($errorNumber) {
                case E_USER_ERROR:
                    echo <<<HTML
                        <b>ERROR</b> [$errorNumber] $errorStr<br>
                        Fatal error on line $line in file $file<br>
                        PHP {PHP_VERSION} ({PHP_OS})<br>
                        Aborting...<br>
                        HTML;
                    exit(1);
                case E_USER_WARNING:
                    echo "<b>WARNING</b> [$errorStr] $errorStr<br>";
                    break;
                case E_USER_NOTICE:
                    echo "<b>NOTICE</b> [$errorNumber] $errorStr<br>";
                    break;
            }

            echo <<<HTML
                <h1>$errorStr</h1>
                File: $file <br>
                Line: $line <br>
                <br>
                Stack Trace: <br>
                HTML;

            echo '<pre>';
            debug_print_backtrace();
            echo '</pre>';

            exit();
        };
    }

    /**
     * View amigável para o erro: 'errors/404' para HTTPException 404, 'errors/500' para o resto.
     * Em development devolve null: o handler mantém mensagem, arquivo, linha e stack trace.
     */
    public static function viewFor(Throwable $e, string $env): ?string
    {
        if ($env === 'development') {
            return null;
        }

        if ($e instanceof HTTPException && $e->getStatusCode() === 404) {
            return 'errors/404';
        }

        return 'errors/500';
    }

    /**
     * APP_ENV vem do $_ENV (o EnvLoader grava só ali, sem putenv; no php_test ele copia o
     * APP_ENV=testing do compose). Sem valor (erro antes do EnvLoader), vale 'production'.
     */
    public static function currentEnv(): string
    {
        $env = $_ENV['APP_ENV'] ?? null;

        return is_string($env) && $env !== '' ? $env : 'production';
    }

    /**
     * HTML da view de erro. A view é incluída direto (sem Controller::render()): o layout depende
     * de sessão e usuário, e se ele falhar o handler entraria em laço.
     */
    public static function friendlyPage(string $view): string
    {
        ob_start();
        require Constants::rootPath()->join("app/views/{$view}.phtml");
        return (string) ob_get_clean();
    }

    private static function sendFriendlyPage(string $view): void
    {
        if ($view === 'errors/404') {
            header('HTTP/1.1 404 Not Found');
        } else {
            header('HTTP/1.1 500 Internal Server Error');
        }

        echo self::friendlyPage($view);
    }
}
