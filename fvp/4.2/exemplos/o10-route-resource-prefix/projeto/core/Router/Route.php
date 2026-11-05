<?php

namespace Core\Router;

use Core\Http\Middleware\Middleware;
use Core\Http\Request;

class Route
{
    /**
     * Rotas RESTful de Route::resource(): método, sufixo da URI, ação e sufixo do
     * nome. A ordem importa: o Router casa a primeira rota, então 'page/{page}'
     * e 'new' vêm antes de '{id}' (senão 'new' seria capturado como id).
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const RESOURCE = [
        ['get', '', 'index', 'index'],
        ['get', '/page/{page}', 'index', 'paginate'],
        ['get', '/new', 'new', 'new'],
        ['post', '', 'create', 'create'],
        ['get', '/{id}', 'show', 'show'],
        ['get', '/{id}/edit', 'edit', 'edit'],
        ['put', '/{id}', 'update', 'update'],
        ['delete', '/{id}', 'destroy', 'destroy'],
    ];

    private string $name = '';

    /**
     * @var Middleware[]
     */
    private array $middlewares = [];

    public function __construct(
        private string $method,
        private string $uri,
        private string $controllerName,
        private string $actionName
    ) {
    }

    public function name(string $name): void
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getControllerName(): string
    {
        return $this->controllerName;
    }

    public function getActionName(): string
    {
        return $this->actionName;
    }

    public function addMiddleware(Middleware $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function runMiddlewares(Request $request): void
    {
        foreach ($this->middlewares as $middleware) {
            $middleware->handle($request);
        }
    }

    public function match(Request $request): bool
    {
        return $this->isSameMethod($request) && $this->isSameUri($request);
    }

    private function isSameMethod(Request $request): bool
    {
        return $this->method === $request->getMethod();
    }

    private function isSameUri(Request $request): bool
    {
        $uri = strtok($request->getUri(), '?');

        $splittedRoute = explode('/', $this->getUri());
        $splittedUri   = explode('/', $uri);

        if (sizeof($splittedRoute) !== sizeof($splittedUri)) {
            return false;
        }

        $params = [];
        foreach ($splittedRoute as $index => $routePart) {
            if (preg_match('/^{[a-z,_]+}$/', $routePart)) {
                $key = substr($routePart, 1, -1);
                $params[$key] = $splittedUri[$index];
            } elseif ($routePart !== $splittedUri[$index]) {
                return false;
            }
        }

        $request->addParams($params);
        return true;
    }

    /*
     * Static Methods
    ________________________________________*/

    /**
     * @param string $uri
     * @param mixed[] $action
     * @return Route
     */
    public static function get(string $uri, $action): Route
    {
        return Router::getInstance()->addRoute(new Route('GET', RoutePrefix::apply($uri), $action[0], $action[1]));
    }

    /**
     * @param string $uri
     * @param mixed[] $action
     * @return Route
     */
    public static function post(string $uri, $action): Route
    {
        return Router::getInstance()->addRoute(new Route('POST', RoutePrefix::apply($uri), $action[0], $action[1]));
    }

    /**
     * @param string $uri
     * @param mixed[] $action
     * @return Route
     */
    public static function put(string $uri, $action): Route
    {
        return Router::getInstance()->addRoute(new Route('PUT', RoutePrefix::apply($uri), $action[0], $action[1]));
    }

    /**
     * @param string $uri
     * @param mixed[] $action
     * @return Route
     */
    public static function delete(string $uri, $action): Route
    {
        return Router::getInstance()->addRoute(new Route('DELETE', RoutePrefix::apply($uri), $action[0], $action[1]));
    }

    /**
     * Registra as 8 rotas RESTful de um recurso (ver RESOURCE), com nomes
     * "<name>.index", "<name>.paginate", ..., "<name>.destroy".
     *
     * Ex.: Route::resource('/admin/events', EventsController::class, 'admin.events')
     */
    public static function resource(string $uri, string $controller, string $name): void
    {
        foreach (self::RESOURCE as [$verb, $suffix, $action, $route]) {
            self::$verb($uri . $suffix, [$controller, $action])->name("{$name}.{$route}");
        }
    }

    public static function middleware(string $middleware): RouteWrapperMiddleware
    {
        return new RouteWrapperMiddleware($middleware);
    }

    public static function prefix(string $prefix): RoutePrefix
    {
        return new RoutePrefix($prefix);
    }
}
