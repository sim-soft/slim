# Requirements Document

## Introduction

The API Resource feature provides a data transformation layer for the Simsoft
Slim library, enabling developers to transform raw data (objects, arrays,
models) into structured JSON API responses. Inspired by Laravel's JsonResource,
this feature gives developers explicit control over which fields are exposed,
how data is formatted, and what metadata accompanies responses. Resources
support single items, collections with pagination metadata, conditional fields,
nested resources, and integrate with the library's existing `response()` helper.

## Glossary

- **Resource**: A class responsible for transforming a single data item (object
  or array) into a structured array representation suitable for JSON
  serialization.
- **Resource_Collection**: A class responsible for transforming a collection of
  data items into a structured array, with support for pagination metadata.
- **Data_Envelope**: The wrapping structure around resource output, typically
  `{"data": ...}`.
- **Conditional_Field**: A field included in the resource output only when a
  specified condition evaluates to true.
- **Meta**: Additional metadata attached to the response alongside the data
  envelope (e.g., pagination info, totals).
- **Response_Helper**: The existing `response()` function extended to support
  returning resource instances as JSON responses.
- **ResourceSerializerInterface**: A pluggable interface defining `serialize()`
  and `contentType()` methods for converting Resource output into text-based
  structured data formats.
- **JsonSerializer**: The default ResourceSerializerInterface implementation
  that serializes resource output to JSON using `json_encode`.
- **XmlSerializer**: A ResourceSerializerInterface implementation that
  serializes resource output to well-formed XML.

## Requirements

### Requirement 1: Single Resource Transformation

**User Story:** As a developer, I want to define how a single data item is
transformed into JSON, so that I can control the API response structure and hide
internal implementation details.

#### Acceptance Criteria

1. THE Resource SHALL accept a data item of type `object|array` via its
   constructor and store it in a public readonly property named `resource` with
   the same type.
2. THE Resource SHALL declare an abstract `toArray` method with no parameters
   that subclasses implement to return an associative array (
   `array<string, mixed>`) representing the transformed data item.
3. WHEN the Resource is serialized to JSON (via PHP's `JsonSerializable`
   interface), THE Resource SHALL return the output of the `toArray` method as
   the value from `jsonSerialize`.
4. THE Resource SHALL provide a static `make` factory method that accepts a data
   item of type `object|array` and returns a new instance of the called
   subclass (return type `static`).
5. THE Resource SHALL be declared as an abstract class, requiring subclasses to
   provide the `toArray` implementation before instantiation via `make` or
   direct construction.

### Requirement 2: Data Envelope Wrapping

**User Story:** As a developer, I want resource output wrapped in a `data` key
by default, so that my API responses follow a consistent structure.

#### Acceptance Criteria

1. WHEN the Resource is serialized to JSON, THE Resource SHALL wrap the
   `toArray` output inside a `{"data": ...}` envelope.
2. IF wrapping is disabled on a Resource class, THEN THE Resource SHALL return
   the `toArray` output without a `data` envelope when serialized to JSON.
3. THE Resource SHALL provide a boolean class-level property, defaulting to
   true (wrapping enabled), that subclasses can override to disable the data
   envelope wrapping.

### Requirement 3: Resource Collection

**User Story:** As a developer, I want to transform a collection of items using
a resource class, so that each item in the list is consistently formatted.

#### Acceptance Criteria

1. THE Resource_Collection SHALL accept an iterable of data items and a Resource
   class-string reference via its constructor.
2. WHEN the Resource_Collection `toArray` method is called, THE
   Resource_Collection SHALL transform each item by instantiating the specified
   Resource class with the item and calling its `toArray` method, preserving the
   order of items from the input iterable.
3. THE Resource_Collection SHALL wrap the transformed items inside a
   `{"data": [...]}` envelope.
4. THE Resource_Collection SHALL provide a method to attach pagination
   metadata (total, per_page, current_page, last_page) as integer values with a
   minimum value of 1 for per_page, current_page, and last_page, and a minimum
   value of 0 for total, and SHALL return the Resource_Collection instance for
   method chaining.
5. WHEN pagination metadata is provided, THE Resource_Collection SHALL include
   it under a `meta` key alongside the `data` key, including when the iterable
   contains zero items.
6. WHEN the iterable contains zero items, THE Resource_Collection SHALL return
   `{"data": []}` as the envelope.
7. IF the provided Resource class-string does not reference a valid Resource
   subclass, THEN THE Resource_Collection SHALL throw an exception indicating
   the invalid class reference.

### Requirement 4: Conditional Fields

