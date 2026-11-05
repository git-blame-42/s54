<?php

use Core\Debug\Debugger;
use Core\Router\Router;

if (!function_exists('dd')) {
    function dd(): void
    {
        Debugger::dd(...func_get_args());
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Campo oculto com o token CSRF da sessão, para os forms que fazem POST/PUT/DELETE.
     */
    function csrf_field(): string
    {
        return '<input type="hidden" name="' . \Lib\Csrf::FIELD . '" value="'
            . htmlspecialchars(\Lib\Csrf::token()) . '">';
    }
}

if (!function_exists('route')) {
    /**
     * @param string $name
     * @param mixed[] $params
     * @return string
     */
    function route(string $name, $params = []): string
    {
        return Router::getInstance()->getRoutePathByName($name, $params);
    }
}
