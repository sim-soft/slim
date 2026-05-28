# Middleware

## Table of Contents

- [Registration](#registration)
- [Built-in Middleware](#built-in-middleware)
    - [CORS](#cors)
    - [CacheOff](#cacheoff)
    - [RateLimit](#ratelimit)
    - [CSRF](#csrf)
    - [Auth](#auth)
- [Creating Custom Middleware](#creating-custom-middleware)
    - [Middleware Pattern: Before & After](#middleware-pattern-before--after)
    - [Route-Specific Middleware](#route-specific-middleware)

---

## Registration

All middlewares are invokable classes implementing the PSR-15 pattern:

```php
public function __invoke(Request $request, RequestHandler $handler): Response
```

Register middleware via `withMiddleware()`:

```php
Route::make()
    ->withMiddleware(function(App $app) {
        $app->add(new YourMiddleware());
    })
    ->withRouting(function(App $app) { /* ... */ })
    ->run();
```

## Built-in Middleware

### CORS

```php
use Simsoft\Slim\Middlewares\CORS;

// Allow all origins
$app->add(new CORS());

// Single origin
$app->add(new CORS('https://myapp.com'));

// Multiple origins (auto-detects matching origin from request)
$app->add(new CORS('https://app1.com,https://app2.com'));

// Custom methods
$app->add(new CORS('*', 'GET,POST,PUT'));

// Additional headers
$cors = new CORS('https://myapp.com');
$cors->allow('Credentials', 'true')
     ->allow('Max-Age', '3600');
$app->add($cors);
```

### CacheOff

Disables browser caching via `Cache-Control`, `Pragma`, and `Expires` headers.

```php
use Simsoft\Slim\Middlewares\CacheOff;

$app->add(new CacheOff());
```

### RateLimit

IP-based rate limiting with pluggable storage backend.

```php
use Simsoft\Slim\Middlewares\RateLimit;
use Simsoft\Slim\Middlewares\RateLimitFileStorage;
use Simsoft\Slim\Middlewares\RateLimitRedisStorage;

// Default: 60 requests per 60 seconds, file-based storage
$app->add(new RateLimit());

// Custom limits
$app->add(new RateLimit(maxRequests: 100, windowSeconds: 120));

// With trusted proxies (resolves real IP from X-Forwarded-For)
$app->add(new RateLimit(
    maxRequests: 60,
    windowSeconds: 60,
    trustedProxies: ['192.168.1.1', '10.0.0.1'],
));
```

#### Storage Backends

**File-based** (default, single-server):

```php
use Simsoft\Slim\Middlewares\RateLimitFileStorage;

$storage = new RateLimitFileStorage('/path/to/storage');
$app->add(new RateLimit(storage: $storage));

// Cleanup expired files (call via cron)
$storage->cleanup();
```

**Redis** (distributed/multi-server):

```php
use Simsoft\Slim\Middlewares\RateLimitRedisStorage;

$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$storage = new RateLimitRedisStorage($redis, prefix: 'myapp:rate:');
$app->add(new RateLimit(maxRequests: 100, windowSeconds: 60, storage: $storage));
```

**Custom storage** — implement `RateLimitStorageInterface`:

```php
use Simsoft\Slim\Middlewares\RateLimitStorageInterface;

class MemcachedStorage implements RateLimitStorageInterface
{
    public function increment(string $clientId, int $windowSeconds): array
    {
        // Your implementation
        return ['count' => $count, 'expires' => $expires];
    }
}
```

Response headers added:

- `X-RateLimit-Limit` — max requests allowed
- `X-RateLimit-Remaining` — requests remaining in a window
- `X-RateLimit-Reset` — Unix timestamp when a window resets

Throws HTTP 429 when the limit is exceeded.

### CSRF

Session-based CSRF protection with a per-request token pool.
Supports multiple concurrent forms/tabs without token conflicts.

```php
use Simsoft\Slim\Middlewares\Csrf;

$csrf = new Csrf();
$app->add($csrf);
```

#### Configuration

```php
$csrf = new Csrf(
    fieldName: '_csrf_token',     // Form field name
    headerName: 'X-CSRF-Token',  // Header name for AJAX
    maxTokens: 20,               // Max tokens in pool (prevents memory bloat)
    tokenLifetime: 3600,         // Token validity in seconds
);
```

#### Form Usage

```php
// In your template/view — each call generates a unique token
echo $csrf->getTokenField();
// Output: <input type="hidden" name="_csrf_token" value="abc123...">

// Or manually
echo '<input type="hidden" name="' . $csrf->getFieldName() . '" value="' . $csrf->generateToken() . '">';
```

#### AJAX Usage

Send the token via header:

```javascript
fetch('/api/resource', {
    method: 'POST',
    headers: { 'X-CSRF-Token': document.querySelector('[name=_csrf_token]').value }
});
```

#### Token Behavior

- Each token is **single-use** — consumed on successful validation
- Multiple tokens can coexist (supports multiple open tabs/forms)
- Pool is capped at `maxTokens` to prevent session bloat
- Expired tokens are automatically pruned
- Tokens are validated with `hash_equals()` (timing-safe)

#### Making CSRF Available in Routes

Store the CSRF instance in the container for access in controllers:

```php
$csrf = new Csrf();

$containerBuilder->addDefinitions([
    'csrf' => fn() => $csrf,
]);

Route::make($container)
    ->withMiddleware(function(App $app) use ($csrf) {
        $app->add($csrf);
    })
    ->withRouting(function(App $app) {
        $app->get('/form', function() use ($app) {
            $csrf = $app->getContainer()->get('csrf');
            response('<form method="POST">' . $csrf->getTokenField() . '<button>Submit</button></form>');
        });
    })
    ->run();
```

### Auth

Authentication and authorization middleware with pluggable authenticator.

```php
use Simsoft\Slim\Middlewares\Auth;

// Basic: authenticate only (no role/permission check)
$auth = new Auth(function ($request) {
    $token = $request->getHeaderLine('Authorization');
    if ($token === '') {
        return null; // Return null = 401
    }
    return ['id' => 1, 'name' => 'John', 'roles' => ['admin'], 'permissions' => ['users.read']];
});

$app->add($auth);
```

#### With Role Requirements (OR logic — a user needs at least one)

```php
$auth = new Auth($authenticator, roles: ['admin', 'moderator']);
$app->add($auth);
```

#### With Permission Requirements (AND logic — user needs all)

```php
$auth = new Auth($authenticator, permissions: ['users.read', 'users.write']);
$app->add($auth);
```

#### Route-Specific Authorization

```php
$auth = new Auth($authenticator);

Route::make()
    ->withMiddleware(function(App $app) use ($auth) {
        // Global: authentication only
        $app->add($auth);
    })
    ->withRouting(function(App $app) use ($auth) {
        // Public routes (no auth middleware added)
        $app->get('/login', [AuthController::class, 'loginForm']);

        // Admin-only routes
        $app->group('/admin', function($group) {
            $group->get('/users', [AdminController::class, 'users']);
            $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);
        })->add($auth->withRoles('admin'));

        // Permission-specific routes
        $app->post('/posts', [PostController::class, 'store'])
            ->add($auth->withPermissions('posts.create'));
    })
    ->run();
```

#### Accessing the Authenticated User

The user array is stored in the request attribute (default: `user`):

```php
class UserController
{
    public function profile()
    {
        $user = request()->getAttribute('user');
        response(['name' => $user['name']]);
    }
}
```

#### Custom Attribute Name

```php
$auth = new Auth($authenticator, attribute: 'currentUser');
// Access via: request()->getAttribute('currentUser')
```

#### Authenticator Examples

**Bearer Token (API):**

```php
$auth = new Auth(function ($request) {
    $header = $request->getHeaderLine('Authorization');
    if (!str_starts_with($header, 'Bearer ')) {
        return null;
    }
    $token = substr($header, 7);
    $user = $this->tokenService->validate($token);
    return $user; // ['id' => ..., 'roles' => [...], 'permissions' => [...]]
});
```

**Session-based:**

```php
$auth = new Auth(function ($request) {
    return $_SESSION['user'] ?? null;
});
```

**Basic Auth:**

```php
$auth = new Auth(function ($request) {
    $header = $request->getHeaderLine('Authorization');
    if (!str_starts_with($header, 'Basic ')) {
        return null;
    }
    $decoded = base64_decode(substr($header, 6));
    [$username, $password] = explode(':', $decoded, 2);
    return $this->userService->verify($username, $password);
});
```

## Creating Custom Middleware

Implement an invokable class:

```php
<?php
declare(strict_types=1);

namespace App\Middlewares;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $token = $request->getHeaderLine('Authorization');

        if (empty($token)) {
            throw new \Slim\Exception\HttpException($request, 'Unauthorized', 401);
        }

        // Add data to request attributes for downstream use
        $request = $request->withAttribute('user_id', $this->validateToken($token));

        return $handler->handle($request);
    }

    private function validateToken(string $token): int
    {
        // Your validation logic
        return 1;
    }
}
```

### Middleware Pattern: Before & After

```php
class TimingMiddleware
{
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        // BEFORE: runs before the route handler
        $start = microtime(true);

        $response = $handler->handle($request);

        // AFTER: runs after the route handler
        $elapsed = microtime(true) - $start;

        return $response->withHeader('X-Response-Time', sprintf('%.3fms', $elapsed * 1000));
    }
}
```

### Route-Specific Middleware

```php
Route::make()
    ->withRouting(function(App $app) {
        $app->get('/admin', [AdminController::class, 'index'])
            ->add(new AuthMiddleware());

        $app->group('/api', function($group) {
            $group->post('/users', [UserController::class, 'store']);
            $group->put('/users/{id}', [UserController::class, 'update']);
        })->add(new RateLimit(maxRequests: 30, windowSeconds: 60));
    })
    ->run();
```
