# API Resource

## Table of Contents

- [Quick Start](#quick-start)
- [Single Resource](#single-resource)
- [Resource Collections](#resource-collections)
- [Conditional Fields](#conditional-fields)
- [Nested Resources](#nested-resources)
- [Additional Metadata](#additional-metadata)
- [Resource Links (HATEOAS)](#resource-links-hateoas)
- [Resource Context](#resource-context)
- [Field Filtering (Sparse Fieldsets)](#field-filtering-sparse-fieldsets)
- [Field Exclusion](#field-exclusion)
- [Declarative Mapping](#declarative-mapping)
- [Type Identifiers](#type-identifiers)
- [Wrapping Key Customization](#wrapping-key-customization)
- [Response Headers](#response-headers)
- [Caching / Memoization](#caching--memoization)
- [Transformation Hooks](#transformation-hooks)
- [Framework Independence](#framework-independence)
- [Format Adapters (Serializers)](#format-adapters-serializers)
- [Advanced Usage](#advanced-usage)
    - [Context with Conditionals](#context-with-conditionals)
    - [Sparse Fieldsets with Nested Resources](#sparse-fieldsets-with-nested-resources)
    - [Collection with Pagination and Links](#collection-with-pagination-and-links)
    - [Mapping with Exclusion](#mapping-with-exclusion)

---

## Quick Start

```php
<?php

declare(strict_types=1);

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

$user = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
response()->resource(UserResource::make($user));
response()->resource(UserResource::collection([$user]));
```

## Single Resource

A Resource transforms a single data item (object or array) into a structured
JSON response. Define a subclass and implement `toArray()` to control which
fields are exposed.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class ProductResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'price' => $this->resource['price'],
        ];
    }
}

$product = ['id' => 42, 'name' => 'Widget', 'price' => 9.99];
$resource = ProductResource::make($product);

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 42,
    "name": "Widget",
    "price": 9.99
  }
}
```

The static `make()` method creates a new resource instance. When `null` is
passed, it returns a `NullResource` that serializes to `{"data": null}`.

## Resource Collections

A `ResourceCollection` transforms an iterable of items using a specified
Resource class. Each item is individually transformed and wrapped in a `data`
array envelope.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class BookResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
        ];
    }
}

$books = [
    (object) ['id' => 1, 'title' => 'PHP in Action'],
    (object) ['id' => 2, 'title' => 'Clean Code'],
];

$collection = BookResource::collection($books);

echo json_encode($collection, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": [
    {
      "id": 1,
      "title": "PHP in Action"
    },
    {
      "id": 2,
      "title": "Clean Code"
    }
  ]
}
```

Attach pagination metadata with `paginate()`:

```php
<?php

declare(strict_types=1);

use App\Resources\BookResource;

$collection = BookResource::collection($books)
    ->paginate(total: 50, perPage: 10, currentPage: 1, lastPage: 5);

echo json_encode($collection, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": [
    {
      "id": 1,
      "title": "PHP in Action"
    },
    {
      "id": 2,
      "title": "Clean Code"
    }
  ],
  "meta": {
    "total": 50,
    "per_page": 10,
    "current_page": 1,
    "last_page": 5
  }
}
```

## Conditional Fields

Include or exclude fields based on runtime conditions. Fields excluded by
conditionals are removed entirely from the output (not set to `null`).

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->when($this->context['include_email'] ?? false, $this->resource->email),
            'secret' => $this->whenNotNull($this->resource->secret ?? null),
            $this->mergeWhen($this->context['is_admin'] ?? false, [
                'role' => 'admin',
                'permissions' => ['all'],
            ]),
        ];
    }
}

$user = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
$resource = UserResource::make($user)->withContext(['include_email' => true]);

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 1,
    "name": "Alice",
    "email": "alice@example.com"
  }
}
```

The `when()` method also accepts closures that are only evaluated when the
condition is true:

```php
'expensive_field' => $this->when($shouldLoad, fn() => $this->computeExpensiveValue()),
```

## Nested Resources

Include related resources within a parent resource. Nested resources are
serialized without their own data envelope, producing a clean nested structure.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class AddressResource extends Resource
{
    public function toArray(): array
    {
        return [
            'street' => $this->resource->street,
            'city' => $this->resource->city,
        ];
    }
}

class CustomerResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'address' => AddressResource::make($this->resource->address),
            'orders' => OrderResource::collection($this->resource->orders),
        ];
    }
}

$customer = (object) [
    'id' => 1,
    'name' => 'Bob',
    'address' => (object) ['street' => '123 Main St', 'city' => 'Springfield'],
    'orders' => [],
];

echo json_encode(CustomerResource::make($customer), JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 1,
    "name": "Bob",
    "address": {
      "street": "123 Main St",
      "city": "Springfield"
    },
    "orders": []
  }
}
```

Nesting works recursively to at least three levels deep. Parent context is
automatically propagated to nested resources.

## Additional Metadata

Attach supplementary information like timestamps, request IDs, or computed
totals alongside the data envelope.

```php
<?php

declare(strict_types=1);

use App\Resources\ProductResource;

$product = ['id' => 1, 'name' => 'Widget', 'price' => 9.99];
$resource = ProductResource::make($product)
    ->withMeta(['generated_at' => '2024-01-15T10:30:00Z']);

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 1,
    "name": "Widget",
    "price": 9.99
  },
  "meta": {
    "generated_at": "2024-01-15T10:30:00Z"
  }
}
```

Multiple `withMeta()` calls are shallow-merged with last-value-wins for
duplicate keys. When wrapping is disabled, metadata merges into the top-level
output.

## Resource Links (HATEOAS)

Attach hypermedia links to resources and collections so API consumers can
discover related endpoints.

```php
<?php

declare(strict_types=1);

use App\Resources\ProductResource;

$product = ['id' => 5, 'name' => 'Gadget', 'price' => 19.99];
$resource = ProductResource::make($product)
    ->withLinks([
        'self' => '/products/5',
        'category' => '/categories/electronics',
    ]);

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 5,
    "name": "Gadget",
    "price": 19.99
  },
  "links": {
    "self": "/products/5",
    "category": "/categories/electronics"
  }
}
```

When wrapping is disabled, links appear under a `_links` key in the top-level
output. Multiple `withLinks()` calls are shallow-merged.

## Resource Context

Pass arbitrary data from the controller layer into resources without coupling to
the HTTP request. Context is accessible in `toArray()` via the protected
`$context` property.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class ArticleResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'can_edit' => $this->when(
                ($this->context['user_id'] ?? null) === $this->resource->author_id,
                true
            ),
        ];
    }
}

$article = (object) ['id' => 10, 'title' => 'Hello World', 'author_id' => 7];
$resource = ArticleResource::make($article)
    ->withContext(['user_id' => 7]);

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 10,
    "title": "Hello World",
    "can_edit": true
  }
}
```

Context is automatically propagated to nested resources and collections.
Multiple `withContext()` calls are shallow-merged.

## Field Filtering (Sparse Fieldsets)

Filter resource output to include only specific fields, reducing payload size
for API consumers.

```php
<?php

declare(strict_types=1);

use App\Resources\UserResource;

$user = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
$resource = UserResource::make($user)
    ->withContext(['include_email' => true])
    ->only('id', 'name');

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 1,
    "name": "Alice"
  }
}
```

The `only()` method accepts variadic strings or an array of field names. It
applies after conditional resolution but before nested resource serialization.
Collections propagate `only()` to each item.

## Field Exclusion

Exclude specific fields from the output while keeping everything else. The
inverse of `only()`.

```php
<?php

declare(strict_types=1);

use App\Resources\UserResource;

$user = (object) ['id' => 1, 'name' => 'Alice', 'email' => 'alice@example.com'];
$resource = UserResource::make($user)
    ->withContext(['include_email' => true])
    ->except('email');

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 1,
    "name": "Alice"
  }
}
```

If both `only()` and `except()` are set, the last one called takes effect.
Collections propagate `except()` to each item.

## Declarative Mapping

Use the `$map` property for simple field transformations without overriding
`toArray()`. Supports dot-notation for nested data access.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class ProfileResource extends Resource
{
    protected array $map = [
        'full_name' => 'name',
        'city' => 'address.city',
        'zip' => 'address.zip_code',
    ];
}

$data = (object) [
    'name' => 'Charlie',
    'address' => (object) ['city' => 'Portland', 'zip_code' => '97201'],
];

echo json_encode(ProfileResource::make($data), JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "full_name": "Charlie",
    "city": "Portland",
    "zip": "97201"
  }
}
```

Each map entry resolves the source path from the resource data. Missing paths
resolve to `null`. If a subclass overrides `toArray()`, the `$map` property is
ignored.

## Type Identifiers

Add a `type` field to the response envelope for resource type discrimination in
polymorphic APIs.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class EventResource extends Resource
{
    protected ?string $type = 'event';

    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}

$event = (object) ['id' => 1, 'name' => 'Conference'];
echo json_encode(EventResource::make($event), JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "type": "event",
  "data": {
    "id": 1,
    "name": "Conference"
  }
}
```

The `type` field appears before `data` in the envelope. Empty or whitespace-only
type values are treated as null and omitted. Collections also support the`$type`
property.

## Wrapping Key Customization

Override the default `"data"` wrapping key with a domain-specific name.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class InvoiceResource extends Resource
{
    protected string $wrapKey = 'invoice';

    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'total' => $this->resource->total,
        ];
    }
}

$invoice = (object) ['id' => 100, 'total' => 250.00];
echo json_encode(InvoiceResource::make($invoice), JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "invoice": {
    "id": 100,
    "total": 250
  }
}
```

The `$wrapKey` must be a non-empty string (1–64 characters) and cannot be
`"meta"` or `"links"`. When wrapping is disabled (`$wrap = false`), the key is
ignored.

## Response Headers

Attach custom HTTP headers directly from the resource layer. Headers are applied
to the PSR-7 response when using the `response()->resource()` helper.

```php
<?php

declare(strict_types=1);

use App\Resources\ProductResource;

$product = ['id' => 1, 'name' => 'Widget', 'price' => 9.99];
$resource = ProductResource::make($product)
    ->withHeaders([
        'X-Cache-TTL' => '3600',
        'X-Request-Id' => 'abc-123',
    ]);

response()->resource($resource);
```

Multiple `withHeaders()` calls are merged with case-insensitive deduplication (
later values overwrite earlier values for the same header name). Empty header
names throw an `InvalidHeaderException`.

## Caching / Memoization

The first call to `jsonSerialize()` on a Resource computes and caches the
result. Subsequent calls return the cached value without re-executing
`toArray()`.

```php
<?php

declare(strict_types=1);

use App\Resources\ProductResource;

$product = ['id' => 1, 'name' => 'Widget', 'price' => 9.99];
$resource = ProductResource::make($product);

// First call: computes and caches
$first = json_encode($resource);

// Second call: returns a cached result (toArray not called again)
$second = json_encode($resource);

// $first === $second
```

Calling `withContext()` invalidates the cache, so the next serialization
re-executes the pipeline. `ResourceCollection` does not cache and always
recomputes on each call.

## Transformation Hooks

Two lifecycle hooks allow setup work before transformation and post-processing
after:

- `beforeSerialize()` runs before `toArray()` — use for lazy-loading or
  computing derived values
- `afterSerialize(array $data): array` runs after field filtering — use for
  sorting keys or removing empty values

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class ReportResource extends Resource
{
    private array $stats = [];

    protected function beforeSerialize(): void
    {
        $this->stats = ['views' => 100, 'shares' => 25];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
            'stats' => $this->stats,
        ];
    }

    protected function afterSerialize(array $data): array
    {
        ksort($data);
        return $data;
    }
}

$report = (object) ['id' => 1, 'title' => 'Monthly Report'];
echo json_encode(ReportResource::make($report), JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 1,
    "stats": {
      "views": 100,
      "shares": 25
    },
    "title": "Monthly Report"
  }
}
```

Both hooks have access to `$this->context`. The default implementations are
no-ops.

## Framework Independence

The `Resource` and `ResourceCollection` classes reside in the `Simsoft\Resource`
namespace with zero external dependencies. They work in any PHP 8.2+ application
without Slim Framework or PSR-7 packages.

```php
<?php

declare(strict_types=1);

// Works without Slim Framework installed
use Simsoft\Resource\Resource;

class SimpleResource extends Resource
{
    public function toArray(): array
    {
        return ['value' => $this->resource['value']];
    }
}

$resource = SimpleResource::make(['value' => 42]);
$json = json_encode($resource, JSON_PRETTY_PRINT);

echo $json;
```

Expected JSON output:

```json
{
  "data": {
    "value": 42
  }
}
```

The framework adapter (`response()->resource()`) lives in `Simsoft\Slim` and
bridges the resource layer to PSR-7 responses. This separation allows the core
resource classes to be extracted into a standalone Composer package.

## Format Adapters (Serializers)

Serializers convert resource output into different text formats. The library
ships with JSON and XML serializers, and you can implement custom ones via
`ResourceSerializerInterface`.

```php
<?php

declare(strict_types=1);

use App\Resources\ProductResource;
use Simsoft\Resource\Serializers\JsonSerializer;
use Simsoft\Resource\Serializers\XmlSerializer;

$product = ['id' => 1, 'name' => 'Widget', 'price' => 9.99];
$resource = ProductResource::make($product);

// JSON (default)
response()->resource($resource, 200, new JsonSerializer());

// XML
response()->resource($resource, 200, new XmlSerializer());
```

The `JsonSerializer` uses `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES` and sets
`Content-Type: application/json`. The `XmlSerializer` produces well-formed XML
1.0 with UTF-8 encoding and sets `Content-Type: application/xml`.

Implement `ResourceSerializerInterface` for custom formats:

```php
<?php

declare(strict_types=1);

namespace App\Serializers;

use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;
use Simsoft\Resource\Serializers\ResourceSerializerInterface;

class CsvSerializer implements ResourceSerializerInterface
{
    public function serialize(Resource|ResourceCollection $resource): string
    {
        // Convert toSerializedArray() to CSV format
        return '...';
    }

    public function contentType(): string
    {
        return 'text/csv';
    }
}
```

## Advanced Usage

### Context with Conditionals

Combine context data with conditional fields to produce different
representations based on the caller's permissions or preferences.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class EmployeeResource extends Resource
{
    public function toArray(): array
    {
        $isManager = ($this->context['role'] ?? '') === 'manager';

        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'department' => $this->resource->department,
            'salary' => $this->when($isManager, $this->resource->salary),
            $this->mergeWhen($isManager, [
                'direct_reports' => $this->resource->direct_reports ?? 0,
                'hire_date' => $this->resource->hire_date,
            ]),
        ];
    }
}

$employee = (object) [
    'id' => 5,
    'name' => 'Dana',
    'department' => 'Engineering',
    'salary' => 95000,
    'direct_reports' => 3,
    'hire_date' => '2020-03-15',
];

$resource = EmployeeResource::make($employee)
    ->withContext(['role' => 'manager']);

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 5,
    "name": "Dana",
    "department": "Engineering",
    "salary": 95000,
    "direct_reports": 3,
    "hire_date": "2020-03-15"
  }
}
```

### Sparse Fieldsets with Nested Resources

Apply field filtering to resources that contain nested resources. Excluded
nested resources are not serialized.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class OrderResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'total' => $this->resource->total,
            'status' => $this->resource->status,
            'customer' => CustomerResource::make($this->resource->customer),
        ];
    }
}

