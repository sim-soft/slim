# Implementation Plan: API Resource

## Overview

Implement a two-layer API Resource system: a framework-independent core (
`Simsoft\Resource`) providing abstract resource transformation, collections,
conditional fields, nested serialization, and format adapters, plus a thin PSR-7
adapter (`Simsoft\Slim\Response::resource()`) bridging the resource layer to the
existing response helper. Implementation follows a bottom-up approach starting
with sentinels and exceptions, building up to the abstract Resource, then
collections, serializers, and finally the framework adapter.

## Tasks

- [x]
    1. Set up project structure, exceptions, and sentinel classes

    - [x] 1.1 Create directory structure and update composer.json autoload
        - Create `src/Resource/`, `src/Resource/Exceptions/`,
          `src/Resource/Serializers/` directories
        - Add `"Simsoft\\Resource\\": "src/Resource"` to `composer.json`
          autoload psr-4
        - Run `composer dump-autoload` to regenerate class map
        - _Requirements: 18.8_

    - [x] 1.2 Implement sentinel classes MissingValue and MergeValue
        - Create `src/Resource/MissingValue.php` — final class, no constructor
          params, `declare(strict_types=1)`
        - Create `src/Resource/MergeValue.php` — final class with
          `public readonly array $fields` constructor parameter
        - Both classes in `Simsoft\Resource` namespace, PSR-12 compliant
        - _Requirements: 4.1, 4.2, 4.5, 4.6_

    - [x] 1.3 Implement custom exception classes
        - Create `src/Resource/Exceptions/InvalidResourceException.php`
          extending `\InvalidArgumentException`
        - Create `src/Resource/Exceptions/InvalidHeaderException.php` extending
          `\InvalidArgumentException`
        - Create `src/Resource/Exceptions/InvalidConfigurationException.php`
          extending `\InvalidArgumentException`
        - Create `src/Resource/Exceptions/SerializationException.php` extending
          `\RuntimeException`
        - Create `src/Resource/Exceptions/InvalidStatusCodeException.php`
          extending `\InvalidArgumentException`
        - All in `Simsoft\Resource\Exceptions` namespace with
          `declare(strict_types=1)`
        - _Requirements: 3.7, 7.5, 7.6, 13.6, 15.1_

