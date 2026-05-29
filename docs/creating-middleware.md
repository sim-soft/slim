# Creating Middleware

Middleware is code that runs before or after your route handler. Think of it as
a series of layers that wrap around your application — each request passes
through all layers on the way in, and the response passes back through them on
the way out.

## Table of Contents

- [Invokable Class](#invokable-class)
- [Before & After Pattern](#before-amp-after-pattern)
- [Short-Circuit](#short-circuit)
- [Registering Middleware](#registering-middleware)
- [Route-Specific Middleware](#route-specific-middleware)

## Invokable Class

A middleware is a PHP class with an `__invoke()` method. It receives the request
and a handler (the next layer), and must return a response:

```php
<?php
declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class JsonContentType
{
    /**
     * This middleware adds a JSON content-type header to every response.
     */
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Call $handler->handle($request) to pass the request to the next layer
        $response = $handler->handle($request);

        // Modify the response before returning it
        return $response->withHeader('Content-Type', 'application/json');
    }
}
```

The key line is `$handler->handle($request)` — this passes the request forward
to the next middleware or the route handler. Without it, your route would never
execute.

## Before & After Pattern

You can run code both before and after the route handler by placing logic on
either side of `$handler->handle()`:

```php
class TimingMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // === BEFORE ===
        // This runs BEFORE the route handler
        $start = microtime(true);

        // Pass request to the next layer (eventually reaches your route)
        $response = $handler->handle($request);

        // === AFTER ===
        // This runs AFTER the route handler has produced a response
        $elapsed = microtime(true) - $start;

        return $response->withHeader('X-Response-Time', sprintf('%.3fms', $elapsed * 1000));
    }
}
```

## Short-Circuit

Sometimes you want to stop the request from reaching the route handler
entirely (e.g., if the user isn't authenticated). Throw an exception or return a
response without calling `$handler->handle()`:

```php
class MaintenanceMode
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // Never calls $handler->handle() — the route handler is skipped
        throw new \Slim\Exception\HttpException($request, 'Service unavailable', 503);
    }
}
```

## Registering Middleware

**Global middleware** applies to every route in your app. Register it with
`withMiddleware()`:

```php
use Simsoft\Slim\Route;
use Slim\App;

Route::make()
    ->withMiddleware(function(App $app) {
        // These run on EVERY request
        $app->add(new \App\Middlewares\JsonContentType());
        $app->add(new \App\Middlewares\TimingMiddleware());
    })
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

> **Execution order:** Middleware runs in reverse order (LIFO). The last one
> added with `$app->add()` runs first. So `TimingMiddleware` wraps around
`JsonContentType`.

## Route-Specific Middleware

You can attach middleware to individual routes or groups so it only runs for
those URLs:

**Per-route** — only the `/admin` route requires authentication:

```php
$app->get('/admin', [AdminController::class, 'index'])
    ->add(new AuthMiddleware());
```

**Per-group** — all `/api/*` routes are rate-limited:

```php
$app->group('/api', function($group) {
    $group->post('/users', [UserController::class, 'store']);
    $group->put('/users/{id}', [UserController::class, 'update']);
})->add(new RateLimit(maxRequests: 30, windowSeconds: 60));
```

This is more efficient than global middleware because it only runs where needed.
