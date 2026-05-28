# Custom Error Handling

## Error Renderer

Extend one of the abstract renderers:

| Class                            | Abstract Method                              | Output     |
|----------------------------------|----------------------------------------------|------------|
| `AbstractHtmlErrorRenderer`      | `renderHtmlBody(Throwable): string`          | HTML       |
| `AbstractJsonErrorRenderer`      | `formatExceptionFragment(Throwable): array`  | JSON       |
| `AbstractPlainTextErrorRenderer` | `formatExceptionFragment(Throwable): string` | Plain text |
| `AbstractXmlErrorRenderer`       | `renderXmlBody(Throwable): string`           | XML        |

All renderers delegate to Slim's detailed renderer when `displayErrorDetails` is
`true`.

### Example: HTML Renderer

```php
<?php
namespace App;

use Simsoft\Slim\ErrorRenderers\AbstractHtmlErrorRenderer;
use Throwable;

class CustomHtmlErrorRenderer extends AbstractHtmlErrorRenderer
{
    public function renderHtmlBody(Throwable $exception): string
    {
        return strtr('<html lang="en">
            <head><title>{{ code }} {{ title }}</title></head>
            <body><h1>{{ title }}</h1><p>{{ message }}</p></body>
            </html>', [
            '{{ code }}' => $exception->getCode(),
            '{{ title }}' => $this->getErrorTitle($exception),
            '{{ message }}' => $exception->getMessage(),
        ]);
    }
}
```

### Example: JSON Renderer

```php
<?php
namespace App;

use Simsoft\Slim\ErrorRenderers\AbstractJsonErrorRenderer;
use Throwable;

class CustomJsonErrorRenderer extends AbstractJsonErrorRenderer
{
    public function formatExceptionFragment(Throwable $exception): array
    {
        return [
            'error' => true,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ];
    }
}
```

## Error Handler

```php
<?php
namespace App;

use Slim\Handlers\ErrorHandler;

class CustomErrorHandler extends ErrorHandler
{
    protected $defaultErrorRenderer = CustomHtmlErrorRenderer::class;

    protected array $errorRenderers = [
        'application/json' => CustomJsonErrorRenderer::class,
        'text/html' => CustomHtmlErrorRenderer::class,
        'text/plain' => CustomPlainTextErrorRenderer::class,
        'application/xml' => CustomXmlErrorRenderer::class,
        'text/xml' => CustomXmlErrorRenderer::class,
    ];
}
```

## Registration

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

### withErrorHandler Parameters

| Parameter              | Type               | Default                  | Description             |
|------------------------|--------------------|--------------------------|-------------------------|
| `displayError`         | `bool`             | `false`                  | Show detailed errors    |
| `logError`             | `bool`             | `false`                  | Log errors              |
| `logErrorDetails`      | `bool`             | `false`                  | Log stack traces        |
| `logger`               | `?LoggerInterface` | `null`                   | PSR-3 logger            |
| `errorHandlerClass`    | `string`           | `ErrorHandler::class`    | Custom handler class    |
| `shutdownHandlerClass` | `string`           | `ShutdownHandler::class` | Custom shutdown handler |
