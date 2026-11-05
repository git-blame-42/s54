<?php

namespace Tests\Unit\Core\Router;

use App\Controllers\Admin\EventsController as AdminEventsController;
use App\Middleware\Admin;
use App\Middleware\Authenticate;
use Core\Constants\Constants;
use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Core\Router\Route;
use Core\Router\Router;
use Tests\TestCase;

class RouteTest extends TestCase
{
    /**
     * Rotas que o config/routes.php registrava uma a uma antes do
     * Route::resource (método, URI, ação, nome), na ordem.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private const ADMIN_EVENTS_ROUTES = [
        ['GET', '/admin/events', 'index', 'admin.events.index'],
        ['GET', '/admin/events/page/{page}', 'index', 'admin.events.paginate'],
        ['GET', '/admin/events/new', 'new', 'admin.events.new'],
        ['POST', '/admin/events', 'create', 'admin.events.create'],
        ['GET', '/admin/events/{id}', 'show', 'admin.events.show'],
        ['GET', '/admin/events/{id}/edit', 'edit', 'admin.events.edit'],
        ['PUT', '/admin/events/{id}', 'update', 'admin.events.update'],
        ['DELETE', '/admin/events/{id}', 'destroy', 'admin.events.destroy'],
    ];

    // O Router é singleton: zera antes e depois de cada teste. No tearDown roda
    // mesmo se o teste falhar, então nenhuma rota vaza para o teste seguinte.
    public function setUp(): void
    {
        parent::setUp();
        Router::reset();
    }

    public function tearDown(): void
    {
        Router::reset();
        parent::tearDown();
        $_REQUEST = [];
    }

    public function test_should_create_route_using_constructor(): void
    {
        $route = new Route(method: 'GET', uri: '/', controllerName: MockController::class, actionName: 'action');

        $this->assertEquals('GET', $route->getMethod());
        $this->assertEquals('/', $route->getURI());
        $this->assertEquals(MockController::class, $route->getControllerName());
        $this->assertEquals('action', $route->getActionName());
    }

    public function test_should_add_route_to_the_router_method_get(): void
    {
        $routerReflection = new \ReflectionClass(Router::class);
        $instanceProperty = $routerReflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        // Store the original instance
        $originalInstance = $instanceProperty->getValue();

        $routerMock = $this->createMock(Router::class);
        $routerMock->expects($this->once())
            ->method('addRoute')
            ->with($this->callback(function ($route) {
                return $route instanceof Route
                    && $route->getMethod() === 'GET'
                    && $route->getUri() === '/test'
                    && $route->getControllerName() === 'TestController'
                    && $route->getActionName() === 'test';
            }));
        $instanceProperty->setValue(null, $routerMock);

        $route = Route::get('/test', ['TestController', 'test']);
        $this->assertInstanceOf(Route::class, $route);

        // Restore the original instance
        $instanceProperty->setValue(null, $originalInstance);
    }

    public function test_should_add_route_to_the_router_method_post(): void
    {
        $routerReflection = new \ReflectionClass(Router::class);
        $instanceProperty = $routerReflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        // Store the original instance
        $originalInstance = $instanceProperty->getValue();

        $routerMock = $this->createMock(Router::class);
        $routerMock->expects($this->once())
            ->method('addRoute')
            ->with($this->callback(function ($route) {
                return $route instanceof Route
                    && $route->getMethod() === 'POST'
                    && $route->getUri() === '/test'
                    && $route->getControllerName() === 'TestController'
                    && $route->getActionName() === 'test';
            }));
        $instanceProperty->setValue(null, $routerMock);

        $route = Route::post('/test', ['TestController', 'test']);
        $this->assertInstanceOf(Route::class, $route);

        // Restore the original instance
        $instanceProperty->setValue(null, $originalInstance);
    }

    public function test_should_add_route_to_the_router_method_put(): void
    {
        $routerReflection = new \ReflectionClass(Router::class);
        $instanceProperty = $routerReflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        // Store the original instance
        $originalInstance = $instanceProperty->getValue();

        $routerMock = $this->createMock(Router::class);
        $routerMock->expects($this->once())
            ->method('addRoute')
            ->with($this->callback(function ($route) {
                return $route instanceof Route
                    && $route->getMethod() === 'PUT'
                    && $route->getUri() === '/test'
                    && $route->getControllerName() === 'TestController'
                    && $route->getActionName() === 'test';
            }));
        $instanceProperty->setValue(null, $routerMock);

        $route = Route::put('/test', ['TestController', 'test']);
        $this->assertInstanceOf(Route::class, $route);

        // Restore the original instance
        $instanceProperty->setValue(null, $originalInstance);
    }

    public function test_should_add_route_to_the_router_method_delete(): void
    {
        $routerReflection = new \ReflectionClass(Router::class);
        $instanceProperty = $routerReflection->getProperty('instance');
        $instanceProperty->setAccessible(true);
        // Store the original instance
        $originalInstance = $instanceProperty->getValue();

        $routerMock = $this->createMock(Router::class);
        $routerMock->expects($this->once())
            ->method('addRoute')
            ->with($this->callback(function ($route) {
                return $route instanceof Route
                    && $route->getMethod() === 'DELETE'
                    && $route->getUri() === '/test'
                    && $route->getControllerName() === 'TestController'
                    && $route->getActionName() === 'test';
            }));
        $instanceProperty->setValue(null, $routerMock);

        $route = Route::delete('/test', ['TestController', 'test']);
        $this->assertInstanceOf(Route::class, $route);

        // Restore the original instance
        $instanceProperty->setValue(null, $originalInstance);
    }

    public function test_match_should_return_true_if_method_and_uri_match(): void
    {
        $route = new Route(method: 'GET', uri: '/', controllerName: 'MockController', actionName: 'index');

        $this->assertTrue($route->match($this->request('GET', '/')));
    }

    public function test_match_should_return_false_if_method_and_uri_do_not_match(): void
    {
        $route = new Route(method: 'GET', uri: '/', controllerName: 'MockController', actionName: 'index');

        $this->assertFalse($route->match($this->request('POST', '/')));
        $this->assertFalse($route->match($this->request('GET', '/test')));
    }

    public function test_name_should_set_the_name_of_the_route(): void
    {
        $route = new Route(method: 'GET', uri: '/', controllerName: 'MockController', actionName: 'index');
        $route->name('root');

        $this->assertEquals('root', $route->getName());
    }

    public function test_match_should_return_true_if_method_and_uri_with_params_match(): void
    {
        $route = new Route(method: 'GET', uri: '/test/{id}', controllerName: 'MockController', actionName: 'show');
        $route->name('test.show');

        $this->assertTrue($route->match($this->request('GET', '/test/1')));
        $this->assertFalse($route->match($this->request('GET', '/test/1/edit')));
        $this->assertFalse($route->match($this->request('GET', '/test')));
    }


    public function test_match_should_return_true_and_add_params_if_method_and_uri_with_params_match(): void
    {
        $route = new Route(method: 'GET', uri: '/test/{id}', controllerName: 'MockController', actionName: 'show');

        $request = $this->request('GET', '/test/1');

        $this->assertTrue($route->match($request));
        $this->assertEquals(['id' => 1], $request->getParams());
    }

    public function test_match_should_return_true_using_query_params(): void
    {
        $route = new Route(method: 'GET', uri: '/test', controllerName: 'MockController', actionName: 'show');
        $request = $this->request('GET', '/test?user_id=1&id=2');

        $this->assertTrue($route->match($request));
    }

    private function request(string $method, string $uri): Request
    {
        require_once Constants::rootPath()->join('tests/Unit/Core/Http/header_mock.php');

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_REQUEST = [];
        return new Request();
    }

    public function test_add_middleware(): void
    {
        $route = new Route(method: 'GET', uri: '/test', controllerName: 'MockController', actionName: 'show');
        $middleware = $this->createMock(Middleware::class);

        $route->addMiddleware($middleware);

        $reflection = new \ReflectionObject($route);
        $property = $reflection->getProperty('middlewares');
        $property->setAccessible(true);

        $middlewares = $property->getValue($route);

        $this->assertContains($middleware, $middlewares);
    }

    /*
     * Route::resource
    ________________________________________*/

