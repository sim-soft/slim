# URL Generation

## Table of Contents

- [URL Helper (Static)](#url-helper-static)
- [URL Generation in Handlers](#url-generation-in-handlers)
- [Domain Configuration](#domain-configuration)
- [Full URL Examples](#full-url-examples)

## URL Helper (Static)

The `URL` class provides static URL generation. Available after `withRouting()`
is called.

```php
use Simsoft\Slim\URL;

// Relative URL (includes base path)
URL::for('home');
// /

URL::for('users.show', ['id' => '42']);
// /users/42

URL::for('users.show', ['id' => '42'], ['tab' => 'posts']);
// /users/42?tab=posts

URL::for('search', [], ['q' => 'php', 'page' => '1']);
// /search?q=php&page=1
```

### Full URL (with domain)

```php
URL::fullFor('users.show', ['id' => '42']);
// https://example.com/users/42

URL::fullFor('search', [], ['q' => 'php']);
// https://example.com/search?q=php
```

## URL Generation in Handlers

The `request()` helper provides URL generation within route handlers and
controllers:

```php
use function Simsoft\Slim\request;

// With base path
request()->urlFor('users.show', ['id' => '42']);
// /api/v1/users/42

// With query parameters
request()->urlFor('search', queryParams: ['q' => 'php']);
// /search?q=php

// Without base path
request()->relativeUrlFor('users.show', ['id' => '42']);
// /users/42

// With domain
request()->fullUrlFor('users.show', ['id' => '42']);
// https://api.example.com/api/v1/users/42
```

### Usage in Redirects

```php
use function Simsoft\Slim\request;
use function Simsoft\Slim\response;

class AuthController
{
    public function login()
    {
        // ... authenticate ...
        response()->redirect(request()->urlFor('dashboard'));
    }

    public function logout()
    {
        // ... clear session ...
        response()->redirect(request()->urlFor('home'));
    }
}
```

### Usage in API Resources

```php
use Simsoft\Slim\URL;

class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'links' => [
                'self' => URL::for('users.show', ['id' => (string)$this->resource->id]),
                'posts' => URL::for('users.posts', ['id' => (string)$this->resource->id]),
            ],
        ];
    }
}
```

## Domain Configuration

Set the domain via the route builder for `fullFor()` and `fullUrlFor()`:

```php
Route::make()
    ->withDomain('https://api.example.com')
    ->withBasePath('/v1')
    ->withRouting(function(App $app) {
        $app->get('/users/{id}', [UserController::class, 'show'])->setName('users.show');
    })
    ->run();
```

## Full URL Examples

Given the configuration above:

| Method                                                   | Output                               |
|----------------------------------------------------------|--------------------------------------|
| `URL::for('users.show', ['id' => '1'])`                  | `/v1/users/1`                        |
| `URL::fullFor('users.show', ['id' => '1'])`              | `https://api.example.com/v1/users/1` |
| `request()->urlFor('users.show', ['id' => '1'])`         | `/v1/users/1`                        |
| `request()->relativeUrlFor('users.show', ['id' => '1'])` | `/users/1`                           |
| `request()->fullUrlFor('users.show', ['id' => '1'])`     | `https://api.example.com/v1/users/1` |