**User Story:** As a developer, I want to include or exclude fields based on
conditions, so that I can return different representations depending on context.

#### Acceptance Criteria

1. THE Resource SHALL provide a `when` method that accepts a boolean condition
   and a value of type mixed (including closures), and returns a sentinel value
   that the Resource uses during `toArray` processing to include the value in
   the output only when the condition is true; IF the value is a closure, THEN
   THE Resource SHALL evaluate the closure only when the condition is true.
2. IF the condition passed to `when` is false, THEN THE Resource SHALL exclude
   the field key from the output array entirely (not set it to null).
3. THE Resource SHALL provide a `whenNotNull` method that accepts a value of
   type mixed and returns a sentinel value that the Resource uses during
   `toArray` processing to include the value in the output only when the value
   is not null.
4. IF the value passed to `whenNotNull` is null, THEN THE Resource SHALL exclude
   the field key from the output array entirely (not set it to null).
5. THE Resource SHALL provide a `mergeWhen` method that accepts a boolean
   condition and an associative array of fields, and merges each key-value pair
   into the top-level output array only when the condition is true; IF a merged
   key conflicts with an existing key in the output array, THEN the merged key's
   value SHALL overwrite the existing value.
6. IF the condition passed to `mergeWhen` is false, THEN THE Resource SHALL
   exclude all fields from that `mergeWhen` call from the output array entirely.

### Requirement 5: Nested Resources

**User Story:** As a developer, I want to include related resources within a
parent resource, so that I can compose complex API responses from reusable
resource classes.

#### Acceptance Criteria

1. WHEN a Resource instance is used as a field value in the associative array
   returned by `toArray`, THE Resource SHALL automatically serialize the nested
   Resource by calling its `toArray` method and including the resulting
   associative array as that field's value without a data envelope wrapper.
2. WHEN a Resource_Collection instance is used as a field value in the
   associative array returned by `toArray`, THE Resource SHALL automatically
   serialize the nested collection by transforming each item via its specified
   Resource class and including the resulting sequential array of transformed
   items as that field's value without a data envelope wrapper.
3. WHEN the associative array returned by `toArray` contains field values that
   are scalar types, plain arrays, or non-Resource objects, THE Resource SHALL
   include those values unchanged in the serialized output.
4. THE Resource SHALL produce correct serialized output for nesting resources to
   at least 3 levels deep (e.g., parent Resource contains a child Resource field
   whose `toArray` returns a grandchild Resource field, and all levels are
   recursively serialized).
5. IF a nested Resource field value is null, THEN THE Resource SHALL include
   null for that field in the output.
6. WHEN a conditional field (via `when`, `whenNotNull`, or `mergeWhen`) resolves
   to a Resource or Resource_Collection instance, THE Resource SHALL serialize
   it using the same automatic nested serialization rules defined in criteria 1
   and 2.

### Requirement 6: Additional Metadata

**User Story:** As a developer, I want to attach extra metadata to my resource
responses, so that I can include supplementary information like timestamps or
request context.

#### Acceptance Criteria

1. THE Resource SHALL provide a method that accepts an associative array of
   metadata and returns the Resource instance for method chaining.
2. WHEN additional metadata is provided, THE Resource SHALL include it under a
   `meta` key alongside the `data` key in the response envelope.
3. IF no additional metadata has been provided or the metadata array is empty,
   THEN THE Resource SHALL omit the `meta` key from the response envelope
   entirely.
4. IF the metadata method is called multiple times, THEN THE Resource SHALL
   shallow-merge all provided metadata arrays using last-value-wins semantics,
   where later values overwrite earlier values for duplicate top-level keys
   without recursing into nested arrays.
5. IF the data envelope wrapping is disabled on a Resource and additional
   metadata has been provided, THEN THE Resource SHALL merge the metadata
   key-value pairs into the top-level output alongside the `toArray` fields
   rather than placing them under a separate `meta` key.
6. THE Resource_Collection SHALL support the same additional metadata method and
   return the Resource_Collection instance for method chaining.
7. WHEN both additional metadata and pagination metadata are provided on a
   Resource_Collection, THE Resource_Collection SHALL merge them into a single
   `meta` key, with additional metadata values taking precedence over pagination
   metadata values for duplicate keys.
8. IF only pagination metadata is provided on a Resource_Collection without
   additional metadata, THEN THE Resource_Collection SHALL include the
   pagination metadata under the `meta` key unchanged.

### Requirement 7: Response Helper Integration

**User Story:** As a developer, I want to return resources directly from
controllers using the existing `response()` helper, so that the resource layer
integrates naturally with the library's workflow.

