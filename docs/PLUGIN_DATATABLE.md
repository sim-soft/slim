# Plugin: DataTable

## Table of Contents

- [Classes](#classes)
- [DataTableResponse](#datatableresponse)
    - [Setup](#setup)
    - [Sorting Behavior](#sorting-behavior)
    - [Building the Response](#building-the-response)
    - [Output](#output)
    - [Response Structure](#response-structure)
- [DataTableActionButton](#datatableactionbutton)
    - [Row with Actions](#row-with-actions)
- [Full Controller Example](#full-controller-example)

Server-side response builder for [jQuery DataTables](https://datatables.net/).

## Classes

| Class                   | Purpose                                         |
|-------------------------|-------------------------------------------------|
| `DataTableResponse`     | Builds the JSON response expected by DataTables |
| `DataTableActionButton` | Configures action buttons per row               |

## DataTableResponse

### Setup

```php
use Simsoft\Slim\Plugins\DataTable\DataTableResponse;

$dt = new DataTableResponse();
$dt->setParams($requestParams);
```

`setParams()` accepts the DataTables request array and extracts:

| Method               | Returns   | Description                   |
|----------------------|-----------|-------------------------------|
| `getStart()`         | `int`     | Offset for SQL LIMIT          |
| `getLength()`        | `int`     | Row count per page            |
| `getPage()`          | `int`     | Current page number (1-based) |
| `getSortAttribute()` | `?string` | Column name to sort by        |
| `getSortDirection()` | `string`  | `ASC` or `DESC`               |

### Sorting Behavior

```php
// First draw: sort applied by default
$dt->setParams($params, firstDrawSort: true);

// First draw: skip sort (let DB use the default order)
$dt->setParams($params, firstDrawSort: false);

// Subsequent draws (draw > 1): sort always applied regardless of firstDrawSort
```

### Building the Response

```php
$dt->setTotalRecords(100);       // Total records before filtering
$dt->setTotalFiltered(45);       // Records after filtering (optional, defaults to total)

$dt->addRow(['id' => 1, 'name' => 'John']);
$dt->addRow(['id' => 2, 'name' => 'jane'], function(array $data) {
    $data['name'] = ucfirst($data['name']);
    return $data;
});

$dt->setError('Optional error message');
```

### Output

```php
$dt->toArray();   // Returns array
$dt->toJson();    // Returns JSON string
$dt();            // Invokable, returns array
(string) $dt;     // Cast to string, returns JSON
```

### Response Structure

```json
{
    "draw": 1,
    "recordsTotal": 100,
    "recordsFiltered": 45,
    "data": [
        {"id": 1, "name": "John"},
        {"id": 2, "name": "Jane"}
    ]
}
```

## DataTableActionButton

```php
use Simsoft\Slim\Plugins\DataTable\DataTableActionButton;

$button = new DataTableActionButton('Edit');
$button->url('/users/1/edit')
       ->title('Edit User')
       ->confirm('Are you sure?');

// Conditional visibility
$deleteBtn = new DataTableActionButton('Delete', enabled: $userCanDelete);
```

| Method            | Description                   |
|-------------------|-------------------------------|
| `label(string)`   | Button display text           |
| `url(string)`     | Action URL                    |
| `title(string)`   | Tooltip or data title         |
| `confirm(string)` | Confirmation prompt text      |
| `isEnabled()`     | Whether button is rendered    |
| `toArray()`       | Convert to array for response |

### Row with Actions

```php
$dt->addRow([
    'id' => 1,
    'name' => 'John',
    'actions' => [
        (new DataTableActionButton('Edit'))->url('/users/1/edit'),
        (new DataTableActionButton('Delete', enabled: false))->url('/users/1'),
    ],
]);
// Disabled buttons are automatically removed from the response
```

## Full Controller Example

```php
<?php
namespace App;

use Simsoft\Slim\Plugins\DataTable\DataTableActionButton;
use Simsoft\Slim\Plugins\DataTable\DataTableResponse;
use function Simsoft\Slim\request;

class UserController
{
    public function datatable(): array
    {
        $dt = new DataTableResponse();
        $dt->setParams(request()->getQueryParams());

        // Query your database using pagination/sort values
        $users = $this->queryUsers(
            offset: $dt->getStart(),
            limit: $dt->getLength(),
            orderBy: $dt->getSortAttribute(),
            direction: $dt->getSortDirection(),
        );

        $dt->setTotalRecords($users['total']);
        $dt->setTotalFiltered($users['filtered']);

        foreach ($users['rows'] as $user) {
            $dt->addRow([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'actions' => [
                    (new DataTableActionButton('Edit'))
                        ->url("/users/{$user->id}/edit")
                        ->title($user->name),
                    (new DataTableActionButton('Delete'))
                        ->url("/users/{$user->id}")
                        ->confirm("Delete {$user->name}?"),
                ],
            ]);
        }

        return $dt->toArray();
    }
}
```
