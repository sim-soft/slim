# Simsoft Slim Route

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Docs](https://img.shields.io/badge/docs-online-blue.svg)](https://sim-soft.github.io/slim/)

A fluent routing wrapper for [Slim Framework 4](https://www.slimframework.com/)
that simplifies route setup, request/response handling, middleware, and error
handling through a chainable builder API.

📖 **[Full Documentation](https://sim-soft.github.io/slim/)**

## Requirements

- PHP >= 8.2
- Composer

## Install

```shell
composer require simsoft/slim
```

## Quick Start

Create an `index.php` entry file:

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
        // Define a GET route for the homepage
        $app->get('/', function() {
            response('Hello World!');
        });

        // {name} is a URL parameter — passed as a function argument
        $app->get('/{name}', function(string $name) {
            response("Hello $name!");
        });
    })
    ->run();
```

Visit `http://localhost/` to see "Hello World!" or `http://localhost/john` to
see "Hello john!".

## Route Builder API

Everything is configured through a fluent (chainable) builder:

```php
Route::make($container)                      // Optional PSR-11 dependency injection container
    ->withDomain('https://example.com')      // Your app's domain (for URL generation)
    ->withBasePath('/api/v1')                // Prefix all routes with this path
    ->withErrorHandler(...)                  // Configure error display and logging
    ->withMiddleware(function(App $app) { }) // Register global middleware
    ->withRouting(
        routes: function(App $app) { },      // Define your routes here
        cachePath: '/path/to/routes.cache',  // Optional: cache routes for production
    )
    ->run();                                 // Process the request and send response
```

## Controllers

Instead of closures, point routes to controller classes for cleaner code:

```php
<?php
namespace App;

use function Simsoft\Slim\response;

class UserController
{
    public function index() { response('Users list'); }

    public function show(string $id) { response("User $id"); }

    // Returning a string sends it as text
    public function version(): string { return '1.0.0'; }

    // Returning an array sends it as JSON automatically
    public function list(): array { return ['users' => []]; }
}
```

Register controller routes:

```php
Route::make()
    ->withRouting(function(App $app) {
        $app->get('/users', [UserController::class, 'index']);
        $app->get('/users/{id}', [UserController::class, 'show']);
    })
    ->run();
```

### ContainerAwareTrait

Access container services (database, logger, etc.) as magic properties in your
controllers:

```php
use Simsoft\Slim\Traits\ContainerAwareTrait;

/**
 * @property \App\Services\Logger $logger
 * @property \App\Services\Database $db
 */
class UserController
{
    use ContainerAwareTrait;

    public function index()
    {
        $this->logger->info('accessed');  // Resolves $container->get('logger')
        $users = $this->db->fetchAll('users');
        response($users);
    }
}
```

## Request & Response

Global helper functions for reading requests and sending responses:

```php
use function Simsoft\Slim\request;
use function Simsoft\Slim\response;

// Reading the request
request()->getQueryParams();           // Get URL query parameters (?key=value)
request()->getParsedBody();            // Get POST body data
request()->isMethod('post');           // Check HTTP method
request()->isXHR();                    // Detect AJAX requests
request()->getBearerToken();           // Extract "Bearer xxx" token
request()->urlFor('users.show', ['id' => '1']); // Generate URL from route name
request()->notFound();                 // Throw 404 exception

// Sending responses
response('Hello World');               // Plain text
response(['status' => 'ok']);          // JSON (arrays auto-encode)
response('Error', 500);               // Text with status code
response()->json($data);              // Explicit JSON
response()->xml($xmlString);          // XML with correct content-type
response()->redirect('/path', 301);   // Redirect
response()->header('X-Custom', 'val'); // Set response header
```

Full reference: [docs/REQUEST_RESPONSE.md](docs/REQUEST_RESPONSE.md)

## URL Helper

Generate URLs from named routes — no hardcoded paths:

```php
use Simsoft\Slim\URL;

URL::for('users.show', ['id' => '42']);       // /users/42
URL::fullFor('users.show', ['id' => '42']);   // https://example.com/users/42
```

## Middleware

Middleware runs on every request (or specific routes) to handle cross-cutting
concerns like authentication, CORS, and rate limiting:

```php
use Simsoft\Slim\Middlewares\Auth;
use Simsoft\Slim\Middlewares\CORS;
use Simsoft\Slim\Middlewares\CacheOff;
use Simsoft\Slim\Middlewares\RateLimit;
use Simsoft\Slim\Middlewares\Csrf;
use Simsoft\Slim\Middlewares\SecurityHeaders;

Route::make()
    ->withMiddleware(function(App $app) {
        $app->add(new SecurityHeaders());                // Security headers
        $app->add(new CORS('https://myapp.com'));        // Allow cross-origin requests
        $app->add(new CacheOff());                       // Prevent browser caching
        $app->add(new RateLimit(maxRequests: 100, windowSeconds: 60)); // 100 req/min
        $app->add(new Csrf());                           // Protect forms from CSRF attacks
        $app->add(new Auth(fn($request) => $_SESSION['user'] ?? null)); // Require login
    })
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

| Middleware           | Purpose                                         |
|----------------------|-------------------------------------------------|
| `Auth`               | Authentication + role/permission authorization  |
| `CORS`               | Cross-origin resource sharing headers           |
| `CacheOff`           | Disable browser caching                         |
| `Benchmark`          | Response time and memory usage headers          |
| `Logging`            | Request/response logging with custom recorder   |
| `RateLimit`          | IP-based rate limiting with configurable window |
| `Quota`              | API usage quota per user/key (daily/monthly)    |
| `Csrf`               | CSRF token validation for forms and AJAX        |
| `SecurityHeaders`    | XSS, clickjacking, MIME sniffing protection     |
| `MaintenanceMode`    | 503 response with IP bypass                     |
| `IpFilter`           | Whitelist or blacklist client IPs               |
| `TrailingSlash`      | Normalize trailing slashes in URLs              |
| `ContentNegotiation` | Detect preferred format from Accept header      |
| `ContentLength`      | Add Content-Length header to responses          |
| `MethodOverride`     | Allow forms to simulate PUT/PATCH/DELETE        |

Full reference: [docs/builtin-middleware.md](docs/builtin-middleware.md)

## Error Handling

Configure how errors are displayed and logged. Hide details in production, show
them in development:

```php
Route::make()
    ->withErrorHandler(
        displayError: false,          // true in dev, false in production
        logError: true,               // Write errors to log
        logErrorDetails: true,        // Include stack traces
        errorHandlerClass: CustomErrorHandler::class, // Optional custom handler
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

📖 **[https://sim-soft.github.io/slim/](https://sim-soft.github.io/slim/)**

| Topic              | Link                                                     |
|--------------------|----------------------------------------------------------|
| Route Builder      | [docs/route-builder.md](docs/route-builder.md)           |
| Defining Routes    | [docs/defining-routes.md](docs/defining-routes.md)       |
| Controllers        | [docs/controllers.md](docs/controllers.md)               |
| Middleware         | [docs/builtin-middleware.md](docs/builtin-middleware.md) |
| Request & Response | [docs/REQUEST_RESPONSE.md](docs/REQUEST_RESPONSE.md)     |
| API Resources      | [docs/RESOURCE.md](docs/RESOURCE.md)                     |
| Error Handling     | [docs/ERROR_HANDLING.md](docs/ERROR_HANDLING.md)         |
| DataTable Plugin   | [docs/PLUGIN_DATATABLE.md](docs/PLUGIN_DATATABLE.md)     |

## License

MIT License. See [LICENSE](LICENSE) for details.
