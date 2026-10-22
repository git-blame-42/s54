<?php

namespace Tests\Unit\Core\Router;

use App\Middleware\Admin;
use App\Middleware\Authenticate;
use Config\App;
use Core\Http\Middleware\Middleware;
use Core\Http\Request;
use Core\Router\Route;
use Core\Router\Router;
use Core\Router\RouteWrapperMiddleware;
use PHPUnit\Framework\TestCase as FrameworkTestCase;
use Tests\Unit\Core\Router\MockController;

class RouteWrapperMiddlewareTest extends FrameworkTestCase
{
    /** @var array<string, string> */
    private array $originalAliases;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalAliases = App::$middlewareAliases;
        App::$middlewareAliases['outer'] = OuterRecordingMiddleware::class;
        App::$middlewareAliases['inner'] = InnerRecordingMiddleware::class;
        RecordingMiddleware::$calls = [];
    }

    public function tearDown(): void
    {
        App::$middlewareAliases = $this->originalAliases;
        RecordingMiddleware::$calls = [];
        Router::reset();
        parent::tearDown();
    }

    public function test_group_should_add_middleware_to_routes(): void
    {
        $middlewareName = 'auth';
        $routeWrapperMiddleware = new RouteWrapperMiddleware($middlewareName);
        $routeSizeBefore = Router::getInstance()->getRouteSize();

        $routeWrapperMiddleware->group(function () {
            Route::get('/home', [MockController::class, 'index'])->name('home');
        });

        $routeSizeAfter = Router::getInstance()->getRouteSize();

        for ($i = $routeSizeBefore; $i < $routeSizeAfter; $i++) {
            $route = Router::getInstance()->getRoute($i);

            $reflection = new \ReflectionObject($route);
            $property = $reflection->getProperty('middlewares');
            $property->setAccessible(true);

            $middlewares = $property->getValue($route);
            $this->assertInstanceOf(Middleware::class, $middlewares[0]);
        }
    }

    public function test_nested_groups_should_run_outer_middleware_first(): void
    {
        Router::reset();

        (new RouteWrapperMiddleware('auth'))->group(function () {
            (new RouteWrapperMiddleware('admin'))->group(function () {
                Route::get('/admin', [MockController::class, 'action'])->name('admin.index');
            });
        });

        $middlewares = $this->middlewaresOf(Router::getInstance()->getRoute(0));

        $this->assertCount(2, $middlewares);
        $this->assertInstanceOf(Authenticate::class, $middlewares[0]);
        $this->assertInstanceOf(Admin::class, $middlewares[1]);
    }

    public function test_nested_groups_should_only_add_outer_middleware_to_outer_routes(): void
    {
        Router::reset();

        (new RouteWrapperMiddleware('outer'))->group(function () {
            Route::get('/dashboard', [MockController::class, 'action'])->name('dashboard');

            (new RouteWrapperMiddleware('inner'))->group(function () {
                Route::get('/admin', [MockController::class, 'action'])->name('admin');
            });
        });

        $dashboard = $this->middlewaresOf(Router::getInstance()->getRoute(0));
        $admin = $this->middlewaresOf(Router::getInstance()->getRoute(1));

        $this->assertCount(1, $dashboard);
        $this->assertInstanceOf(OuterRecordingMiddleware::class, $dashboard[0]);

        $this->assertCount(2, $admin);
        $this->assertInstanceOf(OuterRecordingMiddleware::class, $admin[0]);
        $this->assertInstanceOf(InnerRecordingMiddleware::class, $admin[1]);
    }

    public function test_run_middlewares_should_execute_outer_group_before_inner_group(): void
    {
        Router::reset();

        (new RouteWrapperMiddleware('outer'))->group(function () {
            (new RouteWrapperMiddleware('inner'))->group(function () {
                Route::get('/admin', [MockController::class, 'action'])->name('admin');
            });
        });

        Router::getInstance()->getRoute(0)->runMiddlewares($this->createMock(Request::class));

        $this->assertSame(
            [OuterRecordingMiddleware::class, InnerRecordingMiddleware::class],
            RecordingMiddleware::$calls
        );
    }

    /**
     * @return Middleware[]
     */
    private function middlewaresOf(Route $route): array
    {
        $property = (new \ReflectionObject($route))->getProperty('middlewares');

        return $property->getValue($route);
    }
}
