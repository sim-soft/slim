# Simsoft Slim Route

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A fluent routing wrapper for [Slim Framework 4](https://www.slimframework.com/)
with simplified request/response helpers, built-in middlewares, and DataTable
support.

## Install

```shell
composer require simsoft/slim
```

## Quick Start

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

        $app->get('/{name}', function(string $name) {
            response("Hello $name!");
        });
    })
    ->run();
```

## Route Builder API

```php
Route::make($container)              // Optional PSR-11 container
    ->withDomain('https://example.com')  // Set domain for URL generation
    ->withBasePath('/api/v1')            // URL prefix for all routes
    ->withErrorHandler(...)              // Error handling config
    ->withMiddleware(function(App $app) { /* ... */ })
    ->withRouting(
        routes: function(App $app) { /* ... */ },
        cachePath: '/path/to/routes.cache',
    )
    ->run();
```

## Controllers

```php
<?php
namespace App;

use function Simsoft\Slim\response;

class UserController
{
    public function index() { response('Users list'); }

    public function show(string $id) { response("User $id"); }

    /** Return string → text response */
    public function version(): string { return '1.0.0'; }

    /** Return array → JSON response */
    public function list(): array { return ['users' => []]; }
}
```

```php
Route::make()
    ->withRouting(function(App $app) {
        $app->get('/users', [UserController::class, 'index']);
        $app->get('/users/{id}', [UserController::class, 'show']);
    })
    ->run();
```

### ContainerAwareTrait

```php
use Simsoft\Slim\Traits\ContainerAwareTrait;

class UserController
{
    use ContainerAwareTrait;

    public function index()
    {
        $this->logger->info('accessed');  // Magic property access to container services
        response('Hello');
    }
}
```

## Request & Response

```php
use function Simsoft\Slim\request;
use function Simsoft\Slim\response;

// Request
request()->getQueryParams();
request()->getParsedBody();
request()->isMethod('post');
request()->isXHR();
request()->getBearerToken();
request()->urlFor('route_name', ['id' => '1']);
request()->notFound();

// Response
response('Hello World');
response(['status' => 'ok']);
response('Error', 500);
response()->json($data);
response()->xml($xmlString);
response()->redirect('/path', 301);
response()->header('X-Custom', 'value');
```

Full reference: [docs/REQUEST_RESPONSE.md](docs/REQUEST_RESPONSE.md)

## URL Helper

```php
use Simsoft\Slim\URL;

URL::for('user.show', ['id' => '42']);       // /users/42
URL::fullFor('user.show', ['id' => '42']);   // https://example.com/users/42
```

## Middleware

```php
use Simsoft\Slim\Middlewares\Auth;
use Simsoft\Slim\Middlewares\CORS;
use Simsoft\Slim\Middlewares\CacheOff;
use Simsoft\Slim\Middlewares\RateLimit;
use Simsoft\Slim\Middlewares\Csrf;

Route::make()
    ->withMiddleware(function(App $app) {
        $app->add(new CORS('https://myapp.com'));
        $app->add(new CacheOff());
        $app->add(new RateLimit(maxRequests: 100, windowSeconds: 60));
        $app->add(new Csrf());
        $app->add(new Auth(fn($request) => $_SESSION['user'] ?? null));
    })
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

| Middleware  | Purpose                                         |
|-------------|-------------------------------------------------|
| `Auth`      | Authentication + role/permission authorization  |
| `CORS`      | Cross-origin resource sharing headers           |
| `CacheOff`  | Disable browser caching                         |
| `RateLimit` | IP-based rate limiting with configurable window |
| `Csrf`      | CSRF token validation for forms and AJAX        |

Full reference & custom middleware
guide: [docs/MIDDLEWARE.md](docs/MIDDLEWARE.md)

## Error Handling

```php
Route::make()
    ->withErrorHandler(
        displayError: false,
        logError: true,
        logErrorDetails: true,
        errorHandlerClass: CustomErrorHandler::class,
    )
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

Full reference: [docs/ERROR_HANDLING.md](docs/ERROR_HANDLING.md)

## Plugins

- [DataTable Plugin](docs/PLUGIN_DATATABLE.md) — Server-side response builder
  for jQuery DataTables
- [API Resources](docs/RESOURCE.md) — Data transformation layer for structured
  API responses

## Documentation

| Topic                                                      | Link                                                 |
|------------------------------------------------------------|------------------------------------------------------|
| Middleware (Auth, CORS, CacheOff, RateLimit, CSRF, Custom) | [docs/MIDDLEWARE.md](docs/MIDDLEWARE.md)             |
| Request & Response                                         | [docs/REQUEST_RESPONSE.md](docs/REQUEST_RESPONSE.md) |
| API Resources                                              | [docs/RESOURCE.md](docs/RESOURCE.md)                 |
| Error Handling                                             | [docs/ERROR_HANDLING.md](docs/ERROR_HANDLING.md)     |
| DataTable Plugin                                           | [docs/PLUGIN_DATATABLE.md](docs/PLUGIN_DATATABLE.md) |

## License

MIT License. See [LICENSE](LICENSE) for details.
