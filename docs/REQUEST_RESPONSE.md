# Request & Response

## Table of Contents

- [Request](#request)
    - [Request Data](#request-data)
    - [Sanitizers](#sanitizers)
    - [Request Inspection](#request-inspection)
    - [Request Attributes](#request-attributes)
    - [Route Information](#route-information)
    - [URL Generation](#url-generation)
    - [HTTP Exceptions](#http-exceptions)
- [Response](#response)
    - [Text](#text)
    - [JSON](#json)
    - [XML](#xml)
    - [Headers](#headers)
    - [Redirect](#redirect)
    - [Resource Response](#resource-response)
    - [Return Values in Controllers](#return-values-in-controllers)
- [URL Helper](#url-helper)

## Request

Access via the `request()` helper function.

```php
use function Simsoft\Slim\request;
```

### Request Data

Shorthand methods for accessing query params, body params, and uploaded files:

```php
// Get all params
request()->query();              // All query string params ($_GET)
request()->input();              // All parsed body params (POST, JSON, etc.)
request()->files();              // All uploaded files

// Get a single value
request()->query('page');        // Value of ?page=... (or null if missing)
request()->input('email');       // Value of 'email' from request body
request()->files('avatar');      // Single uploaded file

// Get multiple values
request()->query(['page', 'limit']);    // ['page' => '2', 'limit' => '10']
request()->input(['name', 'email']);    // ['name' => 'John', 'email' => '...']
request()->files(['avatar', 'resume']); // Two specific files

// Default value (returned when key doesn't exist)
request()->query('page', '1');          // Returns '1' if ?page is not set
request()->input('role', 'user');       // Returns 'user' if not in body
```

The original PSR-7 methods still work:

```php
request()->getQueryParams();    // Same as query() with no arguments
request()->getParsedBody();     // Same as input() with no arguments
request()->getUploadedFiles();  // Same as files() with no arguments
```

### Sanitizers

Set a global sanitizer once — it automatically applies to all `query()` and
`input()` calls:

```php
// Set once at app startup (e.g., in index.php or a middleware)
use Simsoft\Slim\Request;

Request::setSanitizer(function(mixed $value, string $key): mixed {
    if (is_string($value)) {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
    return $value;
});

// Now every query() and input() call is automatically sanitized
request()->query('search');           // Already trimmed and escaped
request()->input('name');             // Already trimmed and escaped
request()->input(['name', 'email']); // Both values sanitized
```

The sanitizer receives two arguments: the value and the key name, so you can
apply different logic per field if needed:

```php
Request::setSanitizer(function(mixed $value, string $key): mixed {
    if (!is_string($value)) {
        return $value;
    }

    return match($key) {
        'email' => filter_var(trim($value), FILTER_SANITIZE_EMAIL),
        'page', 'limit', 'id' => (int)$value,
        default => htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8'),
    };
});
```

To disable the sanitizer:

```php
Request::setSanitizer(null);
```

### Request Inspection

```php
request()->getMethod();                   // GET, POST, etc.
request()->isMethod('post');              // Check method (case-insensitive)
request()->isXHR();                       // Detect AJAX request
request()->header('Content-Type');        // Get header value
request()->header('X-Custom', 'default'); // With default fallback
request()->getBearerToken();              // Extract token from "Bearer <token>"
```

### Request Attributes

```php
request()->getAttribute('user');              // Get middleware-set attribute (e.g., Auth user)
request()->getAttribute('key', 'default');    // With default fallback
```

### Route Information

```php
request()->getRouteName();        // Current route name
request()->getRouteIdentifier();  // Route unique identifier
request()->getArgument('id');     // Single route argument
request()->getArguments();        // All route arguments
request()->getBasePath();         // Application base path
```

### URL Generation

```php
request()->urlFor('route_name');
// /url-slug

request()->urlFor('user.show', ['id' => '42']);
// /users/42

request()->urlFor('search', queryParams: ['q' => 'php']);
// /search?q=php

request()->relativeUrlFor('route_name');  // Without a base path
request()->fullUrlFor('route_name');      // With domain
```

### HTTP Exceptions

```php
request()->notFound();                        // Throw 404
request()->notFound('Resource not found');     // 404 with a message
request()->allowedMethods(['POST', 'PUT']);    // Throw 405
```

## Response

Access via the `response()` helper function.

```php
use function Simsoft\Slim\response;
```

### Text

```php
response('Hello World');
response('Unauthorized', 401);
response()->content('Forbidden')->status(403);
```

### JSON

```php
response(['status' => 'ok']);
response()->json(['status' => 'ok']);
```

### XML

```php
response()->xml('<root><item>value</item></root>');
```

### Headers

```php
response()->header('X-Custom', 'value');
response()->withHeaders([
    'Content-Type' => 'text/html',
    'X-App' => 'MyApp',
]);
```

### Redirect

```php
response()->redirect('https://example.com');
response()->redirect('/path', 301);
response()->redirect(request()->urlFor('home'));

// Immediate redirect (exits script)
response()->redirectNow('https://example.com');
response()->redirectNow('https://example.com', 301, new \DateTime('+1 hour'));
```

### Resource Response

Serialize API resources (see [docs/RESOURCE.md](RESOURCE.md) for full
reference):

```php
use Simsoft\Resource\Resource;

class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}

// Single resource
response()->resource(UserResource::make($user));

// Collection with pagination
response()->resource(
    UserResource::collection($users)->paginate(total: 100, perPage: 10, currentPage: 1, lastPage: 10)
);

// With custom status code
response()->resource(UserResource::make($user), 201);
```

### Return Values in Controllers

```php
// Return string → text response
public function show(): string { return 'Hello'; }

// Return array → JSON response
public function list(): array { return [1, 2, 3]; }

// Use response() for full control
public function create() { response(['id' => 1])->status(201); }
```

## URL Helper

Static utility available after `withRouting()`.

```php
use Simsoft\Slim\URL;

URL::for('home');                              // /
URL::for('user.show', ['id' => '42']);         // /users/42
URL::for('search', [], ['q' => 'php']);        // /search?q=php
URL::fullFor('user.show', ['id' => '42']);     // https://example.com/users/42
```