    public function test_resource_should_register_eight_routes_with_names_in_order(): void
    {
        Route::resource('/admin/events', MockResourceController::class, 'admin.events');

        $this->assertSame(8, Router::getInstance()->getRouteSize());
        $this->assertSame(self::ADMIN_EVENTS_ROUTES, $this->routeTable(0));
        for ($i = 0; $i < 8; $i++) {
            $this->assertSame(MockResourceController::class, Router::getInstance()->getRoute($i)->getControllerName());
        }
    }

    public function test_resource_route_names_should_build_the_paths(): void
    {
        Route::resource('/admin/events', MockResourceController::class, 'admin.events');
        $router = Router::getInstance();

        $this->assertSame('/admin/events', $router->getRoutePathByName('admin.events.index'));
        $this->assertSame('/admin/events/page/2', $router->getRoutePathByName('admin.events.paginate', ['page' => 2]));
        $this->assertSame('/admin/events/new', $router->getRoutePathByName('admin.events.new'));
        $this->assertSame('/admin/events/3', $router->getRoutePathByName('admin.events.show', ['id' => 3]));
        $this->assertSame('/admin/events/3/edit', $router->getRoutePathByName('admin.events.edit', ['id' => 3]));
    }

    public function test_resource_new_should_dispatch_to_new_and_not_to_show(): void
    {
        Route::resource('/admin/events', MockResourceController::class, 'admin.events');

        $this->assertSame('new', $this->dispatchTo('GET', '/admin/events/new'));
    }