- [x]
    2. Implement abstract Resource class (core serialization pipeline)

    - [x] 2.1 Implement Resource base structure with constructor, properties,
      and make() factory
        - Create `src/Resource/Resource.php` as abstract class implementing
          `\JsonSerializable`
        - Add constructor accepting `object|array` stored in
          `public readonly object|array $resource`
        - Add class-level properties: `$wrap = true`, `$wrapKey = 'data'`,
          `$type = null`, `$map = []`
        - Add instance state: `$context = []`, `$cachedResult`, `$onlyFields`,
          `$exceptFields`, `$meta`, `$links`, `$headers`
        - Implement `static make(object|array|null $data): static` — returns
          `NullResource` for null, else `new static($data)`
        - Declare `abstract public function toArray(): array`
        - Validate `$wrapKey` (non-empty, 1-64 chars, not "meta"/"links") and
          `$type` (max 64 chars) in constructor
        - _Requirements: 1.1, 1.2, 1.4, 1.5, 2.3, 10.1, 15.1, 21.1, 21.6, 21.8_

    - [x] 2.2 Implement fluent builder methods (withContext, withMeta,
      withLinks, withHeaders, only, except)
        - `withContext(array $context): static` — shallow-merge into
          `$this->context`, invalidate cache
        - `withMeta(array $meta): static` — shallow-merge into `$this->meta`
        - `withLinks(array $links): static` — shallow-merge into `$this->links`
        - `withHeaders(array $headers): static` — merge with case-insensitive
          dedup, throw `InvalidHeaderException` on empty name
        - `only(string|array ...$fields): static` — normalize to flat array,
          store in `$onlyFields`
        - `except(string|array ...$fields): static` — normalize to flat array,
          store in `$exceptFields`
        - `getHeaders(): array` — public getter for Response adapter
        - _Requirements: 6.1, 6.4, 8.1, 8.2, 8.3, 8.9, 9.1, 9.7, 13.1, 13.4,
          13.6, 14.3, 16.1, 16.6, 20.1, 20.7_

    - [x] 2.3 Implement conditional helpers (when, whenNotNull, mergeWhen)
        - `protected function when(bool $condition, mixed $value): mixed` —
          returns value/closure-result when true, `MissingValue` when false
        - `protected function whenNotNull(mixed $value): mixed` — returns value
          when non-null, `MissingValue` when null
        -
        `protected function mergeWhen(bool $condition, array $fields): MergeValue|MissingValue` —
        returns `MergeValue` when true, `MissingValue` when false
        - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_

    - [x] 2.4 Implement jsonSerialize with full serialization pipeline
        - Check cache first — return if cached
        - Call `beforeSerialize()` hook
        - Resolve `toArray()` or `$map` (if toArray not overridden and map
          non-empty)
        - Resolve conditionals: iterate output, remove `MissingValue` keys,
          flatten `MergeValue` fields
        - Apply `only`/`except` filtering (only takes precedence)
        - Serialize nested Resources/ResourceCollections (call `toArray()`,
          propagate context)
        - Call `afterSerialize($data)` hook
        - Build envelope: add `type` (if non-null/non-empty), wrap with
          `$wrapKey` (if `$wrap`), add `meta`, add `links`
        - Handle unwrapped mode: merge meta into top-level, links under `_links`
        - Cache result and return
        - _Requirements: 1.3, 2.1, 2.2, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.2, 6.3,
          6.5, 8.4, 8.5, 8.10, 9.2, 9.3, 9.6, 11.1, 11.4, 14.1, 14.2, 14.5,
          15.2, 15.3, 16.2, 16.3, 16.4, 17.1, 17.2, 17.4, 20.2, 20.3, 20.4,
          20.5, 21.2, 21.3, 21.7_

    - [x] 2.5 Implement $map-based auto-transform with dot-notation resolution
        - When `toArray()` is not overridden and `$map` is non-empty, resolve
          each map entry
        - Dot-notation traversal: split path by `.`, resolve each segment as
          object property or array key
        - Return `null` for missing/non-traversable intermediate segments
        - If subclass overrides `toArray()`, ignore `$map` entirely
        - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6_

    - [x] 2.6 Implement beforeSerialize and afterSerialize hooks
        - `protected function beforeSerialize(): void {}` — no-op default
        -
        `protected function afterSerialize(array $data): array { return $data; }` —
        identity default
        - `afterSerialize` return type enforced by PHP type declaration (
          TypeError on non-array)
        - _Requirements: 11.1, 11.2, 11.3, 11.5, 17.1, 17.2, 17.3, 17.4, 17.5_

    - [x] 2.7 Implement toSerializedArray() method for serializer interface
      support
        - `public function toSerializedArray(): array` — returns the full
          envelope as a PHP array (same structure as `jsonSerialize()`)
        - Reuse internal serialization logic from `jsonSerialize()`
        - _Requirements: 23.1, 23.9_

  - [x]* 2.8 Write property tests for Resource construction and envelope
  wrapping
    - **Property 1: Construction preserves data**
    - **Property 2: Envelope wrapping round-trip**
    - **Validates: Requirements 1.1, 1.4, 2.1, 2.2, 15.2, 15.3**

  - [x]* 2.9 Write property tests for conditional fields
    - **Property 5: Conditional field inclusion/exclusion**
    - **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 4.6**

  - [x]* 2.10 Write property tests for field filtering and mapping
    - **Property 12: Field filtering with only**
    - **Property 13: Field exclusion with except**
    - **Property 14: Declarative mapping resolution**
    - **Validates: Requirements 16.2, 16.4, 19.2, 19.3, 19.4, 19.5, 19.6, 20.2,
      20.4, 20.5**

  - [x]* 2.11 Write property tests for caching, hooks, and type identifier
    - **Property 11: Serialization idempotence (caching)**
    - **Property 15: afterSerialize pipeline**
    - **Property 16: Type identifier in envelope**
    - **Validates: Requirements 14.1, 14.2, 14.3, 14.5, 17.1, 17.2, 17.3, 21.2,
      21.6, 21.7**

- [x]
    3. Implement NullResource

    - [x] 3.1 Create NullResource class
        - Create `src/Resource/NullResource.php` extending `Resource`
        - Constructor takes no arguments (does not call parent)
        - `toArray()` returns `null`
        - Override `jsonSerialize()`: when `$wrap` enabled return
          `[$wrapKey => null]` + meta/links; when disabled return `null`
        - Discard meta/links when wrapping disabled
        - Support `withContext()` (stores but has no effect on output)
        - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9_

  - [x]* 3.2 Write property tests for NullResource
    - **Property 10: NullResource serialization**
    - **Validates: Requirements 10.3, 10.4, 10.5, 10.6, 10.7, 10.8**

