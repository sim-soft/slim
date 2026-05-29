# Defining Routes

Routes map URLs to code. When a user visits a URL, the matching route's handler
runs and sends a response.

## Table of Contents

- [Anonymous Functions](#anonymous-functions)
- [HTTP Methods](#http-methods)
- [Route Redirect Helper](#route-redirect-helper)
- [Route Parameters](#route-parameters)
- [Named Routes](#named-routes)
- [Route Groups](#route-groups)
- [Group with Middleware](#group-with-middleware)
- [Container Access in Routes](#container-access-in-routes)

## Anonymous Functions

The simplest way to define routes is with anonymous functions (closures). Each
route needs an HTTP method, a URL pattern, and a handler:

```php
use Simsoft\Slim\Route;
use Slim\App;
use function Simsoft\Slim\response;

Route::make()
    ->withRouting(function(App $app) {

        // When someone visits the homepage (GET /), respond with "Hello World!"
        $app->get('/', function() {
            response('Hello World!');
        });

        // When someone visits /about, respond with "About page"
        $app->get('/about', function() {
            response('About page');
        });

    })
    ->run();
```

## HTTP Methods

Each HTTP method has a corresponding function. Use the right method for the
action:

- `GET` — read/fetch data
- `POST` — create new data
- `PUT` — replace existing data entirely
- `PATCH` — update part of existing data
- `DELETE` — remove data

```php
$app->get('/users', function() { response('list'); });          // Read all users
$app->post('/users', function() { response('created', 201); }); // Create a user
$app->put('/users/{id}', function(string $id) { response("updated $id"); });    // Replace user
$app->patch('/users/{id}', function(string $id) { response("patched $id"); });  // Partial update
$app->delete('/users/{id}', function(string $id) { response("deleted $id"); }); // Delete user
$app->options('/users', function() { response(''); });          // CORS preflight

// Handle multiple methods on the same URL
$app->map(['GET', 'POST'], '/form', function() {
    if (request()->isMethod('post')) {
        response('submitted');
    }
    response('form page');
});

// Match ANY HTTP method
$app->any('/webhook', function() {
    response(['received' => true]);
});
```

### Route Redirect Helper

For simple redirects that don't need a handler, use the `redirect()` shortcut:

```php
// Redirect /old-page to /new-page with 301 (permanent)
$app->redirect('/old-page', '/new-page', 301);

// Default status is 302 (temporary)
$app->redirect('/temp', '/destination');
```

## Route Parameters

Wrap parameter names in `{curly braces}` in the URL pattern. They're passed as
function arguments in the same order:

```php
// Single parameter: /users/42 → $id = "42"
$app->get('/users/{id}', function(string $id) {
    response("User ID: $id");
});

// Multiple parameters: /posts/2025/my-article → $year = "2025", $slug = "my-article"
$app->get('/posts/{year}/{slug}', function(string $year, string $slug) {
    response("Post: $year/$slug");
});
```

> **Note:** Parameters are always strings. Cast them if you need integers:
`$id = (int)$id`.

### Placeholder Regex Constraints

Add a regex pattern after a colon to restrict what a parameter matches. If the
URL doesn't match the pattern, Slim returns 404 automatically:

```php
// Only matches numeric IDs: /users/42 ✅, /users/abc ❌ (404)
$app->get('/users/{id:[0-9]+}', function(string $id) {
    response("User $id");
});

// Only lowercase slugs with hyphens: /posts/my-article ✅, /posts/My Article ❌
$app->get('/posts/{slug:[a-z0-9\-]+}', function(string $slug) {
    response("Post: $slug");
});

// Year must be 4 digits
$app->get('/archive/{year:[0-9]{4}}', function(string $year) {
    response("Archive for $year");
});
```

### Optional Route Segments

Wrap optional parts in `[square brackets]`. The route matches with or without
that segment:

```php
// Matches both /users and /users/42
$app->get('/users[/{id}]', function(?string $id = null) {
    if ($id === null) {
        response('All users');
        return;
    }
    response("User $id");
});

// Optional format: /export or /export/csv
$app->get('/export[/{format}]', function(string $format = 'json') {
    response("Exporting as $format");
});

// Multiple optional segments: /archive, /archive/2025, /archive/2025/06
$app->get('/archive[/{year}[/{month}]]', function(string $year = '', string $month = '') {
    response("Archive: year=$year month=$month");
});
```

> **Note:** Optional parameters must have default values in the function
> signature.

### Unlimited Optional Parameters

Capture any number of path segments using a catch-all pattern:

```php
// Matches: /files, /files/docs, /files/docs/2025/report.pdf
$app->get('/files[/{path:.*}]', function(string $path = '') {
    $segments = $path !== '' ? explode('/', $path) : [];
    response(['segments' => $segments]);
});
// /files/docs/2025/report.pdf → $segments = ['docs', '2025', 'report.pdf']
```

## Named Routes

Give routes a name so you can generate their URLs without hardcoding paths. This
makes your app easier to maintain — if a URL changes, you only update it in one
place:

```php
$app->get('/users/{id}', function(string $id) {
    response("User $id");
})->setName('users.show');  // Name this route "users.show"

$app->get('/posts', function() {
    response('Posts');
})->setName('posts.index');
```

Then generate URLs by name anywhere in your app:

```php
use Simsoft\Slim\URL;

URL::for('users.show', ['id' => '42']);   // Returns: /users/42
URL::for('posts.index');                   // Returns: /posts
```

## Route Groups

Groups let you share a URL prefix across related routes. This avoids repeating
the same prefix:

```php
// Without groups (repetitive):
$app->get('/api/users', function() { /* ... */ });
$app->get('/api/posts', function() { /* ... */ });

// With groups (cleaner):
$app->group('/api', function($group) {

    $group->get('/users', function() {
        response(['users' => []]);
    })->setName('api.users');

    $group->get('/posts', function() {
        response(['posts' => []]);
    })->setName('api.posts');

});
```

Groups can be nested for versioned APIs:

```php
$app->group('/api', function($group) {

    $group->group('/v1', function($v1) {
        $v1->get('/users', function() { response('v1 users'); });
    });

    $group->group('/v2', function($v2) {
        $v2->get('/users', function() { response('v2 users'); });
    });

});
// Responds to: GET /api/v1/users and GET /api/v2/users
```

### Group with Placeholders

The group pattern can contain placeholders. They're available to all routes
inside:

```php
// All routes inside share the {id} parameter
$app->group('/users/{id:[0-9]+}', function($group) {

    // GET /users/42 → show user profile
    $group->get('', function(string $id) {
        response("User $id profile");
    });

    // GET /users/42/posts → show user's posts
    $group->get('/posts', function(string $id) {
        response("Posts by user $id");
    });

    // POST /users/42/reset-password
    $group->post('/reset-password', function(string $id) {
        response("Password reset for user $id");
    });
});
```

### Empty Group Pattern

Use an empty group to logically group routes that share middleware but not a URL
prefix:

```php
// These routes don't share a prefix, but all need billing middleware
$app->group('', function($group) {
    $group->get('/billing', function() { response('Billing page'); });
    $group->get('/invoices', function() { response('Invoices'); });
    $group->get('/payments', function() { response('Payments'); });
})->add(new BillingAuthMiddleware());
```

## Group with Middleware

Attach middleware to a group to protect all routes inside it. This is how you
add authentication or rate limiting to specific sections:

```php
use Simsoft\Slim\Middlewares\Auth;
use Simsoft\Slim\Middlewares\RateLimit;

// Create an Auth middleware that checks if the user is logged in
$auth = new Auth(fn($request) => $_SESSION['user'] ?? null);

// Only logged-in admins can access /admin/* routes
$app->group('/admin', function($group) {
    $group->get('/dashboard', function() { response('Dashboard'); });
    $group->get('/users', function() { response('Manage users'); });
})->add($auth->withRoles('admin'));

// Rate limit the /api/* routes to 30 requests per minute
$app->group('/api', function($group) {
    $group->get('/data', function() { response(['data' => []]); });
})->add(new RateLimit(maxRequests: 30, windowSeconds: 60));
```

## Container Access in Routes

If you passed a container to `Route::make($container)`, you can access your
services inside route handlers using `use ($app)`:

```php
Route::make($container)
    ->withRouting(function(App $app) {

        $app->get('/users', function() use ($app) {
            // Get the database service from the container
            $db = $app->getContainer()->get('db');
            $users = $db->query('SELECT * FROM users');
            response($users); // Arrays are automatically returned as JSON
        });

        $app->get('/health', function() use ($app) {
            $logger = $app->getContainer()->get('logger');
            $logger->info('Health check');
            response(['status' => 'ok']);
        });

    })
    ->run();
```

> **Tip:** For cleaner code, use [Controllers](controllers.md) with the
`ContainerAwareTrait` instead of accessing the container directly in closures.
