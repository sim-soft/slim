# Route Builder

The Route Builder is the entry point for your application. It creates a Slim app
instance and lets you configure everything — routes, middleware, error
handling — using a fluent (chainable) API.

## Table of Contents

- [Basic Usage](#basic-usage)
- [Configuration Methods](#configuration-methods)
- [Error Handling](#error-handling)
- [Middleware Registration](#middleware-registration)
- [Route Caching](#route-caching)
- [Base Path](#base-path)
- [Domain Configuration](#domain-configuration)
- [Container Support](#container-support)
- [Full Example](#full-example)

## Basic Usage

Create an `index.php` file as your application entry point:

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use Simsoft\Slim\Route;
use Slim\App;
use function Simsoft\Slim\response;

Route::make()
    ->withErrorHandler(false, true, true)
    ->withRouting(function(App $app) {
        $app->get('/', function() {
            response('Hello World!');
        });
    })
    ->run();
```

Here's what each part does:

- `Route::make()` — creates a new application instance
- `->withErrorHandler(false, true, true)` — enables error logging (the three
  booleans are: display errors, log errors, log error details)
- `->withRouting(...)` — defines your URL routes inside a callback
- `$app->get('/', ...)` — registers a GET route for the homepage
- `response('Hello World!')` — sends text back to the browser
- `->run()` — starts listening for requests

## Configuration Methods

All methods return the Route instance, so you can chain them:

| Method                              | Description                                                                   |
|-------------------------------------|-------------------------------------------------------------------------------|
| `make($container)`                  | Create instance. Pass a PSR-11 container for dependency injection (optional). |
| `withDomain(string)`                | Set your app's domain for generating full URLs (e.g., `https://example.com`). |
| `withBasePath(string)`              | Add a URL prefix to all routes (e.g., `/api/v1`).                             |
| `withErrorHandler(...)`             | Configure how errors are displayed and logged.                                |
| `withMiddleware(callable)`          | Register middleware that runs on every request.                               |
| `withRouting(callable, ?cachePath)` | Define your routes. Optionally pass a cache file path for production.         |
| `run()`                             | Process the incoming HTTP request and send the response.                      |

## Error Handling

Control how your app handles errors. In development, show details. In
production, hide them and log instead:

```php
Route::make()
    ->withErrorHandler(
        displayError: false,       // Set true in development to see error details
        logError: true,            // Write errors to your logger
        logErrorDetails: true,     // Include stack traces in logs
        logger: $psrLogger,        // Optional: pass your own PSR-3 logger
        errorHandlerClass: CustomErrorHandler::class, // Optional: use a custom handler
    )
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

See [Error Handling](ERROR_HANDLING.md) for creating custom error pages.

## Middleware Registration

Middleware runs on every request before/after your route handler. Use it for
things like CORS headers, authentication, or logging:

```php
use Simsoft\Slim\Middlewares\CORS;
use Simsoft\Slim\Middlewares\CacheOff;

Route::make()
    ->withMiddleware(function(App $app) {
        // Each $app->add() registers a middleware
        $app->add(new CORS());      // Add CORS headers to responses
        $app->add(new CacheOff()); // Prevent browser caching
    })
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

See [Middleware](MIDDLEWARE.md) for all built-in middleware and how to create
your own.

## Route Caching

In production, caching routes improve performance by skipping route parsing on
every request:

```php
Route::make()
    ->withRouting(
        routes: function(App $app) { /* ... */ },
        cachePath: __DIR__ . '/routes.cache', // Path where the cache file will be stored
    )
    ->run();
```

> **Tip:** Commit the cache file to your repository for production. Delete it
> when you change routes to regenerate.

## Base Path

If your app lives in a subdirectory (e.g., `https://example.com/api/v1/...`),
set a base path so all routes are prefixed automatically:

```php
Route::make()
    ->withBasePath('/api/v1')
    ->withRouting(function(App $app) {
        // This route responds to: GET /api/v1/users
        $app->get('/users', function() {
            response(['users' => []]);
        });
    })
    ->run();
```

## Domain Configuration

Set your app's domain to enable full URL generation (useful for emails, API
responses, redirects):

```php
use Simsoft\Slim\URL;

Route::make()
    ->withDomain('https://api.example.com')
    ->withBasePath('/v1')
    ->withRouting(function(App $app) {
        $app->get('/users/{id}', function(string $id) {
            response("User $id");
        })->setName('users.show');
    })
    ->run();

// Now you can generate URLs anywhere in your app:
URL::for('users.show', ['id' => '1']);      // Returns: /v1/users/1
URL::fullFor('users.show', ['id' => '1']);   // Returns: https://api.example.com/v1/users/1
```

## Container Support

A container (dependency injection) lets you share services (database, logger,
mailer) across your app without creating them everywhere. Any PSR-11 container
works:

```php
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

// 1. Define your services
$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions([
    'logger' => function(ContainerInterface $c) {
        return new \App\Services\Logger();
    },
    'db' => function(ContainerInterface $c) {
        return new \App\Services\Database();
    },
]);
$container = $containerBuilder->build();

// 2. Pass the container to Route::make()
Route::make($container)
    ->withRouting(function(App $app) {
        $app->get('/', function() use ($app) {
            // 3. Access services via the container
            $app->getContainer()->get('logger')->info('Home accessed');
            response('Hello World!');
        });
    })
    ->run();
```

> **Note:** This example uses [PHP-DI](https://php-di.org/), but any PSR-11
> container works (League Container, Pimple, etc.).

## Full Example

A complete production-ready setup combining all features:

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use App\Controllers\UserController;
use Simsoft\Slim\Middlewares\Auth;
use Simsoft\Slim\Middlewares\CORS;
use Simsoft\Slim\Middlewares\CacheOff;
use Simsoft\Slim\Middlewares\RateLimit;
use Simsoft\Slim\Route;
use Slim\App;

Route::make($container)
    ->withDomain('https://api.example.com')
    ->withBasePath('/v1')
    ->withErrorHandler(
        displayError: false,        // Never show errors in production
        logError: true,
        logErrorDetails: true,
    )
    ->withMiddleware(function(App $app) {
        $app->add(new CORS('https://frontend.example.com')); // Only allow your frontend
        $app->add(new CacheOff());                            // No browser caching for API
        $app->add(new RateLimit(maxRequests: 100, windowSeconds: 60)); // 100 req/min
        $app->add(new Auth(fn($request) => $_SESSION['user'] ?? null)); // Require login
    })
    ->withRouting(
        routes: function(App $app) {
            $app->get('/users', [UserController::class, 'index'])->setName('users.index');
            $app->get('/users/{id}', [UserController::class, 'show'])->setName('users.show');
            $app->post('/users', [UserController::class, 'store'])->setName('users.store');
        },
        cachePath: __DIR__ . '/routes.cache', // Cache routes for performance
    )
    ->run();
```