- [x]
    4. Implement ResourceCollection

    - [x] 4.1 Create ResourceCollection class with constructor validation
        - Create `src/Resource/ResourceCollection.php` implementing
          `\JsonSerializable`
        - Constructor accepts `iterable $items` and `string $resourceClass`
        - Validate `$resourceClass` is a valid concrete Resource subclass, throw
          `InvalidResourceException` if not
        - Add class-level properties: `$wrapKey = 'data'`, `$type = null`
        - Add instance state: `$context`, `$meta`, `$links`, `$headers`,
          `$onlyFields`, `$exceptFields`, `$pagination`
        - Validate `$wrapKey` and `$type` same as Resource
        - _Requirements: 3.1, 3.7, 12.4, 15.4, 21.4_

    - [x] 4.2 Implement ResourceCollection fluent methods and pagination
        -
        `paginate(int $total, int $perPage, int $currentPage, int $lastPage): static` —
        validate minimums, store pagination
        - `withContext(array $context): static` — shallow-merge
        - `withMeta(array $meta): static` — shallow-merge
        - `withLinks(array $links): static` — shallow-merge
        - `withHeaders(array $headers): static` — merge with case-insensitive
          dedup, throw on empty name
        - `only(string|array ...$fields): static` — store field list
        - `except(string|array ...$fields): static` — store field list
        - `getHeaders(): array` — public getter
        - _Requirements: 3.4, 6.6, 8.6, 9.4, 13.2, 13.4, 16.5, 20.6_

    - [x] 4.3 Implement ResourceCollection toArray and jsonSerialize
        - `toArray()`: iterate items, instantiate resource class per item,
          propagate context, apply only/except, call `toArray()` on each
        - `jsonSerialize()`: build envelope with `$wrapKey`, add `type` if
          non-null, merge pagination + additional meta under `meta` key (
          additional takes precedence), add `links`
        - No caching — always recomputes
        - Preserve item order from input iterable
        - Empty iterable produces `[$wrapKey => []]`
        - _Requirements: 3.2, 3.3, 3.5, 3.6, 6.7, 6.8, 8.7, 9.5, 14.4, 15.5,
          21.5, 21.9_

    - [x] 4.4 Implement toSerializedArray() on ResourceCollection
        - `public function toSerializedArray(): array` — returns full envelope
          as PHP array
        - _Requirements: 23.1, 23.9_

    - [x] 4.5 Implement static collection() factory method on Resource
        - Add
          `public static function collection(iterable $items): ResourceCollection`
          to Resource class
        - Uses late static binding (`static::class`) as the resource
          class-string
        - _Requirements: 12.1, 12.2, 12.3, 12.4_

  - [x]* 4.6 Write property tests for ResourceCollection
    - **Property 3: Collection transformation preserves order**
    - **Property 4: Pagination metadata structure**
    - **Property 18: Collection metadata precedence**
    - **Property 19: Invalid class-string rejection**
    - **Validates: Requirements 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 6.7, 6.8, 12.4**

- [x]
    5. Checkpoint - Ensure core classes pass all tests

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    6. Implement nested resources, context propagation, metadata, links, and
       headers

    - [x] 6.1 Implement nested resource serialization within jsonSerialize
      pipeline
        - During serialization, detect Resource/ResourceCollection field values
        - Call `toArray()` on nested Resources (no envelope), `toArray()` on
          nested ResourceCollections (no envelope)
        - Propagate parent context to nested instances via shallow-merge (parent
          wins)
        - Handle conditional fields resolving to Resource/ResourceCollection
        - Support at least 3 levels of nesting depth
        - Skip nested serialization for fields excluded by only/except
        - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 8.4, 8.5, 8.10_

  - [x]* 6.2 Write property tests for nested resources and context propagation
    - **Property 6: Nested resource serialization without envelope**
    - **Property 8: Context propagation through nesting**
    - **Validates: Requirements 5.1, 5.2, 5.4, 5.6, 8.3, 8.4, 8.5, 8.7, 8.10**

  - [x]* 6.3 Write property tests for metadata, links, and headers
    - **Property 7: Metadata merge semantics**
    - **Property 9: Links envelope placement**
    - **Property 17: Header merge with case-insensitive deduplication**
    - **Validates: Requirements 6.2, 6.4, 6.5, 9.2, 9.6, 9.7, 13.4, 13.6**

