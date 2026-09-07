# API Resources

## Table of Contents

- [Basic Usage](#basic-usage)
- [Collections](#collections)
- [Declarative Mapping](#declarative-mapping)
- [Envelope Configuration](#envelope-configuration)
- [Metadata & Links](#metadata-amp-links)
- [Conditional Fields](#conditional-fields)
- [Field Filtering](#field-filtering)
- [Nested Resources](#nested-resources)
- [Lifecycle Hooks](#lifecycle-hooks)
- [Custom Headers](#custom-headers)
- [Serializers](#serializers)
- [Full Controller Example](#full-controller-example)

## Basic Usage

```php
<?php
namespace App\Resources;

use Simsoft\Resource\Resource;

class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
        ];
    }
}
```

```php
use App\Resources\UserResource;
use function Simsoft\Slim\response;

// Single resource
response()->resource(UserResource::make($user));
// {"data": {"id": 1, "name": "John", "email": "john@example.com"}}

// Null-safe
response()->resource(UserResource::make(null));
// {"data": null}
```

## Collections

```php
response()->resource(UserResource::collection($users));
// {"data": [{"id": 1, ...}, {"id": 2, ...}]}

// With pagination
response()->resource(
    UserResource::collection($users)
        ->paginate(total: 100, perPage: 10, currentPage: 1, lastPage: 10)
);
// {"data": [...], "meta": {"total": 100, "per_page": 10, "current_page": 1, "last_page": 10}}
```

## Declarative Mapping

For simple transformations, use `$map` instead of overriding `toArray()`:

```php
class UserResource extends Resource
{
    protected array $map = [
        'id' => 'id',
        'full_name' => 'profile.name',    // Dot-notation for nested access
        'city' => 'address.city',
    ];
}
```

## Envelope Configuration

```php
class UserResource extends Resource
{
    protected bool $wrap = true;        // Wrap in {"data": ...} (default: true)
    protected string $wrapKey = 'data'; // Envelope key name
    protected ?string $type = 'user';   // Optional type identifier
}
// {"type": "user", "data": {...}}
```

Disable wrapping:

```php
class UserResource extends Resource
{
    protected bool $wrap = false;
}
// {"id": 1, "name": "John"} (no envelope)
```

## Metadata & Links

```php
response()->resource(
    UserResource::make($user)
        ->withMeta(['cached' => true, 'generated_at' => time()])
        ->withLinks(['self' => '/users/1', 'posts' => '/users/1/posts'])
);
// {"data": {...}, "meta": {"cached": true, ...}, "links": {"self": "/users/1", ...}}
```

## Conditional Fields

```php
class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->when($this->context['include_email'] ?? false, $this->resource->email),
            'role' => $this->whenNotNull($this->resource->role),
            $this->mergeWhen($this->context['is_admin'] ?? false, [
                'secret_key' => $this->resource->secret_key,
                'internal_id' => $this->resource->internal_id,
            ]),
        ];
    }
}

// With context
response()->resource(
    UserResource::make($user)->withContext(['include_email' => true, 'is_admin' => false])
);
```

| Helper                   | Behavior                                        |
|--------------------------|-------------------------------------------------|
| `when(bool, value)`      | Include field only when condition is true       |
| `whenNotNull(value)`     | Include field only when value is not null       |
| `mergeWhen(bool, array)` | Merge fields into parent when condition is true |

## Field Filtering

```php
// Include only specific fields
response()->resource(UserResource::make($user)->only('id', 'name'));

// Exclude specific fields
response()->resource(UserResource::make($user)->except('email', 'secret_key'));

// Works on collections too
response()->resource(UserResource::collection($users)->only('id', 'name'));
```

## Nested Resources

```php
class PostResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'author' => UserResource::make($this->resource->author),
        ];
    }
}
// Nested resources are serialized without their own envelope
```

## Lifecycle Hooks

```php
class UserResource extends Resource
{
    protected function beforeSerialize(): void
    {
        // Runs before toArray() — lazy-load relationships, compute values
    }

    protected function afterSerialize(array $data): array
    {
        // Runs after toArray() + filtering — post-process the output
        $data['name'] = strtoupper($data['name']);
        return $data;
    }
}
```

## Custom Headers

```php
response()->resource(
    UserResource::make($user)
        ->withHeaders(['X-Request-Id' => 'abc-123', 'X-Cache' => 'HIT'])
);
```

## Serializers

The default is JSON. XML serializer included:

```php
use Simsoft\Resource\Serializers\XmlSerializer;

response()->resource(UserResource::make($user), serializer: new XmlSerializer());
// Content-Type: application/xml
```

### Keys that XML cannot use as tag names

XML is stricter than JSON about names. A tag name has to start with a letter or
an underscore, and it cannot contain spaces. So `first_name` is fine, but
`first name`, `2fa` and `user-id!` are not.

Your array keys often come from database columns or client input, so you cannot
always guarantee they are legal. Rather than emit broken XML, the serializer
swaps any unusable key for an `<item>` element and keeps the original key in a
`name` attribute:

```php
// Resource data
['first_name' => 'Ada', 'home address' => 'Somewhere', '2fa' => true]
```

```xml
<?xml version="1.0" encoding="UTF-8"?>
<response>
  <data>
    <first_name>Ada</first_name>
    <item name="home address">Somewhere</item>
    <item name="2fa">true</item>
  </data>
</response>
```

Valid keys are always used as-is, so nothing changes for normal data. The output
is guaranteed to parse — you will never get an XML parse error just because of a
key name.

> **Tip:** if you want clean tag names in your XML, rename the keys inside your
> resource's `toArray()` — for example map `'home address'` to `'home_address'`.

Values are escaped for you, so `&`, `<`, `>` and quotes in your data are safe.
`null` becomes an empty element, and `true`/`false` become the text `true` and
`false`.

Custom serializer — implement `ResourceSerializerInterface`:

```php
use Simsoft\Resource\Serializers\ResourceSerializerInterface;

class CsvSerializer implements ResourceSerializerInterface
{
    public function serialize(Resource|ResourceCollection $resource): string
    {
        // Your CSV logic
    }

    public function contentType(): string
    {
        return 'text/csv';
    }
}
```

## Full Controller Example

```php
<?php
namespace App\Controllers;

use App\Resources\UserResource;
use function Simsoft\Slim\request;
use function Simsoft\Slim\response;

class UserController
{
    public function index()
    {
        $users = User::paginate(page: request()->getQueryParams()['page'] ?? 1);

        response()->resource(
            UserResource::collection($users->items)
                ->paginate($users->total, $users->perPage, $users->currentPage, $users->lastPage)
                ->withLinks(['self' => request()->urlFor('users.index')])
        );
    }

    public function show(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            request()->notFound('User not found');
        }

        response()->resource(
            UserResource::make($user)
                ->withContext(['include_email' => true])
                ->withLinks(['self' => request()->urlFor('users.show', ['id' => $id])])
        );
    }

    public function store()
    {
        $data = request()->getParsedBody();
        $user = User::create($data);

        response()->resource(UserResource::make($user), 201);
    }
}
```
