# Middleware

## Overview

Middleware is code that wraps around your route handlers. It can inspect or
modify requests before they reach your route, and inspect or modify responses
before they're sent to the client.

Common uses: authentication, CORS headers, logging, rate limiting, caching
control.

### How It Works

Think of middleware as layers of an onion. A request passes through each layer
on the way in, hits your route handler, then the response passes back through
each layer on the way out:

```
Request → Middleware C → Middleware B → Middleware A → Route Handler
Response ← Middleware C ← Middleware B ← Middleware A ←
```

### The Pattern

Every middleware follows the same PSR-15 pattern — an invokable class with this
signature:

```php
public function __invoke(
    ServerRequestInterface $request,   // The incoming HTTP request
    RequestHandlerInterface $handler   // The next layer (call $handler->handle($request) to continue)
): ResponseInterface                   // The outgoing HTTP response
```

### Quick Start

```php
use Simsoft\Slim\Middlewares\CORS;
use Simsoft\Slim\Route;
use Slim\App;

Route::make()
    ->withMiddleware(function(App $app) {
        $app->add(new CORS()); // Add CORS headers to all responses
    })
    ->withRouting(function(App $app) { /* your routes */ })
    ->run();
```

### Next Steps

- [Creating Middleware](creating-middleware.md) — how to write your own
  middleware
- [Built-in Middleware](builtin-middleware.md) — ready-to-use middleware
  included with this library