#### Acceptance Criteria

1. THE Response_Helper SHALL provide a `resource` method that accepts a Resource
   or Resource_Collection instance and an optional HTTP status code parameter (
   integer), and SHALL return the Response instance for method chaining.
2. WHEN a Resource or Resource_Collection is passed to the `resource` method,
   THE Response_Helper SHALL serialize the instance using `json_encode` (
   leveraging the instance's `JsonSerializable` implementation) with
   `JSON_PRETTY_PRINT` and `JSON_UNESCAPED_SLASHES` flags, write the result to
   the response body, and set the `Content-Type` header to `application/json`.
3. WHEN a Resource or Resource_Collection is passed to the `resource` method
   without a status code parameter, THE Response_Helper SHALL set the HTTP
   status code to 200.
4. WHEN a status code parameter between 100 and 599 is provided to the
   `resource` method, THE Response_Helper SHALL use the provided status code
   instead of the default 200.
5. IF JSON serialization of the Resource or Resource_Collection fails (i.e.,
   `json_encode` returns false), THEN THE Response_Helper SHALL throw an
   Exception indicating the serialization failure.
6. IF a status code parameter outside the range 100 to 599 is provided to the
   `resource` method, THEN THE Response_Helper SHALL throw an Exception
   indicating the invalid status code.

### Requirement 8: Resource Context

**User Story:** As a developer, I want to pass arbitrary context data into
resources from the controller layer, so that resources can conditionally include
or exclude fields or change behavior based on external context without directly
coupling to the HTTP request.

#### Acceptance Criteria

1. THE Resource SHALL provide a `withContext` method that accepts an associative
   array (`array<string, mixed>`) and returns the Resource instance for method
   chaining.
2. THE Resource SHALL store the context data in a protected property accessible
   within the `toArray` method, enabling subclasses to read context values when
   determining which fields to include or how to format them.
3. IF `withContext` is called multiple times on the same Resource instance, THEN
   THE Resource SHALL shallow-merge all provided context arrays using
   last-value-wins semantics, where later values overwrite earlier values for
   duplicate keys.
4. WHEN a Resource contains nested Resource instances as field values in the
   `toArray` output, THE Resource SHALL automatically propagate its context data
   to each nested Resource by shallow-merging the parent's context into the
   nested Resource's existing context using last-value-wins semantics (parent
   context values overwrite nested Resource's prior context values for duplicate
   keys) before calling the nested Resource's `toArray` method.
