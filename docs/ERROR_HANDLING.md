# Error Handling

Simsoft Slim catches all uncaught exceptions and PHP fatal errors, then renders
an appropriate error response based on the content type. In development, it
shows
details; in production it shows a clean error page.

## Table of Contents

- [Configuration](#configuration)
- [HTTP Exceptions](#http-exceptions)
- [Custom HTTP Exceptions](#custom-http-exceptions)
- [PHP Fatal Errors (Shutdown Handler)](#php-fatal-errors-shutdown-handler)
- [Custom Error Renderer](#custom-error-renderer)
- [Custom Error Handler](#custom-error-handler)
- [Error Logging](#error-logging)
- [Force Content Type](#force-content-type)

## Configuration

```php
Route::make()
    ->withErrorHandler(
        displayError: false,    // true = show stack traces (dev only!)
        logError: true,         // Write errors to logger
        logErrorDetails: true,  // Include stack traces in logs
        logger: $psrLogger,     // Optional PSR-3 logger (Monolog, etc.)
        errorHandlerClass: CustomErrorHandler::class,    // Optional custom handler
        shutdownHandlerClass: ShutdownHandler::class,    // Optional custom shutdown handler
    )
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

| Parameter              | Type               | Default                  | Description                        |
|------------------------|--------------------|--------------------------|------------------------------------|
| `displayError`         | `bool`             | `false`                  | Show detailed errors to the client |
| `logError`             | `bool`             | `false`                  | Log errors                         |
| `logErrorDetails`      | `bool`             | `false`                  | Include stack traces in logs       |
| `logger`               | `?LoggerInterface` | `null`                   | PSR-3 logger instance              |
| `errorHandlerClass`    | `string`           | `ErrorHandler::class`    | Custom error handler class         |
| `shutdownHandlerClass` | `string`           | `ShutdownHandler::class` | Custom shutdown handler class      |

## HTTP Exceptions

Throw HTTP exceptions anywhere in your code to return proper error responses.
The error handler catches them and renders the appropriate status code and
message:

```php
use function Simsoft\Slim\request;

// In a controller or route handler:
request()->notFound();                          // 404 Not Found
request()->notFound('User not found');          // 404 with a custom message
request()->allowedMethods(['POST', 'PUT']);      // 405 Method Not Allowed
```

You can also throw them directly:

```php
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpInternalServerErrorException;

throw new HttpNotFoundException($request, 'Resource not found');
throw new HttpForbiddenException($request, 'Access denied');
throw new HttpUnauthorizedException($request, 'Login required');
throw new HttpBadRequestException($request, 'Invalid input');
```

### Available HTTP Exceptions

| Class                              | Code | Default Message       |
|------------------------------------|------|-----------------------|
| `HttpBadRequestException`          | 400  | Bad Request           |
| `HttpUnauthorizedException`        | 401  | Unauthorized          |
| `HttpForbiddenException`           | 403  | Forbidden             |
| `HttpNotFoundException`            | 404  | Not Found             |
| `HttpMethodNotAllowedException`    | 405  | Method Not Allowed    |
| `HttpGoneException`                | 410  | Gone                  |
| `HttpTooManyRequestsException`     | 429  | Too Many Requests     |
| `HttpInternalServerErrorException` | 500  | Internal Server Error |
| `HttpNotImplementedException`      | 501  | Not Implemented       |

## Custom HTTP Exceptions

Create your own HTTP exceptions for status codes not covered by the built-in
ones:

```php
<?php
namespace App\Exceptions;

use Slim\Exception\HttpSpecializedException;

class HttpGatewayTimeoutException extends HttpSpecializedException
{
    protected $code = 504;
    protected $message = 'Gateway Timeout.';
    protected string $title = '504 Gateway Timeout';
    protected string $description = 'Timed out before receiving response from the upstream server.';
}

class HttpServiceUnavailableException extends HttpSpecializedException
{
    protected $code = 503;
    protected $message = 'Service Unavailable.';
    protected string $title = '503 Service Unavailable';
    protected string $description = 'The server is temporarily unable to handle the request.';
}
```

Usage:

```php
throw new HttpGatewayTimeoutException($request);
```

## PHP Fatal Errors (Shutdown Handler)

Some PHP errors are so severe that they stop the script instantly — a call to a
method that does not exist, running out of memory, a parse error in an included
file. These happen *outside* the normal try/catch pipeline, so Slim's error
handler never sees them and the visitor gets a blank page.

The `ShutdownHandler` is the safety net for exactly that case. It runs at the
very end of the request and, if the script died from a fatal error, renders a
proper HTTP 500 page instead of nothing at all.

It is registered automatically when you call `withErrorHandler()` — you do not
have to do anything.

### What counts as fatal

Only these error types trigger the 500 page:

| Error type             | Typical cause                              |
|------------------------|--------------------------------------------|
| `E_ERROR`              | Calling an undefined function or method    |
| `E_PARSE`              | Syntax error in an included file           |
| `E_CORE_ERROR`         | Failure during PHP startup                 |
| `E_COMPILE_ERROR`      | Failure while compiling a file             |
| `E_USER_ERROR`         | `trigger_error(..., E_USER_ERROR)`         |

Everything else is left alone:

- `E_WARNING`, `E_NOTICE` — e.g. reading a missing array key
- `E_DEPRECATED`, `E_USER_DEPRECATED` — e.g. a library warning about PHP 8.5
- `E_USER_WARNING`, `E_USER_NOTICE`

> **Why this matters:** these non-fatal errors do **not** stop your script. Your
> route already ran, already returned its `200 OK`, and the response is on its
> way to the browser. Turning that into a 500 at the last second would break a
> request that actually succeeded. A single deprecation notice from a third-party
> package should never take your API down.
>
> Non-fatal errors still belong in your logs — use PHP's `error_log` or a PSR-3
> logger for those. The shutdown handler's job is only the blank-page case.

The handler also stays quiet if the response has already been sent to the
browser, since appending an error page at that point would only corrupt it.

When `displayError` is `true`, the error message includes the file and line
number. When `false`, it shows a generic "An error while processing your
request" message.

### Custom Shutdown Handler

If you need custom behavior (e.g., send alerts on fatal errors):

```php
<?php
namespace App\Handlers;

use Simsoft\Slim\Handlers\ShutdownHandler;

class CustomShutdownHandler extends ShutdownHandler
{
    /** Error types that stop the script dead. */
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    public function __invoke(): void
    {
        $error = error_get_last();

        // Only alert on real fatals. Without this check you would page the
        // team every time a library emits a deprecation notice.
        if ($error !== null && ($error['type'] & self::FATAL) !== 0) {
            // Send alert, log to external service, etc.
            $this->notifyTeam($error);
        }

        // Call parent to render the 500 response
        parent::__invoke();
    }

    private function notifyTeam(array $error): void
    {
        // Your notification logic
    }
}
```

Register it:

```php
Route::make()
    ->withErrorHandler(
        displayError: false,
        logError: true,
        logErrorDetails: true,
        shutdownHandlerClass: CustomShutdownHandler::class,
    )
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

## Custom Error Renderer

Create custom error pages by extending the abstract renderers. When
`displayError` is `false`, your renderer is used. When `true`, Slim's detailed
renderer shows the full stack trace.

| Class                            | Abstract Method                              | Output     |
|----------------------------------|----------------------------------------------|------------|
| `AbstractHtmlErrorRenderer`      | `renderHtmlBody(Throwable): string`          | HTML       |
| `AbstractJsonErrorRenderer`      | `formatExceptionFragment(Throwable): array`  | JSON       |
| `AbstractPlainTextErrorRenderer` | `formatExceptionFragment(Throwable): string` | Plain text |
| `AbstractXmlErrorRenderer`       | `renderXmlBody(Throwable): string`           | XML        |

### Example: HTML Error Page

```php
<?php
namespace App\ErrorRenderers;

use Simsoft\Slim\ErrorRenderers\AbstractHtmlErrorRenderer;
use Throwable;

class HtmlErrorRenderer extends AbstractHtmlErrorRenderer
{
    public function renderHtmlBody(Throwable $exception): string
    {
        $code = $exception->getCode();
        $title = $this->getErrorTitle($exception);
        $message = $exception->getMessage();

        return <<<HTML
        <html lang="en">
        <head><title>{$code} {$title}</title></head>
        <body>
            <h1>{$title}</h1>
            <p>{$message}</p>
            <a href="/">Go Home</a>
        </body>
        </html>
        HTML;
    }
}
```

### Example: JSON Error Response

```php
<?php
namespace App\ErrorRenderers;

use Simsoft\Slim\ErrorRenderers\AbstractJsonErrorRenderer;
use Throwable;

class JsonErrorRenderer extends AbstractJsonErrorRenderer
{
    public function formatExceptionFragment(Throwable $exception): array
    {
        return [
            'error' => true,
            'code' => $exception->getCode(),
            'message' => $exception->getMessage(),
        ];
    }
}
```

## Custom Error Handler

Combine your renderers into a custom error handler:

```php
<?php
namespace App\Handlers;

use App\ErrorRenderers\HtmlErrorRenderer;
use App\ErrorRenderers\JsonErrorRenderer;
use Slim\Error\Renderers\PlainTextErrorRenderer;
use Slim\Error\Renderers\XmlErrorRenderer;
use Slim\Handlers\ErrorHandler;

class CustomErrorHandler extends ErrorHandler
{
    protected $defaultErrorRenderer = HtmlErrorRenderer::class;

    protected array $errorRenderers = [
        'application/json' => JsonErrorRenderer::class,
        'text/html' => HtmlErrorRenderer::class,
        'text/plain' => PlainTextErrorRenderer::class,
        'application/xml' => XmlErrorRenderer::class,
        'text/xml' => XmlErrorRenderer::class,
    ];
}
```

Register it:

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

## Error Logging

### With PSR-3 Logger

Pass any PSR-3 compatible logger (Monolog, etc.):

```php
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

$logger = new Logger('app');
$logger->pushHandler(new StreamHandler(__DIR__ . '/logs/error.log'));

Route::make()
    ->withErrorHandler(
        displayError: false,
        logError: true,
        logErrorDetails: true,
        logger: $logger,
    )
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

### Custom Log Method

Override `logError()` in your error handler for custom logging behavior:

```php
<?php
namespace App\Handlers;

use Slim\Handlers\ErrorHandler;

class CustomErrorHandler extends ErrorHandler
{
    protected function logError(string $error): void
    {
        // Send to external service, database, Slack, etc.
        file_put_contents('/var/log/app-errors.log', date('Y-m-d H:i:s') . " $error\n", FILE_APPEND);
    }
}
```

## Force Content Type

By default, the error handler detects the response format from the request's
`Accept` header. To always render errors in a specific format (e.g., for an API
that only speaks JSON):

```php
<?php
namespace App\Handlers;

use Slim\Handlers\ErrorHandler;

class ApiErrorHandler extends ErrorHandler
{
    public function __construct($callableResolver, $responseFactory)
    {
        parent::__construct($callableResolver, $responseFactory);
        $this->forceContentType('application/json');
    }
}
```

Now all errors render as JSON regardless of the `Accept` header.
