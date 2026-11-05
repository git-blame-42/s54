<?php

namespace Tests\Unit\Core\Router;

use App\Middleware\Authenticate;
use Core\Constants\Constants;
use Core\Http\Middleware\Middleware;
use Core\Router\Route;
use Core\Router\Router;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

class RoutePrefixTest extends FrameworkTestCase
{
    public function setUp(): void
    {
        require_once Constants::rootPath()->join('tests/Unit/Core/Http/header_mock.php');
        Router::reset();
        $_REQUEST = [];
    }

    public function tearDown(): void
    {
        Router::reset();
        $_REQUEST = [];
    }

    public function test_group_should_prefix_only_the_routes_registered_inside_it(): void
    {
        Route::get('/before', [MockController::class, 'action']);
        Route::prefix('/admin')->group(function () {
            Route::get('/events', [MockController::class, 'action'])->name('admin.events');
            Route::post('/events', [MockController::class, 'action']);
            Route::put('/events/{id}', [MockController::class, 'action']);
            Route::delete('/events/{id}', [MockController::class, 'action']);
        });
        Route::get('/after', [MockController::class, 'action']);

        $this->assertSame(
            ['/before', '/admin/events', '/admin/events', '/admin/events/{id}', '/admin/events/{id}', '/after'],
            $this->uris()
        );
        $this->assertSame('/admin/events', Router::getInstance()->getRoutePathByName('admin.events'));
    }

    public function test_root_route_should_become_the_prefix_itself(): void
    {
        Route::prefix('/admin/')->group(function () {
            Route::get('/', [MockController::class, 'action']);
            Route::get('/events', [MockController::class, 'action']);
        });

        $this->assertSame(['/admin', '/admin/events'], $this->uris());
    }

    public function test_nested_prefixes_should_be_concatenated_in_order(): void
    {
        Route::prefix('/api')->group(function () {
            Route::prefix('/v1')->group(function () {
                Route::get('/events', [MockController::class, 'action']);
            });
            Route::get('/status', [MockController::class, 'action']);
        });

        $this->assertSame(['/api/v1/events', '/api/status'], $this->uris());
    }

    public function test_prefix_should_be_removed_even_if_the_callback_throws(): void
    {
        try {
            Route::prefix('/admin')->group(function () {
                throw new \RuntimeException('falha no callback');
            });
        } catch (\RuntimeException) {
            // esperado
        }
        Route::get('/events', [MockController::class, 'action']);

        $this->assertSame(['/events'], $this->uris());
    }

    public function test_prefix_with_resource_should_give_the_same_routes(): void
    {
        Route::prefix('/admin')->group(function () {
            Route::resource('/events', MockResourceController::class, 'admin.events');
        });

        $this->assertSame(
            [
                '/admin/events',
                '/admin/events/page/{page}',
                '/admin/events/new',
                '/admin/events',
                '/admin/events/{id}',
                '/admin/events/{id}/edit',
                '/admin/events/{id}',
                '/admin/events/{id}',
            ],
            $this->uris()
        );

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin/events/new';
        ob_start();
        Router::getInstance()->dispatch();
        $this->assertSame('new', ob_get_clean());
    }

    public function test_prefix_inside_middleware_group_should_keep_the_middleware(): void
    {
        Route::middleware('auth')->group(function () {
            Route::prefix('/admin')->group(function () {
                Route::get('/events', [MockController::class, 'action']);
            });
        });

        $route = Router::getInstance()->getRoute(0);
        $property = (new \ReflectionObject($route))->getProperty('middlewares');
        $property->setAccessible(true);
        /** @var Middleware[] $middlewares */
        $middlewares = $property->getValue($route);

        $this->assertSame('/admin/events', $route->getUri());
        $this->assertCount(1, $middlewares);
        $this->assertInstanceOf(Authenticate::class, $middlewares[0]);
    }

    /**
     * @return list<string>
     */
    private function uris(): array
    {
        $uris = [];
        for ($i = 0; $i < Router::getInstance()->getRouteSize(); $i++) {
            $uris[] = Router::getInstance()->getRoute($i)->getUri();
        }
        return $uris;
    }
}
