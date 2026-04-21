<?php

namespace PhalconOpenApi\Tests;

use PHPUnit\Framework\TestCase;
use PhalconOpenApi\RouteCollector;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Router\Route;

class RouteCollectorTest extends TestCase
{
    public function testCollectsSimpleRoute(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn('/users');
        $route->method('getHttpMethods')->willReturn('GET');
        $route->method('getPaths')->willReturn([
            'namespace'  => 'DemoApp\\Controllers',
            'controller' => 'user',
            'action'     => 'list',
        ]);

        $router = $this->createMock(Router::class);
        $router->method('getRoutes')->willReturn([$route]);

        $collector = new RouteCollector($router);
        $routes = $collector->collect();

        $this->assertCount(1, $routes);
        $this->assertSame('/users', $routes[0]['path']);
        $this->assertSame('get', $routes[0]['method']);
        $this->assertSame('DemoApp\\Controllers\\UserController', $routes[0]['controller']);
        $this->assertSame('listAction', $routes[0]['action']);
        $this->assertSame([], $routes[0]['pathParams']);
    }

    public function testConvertsRegexGroupToOpenApiParam(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn('/users/([0-9]+)');
        $route->method('getHttpMethods')->willReturn('GET');
        $route->method('getPaths')->willReturn([
            'namespace'  => 'DemoApp\\Controllers',
            'controller' => 'user',
            'action'     => 'get',
            1            => 'id',
        ]);

        $router = $this->createMock(Router::class);
        $router->method('getRoutes')->willReturn([$route]);

        $collector = new RouteCollector($router);
        $routes = $collector->collect();

        $this->assertSame('/users/{id}', $routes[0]['path']);
        $this->assertSame(['id'], $routes[0]['pathParams']);
    }

    public function testConvertsNamedRegexGroup(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn('/users/(?P<id>[0-9]+)');
        $route->method('getHttpMethods')->willReturn('GET');
        $route->method('getPaths')->willReturn([
            'namespace'  => 'DemoApp\\Controllers',
            'controller' => 'user',
            'action'     => 'get',
        ]);

        $router = $this->createMock(Router::class);
        $router->method('getRoutes')->willReturn([$route]);

        $collector = new RouteCollector($router);
        $routes = $collector->collect();

        $this->assertSame('/users/{id}', $routes[0]['path']);
        $this->assertSame(['id'], $routes[0]['pathParams']);
    }

    public function testPreservesOpenApiPlaceholders(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn('/users/{id}');
        $route->method('getHttpMethods')->willReturn('GET');
        $route->method('getPaths')->willReturn([
            'namespace'  => 'DemoApp\\Controllers',
            'controller' => 'user',
            'action'     => 'get',
        ]);

        $router = $this->createMock(Router::class);
        $router->method('getRoutes')->willReturn([$route]);

        $collector = new RouteCollector($router);
        $routes = $collector->collect();

        $this->assertSame('/users/{id}', $routes[0]['path']);
        $this->assertSame(['id'], $routes[0]['pathParams']);
    }

    public function testSkipsRoutesWithoutController(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn('/health');
        $route->method('getHttpMethods')->willReturn('GET');
        $route->method('getPaths')->willReturn([]);

        $router = $this->createMock(Router::class);
        $router->method('getRoutes')->willReturn([$route]);

        $collector = new RouteCollector($router);
        $routes = $collector->collect();

        $this->assertCount(0, $routes);
    }

    public function testMultipleHttpMethods(): void
    {
        $route = $this->createMock(Route::class);
        $route->method('getPattern')->willReturn('/users');
        $route->method('getHttpMethods')->willReturn(['GET', 'POST']);
        $route->method('getPaths')->willReturn([
            'namespace'  => 'DemoApp\\Controllers',
            'controller' => 'user',
            'action'     => 'list',
        ]);

        $router = $this->createMock(Router::class);
        $router->method('getRoutes')->willReturn([$route]);

        $collector = new RouteCollector($router);
        $routes = $collector->collect();

        $this->assertCount(2, $routes);
        $this->assertSame('get', $routes[0]['method']);
        $this->assertSame('post', $routes[1]['method']);
    }
}
