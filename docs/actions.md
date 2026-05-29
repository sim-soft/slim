# Single Action Controller

Instead of a controller with multiple methods, you can create a dedicated class
for each action. Each class has a single `__invoke()` method — one class, one
responsibility. This is the pattern used by
the [official Slim 4 skeleton](https://github.com/slimphp/Slim-Skeleton).

## Table of Contents

- [Basic Action](#basic-action)
- [Registering Actions](#registering-actions)
- [Action with Container](#action-with-container)
- [Action with ContainerAwareTrait](#action-with-containerawaretrait)
- [When to Use Actions vs Controllers](#when-to-use-actions-vs-controllers)

## Basic Action

An action class only needs an `__invoke()` method. Route parameters are passed
as arguments (same as closures and controller methods):

```php
<?php
namespace App\Actions;

use function Simsoft\Slim\response;

class ListUsersAction
{
    public function __invoke(): array
    {
        // Returning an array sends JSON automatically
        return ['users' => []];
    }
}
```

```php
<?php
namespace App\Actions;

use function Simsoft\Slim\response;

class ShowUserAction
{
    public function __invoke(string $id)
    {
        // Route parameter {id} is passed as argument
        response(['id' => $id, 'name' => 'John']);
    }
}
```

## Registering Actions

Pass the class name directly — no method array needed. Slim calls `__invoke`
automatically:

```php
use App\Actions\ListUsersAction;
use App\Actions\ShowUserAction;
use App\Actions\CreateUserAction;
use App\Actions\DeleteUserAction;

Route::make()
    ->withRouting(function(App $app) {
        $app->get('/users', ListUsersAction::class)->setName('users.index');
        $app->get('/users/{id}', ShowUserAction::class)->setName('users.show');
        $app->post('/users', CreateUserAction::class)->setName('users.store');
        $app->delete('/users/{id}', DeleteUserAction::class)->setName('users.delete');
    })
    ->run();
```

> **Note:** Compare with multi-method controllers where you pass
`[ClassName::class, 'method']`. With actions, you pass just `ClassName::class`.

## Action with Container

When you pass a container to `Route::make($container)`, Slim automatically
resolves the action's constructor dependencies from the container. This means
you can type-hint services in the constructor and they'll be injected for you:

```php
<?php
namespace App\Actions;

use App\Services\UserRepository;
use function Simsoft\Slim\response;
use function Simsoft\Slim\request;

class CreateUserAction
{
    // Slim resolves UserRepository from the container automatically
    public function __construct(private UserRepository $userRepository)
    {
    }

    public function __invoke()
    {
        $data = request()->input(['name', 'email']);
        $user = $this->userRepository->create($data);

        response(['id' => $user->id, 'name' => $user->name], 201);
    }
}
```

Register the dependency in your container:

```php
$containerBuilder->addDefinitions([
    UserRepository::class => fn() => new UserRepository($db),
]);
$container = $containerBuilder->build();

Route::make($container)
    ->withRouting(function(App $app) {
        $app->post('/users', CreateUserAction::class);
    })
    ->run();
```

## Action with ContainerAwareTrait

You can also use `ContainerAwareTrait` in actions for magic property access to
container services:

```php
<?php
namespace App\Actions;

use Simsoft\Slim\Traits\ContainerAwareTrait;
use function Simsoft\Slim\response;

/**
 * @property \App\Services\UserRepository $userRepository
 * @property \App\Services\Logger $logger
 */
class DeleteUserAction
{
    use ContainerAwareTrait;

    public function __invoke(string $id)
    {
        $this->userRepository->delete($id);
        $this->logger->info("User $id deleted");

        response(null, 204);
    }
}
```

## When to Use Actions vs Controllers

| Pattern                     | Best for                                                                                          |
|-----------------------------|---------------------------------------------------------------------------------------------------|
| **Multi-method controller** | CRUD resources where methods are related (UserController with index, show, store, update, delete) |
| **Single action class**     | Complex operations that deserve their own file, or when you want strict single-responsibility     |

Both patterns work with named routes, groups, and middleware — choose based on
your preference.
