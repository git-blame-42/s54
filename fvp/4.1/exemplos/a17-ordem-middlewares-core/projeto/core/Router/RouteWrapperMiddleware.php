<?php

namespace Core\Router;

use Config\App;
use Core\Http\Middleware\Middleware;

class RouteWrapperMiddleware
{
    public function __construct(
        private string $name
    ) {
    }

    public function group(callable $callback): void
    {
        $routeSizeBefore = Router::getInstance()->getRouteSize();
        $callback();
        $routeSizeAfter = Router::getInstance()->getRouteSize();

        // O callback roda antes do laço: num grupo aninhado, o grupo interno
        // já anexou o middleware dele quando chegamos aqui. Por isso o
        // middleware deste grupo (o externo) entra no início da lista, e a
        // execução segue a ordem de escrita: externo antes do interno
        // (ex.: em /admin, Authenticate roda antes de Admin).
        for ($i = $routeSizeBefore; $i < $routeSizeAfter; $i++) {
            $route = Router::getInstance()->getRoute($i);
            $route->prependMiddleware($this->middlewareInstance());
        }
    }

    private function middlewareInstance(): Middleware
    {
        $class = App::$middlewareAliases[$this->name];
        return new $class();
    }
}
