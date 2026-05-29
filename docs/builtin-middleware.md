# Built-in Middleware

## Table of Contents

- [Summary](#summary)
- [Auth](#auth)
- [CORS](#cors)
- [CacheOff](#cacheoff)
- [Benchmark](#benchmark)
- [Logging](#logging)
- [RateLimit](#ratelimit)
- [Quota](#quota)
- [CSRF](#csrf)
- [TrailingSlash](#trailingslash)
- [ContentNegotiation](#contentnegotiation)
- [ContentLength](#contentlength)
- [MethodOverride](#methodoverride)
- [SecurityHeaders](#securityheaders)
- [MaintenanceMode](#maintenancemode)
- [IpFilter](#ipfilter)

## Summary

| Middleware           | Purpose                                                 |
|----------------------|---------------------------------------------------------|
| `Auth`               | Authentication + role/permission authorization          |
| `CORS`               | Cross-origin resource sharing headers                   |
| `CacheOff`           | Disable browser caching                                 |
| `Benchmark`          | Response time and memory usage headers                  |
| `Logging`            | Request/response logging with custom recorder           |
| `RateLimit`          | IP-based rate limiting with pluggable storage           |
| `Quota`              | API usage quota per user/key (daily, monthly, yearly)   |
| `Csrf`               | CSRF token validation for forms and AJAX                |
| `TrailingSlash`      | Normalize trailing slashes in URLs                      |
| `ContentNegotiation` | Detect preferred response format from Accept header     |
| `ContentLength`      | Add Content-Length header to responses                  |
| `MethodOverride`     | Allow forms to simulate PUT/PATCH/DELETE                |
| `SecurityHeaders`    | Add security headers (XSS, clickjacking, MIME sniffing) |
| `MaintenanceMode`    | Return 503 when app is under maintenance                |
| `IpFilter`           | Whitelist or blacklist IPs                              |

---

## Auth

The Auth middleware protects routes by requiring users to be authenticated (
logged in). It can also check if the user has the right **role** or *
*permissions** to access a resource.

You provide an **authenticator function** that checks the request and returns
user data (if valid) or `null` (if not). The middleware handles the rest —
returning 401 for unauthenticated requests and 403 for unauthorized ones.

```php
use Simsoft\Slim\Middlewares\Auth;

// The authenticator receives the request and must return:
// - An array with user data (authentication successful)
// - null (authentication failed → 401 Unauthorized)
$auth = new Auth(function ($request) {
    $token = $request->getHeaderLine('Authorization');
    if ($token === '') {
        return null; // No token → 401
    }
    // Return user data with optional 'roles' and 'permissions' arrays
    return ['id' => 1, 'name' => 'John', 'roles' => ['admin'], 'permissions' => ['users.read']];
});

$app->add($auth);
```

### With Role Requirements (OR logic — a user needs at least one)

Roles use **OR logic**: the user passes if they have *any one* of the listed
roles.

```php
// User with a role "admin" OR "moderator" can access
$auth = new Auth($authenticator, roles: ['admin', 'moderator']);
```

### With Permission Requirements (AND logic — user needs all)

Permissions use **AND logic**: the user must have *every* listed permission.

```php
// User must have BOTH "users.read" AND "users.write" permissions
$auth = new Auth($authenticator, permissions: ['users.read', 'users.write']);
```

### Route-Specific Authorization

Use `withRoles()` and `withPermissions()` to create route-specific variants
without modifying the original middleware. These return new instances (
immutable):

```php
$auth = new Auth($authenticator);

Route::make()
    ->withMiddleware(function(App $app) use ($auth) {
        $app->add($auth); // Global: authentication only
    })
    ->withRouting(function(App $app) use ($auth) {
        $app->get('/login', [AuthController::class, 'loginForm']);

        $app->group('/admin', function($group) {
            $group->get('/users', [AdminController::class, 'users']);
            $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);
        })->add($auth->withRoles('admin'));

        $app->post('/posts', [PostController::class, 'store'])
            ->add($auth->withPermissions('posts.create'));
    })
    ->run();
```

### Accessing the Authenticated User

After authentication succeeds, the user data is stored on the request as an
attribute. Access it in your route handlers or controllers:

```php
$user = request()->getAttribute('user');
response(['name' => $user['name']]);

// Custom attribute name
$auth = new Auth($authenticator, attribute: 'currentUser');
// Access via: request()->getAttribute('currentUser')
```

### Authenticator Examples

**Bearer Token (API):**

```php
$tokenService = $container->get('tokenService');

$auth = new Auth(function ($request) use ($tokenService) {
    $header = $request->getHeaderLine('Authorization');
    if (!str_starts_with($header, 'Bearer ')) {
        return null;
    }
    return $tokenService->validate(substr($header, 7));
});
```

**Session-based:**

```php
$auth = new Auth(fn($request) => $_SESSION['user'] ?? null);
```

**Basic Auth:**

```php
$userService = $container->get('userService');

$auth = new Auth(function ($request) use ($userService) {
    $header = $request->getHeaderLine('Authorization');
    if (!str_starts_with($header, 'Basic ')) {
        return null;
    }
    $decoded = base64_decode(substr($header, 6));
    [$username, $password] = explode(':', $decoded, 2);
    return $userService->verify($username, $password);
});
```

---

## CORS

CORS (Cross-Origin Resource Sharing) headers are required when your frontend and
backend are on different domains. Without them, browsers block cross-origin
requests. This middleware automatically adds the necessary
`Access-Control-Allow-*` headers.

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

---

## CacheOff

Prevents browsers from caching responses. Useful for APIs and dynamic pages
where you always want fresh data. Adds `Cache-Control`, `Pragma`, and `Expires`
headers that tell browsers not to store the response.

```php
use Simsoft\Slim\Middlewares\CacheOff;

$app->add(new CacheOff());
```

---

## Benchmark

Adds response headers showing how long the request took and how much memory was
used. Useful for monitoring performance without external tools.

```php
use Simsoft\Slim\Middlewares\Benchmark;

// Default: adds X-Response-Time and X-Memory-Peak headers
$app->add(new Benchmark());

// Without memory header
$app->add(new Benchmark(includeMemory: false));

// Custom header names
$app->add(new Benchmark(
    timeHeader: 'X-Execution-Time',
    memoryHeader: 'X-Memory-Usage',
));
```

Response headers:

- `X-Response-Time: 12.34ms`
- `X-Memory-Peak: 2.50MB`

---

## Logging

Records details about every request and response. You provide a **recorder
function** that decides what to do with the data — write to a file, insert into
a database, send to a logging service, etc.

The middleware collects everything into a `LogEntry` DTO (data transfer object)
with typed properties, then passes it to your recorder.

```php
use Simsoft\Slim\Middlewares\Logging;
use Simsoft\Slim\Middlewares\LogEntry;

$app->add(new Logging(function(LogEntry $entry) {
    file_put_contents('app.log', json_encode($entry->toArray()) . "\n", FILE_APPEND);
}));
```

### LogEntry DTO

**Request identification:**

| Property             | Type      | Description                                            |
|----------------------|-----------|--------------------------------------------------------|
| `$entry->timestamp`  | `string`  | `2025-01-15 10:30:00`                                  |
| `$entry->requestId`  | `string`  | Correlation ID (from `X-Request-ID` or auto-generated) |
| `$entry->pid`        | `int`     | PHP process ID                                         |
| `$entry->serverName` | `?string` | Server hostname (load-balanced setups)                 |

**Request details:**

| Property                       | Type      | Description                     |
|--------------------------------|-----------|---------------------------------|
| `$entry->method`               | `string`  | `POST`                          |
| `$entry->uri`                  | `string`  | Full URI with query string      |
| `$entry->scheme`               | `string`  | `http` or `https`               |
| `$entry->host`                 | `string`  | Domain name                     |
| `$entry->port`                 | `?int`    | Port (null if standard 80/443)  |
| `$entry->path`                 | `string`  | URI path without query string   |
| `$entry->ip`                   | `string`  | Client IP address               |
| `$entry->isXhr`                | `bool`    | AJAX request detection          |
| `$entry->protocolVersion`      | `string`  | `1.1`, `2`                      |
| `$entry->requestHeaders`       | `array`   | All request headers (flattened) |
| `$entry->userAgent`            | `?string` | User-Agent header               |
| `$entry->referer`              | `?string` | Referer header                  |
| `$entry->requestContentLength` | `?int`    | Incoming payload size in bytes  |

**Route & params:**

| Property                 | Type                  | Description                              |
|--------------------------|-----------------------|------------------------------------------|
| `$entry->routeName`      | `?string`             | Matched route name (e.g., `users.show`)  |
| `$entry->routeArguments` | `array`               | Resolved route params (`['id' => '42']`) |
| `$entry->queryParams`    | `array`               | Query string parameters                  |
| `$entry->bodyParams`     | `array\|object\|null` | Parsed request body                      |

**Auth & middleware data:**

| Property             | Type     | Description                               |
|----------------------|----------|-------------------------------------------|
| `$entry->user`       | `?array` | Authenticated user (from Auth middleware) |
| `$entry->attributes` | `array`  | All request attributes set by middleware  |

**Response:**

| Property                      | Type      | Description                      |
|-------------------------------|-----------|----------------------------------|
| `$entry->status`              | `int`     | HTTP status code                 |
| `$entry->responseContentType` | `?string` | Response Content-Type            |
| `$entry->responseHeaders`     | `array`   | All response headers (flattened) |
| `$entry->responseSize`        | `int`     | Response body size in bytes      |
| `$entry->responseBody`        | `?string` | Response body (truncated)        |

**Performance:**

| Property              | Type    | Description                    |
|-----------------------|---------|--------------------------------|
| `$entry->durationMs`  | `float` | Execution time in milliseconds |
| `$entry->memoryUsage` | `int`   | Peak memory usage in bytes     |

`$entry->toArray()` converts all fields to an associative array.

### Configuration

```php
$app->add(new Logging(
    recorder: $myRecorder,
    userAttribute: 'currentUser',   // Attribute name set by Auth middleware
    logResponseBody: true,          // Include response body (default: true)
    maxBodyLength: 4096,            // Max body chars to capture (default: 4096, 0 = unlimited)
    logRequestHeaders: true,        // Include request headers (default: true)
    logResponseHeaders: true,       // Include response headers (default: true)
));
```

### Recorder Examples

**PSR-3 Logger:**

```php
$app->add(new Logging(function(LogEntry $entry) use ($logger) {
    $logger->info("{$entry->method} {$entry->uri} [{$entry->status}] {$entry->durationMs}ms", [
        'request_id' => $entry->requestId,
        'route' => $entry->routeName,
        'ip' => $entry->ip,
        'user_id' => $entry->user['id'] ?? null,
    ]);
}));
```

**Database:**

```php
$app->add(new Logging(function(LogEntry $entry) use ($db) {
    $db->insert('request_logs', [
        'method' => $entry->method,
        'uri' => $entry->uri,
        'ip' => $entry->ip,
        'user_id' => $entry->user['id'] ?? null,
        'status' => $entry->status,
        'duration_ms' => $entry->durationMs,
        'created_at' => $entry->timestamp,
    ]);
}));
```

**JSON file:**

```php
$app->add(new Logging(function(LogEntry $entry) {
    $line = json_encode($entry->toArray(), JSON_UNESCAPED_SLASHES) . "\n";
    file_put_contents(__DIR__ . '/logs/' . date('Y-m-d') . '.log', $line, FILE_APPEND);
}));
```

---

## RateLimit

Limits how many requests a single client (identified by IP address) can make
within a time window. Protects your API from abuse and brute-force attacks. When
the limit is exceeded, it returns HTTP 429 (Too Many Requests).

```php
use Simsoft\Slim\Middlewares\RateLimit;

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

### Storage Backends

The default file-based storage works for single-server apps. For apps running on
multiple servers (behind a load balancer), use Redis so all servers share the
same rate limit counters.

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

Response headers:

- `X-RateLimit-Limit` — max requests allowed
- `X-RateLimit-Remaining` — requests remaining in a window
- `X-RateLimit-Reset` — Unix timestamp when a window resets

Throws HTTP 429 when the limit is exceeded.

---

## Quota

Enforces API usage quotas per identity (user, API key, subscription, etc.).
Unlike RateLimit (which prevents bursts and resets every few seconds/minutes),
Quota tracks **total usage** over longer periods — daily, monthly, or yearly.

```php
use Simsoft\Slim\Middlewares\Quota;

$app->add(new Quota(
    // WHO: resolve the identity from the request
    resolver: fn($request) => (string)$request->getAttribute('user')['id'],

    // HOW MUCH: return the max allowed for this identity
    limit: fn($request, $key) => 1000,

    // WHEN: reset period
    period: 'monthly',
));
```

### Dynamic Limits (Subscription Tiers)

```php
$app->add(new Quota(
    resolver: fn($request) => (string)$request->getAttribute('user')['id'],
    limit: function($request, $key) {
        $user = $request->getAttribute('user');
        return match($user['plan'] ?? 'free') {
            'free' => 1000,
            'pro' => 50000,
            'enterprise' => 500000,
            default => 1000,
        };
    },
    period: 'monthly',
));
```

### By API Key

```php
$app->add(new Quota(
    resolver: fn($request) => $request->getHeaderLine('X-API-Key'),
    limit: fn($request, $key) => $this->getKeyLimit($key),
    period: 'daily',
));
```

### Periods

| Value       | Duration                        |
|-------------|---------------------------------|
| `'hourly'`  | 1 hour                          |
| `'daily'`   | 24 hours                        |
| `'weekly'`  | 7 days                          |
| `'monthly'` | 30 days                         |
| `'yearly'`  | 365 days                        |
| `'3600'`    | Custom seconds (pass as string) |

### Storage

Same as RateLimit — defaults to file storage, use Redis for production:

```php
use Simsoft\Slim\Middlewares\RateLimitRedisStorage;

$app->add(new Quota(
    resolver: fn($request) => (string)$request->getAttribute('user')['id'],
    limit: fn($request, $key) => 1000,
    period: 'monthly',
    storage: new RateLimitRedisStorage($redis, prefix: 'quota:'),
));
```

### Response Headers

| Header              | Description                      |
|---------------------|----------------------------------|
| `X-Quota-Limit`     | Maximum allowed requests         |
| `X-Quota-Remaining` | Requests remaining in period     |
| `X-Quota-Reset`     | Unix timestamp when quota resets |

Throws HTTP 429 when the quota is exceeded.

### RateLimit vs Quota

|               | RateLimit                                           | Quota                     |
|---------------|-----------------------------------------------------|---------------------------|
| Purpose       | Prevent burst abuse                                 | Enforce usage tiers       |
| Resets        | Every X seconds (auto)                              | Daily/monthly/yearly      |
| Identified by | IP address                                          | User/API key (you decide) |
| Typical limit | 60-100/minute                                       | 1000-500000/month         |
| Use together? | ✅ Yes — RateLimit for bursts, Quota for total usage |

---

## CSRF

CSRF (Cross-Site Request Forgery) protection prevents attackers from tricking
users into submitting forms on your site from a malicious page. It works by
generating a unique token for each form — when the form is submitted, the token
must match or the request is rejected (HTTP 403).

This middleware uses a **token pool** so multiple forms/tabs can be opened
simultaneously without conflicts.

```php
use Simsoft\Slim\Middlewares\Csrf;

$csrf = new Csrf();
$app->add($csrf);
```

### Configuration

```php
$csrf = new Csrf(
    fieldName: '_csrf_token',     // Form field name
    headerName: 'X-CSRF-Token',  // Header name for AJAX
    maxTokens: 20,               // Max tokens in pool
    tokenLifetime: 3600,         // Token validity in seconds
);
```

### Form Usage

```php
echo $csrf->getTokenField();
// <input type="hidden" name="_csrf_token" value="abc123...">

// Or manually
echo '<input type="hidden" name="' . $csrf->getFieldName() . '" value="' . $csrf->generateToken() . '">';
```

### AJAX Usage

```javascript
fetch('/api/resource', {
    method: 'POST',
    headers: {'X-CSRF-Token': document.querySelector('[name=_csrf_token]').value}
});
```

### Token Behavior

- Single-use — consumed on successful validation
- Multiple tokens coexist (supports multiple open tabs/forms)
- Pool capped at `maxTokens` to prevent session bloat
- Expired tokens auto-pruned
- Timing-safe validation via `hash_equals()`

### Making CSRF Available in Controllers

To use the CSRF token in your templates/views, store the `Csrf` instance in the
container so controllers can access it:

```php
$csrf = new Csrf();

$containerBuilder->addDefinitions(['csrf' => fn() => $csrf]);

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

---

## TrailingSlash

Slim treats `/users` and `/users/` as different routes. This middleware
normalizes URLs by either removing or adding trailing slashes, so you don't need
to register both patterns.

```php
use Simsoft\Slim\Middlewares\TrailingSlash;

// Remove trailing slashes (recommended): /users/ → 301 redirect to /users
$app->add(new TrailingSlash());

// Add trailing slashes: /users → 301 redirect to /users/
$app->add(new TrailingSlash(add: true));

// Silent rewrite (no redirect, just fix internally):
$app->add(new TrailingSlash(redirect: false));
```

| Parameter  | Type   | Default | Description                                     |
|------------|--------|---------|-------------------------------------------------|
| `add`      | `bool` | `false` | `false` = remove slash, `true` = add slash      |
| `redirect` | `bool` | `true`  | `true` = 301 redirect, `false` = silent rewrite |

> **Tip:** Place this middleware before the routing middleware so the URL is
> normalized before route matching.

---

## ContentNegotiation

Parses the `Accept` header from the request and sets a request attribute (
`format`) indicating the client's preferred response format. Your handlers can
then use this to decide whether to respond with JSON, XML, HTML, etc.

```php
use Simsoft\Slim\Middlewares\ContentNegotiation;

$app->add(new ContentNegotiation());
```

### Usage in Handlers

```php
$app->get('/users', function() {
    $format = request()->getAttribute('format'); // 'json', 'html', 'xml', or 'text'

    $users = ['users' => [['id' => 1, 'name' => 'John']]];

    match($format) {
        'html' => response('<h1>Users</h1><p>John</p>'),
        'xml' => response()->xml('<users><user>John</user></users>'),
        default => response($users), // JSON
    };
});
```

### Default Format Mapping

| Accept Header      | Resolved Format  |
|--------------------|------------------|
| `application/json` | `json`           |
| `text/html`        | `html`           |
| `application/xml`  | `xml`            |
| `text/xml`         | `xml`            |
| `text/plain`       | `text`           |
| `*/*` or empty     | `json` (default) |

### Custom Configuration

```php
$app->add(new ContentNegotiation(
    formats: [
        'application/json' => 'json',
        'text/html' => 'html',
        'text/csv' => 'csv',
    ],
    defaultFormat: 'html',       // Fallback when no match
    attribute: 'responseFormat', // Custom attribute name
));

// Access via: request()->getAttribute('responseFormat')
```

### Quality Parsing

The middleware respects quality values in the Accept header:

```
Accept: text/html;q=0.9, application/json;q=1.0
```

This resolves to `json` because it has higher quality (1.0 > 0.9).


---

## ContentLength

Adds a `Content-Length` header to responses based on the body size. Some HTTP
clients and proxies require this header for proper handling of the response.

```php
use Simsoft\Slim\Middlewares\ContentLength;

$app->add(new ContentLength());
```

The header is only added when:

- The body size is known (not null)
- The response doesn't already have a `Content-Length` header

> **Tip:** Add this as the outermost middleware so it calculates the final body
> size after all other middleware has modified the response.


---

## MethodOverride

HTML forms only support GET and POST. This middleware lets forms simulate PUT,
PATCH, and DELETE requests by checking for a `_METHOD` field in the form body or
an `X-Http-Method-Override` header.

```php
use Simsoft\Slim\Middlewares\MethodOverride;

$app->add(new MethodOverride());
```

### Form Usage

Add a hidden `_METHOD` field to your form:

```html
<form method="POST" action="/users/42">
    <input type="hidden" name="_METHOD" value="DELETE">
    <button type="submit">Delete User</button>
</form>
```

This POST request will be routed as a DELETE request, matching:

```php
$app->delete('/users/{id}', [UserController::class, 'destroy']);
```

### AJAX Usage

Send the override via header:

```javascript
fetch('/users/42', {
    method: 'POST',
    headers: { 'X-Http-Method-Override': 'PUT' },
    body: JSON.stringify({ name: 'Updated' })
});
```

### Configuration

```php
$app->add(new MethodOverride(
    fieldName: '_METHOD',                  // Form field name (default)
    headerName: 'X-Http-Method-Override',  // Header name (default)
));
```

### Behavior

- Only applies to POST requests (GET requests are never overridden)
- Only allows override to PUT, PATCH, or DELETE
- Header takes priority over the body field
- Invalid or unsupported methods are ignored (request stays as POST)

---

## SecurityHeaders

Adds recommended security headers to protect against common web
vulnerabilities (clickjacking, MIME sniffing, XSS).

```php
use Simsoft\Slim\Middlewares\SecurityHeaders;

// Default headers (X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy)
$app->add(new SecurityHeaders());
```

### With HSTS (HTTPS only)

```php
// Enable Strict-Transport-Security (only if your site is fully HTTPS)
$app->add((new SecurityHeaders())->withHsts());

// Custom max-age (default: 1 year)
$app->add((new SecurityHeaders())->withHsts(maxAge: 86400));
```

### With Content Security Policy

```php
$app->add(
    (new SecurityHeaders())
        ->withHsts()
        ->withCsp("default-src 'self'; script-src 'self' https://cdn.example.com")
);
```

### Custom Headers

Override the defaults entirely:

```php
$app->add(new SecurityHeaders([
    'X-Frame-Options' => 'DENY',
    'X-Content-Type-Options' => 'nosniff',
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
]));
```

### Default Headers

| Header                   | Value                                      | Protection                  |
|--------------------------|--------------------------------------------|-----------------------------|
| `X-Frame-Options`        | `SAMEORIGIN`                               | Prevents clickjacking       |
| `X-Content-Type-Options` | `nosniff`                                  | Prevents MIME type sniffing |
| `X-XSS-Protection`       | `1; mode=block`                            | Legacy XSS filter           |
| `Referrer-Policy`        | `strict-origin-when-cross-origin`          | Controls referer leakage    |
| `Permissions-Policy`     | `geolocation=(), microphone=(), camera=()` | Disables browser APIs       |

---

## MaintenanceMode

Returns a 503 Service Unavailable response when your app is under maintenance.
Optionally allows specific IPs (developers) to bypass.

```php
use Simsoft\Slim\Middlewares\MaintenanceMode;

// Enable maintenance mode
$app->add(new MaintenanceMode(enabled: true));

// With a custom message
$app->add(new MaintenanceMode(
    enabled: true,
    message: 'We are upgrading. Back in 30 minutes.',
));

// Allow developers to bypass
$app->add(new MaintenanceMode(
    enabled: true,
    allowedIps: ['192.168.1.100', '10.0.0.1'],
));

// Set Retry-After hint (seconds)
$app->add(new MaintenanceMode(
    enabled: true,
    retryAfter: 1800, // 30 minutes
));
```

### Toggle via Environment Variable

```php
$app->add(new MaintenanceMode(
    enabled: (bool)getenv('MAINTENANCE_MODE'),
    allowedIps: explode(',', getenv('MAINTENANCE_ALLOWED_IPS') ?: ''),
));
```

---

## IpFilter

Restricts access based on the client IP address. Works in two modes:

- **Whitelist** (default): Only listed IPs can access. Everyone else gets
  blocked.
- **Blacklist**: Listed IPs are blocked. Everyone else can access.

```php
use Simsoft\Slim\Middlewares\IpFilter;

// Whitelist: only these IPs can access
$app->add(new IpFilter(ips: ['192.168.1.100', '10.0.0.1']));

// Blacklist: block these IPs
$app->add(new IpFilter(
    ips: ['1.2.3.4', '5.6.7.8'],
    whitelist: false,
));
```

### Route-Specific (Admin Panel)

```php
$app->group('/admin', function($group) {
    $group->get('/dashboard', [AdminController::class, 'index']);
})->add(new IpFilter(ips: ['192.168.1.0/24', '10.0.0.1']));
```

### Custom Error

```php
$app->add(new IpFilter(
    ips: ['192.168.1.100'],
    statusCode: 404,                    // Pretend the page doesn't exist
    message: 'Page not found.',
));
```
