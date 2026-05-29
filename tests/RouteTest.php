<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests;

use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Slim\Route;
use Slim\App;

class RouteTest extends TestCase
{
    #[Test]
    public function makeReturnsRouteInstance(): void
    {
        $route = Route::make();
        $this->assertInstanceOf(Route::class, $route);
    }

    #[Test]
    public function makeWithNullContainer(): void
    {
        $route = Route::make(null);
        $this->assertInstanceOf(Route::class, $route);
    }

    #[Test]
    public function withDomainReturnsSelf(): void
    {
        $route = Route::make();
        $result = $route->withDomain('https://example.com');

        $this->assertSame($route, $result);
    }

    #[Test]
    public function withBasePathReturnsSelf(): void
    {
        $route = Route::make();
        $result = $route->withBasePath('/api/v1');

        $this->assertSame($route, $result);
    }

    #[Test]
    public function withMiddlewareReturnsSelf(): void
    {
        $route = Route::make();
        $result = $route->withMiddleware(function (App $app) {
            // no-op
        });

        $this->assertSame($route, $result);
    }

    #[Test]
    public function withMiddlewareCallsCallable(): void
    {
        $called = false;
        $route = Route::make();
        $route->withMiddleware(function (App $app) use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    #[Test]
    public function withRoutingReturnsSelf(): void
    {
        $route = Route::make();
        $result = $route->withRouting(function (App $app) {
            $app->get('/test', function () {
                return 'test';
            });
        });

        $this->assertSame($route, $result);
    }

    #[Test]
    public function withRoutingCallsCallable(): void
    {
        $called = false;
        $route = Route::make();
        $route->withRouting(function (App $app) use (&$called) {
            $called = true;
            $app->get('/test', function () {
                return 'test';
            });
        });

        $this->assertTrue($called);
    }

    #[Test]
    public function withErrorHandlerReturnsSelf(): void
    {
        $route = Route::make();
        $result = $route->withErrorHandler(false, false, false);

        $this->assertSame($route, $result);
    }

    #[Test]
    public function withErrorHandlerWithAllOptions(): void
    {
        $route = Route::make();
        $result = $route->withErrorHandler(
            displayError: true,
            logError: true,
            logErrorDetails: true
        );

        $this->assertSame($route, $result);
    }

    #[Test]
    public function withErrorHandlerWithInvalidClassThrowsException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid Error handler provided');

        $route = Route::make();
        $route->withErrorHandler(errorHandlerClass: \stdClass::class);
    }

    #[Test]
    public function getRequestReturnsServerRequestInterface(): void
    {
        $route = Route::make();
        $request = $route->getRequest();

        $this->assertNotNull($request);
        $this->assertInstanceOf(\Psr\Http\Message\ServerRequestInterface::class, $request);
    }

    #[Test]
    public function fluentBuilderChaining(): void
    {
        $route = Route::make();
        $result = $route
            ->withDomain('https://example.com')
            ->withBasePath('/api')
            ->withMiddleware(function (App $app) {
            })
            ->withRouting(function (App $app) {
                $app->get('/', function () {
                    return 'home';
                });
            });

        $this->assertInstanceOf(Route::class, $result);
    }
}
