# Simsoft Slim Route

A nice wrapper for [slim/slim](https://www.slimframework.com/).

## Install

```shell
composer require simsoft/slim
```

## Basic Usage

Set up the following code at index.php or any entry script file.
For create route tutorial, please refer
to [Slim Route](https://www.slimframework.com/docs/v4/objects/routing.html)

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use Simsoft\Slim\Route;
use Slim\App;
use function Simsoft\Slim\response;

// Usage example
Route::make()
    ->withErrorHandler(false, true, true)
    ->withRouting(fucntion(App $app) {
        $app->get('/', function() {
            response('Hello World!');
        });

        $app->get('/{name}', function(string $name) {
            response("Hello $name!");
        });
    })
    ->run();
```

### Enable cache

```php
Route::make()
    ->withErrorHandler(false, true, true)
    ->withRouting(
        routes: fucntion(App $app) {
            $app->get('/', function() {
                response('Hello World!');
            });

            $app->get('/{name}', function(string $name) {
                response("Hello $name!");
            });
        },
        cachePath: '/path/to/routes.cache', // Commit the cache file for production.
    )
    ->run();
```

## Middleware

For middleware tutorial, please refer
to [Slim Middleware](https://www.slimframework.com/docs/v4/concepts/middleware.html)

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use Simsoft\Slim\Route;
use Slim\App;
use function Simsoft\Slim\response;

Route::make()
    ->withErrorHandler(false, true, true)
    ->withMiddleware(function(App $app) {

        $app->add(function($request, $handler) {
            $response = $handler->handle($request);
            $response->getBody()->write('Welcome');
            return $response;
        });

    })
    ->withRouting(
        routes: fucntion(App $app) {
            $app->get('/', function() {
                response('Hello World!');
            });

            $app->get('/{name}', function(string $name) {
                response("Hello $name!");
            });
        },
        cachePath: '/path/to/routes.cache', // Enable cache. Commit the cache file for production.
    )
    ->run();
```

## Usage with Container

Slim Route support any ContainerInterface container.

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Simsoft\Slim\Route;
use Slim\App;
use function Simsoft\Slim\response;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    'logger' => function(ContainerInterface $c) {
        return new \Your\Namespace\CustomLogger();
    }
]);

$container = $containerBuilder->build();

Route::make($container)
    ->withErrorHandler(false, true, true)
    ->withMiddleware(function(App $app) {

        $app->add(function($request, $handler) use ($app) {
            $response = $handler->handle($request);
            $app->getContainer()->get('logger')->info('Use container log service.');
            return $response;
        });

    })
    ->withRouting(fucntion(App $app) {
        $app->get('/{name}', function(string $name) use ($app) {
            $app->getContainer()->get('logger')->info('Use container log service.');
            response("Hello $name!");
        });
    })
    ->run();
```

## Using Controller

###### The App\AppController.php

```php
<?php
namespace App;

use function Simsoft\Slim\response;

class AppController
{
    public function index()
    {
        response('Hello World');
    }

    public function name(string $name)
    {
        response("Hello $name!");
    }

    /**
     * Return string will display text immediately.
     * @return string
     */
    public function showText(): string
    {
        return 'Display text';
    }

    /**
     * Return array will display JSON immediately.
     * @return array
     */
    public function getJson(): array
    {
        return [1, 2, 3, 4, 5];
    }
}
```

###### The index.php entry script file.

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use App\AppController;
use Simsoft\Slim\Route;
use Slim\App;

Route::make()
    ->withRouting(fucntion(App $app) {
        $app->get('', [AppController::class, 'index']);
        $app->get('/{name}', [AppController::class, 'name']);
        $app->get('/show-text', [AppController::class, 'showText']);
        $app->get('/get-json', [AppController::class, 'getJson']);
    })
    ->run();
```

## Using Controller with Container

###### The App\AppController.php file.

```php
<?php
namespace App;

use Psr\Container\ContainerInterface;
use function Simsoft\Slim\response;

class AppController
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function index()
    {   // call container service.
        $this->container->get('logger')->info('Log information');

        response('Hello World');
    }

    public function name(string $name)
    {
        response("Hello $name!");
    }
}
```

###### The index.php entry script file

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use App\AppController;
use DI\ContainerBuilder;
use Simsoft\Slim\Route;
use Slim\App;

$containerBuilder = new ContainerBuilder();

$containerBuilder->addDefinitions([
    'logger' => function(ContainerInterface $c) {
        return new \Your\Namespace\CustomLogger();
    }
]);

$container = $containerBuilder->build();

Route::make($container)
    ->withRouting(fucntion(App $app) {
        $app->get('', [AppController::class, 'index']);
    })
    ->run();