$order = (object) [
    'id' => 99,
    'total' => 150.00,
    'status' => 'shipped',
    'customer' => (object) ['id' => 1, 'name' => 'Bob', 'address' => null, 'orders' => []],
];

$resource = OrderResource::make($order)->only('id', 'total');

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "id": 99,
    "total": 150
  }
}
```

The `customer` field is excluded and its nested resource is never serialized.

### Collection with Pagination and Links

Combine pagination metadata with HATEOAS links for a complete paginated API
response.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class PostResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'title' => $this->resource->title,
        ];
    }
}

$posts = [
    (object) ['id' => 1, 'title' => 'First Post'],
    (object) ['id' => 2, 'title' => 'Second Post'],
];

$collection = PostResource::collection($posts)
    ->paginate(total: 100, perPage: 10, currentPage: 1, lastPage: 10)
    ->withMeta(['sort' => 'created_at', 'order' => 'desc'])
    ->withLinks([
        'self' => '/posts?page=1',
        'next' => '/posts?page=2',
        'last' => '/posts?page=10',
    ]);

echo json_encode($collection, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": [
    {
      "id": 1,
      "title": "First Post"
    },
    {
      "id": 2,
      "title": "Second Post"
    }
  ],
  "meta": {
    "total": 100,
    "per_page": 10,
    "current_page": 1,
    "last_page": 10,
    "sort": "created_at",
    "order": "desc"
  },
  "links": {
    "self": "/posts?page=1",
    "next": "/posts?page=2",
    "last": "/posts?page=10"
  }
}
```

Additional metadata keys take precedence over pagination keys when there are
duplicates.

### Mapping with Exclusion

Combine declarative mapping with field exclusion for concise resource
definitions.

```php
<?php

declare(strict_types=1);

namespace App\Resources;

use Simsoft\Resource\Resource;

class ContactResource extends Resource
{
    protected array $map = [
        'name' => 'full_name',
        'email' => 'contact.email',
        'phone' => 'contact.phone',
        'city' => 'address.city',
        'country' => 'address.country',
    ];
}

$data = [
    'full_name' => 'Eve',
    'contact' => ['email' => 'eve@example.com', 'phone' => '+1-555-0100'],
    'address' => ['city' => 'Seattle', 'country' => 'US'],
];

$resource = ContactResource::make($data)->except('phone');

echo json_encode($resource, JSON_PRETTY_PRINT);
```

Expected JSON output:

```json
{
  "data": {
    "name": "Eve",
    "email": "eve@example.com",
    "city": "Seattle",
    "country": "US"
  }
}
```

The `$map` property handles the transformation, and `except()` removes unwanted
fields from the final output.
