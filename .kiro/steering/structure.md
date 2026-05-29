# Project Structure

```
src/                          # Library source (namespace: Simsoft\Slim\)
├── Route.php                 # Main entry point — fluent builder for Slim App
├── Request.php               # Request wrapper + request() helper function
├── Response.php              # Response wrapper + response() helper function
├── URL.php                   # Static URL helper for named route resolution
├── ErrorRenderers/           # Abstract base classes for custom error renderers
│   ├── AbstractHtmlErrorRenderer.php
│   ├── AbstractJsonErrorRenderer.php
│   ├── AbstractPlainTextErrorRenderer.php
│   └── AbstractXmlErrorRenderer.php
├── Handlers/
│   ├── ShutdownHandler.php
│   └── Strategies/
│       └── Args.php          # Invocation strategy: route args as function params
├── Middlewares/
│   ├── Auth.php              # Authentication + role/permission authorization
│   ├── CacheOff.php          # Disables browser caching
│   ├── CORS.php              # CORS header management
│   ├── Csrf.php              # CSRF token validation (per-request token pool)
│   ├── RateLimit.php         # Rate limiting with pluggable storage
│   ├── RateLimitFileStorage.php
│   ├── RateLimitRedisStorage.php
│   └── RateLimitStorageInterface.php
├── Plugins/DataTable/        # Correctly-spelled namespace (extends Pugins/)
├── Pugins/DataTable/         # Legacy namespace (kept for backward compat)
│   ├── DataTableResponse.php
│   └── DataTableActionButton.php
└── Traits/
    └── ContainerAwareTrait.php

docs/                         # Detailed documentation
├── MIDDLEWARE.md
├── REQUEST_RESPONSE.md
├── ERROR_HANDLING.md
└── PLUGIN_DATATABLE.md

tests/                        # PHPUnit test suite (mirrors src/ structure)
example/                      # Example application
```

## Architecture Patterns

- **Builder pattern**: `Route::make()` with `with*()` fluent methods
- **Static singleton access**: `Request`/`Response` use static properties for
  global helpers
- **Callable middleware**: PSR-15 `__invoke(Request, RequestHandler): Response`
- **Strategy pattern**: `Args` implements `InvocationStrategyInterface`
- **Abstract template**: Error renderers define rendering contract
- **Interface-based storage**: `RateLimitStorageInterface` for pluggable
  backends

## Naming Conventions

- Classes: PascalCase
- Methods/properties/variables: camelCase
- Namespace mirrors directory structure under `src/`
- Helper functions defined in same file as their class