```

## Request and Response Usage

Handing request, response and redirect.

### Request method

For detail tutorials, please refer
to: [Slim Request Tutorial](https://www.slimframework.com/docs/v4/objects/request.html)

```php
<?php
namespace App;

use function Simsoft\Slim\request;

class AppController
{
    public function action1()
    {
        // trigger not found.
        request()->notFound();
        request()->notFound('The resource is not available.');

        request()->getQueryParams();    // return $_GET
        request()->getParsedBody();     // return $_POST
        request()->getUploadedFiles();  // return UploadedFileInterface[]

        // route URL.
        echo request()->urlFor('route_name')
        //output: /url-slug

        echo request()->urlFor('route_name', queryParams: ['a' => 1, 'b' => 2])
        //output: /route_name?a=1&b=2

        if (request()->isXHR()) { /**/ }            // Detect is XHR request.
        if (request()->isMethod('post')) { /**/ }   // Detect request method is POST.
    }
}
```

### Response method.

For detail tutorials, please refer
to: [Slim Response Tutorial](https://www.slimframework.com/docs/v4/objects/response.html)

```php
<?php
namespace App;

use function Simsoft\Slim\response;
use function Simsoft\Slim\request;

class AppController
{
    public function action1()
    {
        // Return text.
        response('Hello World');            // normal display.
        response('Authorized only', 401)    // Display text with status code
        response()->content('Authorized only')->status(401);

        // Return JSON
        $data = ['status' => 'success', 'message' => 'Welcome'];
        response($data);
        response()->json($data);

        // Set header
        response()->header('Content-Type', 'text/html');
        response()->withHeaders([
            'Content-Type' => 'text/html',
            'X-app' => 'Slim route',
        ])

        // Redirect
        response()->redirect('https://www.somedomain.com');   // normal usage.
        response()->redirect('/url-slug', 302);               // redirect with status code
        response()->redirect(request()->urlFor('route_name')) // use with slim route.
    }
}
```

## Create Customized Error Handler

### Step 1: Create a customized error renderer for HTML.

###### The CustomizedHtmlErrorRenderer file.

```php
<?php
namespace App;

use Simsoft\Slim\ErrorRenderers\AbstractHtmlErrorRenderer;
use Throwable;

class CustomizedHtmlErrorRenderer extends AbstractHtmlErrorRenderer
{
    public function renderHtmlBody(Throwable $exception): string
    {
        // write your own HTML body
        return strtr('<html lang="en">
                    <header>
                        <title>{{ code }} {{ title }}</title>
                    </header>
                    <body>
                        {{ message }} <br />
                        {{ description }}
                    </body>
                    </html>', [
                '{{ code }}' => $exception->getCode(),
                '{{ title }}' => $this->getErrorTitle($exception),
                '{{ message }}' => $exception->getMessage(),
                '{{ description }}' => $this->getErrorDescription($exception),
            ]);
    }
}
```

### Step 2: Apply the customized error renderer class accordingly in CustomizedErrorHandler class.

###### The CustomizedErrorHandler file

```php
<?php
namespace App;

use App\CustomizedHtmlErrorRenderer;
use Slim\Error\Renderers\JsonErrorRenderer;
use Slim\Error\Renderers\PlainTextErrorRenderer;
use Slim\Error\Renderers\XmlErrorRenderer;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\ErrorRendererInterface;

class CustomizedErrorHandler extends ErrorHandler
{
    protected $defaultErrorRenderer = CustomizedHtmlErrorRenderer::class; // use CustomizedHtmlErrorRenderer

    /**
     * @var ErrorRendererInterface|string|callable
     */
    protected $logErrorRenderer = PlainTextErrorRenderer::class;

    protected array $errorRenderers = [
        'application/json' => JsonErrorRenderer::class,
        'application/xml' => XmlErrorRenderer::class,
        'text/xml' => XmlErrorRenderer::class,
        'text/html' => CustomizedHtmlErrorRenderer::class, // use CustomizedHtmlErrorRenderer
        'text/plain' => PlainTextErrorRenderer::class,
    ];
}
```

### Step 3: Set customizedErrorHandler with withErrorHandler() method.

###### The index.php entry script file.

```php
<?php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use App\CustomizedErrorHandler;
use Simsoft\Slim\Route;
use Slim\App;
use function Simsoft\Slim\response;

Route::make()
    ->withErrorHandler(false, true, true, errorHandlerClass: CustomizedErrorHandler::class)
    ->withRouting(fucntion(App $app) {
        $app->get('/{name}', function(string $name){
            response("Hello world!");
        });
    })
    ->run();
```

## License

The Simsoft Validator is licensed under the MIT License. See
the [LICENSE](LICENSE) file for details