    public function test_resource_page_should_dispatch_to_index_with_the_page_param(): void
    {
        Route::resource('/admin/events', MockResourceController::class, 'admin.events');

        $this->assertSame('index page=1', $this->dispatchTo('GET', '/admin/events'));
        $this->assertSame('index page=2', $this->dispatchTo('GET', '/admin/events/page/2'));
    }

    public function test_resource_each_verb_should_dispatch_to_its_action(): void
    {
        Route::resource('/admin/events', MockResourceController::class, 'admin.events');

        $this->assertSame('create', $this->dispatchTo('POST', '/admin/events'));
        $this->assertSame('show id=5', $this->dispatchTo('GET', '/admin/events/5'));
        $this->assertSame('edit id=5', $this->dispatchTo('GET', '/admin/events/5/edit'));
        $this->assertSame('update id=5', $this->dispatchTo('POST', '/admin/events/5', 'PUT'));
        $this->assertSame('destroy id=5', $this->dispatchTo('POST', '/admin/events/5', 'DELETE'));
    }

    public function test_resource_inside_middleware_groups_should_get_auth_and_admin(): void
    {
        Route::get('/outside', [MockResourceController::class, 'index']);
        Route::middleware('auth')->group(function () {
            Route::middleware('admin')->group(function () {
                Route::resource('/admin/events', MockResourceController::class, 'admin.events');
            });
        });

        $this->assertSame(9, Router::getInstance()->getRouteSize());
        $this->assertSame([], $this->middlewareClasses(0));
        for ($i = 1; $i <= 8; $i++) {
            $this->assertSame([Admin::class, Authenticate::class], $this->middlewareClasses($i));
        }
    }

    public function test_config_routes_should_keep_the_same_admin_events_routes(): void
    {
        require Constants::rootPath()->join('config/routes.php');
        $router = Router::getInstance();

        $start = null;
        for ($i = 0; $i < $router->getRouteSize(); $i++) {
            if ($router->getRoute($i)->getName() === 'admin.events.index') {
                $start = $i;
                break;
            }
        }
        $this->assertNotNull($start);
        $this->assertSame('admin.dashboard.index', $router->getRoute($start - 1)->getName());
        $this->assertSame(self::ADMIN_EVENTS_ROUTES, $this->routeTable($start));

        for ($i = $start; $i < $start + 8; $i++) {
            $this->assertSame(AdminEventsController::class, $router->getRoute($i)->getControllerName());
            $this->assertSame([Admin::class, Authenticate::class], $this->middlewareClasses($i));
        }
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function routeTable(int $start): array
    {
        $table = [];
        for ($i = $start; $i < $start + 8; $i++) {
            $route = Router::getInstance()->getRoute($i);
            $table[] = [$route->getMethod(), $route->getUri(), $route->getActionName(), $route->getName()];
        }
        return $table;
    }

    /**
     * Classes dos middlewares da rota, em ordem alfabética.
     *
     * @return list<string>
     */
    private function middlewareClasses(int $index): array
    {
        $route = Router::getInstance()->getRoute($index);
        $property = (new \ReflectionObject($route))->getProperty('middlewares');
        $property->setAccessible(true);

        /** @var Middleware[] $middlewares */
        $middlewares = $property->getValue($route);
        $classes = array_values(array_map(fn (Middleware $middleware) => get_class($middleware), $middlewares));
        sort($classes);
        return $classes;
    }

    private function dispatchTo(string $method, string $uri, ?string $override = null): string
    {
        require_once Constants::rootPath()->join('tests/Unit/Core/Http/header_mock.php');

        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI'] = $uri;
        $_REQUEST = $override === null ? [] : ['_method' => $override];

        return $this->getOutput(fn () => Router::getInstance()->dispatch());
    }
}
