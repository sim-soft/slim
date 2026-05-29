# Simsoft Slim

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)]([LICENSE](https://github.com/sim-soft/slim/blob/master/LICENSE))

> A fluent routing wrapper
> for [Slim Framework 4](https://www.slimframework.com/) with simplified
> request/response helpers, built-in middlewares, API resources, and DataTable
> support.

## Features

- **Fluent Builder** — configure routes, middleware, and error handling in a
  single chain
- **Request/Response Helpers** — `request()` and `response()` global functions
  with input sanitizer
- **15 Built-in Middleware** — Auth, CORS, CacheOff, Benchmark, Logging,
  RateLimit, Quota, CSRF, SecurityHeaders, MaintenanceMode, IpFilter,
  TrailingSlash, ContentNegotiation, ContentLength, MethodOverride
- **API Resources** — data transformation layer with conditional fields,
  pagination, and serializers
- **DataTable Plugin** — server-side response builder for jQuery DataTables
- **Custom Error Handling** — abstract renderers for HTML, JSON, XML, and plain
  text
- **Single Action Controllers** — invokable classes with auto-injected
  dependencies
- **PSR Compliant** — PSR-7, PSR-15, PSR-11

## Requirements

- PHP >= 8.2
- Slim Framework 4

## Installation

```bash
composer require simsoft/slim
```

## Quick Example

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use Simsoft\Slim\Middlewares\Auth;
use Simsoft\Slim\Middlewares\CORS;
use Simsoft\Slim\Middlewares\SecurityHeaders;
use Simsoft\Slim\Route;
use Slim\App;
use function Simsoft\Slim\response;

Route::make()
    ->withDomain('https://api.example.com')
    ->withBasePath('/v1')
    ->withErrorHandler(displayError: false, logError: true, logErrorDetails: true)
    ->withMiddleware(function(App $app) {
        $app->add(new CORS('https://frontend.example.com'));
        $app->add(new SecurityHeaders());
        $app->add(new Auth(fn($request) => $_SESSION['user'] ?? null));
    })
    ->withRouting(function(App $app) {
        $app->get('/users', [UserController::class, 'index'])->setName('users.index');
        $app->get('/users/{id}', [UserController::class, 'show'])->setName('users.show');
        $app->post('/users', [UserController::class, 'store'])->setName('users.store');
    })
    ->run();
```

## License

MIT — [View License](https://github.com/sim-soft/slim/blob/master/LICENSE)
