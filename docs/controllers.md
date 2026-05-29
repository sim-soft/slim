# Controllers

## Table of Contents

- [Basic Controller](#basic-controller)
- [Registering Controller Routes](#registering-controller-routes)
- [Named Routes](#named-routes)
- [Route Groups](#route-groups)
- [ContainerAwareTrait](#containerawaretrait)
- [Controller with Container (Manual)](#controller-with-container-manual)
- [Return Value Behavior](#return-value-behavior)

## Basic Controller

Route parameters are passed as method arguments:

```php
<?php
namespace App\Controllers;

use function Simsoft\Slim\response;
use function Simsoft\Slim\request;

class UserController
{
    public function index()
    {
        response('Users list');
    }

    public function show(string $id)
    {
        response("User $id");
    }

    public function store()
    {
        $data = request()->getParsedBody();
        // create user...
        response(['id' => 1, 'name' => $data['name']], 201);
    }

    /** Return string → text response */
    public function version(): string
    {
        return '1.0.0';
    }

    /** Return array → JSON response */
    public function list(): array
    {
        return ['users' => []];
    }
}
```

## Registering Controller Routes

```php
use App\Controllers\UserController;
use Simsoft\Slim\Route;
use Slim\App;

Route::make()
    ->withRouting(function(App $app) {
        $app->get('/users', [UserController::class, 'index']);
        $app->get('/users/{id}', [UserController::class, 'show']);
        $app->post('/users', [UserController::class, 'store']);
        $app->put('/users/{id}', [UserController::class, 'update']);
        $app->delete('/users/{id}', [UserController::class, 'destroy']);
    })
    ->run();
```

## Named Routes

```php
$app->get('/users', [UserController::class, 'index'])->setName('users.index');
$app->get('/users/{id}', [UserController::class, 'show'])->setName('users.show');
$app->post('/users', [UserController::class, 'store'])->setName('users.store');
```

## Route Groups

```php
use App\Controllers\UserController;
use App\Controllers\PostController;
use Simsoft\Slim\Middlewares\Auth;

$auth = new Auth(fn($request) => $_SESSION['user'] ?? null);

Route::make($container)
    ->withRouting(function(App $app) use ($auth) {

        // Public routes
        $app->get('/login', [AuthController::class, 'loginForm']);
        $app->post('/login', [AuthController::class, 'login']);

        // Authenticated routes
        $app->group('/api', function($group) {
            $group->get('/users', [UserController::class, 'index'])->setName('users.index');
            $group->get('/users/{id}', [UserController::class, 'show'])->setName('users.show');
            $group->post('/users', [UserController::class, 'store'])->setName('users.store');
        })->add($auth);

        // Admin-only routes
        $app->group('/admin', function($group) {
            $group->get('/dashboard', [AdminController::class, 'index']);
            $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);
        })->add($auth->withRoles('admin'));

    })
    ->run();
```

## ContainerAwareTrait

Access container services as magic properties via `__get()`:

```php
<?php
namespace App\Controllers;

use Simsoft\Slim\Traits\ContainerAwareTrait;
use function Simsoft\Slim\response;

/**
 * @property \App\Services\Logger $logger
 * @property \App\Services\Database $db
 * @property \App\Services\Cache $cache
 */
class UserController
{
    use ContainerAwareTrait;

    public function index()
    {
        $this->logger->info('Users page accessed');

        $users = $this->db->query('SELECT * FROM users');

        response($users);
    }

    public function show(string $id)
    {
        $this->cache->remember("user:$id", function() use ($id) {
            return $this->db->find('users', $id);
        });

        response($user);
    }
}
```

### How It Works

The trait provides a constructor that accepts a `ContainerInterface` and a
`__get()` magic method that resolves services by name:

```php
trait ContainerAwareTrait
{
    public function __construct(protected ?ContainerInterface $container = null) {}

    public function __get(string $id)
    {
        if ($this->container?->has($id)) {
            return $this->container->get($id);
        }

        throw new Exception("Service: '$id' not found.");
    }
}
```

When you access `$this->logger`, it calls `__get('logger')` which resolves
`$container->get('logger')`.

### IDE Support with @property

Add `@property` PHPDoc annotations to your controller for autocompletion and
static analysis:

```php
/**
 * @property \App\Services\Logger $logger
 * @property \App\Services\Database $db
 * @property \App\Services\Cache $cache
 * @property \App\Services\Mailer $mailer
 */
class OrderController
{
    use ContainerAwareTrait;

    public function store()
    {
        $order = $this->db->insert('orders', request()->getParsedBody());
        $this->mailer->send($order->email, 'Order confirmed');
        $this->logger->info("Order {$order->id} created");

        response(['id' => $order->id], 201);
    }
}
```

Register with container:

```php
$containerBuilder->addDefinitions([
    'logger' => fn() => new Logger(),
    'db' => fn() => new Database(),
    'cache' => fn() => new Cache(),
]);
$container = $containerBuilder->build();

Route::make($container)
    ->withRouting(function(App $app) {
        $app->get('/users', [UserController::class, 'index']);
    })
    ->run();
```

## Controller with Container (Manual)

```php
<?php
namespace App\Controllers;

use Psr\Container\ContainerInterface;
use function Simsoft\Slim\response;

class UserController
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    public function index()
    {
        $this->container->get('logger')->info('accessed');
        $users = $this->container->get('db')->fetchAll('users');
        response($users);
    }
}
```

## Return Value Behavior

| Return Type               | Behavior                                              |
|---------------------------|-------------------------------------------------------|
| `void` (use `response()`) | Manual response control                               |
| `string`                  | Written as text response body                         |
| `array`                   | Encoded as JSON with `Content-Type: application/json` |