- [x]
    7. Implement serializers

    - [x] 7.1 Create ResourceSerializerInterface
        - Create `src/Resource/Serializers/ResourceSerializerInterface.php`
        - Define `serialize(Resource|ResourceCollection $resource): string`
        - Define `contentType(): string`
        - _Requirements: 23.2, 23.3, 23.6_

    - [x] 7.2 Implement JsonSerializer
        - Create `src/Resource/Serializers/JsonSerializer.php` implementing
          `ResourceSerializerInterface`
        - `serialize()`: call `$resource->toSerializedArray()`, `json_encode`
          with `JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES`
        - Throw `SerializationException` if `json_encode` returns false
        - `contentType()`: return `'application/json'`
        - _Requirements: 23.4, 23.11_

    - [x] 7.3 Implement XmlSerializer
        - Create `src/Resource/Serializers/XmlSerializer.php` implementing
          `ResourceSerializerInterface`
        - `serialize()`: convert `toSerializedArray()` to well-formed XML 1.0,
          UTF-8, root element `<response>`
        - Each top-level key (`data`, `meta`, `links`, `type`) becomes a child
          element of root
        - Handle arrays, nested structures, null values
        - Throw `SerializationException` on failure
        - `contentType()`: return `'application/xml'`
        - _Requirements: 23.5, 23.6, 23.10, 23.11_

  - [x]* 7.4 Write unit tests for JsonSerializer and XmlSerializer
    - Test successful serialization of Resource and ResourceCollection
    - Test SerializationException on encoding failure
    - Test contentType() returns correct MIME types
    - _Requirements: 23.4, 23.5, 23.11_

- [x]
    8. Implement Response adapter integration

    - [x] 8.1 Add resource() method to Simsoft\Slim\Response
        - Add
          `resource(Resource|ResourceCollection $resource, int $code = 200, ?ResourceSerializerInterface $serializer = null): static`
        - Validate status code (100-599), throw `InvalidStatusCodeException` if
          invalid
        - Use provided serializer or default to `JsonSerializer`
        - Call `$serializer->serialize($resource)` to get body string
        - Set `Content-Type` from `$serializer->contentType()`
        - Apply resource-level headers via `$resource->getHeaders()`
        - Write body and set status code on PSR-7 response
        - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 13.3, 13.5, 18.5, 23.7,
          23.8_

  - [x]* 8.2 Write unit tests for Response::resource() adapter
    - Test default JSON serialization with status 200
    - Test custom status codes
    - Test custom serializer parameter
    - Test InvalidStatusCodeException for out-of-range codes
    - Test SerializationException propagation
    - Test resource headers applied to response
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 13.3, 23.7_

- [x]
    9. Checkpoint - Ensure all tests pass

    - Ensure all tests pass, ask the user if questions arise.

- [x]
    10. Create documentation

    - [x] 10.1 Write docs/API_RESOURCE.md documentation
        - Create comprehensive Markdown documentation at `docs/API_RESOURCE.md`
        - Include table of contents with anchor links
        - Quick Start section (≤20 lines of code)
        - Feature sections in requirements order: single resource, collections,
          conditional fields, nested resources, metadata, links, context, field
          filtering, field exclusion, mapping, type identifiers, wrapping key
          customization, response headers, caching, transformation hooks,
          framework independence
        - Each section: 1-3 sentence description, complete code example,
          expected JSON output
        - Advanced Usage section: context + conditionals, sparse fieldsets +
          nested, collection + pagination + links, mapping + exclusion
        - Standard Markdown only, match existing docs/ conventions
        - _Requirements: 22.1, 22.2, 22.3, 22.4, 22.5, 22.6, 22.7, 22.8, 22.9_

- [x]
    11. Final checkpoint - Ensure all tests pass and static analysis is clean

    - Ensure all tests pass, ask the user if questions arise.
    - Run `composer qc` (PHPStan level 8 + PHPMD) and fix any issues.

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation
- Property tests validate universal correctness properties from the design
  document
- Unit tests validate specific examples and edge cases
- The implementation language is PHP >= 8.2 with strict types as specified in
  the design
- All code must pass PHPStan level 8 and PHPMD analysis
- No `else` expressions — use early returns per project conventions

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "1.3"] },
    { "id": 1, "tasks": ["2.1"] },
    { "id": 2, "tasks": ["2.2", "2.3", "2.6"] },
    { "id": 3, "tasks": ["2.4", "2.5", "2.7"] },
    { "id": 4, "tasks": ["2.8", "2.9", "2.10", "2.11", "3.1"] },
    { "id": 5, "tasks": ["3.2", "4.1"] },
    { "id": 6, "tasks": ["4.2", "4.3", "4.4", "4.5"] },
    { "id": 7, "tasks": ["4.6", "6.1"] },
    { "id": 8, "tasks": ["6.2", "6.3", "7.1"] },
    { "id": 9, "tasks": ["7.2", "7.3"] },
    { "id": 10, "tasks": ["7.4", "8.1"] },
    { "id": 11, "tasks": ["8.2"] },
    { "id": 12, "tasks": ["10.1"] }
  ]
}
```