5. WHEN a Resource contains nested Resource_Collection instances as field values
   in the `toArray` output, THE Resource SHALL automatically propagate its
   context data to the nested Resource_Collection by shallow-merging the
   parent's context into the nested Resource_Collection's existing context using
   last-value-wins semantics (parent context values overwrite nested
   Resource_Collection's prior context values for duplicate keys) before
   serialization.
6. THE Resource_Collection SHALL provide a `withContext` method that accepts an
   associative array (`array<string, mixed>`) and returns the
   Resource_Collection instance for method chaining.
7. WHEN the Resource_Collection transforms its items, THE Resource_Collection
   SHALL propagate its context data to each item Resource instance by
   shallow-merging the collection's context into the item Resource's context
   using last-value-wins semantics before calling the item's `toArray` method.
8. THE Resource SHALL NOT accept a PSR-7 Request object as a constructor
   parameter, method parameter, or stored property; context data SHALL be passed
   explicitly by the controller via the `withContext` method.
9. IF no context has been provided via `withContext`, THEN THE Resource SHALL
   use an empty array as the default context value.
10. WHEN context propagation occurs through multiple nesting levels (e.g.,
    parent Resource contains a child Resource whose `toArray` returns a
    grandchild Resource), THE Resource SHALL recursively propagate context at
    each level, resulting in the grandchild receiving the merged context from
    all ancestor Resources.

### Requirement 9: Resource Links (HATEOAS)

**User Story:** As a developer, I want to attach hypermedia links to resources
and collections, so that API consumers can discover related endpoints and
navigate the API programmatically.

#### Acceptance Criteria

1. THE Resource SHALL provide a `withLinks` method that accepts an associative
   array of link relations (e.g., 'self', 'next', 'related') mapped to URL
   strings, and returns the Resource instance for method chaining.
2. WHEN links are provided via `withLinks`, THE Resource SHALL include them
   under a `links` key in the response envelope alongside `data` and `meta`,
   preserving the associative array structure as-is.
3. IF no links are provided or `withLinks` is called with an empty array, THEN
   THE Resource SHALL omit the `links` key from the response envelope entirely.
4. THE Resource_Collection SHALL provide a `withLinks` method that accepts an
   associative array of collection-level link relations (e.g., 'self', 'next', '
   prev', 'first', 'last') mapped to URL strings, and returns the
   Resource_Collection instance for method chaining.
5. WHEN links are provided via `withLinks` on a Resource_Collection, THE
   Resource_Collection SHALL include them under a `links` key in the response
   envelope alongside `data` and `meta`.
6. IF data envelope wrapping is disabled on a Resource and links have been
   provided, THEN THE Resource SHALL merge the links into the top-level output
   under a `_links` key.
7. IF `withLinks` is called multiple times on the same Resource or
   Resource_Collection instance, THEN the instance SHALL shallow-merge all
   provided link arrays using last-value-wins semantics, where later values
   overwrite earlier values for duplicate link relation keys.

### Requirement 10: Null Resource Handling

**User Story:** As a developer, I want a consistent representation when a
resource is null, so that API consumers receive a predictable structure even
when no data exists.

#### Acceptance Criteria

1. WHEN null is passed to `Resource::make()`, THE Resource SHALL accept the null
   value (extending the parameter type to `object|array|null`) and return a
   NullResource instance (a concrete Resource subclass).
2. THE NullResource `toArray` method SHALL return null.
3. WHEN the NullResource is serialized to JSON with wrapping enabled, THE
   NullResource SHALL produce `{"data": null}`.
4. WHEN the NullResource is serialized to JSON with wrapping disabled, THE
   NullResource SHALL produce the JSON literal `null`.
5. WHEN metadata is provided via the metadata method with wrapping enabled, THE
   NullResource SHALL produce `{"data": null, "meta": {...}}` with the provided
   metadata under the `meta` key.
6. IF metadata is provided via the metadata method with wrapping disabled, THEN
   THE NullResource SHALL discard the metadata and produce the JSON literal
   `null`.
7. WHEN links are provided via `withLinks` with wrapping enabled, THE
   NullResource SHALL produce `{"data": null, "links": {...}}` with the provided
   links under the `links` key.
8. IF links are provided via `withLinks` with wrapping disabled, THEN THE
   NullResource SHALL discard the links and produce the JSON literal `null`.
9. THE NullResource SHALL support `withContext` by storing the context array,
   but the stored context SHALL have no effect on the serialized output (output
   remains as defined in criteria 3 through 8 regardless of context values).

### Requirement 11: Resource Transformation Hooks

**User Story:** As a developer, I want lifecycle hooks before serialization, so
that I can perform setup work like lazy-loading relationships or computing
derived values before the resource is transformed.

#### Acceptance Criteria

1. THE Resource SHALL provide a protected `beforeSerialize` method with a `void`
   return type that is called before `toArray` is invoked when `jsonSerialize`
   executes.
2. THE default `beforeSerialize` implementation SHALL be a no-op (empty method
   body).
3. THE `beforeSerialize` method SHALL have access to the resource's context data
   via the protected `context` property, typed as `array<string, mixed>`.
4. THE `beforeSerialize` method SHALL be called exactly once per `jsonSerialize`
   invocation, even if the resource is nested within another resource.
5. IF the `beforeSerialize` method throws an exception, THEN THE Resource SHALL
   propagate the exception to the caller without invoking `toArray`.

### Requirement 12: Collection Factory Method

**User Story:** As a developer, I want a convenient static method on resource
classes to create collections, so that I can avoid manually instantiating
Resource_Collection with class-string references.

#### Acceptance Criteria

1. THE Resource SHALL provide a static `collection` method that accepts a
   parameter of type `iterable` and returns a Resource_Collection instance
   constructed with the provided iterable and the calling class's class-string
   as the Resource reference.
2. THE `collection` method SHALL be declared with a return type of
   Resource_Collection to allow method chaining (e.g.,
   `UserResource::collection($users)->paginate(...)`).
3. THE `collection` method SHALL be inherited by subclasses and SHALL resolve
   the Resource class-string using late static binding so that
   `UserResource::collection($users)` configures the Resource_Collection with
   `UserResource` as the item resource class without requiring subclasses to
   override the method.
4. IF the `collection` method is called on a class that is not a valid concrete
   Resource subclass, THEN THE Resource_Collection SHALL throw an exception
   indicating the invalid class reference, as defined by the Resource_Collection
   constructor validation.

### Requirement 13: Response Headers from Resource

**User Story:** As a developer, I want to attach custom HTTP headers to
resources, so that I can set cache-control, rate-limit, or other headers
directly from the resource layer.

#### Acceptance Criteria

1. THE Resource SHALL provide a `withHeaders` method that accepts an associative
   array of header name (string) to value (`string|string[]`) pairs and returns
   the Resource instance for method chaining.
2. THE Resource_Collection SHALL provide a `withHeaders` method that accepts an
   associative array of header name (string) to value (`string|string[]`) pairs
   and returns the Resource_Collection instance for method chaining.
3. WHEN the Response_Helper `resource` method serializes a Resource or
   Resource_Collection that has headers, THE Response_Helper SHALL set those
   headers on the PSR-7 Response object using `withHeader`, replacing any
   existing response header with the same name including `Content-Type` if
   explicitly provided in the resource headers.
4. IF `withHeaders` is called multiple times on the same Resource or
   Resource_Collection instance, THEN THE instance SHALL merge all provided
   header arrays with later values overwriting earlier values for duplicate
   header names using case-insensitive name comparison.
5. IF no headers are provided via `withHeaders`, THEN THE Response_Helper SHALL
   set no additional headers on the response beyond `Content-Type`.
6. IF `withHeaders` is called with an associative array containing an empty
   string as a header name, THEN THE Resource or Resource_Collection SHALL throw
   an exception indicating the invalid header name.

### Requirement 14: Resource Caching / Memoization

**User Story:** As a developer, I want the resource transformation result to be
cached after first invocation, so that repeated serialization of the same
instance does not re-execute transformation logic.

#### Acceptance Criteria

1. WHEN `jsonSerialize` is called on a Resource instance for the first time, THE
   Resource SHALL invoke `toArray`, store the resulting array, and return it.
2. WHEN `jsonSerialize` is called on the same Resource instance a second or
   subsequent time without an intervening context change, THE Resource SHALL
   return the previously stored array without invoking `toArray` again.
3. IF `withContext` is called on a Resource instance that already has a cached
   result, THEN THE Resource SHALL discard the cached array so that the next
   `jsonSerialize` call re-invokes `toArray` with the updated context.
4. THE Resource_Collection SHALL invoke `toArray` on every call to
   `jsonSerialize` without caching the result.
5. WHEN `jsonSerialize` returns a cached result, THE Resource SHALL return an
   array identical in structure and values to the array produced by the original
   `toArray` invocation.

### Requirement 15: Data Wrapping Key Customization

**User Story:** As a developer, I want to customize the wrapping key name, so
that I can use domain-specific keys like "user" or "results" instead of the
generic "data" key.

#### Acceptance Criteria

1. THE Resource SHALL provide a class-level string property that defines the
   wrapping key name, defaulting to `"data"`, where the key must be a non-empty
   string containing between 1 and 64 characters and must not be `"meta"` or
   `"links"`.
2. WHEN a custom wrapping key is set on a Resource subclass by overriding the
   class-level property, THE Resource SHALL use that key instead of `"data"` as
   the top-level key in the response envelope (e.g., `{"user": {...}}`).
3. IF wrapping is disabled on a Resource class (per Requirement 2), THEN THE
   Resource SHALL ignore the custom wrapping key and return the `toArray` output
   without any envelope, regardless of the wrapping key value.
4. THE Resource_Collection SHALL provide its own class-level string property
   that defines the wrapping key name, defaulting to `"data"`, independent of
   the Resource class it transforms items with, where the key must be a
   non-empty string containing between 1 and 64 characters and must not be
   `"meta"` or `"links"`.
5. WHEN a custom wrapping key is set on a Resource_Collection by overriding the
   class-level property, THE Resource_Collection SHALL use that key instead of
   `"data"` as the top-level key in the response envelope (e.g.,
   `{"results": [...]}`).
6. THE `meta` and `links` keys in the response envelope SHALL remain unchanged
   regardless of the custom wrapping key value set on the Resource or
   Resource_Collection.

### Requirement 16: Sparse Fieldsets (Field Filtering)

**User Story:** As a developer, I want to filter resource output to only include
specific fields, so that API consumers can request minimal payloads and reduce
bandwidth.

#### Acceptance Criteria

1. THE Resource SHALL provide an `only` method that accepts field names as
   either a variadic list of strings or a single array of strings, and returns
   the Resource instance for method chaining.
2. WHEN `only` is applied, THE Resource SHALL filter the `toArray` output to
   include only the specified field keys that exist in the `toArray` output,
   excluding all others; field names specified in `only` that do not exist in
   the `toArray` output SHALL be silently ignored.
3. IF `only` is not called or is called with an empty list, THEN THE Resource
   SHALL include all fields from `toArray` (no filtering).
4. THE `only` filter SHALL be applied after `toArray` runs and after conditional
   field resolution, but before nested resource serialization; nested Resource
   or Resource_Collection instances in excluded fields SHALL NOT have their
   `toArray` method invoked.
5. THE Resource_Collection SHALL propagate `only` field filtering to each item
   Resource during transformation by calling `only` with the same field list on
   each item Resource instance, overriding any previously set `only` filter on
   that item.
6. WHEN `only` is called multiple times on the same Resource instance, THE
   Resource SHALL use only the field list from the last invocation, replacing
   any previously specified field list.

### Requirement 17: Resource Transformation Pipeline

**User Story:** As a developer, I want a post-processing hook after
serialization, so that I can perform final transformations like sorting keys,
adding computed fields, or removing empty values.

#### Acceptance Criteria

1. THE Resource SHALL provide a protected method
   `afterSerialize(array $data): array` that is invoked after `toArray`
   completes and after any field inclusion/exclusion filtering has been applied,
   receiving the filtered output array as its sole parameter.
2. WHEN `afterSerialize` is invoked, THE Resource SHALL use the returned array
   as the final serialization output, replacing the input array entirely.
3. THE default `afterSerialize` implementation SHALL return the input array
   unchanged (identity function), ensuring subclasses that do not override the
   method produce no side effects.
4. WHILE `afterSerialize` is executing, THE Resource SHALL expose the resource's
   context data as a readable protected property of type array, allowing the
   override to use contextual information for conditional transformations.
5. IF a subclass override of `afterSerialize` returns a value that is not an
   array, THEN THE Resource SHALL raise a TypeError.

### Requirement 18: Framework Independence

**User Story:** As a developer, I want the Resource and ResourceCollection
classes to be framework-agnostic, so that I can reuse them in any PHP
application without requiring Slim Framework or PSR-7 packages.

#### Acceptance Criteria

1. THE Resource SHALL NOT contain any `use` import statements, type hints,
   `instanceof` checks, or class references to Slim Framework classes, PSR-7
   interfaces, or Simsoft\Slim namespace classes.
2. THE ResourceCollection SHALL NOT contain any `use` import statements, type
   hints, `instanceof` checks, or class references to Slim Framework classes,
   PSR-7 interfaces, or Simsoft\Slim namespace classes.
3. THE Resource SHALL only depend on PHP built-in interfaces (JsonSerializable,
   Countable, IteratorAggregate, Stringable) and standard PHP scalar types,
   arrays, and objects with no external Composer package requirements.
4. THE ResourceCollection SHALL only depend on PHP built-in interfaces (
   JsonSerializable, Countable, IteratorAggregate, Stringable) and standard PHP
   scalar types, arrays, and objects with no external Composer package
   requirements.
5. THE Response helper adapter SHALL be implemented as a separate class within
   the Simsoft\Slim namespace that accepts a Resource or ResourceCollection
   instance and writes its JSON-serialized output to a PSR-7 ResponseInterface
   body with a `Content-Type: application/json` header.
6. WHEN the Resource class is instantiated in a PHP >= 8.2 application that does
   not have Slim Framework or PSR-7 packages installed, THE Resource SHALL be
   instantiable and SHALL produce valid output from its `jsonSerialize()` method
   without triggering class-not-found or dependency errors.
7. WHEN the ResourceCollection class is instantiated in a PHP >= 8.2 application
   that does not have Slim Framework or PSR-7 packages installed, THE
   ResourceCollection SHALL be instantiable and SHALL produce valid output from
   its `jsonSerialize()` method without triggering class-not-found or dependency
   errors.
8. THE Resource and ResourceCollection classes SHALL reside in an independent
   namespace (e.g., Simsoft\Resource) so that they can be extracted into a
   standalone Composer package by copying the source files and updating only the
   autoload path in composer.json, with no changes to the class source code.

### Requirement 19: Resource Mapping (Auto-Transform)

**User Story:** As a developer, I want a declarative field mapping mechanism, so
that simple resources can define field-to-property mappings without writing a
full toArray method.

#### Acceptance Criteria

1. THE Resource SHALL provide a protected array property named `$map` that maps
   output field names (keys) to source property or key names (values),
   defaulting to an empty array.
2. WHEN `toArray` is invoked on a Resource subclass that has a non-empty `$map`
   property and does not override the `toArray` method, THE Resource SHALL
   return an associative array where each key is an output field name from
   `$map` and each value is read from the corresponding source property or key
   on the underlying data item, preserving the declaration order of `$map`.
3. IF a subclass defines both a non-empty `$map` property and overrides the
   `toArray` method, THEN THE Resource SHALL use the `toArray` method output,
   ignoring the `$map` property.
4. THE mapping SHALL support dot-notation for nested property access (e.g.,
   `'city' => 'address.city'` reads `$item->address->city` for objects or
   `$item['address']['city']` for arrays), resolving each segment according to
   the segment's container type (object property access for objects, key access
   for arrays) to support mixed nested structures.
5. IF a mapped source path does not exist on the data item — including when any
   intermediate segment in a dot-notation path is null, missing, or not
   traversable — THEN THE Resource SHALL include null for that field in the
   output.
6. THE mapping SHALL work with both object properties and array keys on the data
   item, determining access method per segment based on whether the current
   value is an object or an array.

### Requirement 20: Except (Field Exclusion)

**User Story:** As a developer, I want to exclude specific fields from resource
output while keeping everything else, so that I can hide sensitive fields
without listing every included field.

#### Acceptance Criteria

1. THE Resource SHALL provide an `except` method that accepts field names as
   either a variadic list of strings or a single array of strings, and returns
   the Resource instance for method chaining.
2. WHEN `except` is applied, THE Resource SHALL filter the `toArray` output to
   exclude the specified field keys, including all others; field names specified
   in `except` that do not exist in the `toArray` output SHALL be silently
   ignored.
3. IF `except` is not called or is called with an empty list, THEN THE Resource
   SHALL include all fields from `toArray` (no filtering).
4. THE `except` filter SHALL be applied after `toArray` runs and after
   conditional field resolution, at the same stage as `only`; nested Resource or
   Resource_Collection instances in excluded fields SHALL NOT have their
   `toArray` method invoked.
5. IF both `only` and `except` are applied to the same Resource instance, THEN
   THE Resource SHALL use `only` and ignore `except`.
6. THE Resource_Collection SHALL propagate `except` field filtering to each item
   Resource during transformation by calling `except` with the same field list
   on each item Resource instance, overriding any previously set `except` filter
   on that item.
7. WHEN `except` is called multiple times on the same Resource instance, THE
   Resource SHALL use only the field list from the last invocation, replacing
   any previously specified exclusion list.

### Requirement 21: Resource Type Identifier

**User Story:** As a developer, I want a type identifier for resources, so that
I can support polymorphic collections and JSON:API-style responses with explicit
type information.

#### Acceptance Criteria

1. THE Resource SHALL provide a protected nullable string property (`?string`)
   named `$type` that defines the resource type identifier, defaulting to null (
   no type).
2. WHEN a type is defined (non-null) and data envelope wrapping is enabled, THE
   Resource SHALL include a `type` field in the response envelope alongside the
   data key (e.g., `{"type": "user", "data": {...}}`).
3. IF no type is defined (null), THEN THE Resource SHALL omit the `type` field
   from the response envelope.
4. THE Resource_Collection SHALL provide a protected nullable string property (
   `?string`) for the collection type identifier, defaulting to null.
5. WHEN a type is defined (non-null) on a Resource_Collection and data envelope
   wrapping is enabled, THE Resource_Collection SHALL include a `type` field in
   the response envelope alongside the data key.
6. IF a type identifier is provided as an empty string or a string containing
   only whitespace, THEN THE Resource or Resource_Collection SHALL treat it as
   null (no type defined).
7. IF data envelope wrapping is disabled on a Resource or Resource_Collection,
   THEN THE Resource or Resource_Collection SHALL NOT include the type in the
   output.
8. THE type identifier SHALL contain between 1 and 64 characters after trimming
   when provided as a non-null, non-empty value.
9. WHEN a Resource_Collection has a collection-level type defined, THE
   Resource_Collection SHALL include only the collection-level type in the
   collection response envelope, independent of any type identifiers defined on
   individual Resource classes within the collection.

### Requirement 22: Documentation and Usage Tutorials

**User Story:** As a developer, I want comprehensive documentation with usage
tutorials for each feature of the API Resource library, so that I can quickly
learn and adopt features without reading source code.

#### Acceptance Criteria

1. THE Documentation SHALL be provided as a Markdown file at
   `docs/API_RESOURCE.md` covering all 16 features of the Resource layer listed
   in criterion 4, with usage examples for each.
2. THE Documentation SHALL include a table of contents with anchor links
   enabling quick navigation to each feature section.
3. WHEN a feature section is rendered, THE Documentation SHALL include at
   minimum: a description of 1 to 3 sentences explaining the feature's purpose,
   a complete code example showing basic usage, and the expected JSON output
   presented as a fenced code block with `json` syntax highlighting.
4. THE Documentation SHALL organize feature sections in the same logical order
   as the requirements: single resource transformation, collections, conditional
   fields, nested resources, metadata, links, context, field filtering, field
   exclusion, mapping, type identifiers, wrapping key customization, response
   headers, caching, transformation hooks, and framework independence.
5. THE Documentation SHALL include a "Quick Start" section at the top
   demonstrating the minimal setup to create a Resource subclass with a
   `toArray` method and return it as a JSON response, requiring no more than 20
   lines of code excluding blank lines.
6. WHEN a code example is presented, THE Documentation SHALL provide a complete,
   runnable PHP snippet (not a fragment) that a developer can copy-paste and
   adapt, including `declare(strict_types=1)`, namespace declarations, and `use`
   statements where applicable.
7. THE Documentation SHALL include an "Advanced Usage" section covering feature
   combinations including: context with conditional fields, sparse fieldsets
   with nested resources, collection with pagination and links, and mapping with
   field exclusion.
8. THE Documentation SHALL use only standard Markdown syntax (headings, fenced
   code blocks, lists, links, tables, bold, italic) without HTML tags or
   platform-specific extensions, ensuring correct rendering on both GitHub and
   GitLab.
9. THE Documentation SHALL follow the same structural conventions as existing
   documentation files in the `docs/` folder: a level-1 heading matching the
   filename topic, a table of contents section, level-2 headings for major
   sections, and level-3 headings for subsections.

### Requirement 23: Format Adapter Interface

**User Story:** As a developer, I want to serialize resources into multiple
output formats (JSON, XML, CSV) through a pluggable interface, so that the
Resource layer remains format-agnostic and I can support different content types
without modifying resource classes.

#### Acceptance Criteria

1. THE Resource SHALL provide a `toSerializedArray(): array` method that returns
   the full envelope structure (including data wrapper, meta, links, and type)
   as an associative array, identical in structure to the output of
   `jsonSerialize()` but as a native PHP array rather than a JSON string.
2. THE ResourceSerializerInterface SHALL define a
   `serialize(Resource|ResourceCollection $resource): string` method that
   accepts a Resource or Resource_Collection instance, internally calls
   `toSerializedArray()` on the instance to obtain the full envelope as an
   associative array, and returns the serialized string representation in the
   implementing format.
3. THE ResourceSerializerInterface SHALL define a `contentType(): string` method
   that returns the MIME type string for the serialized format (e.g.,
   `application/json`, `application/xml`, `text/csv`).
4. THE JsonSerializer SHALL implement ResourceSerializerInterface and SHALL
   serialize the resource's `toSerializedArray()` output using `json_encode`
   with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES` flags.
5. THE XmlSerializer SHALL implement ResourceSerializerInterface and SHALL
   convert the resource's `toSerializedArray()` output into a well-formed XML
   1.0 string with UTF-8 encoding declaration and a root element named
   `response`, where each top-level envelope key (`data`, `meta`, `links`,
   `type`) becomes a direct child element of the root.
6. THE ResourceSerializerInterface, JsonSerializer, and XmlSerializer SHALL
   reside in the `Simsoft\Resource\Serializers` namespace, independent of any
   framework.
7. WHEN an optional ResourceSerializerInterface parameter is provided to the
   Response_Helper `resource` method, THE Response_Helper SHALL use the
   serializer's `serialize()` method instead of `json_encode` to produce the
   response body, and SHALL set the `Content-Type` header to the value returned
   by the serializer's `contentType()` method.
8. WHEN no ResourceSerializerInterface parameter is provided to the
   Response_Helper `resource` method, THE Response_Helper SHALL default to
   JsonSerializer behavior (using `json_encode` with
   `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES` flags and
   `Content-Type: application/json`), maintaining backward compatibility with
   Requirement 7.
9. THE ResourceSerializerInterface `serialize` method SHALL receive the full
   envelope output as produced by `toSerializedArray()` (including data wrapper,
   meta, links, and type keys), not the raw `toArray()` output.
10. THE ResourceSerializerInterface SHALL be limited to text-based structured
    data formats; binary formats (images, file downloads) are explicitly out of
    scope.
11. IF the `serialize` method of a ResourceSerializerInterface implementation
    fails to produce a valid serialized string (e.g., `json_encode` returns
    false, or XML generation encounters non-serializable data), THEN the
    implementation SHALL throw a SerializationException indicating the failure
    and the format that failed.
