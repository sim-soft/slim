# Product Overview

Simsoft Slim is a PHP library providing a fluent wrapper around Slim Framework
4. It simplifies routing, request/response handling, error handling, middleware,
and authentication through a builder API.

## Key Capabilities

- Fluent route configuration via `Route::make()` builder
- Simplified request/response via `request()` and `response()` helpers
- Custom invocation strategy (`Args`) passing route params as function arguments
- Built-in middleware: Auth, CORS, CacheOff, RateLimit, CSRF
- Pluggable rate limit storage (file, Redis, custom)
- Customizable error handling with abstract renderer classes
- URL helper for named route resolution
- DataTable plugin for server-side jQuery DataTables
- PSR-7/PSR-15 compliant, supports any PSR-11 container
