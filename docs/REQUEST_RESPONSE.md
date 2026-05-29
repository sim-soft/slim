# Request & Response

## Request

Access via the `request()` helper function.

```php
use function Simsoft\Slim\request;
```

### Request Data

```php
request()->getQueryParams();    // $_GET
request()->getParsedBody();     // $_POST
request()->getUploadedFiles();  // UploadedFileInterface[]
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
