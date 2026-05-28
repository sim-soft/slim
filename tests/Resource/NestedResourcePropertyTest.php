<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Resource;
use Simsoft\Resource\ResourceCollection;

/**
 * Property-based tests for nested resource serialization and context propagation.
 *
 * @SuppressWarnings(PHPMD)
 */
class NestedResourcePropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 6: Nested resource serialization without envelope
     *
     * For any Resource instance used as a field value, the parent serializes it
     * by calling its full pipeline (processData) and including the raw result
     * without any data envelope wrapper, recursively to at least 3 levels.
     *
     * Validates: Requirements 5.1, 5.2, 5.4, 5.6
     */
    #[Test]
    #[DataProvider('nestedResourceDataProvider')]
    public function nestedResourceSerializationWithoutEnvelope(
        array $parentData,
        array $childData,
        array $grandchildData,
    ): void
    {
        $grandchildClass = NestedGrandchildResource::class;
        $childClass = NestedChildResource::class;

        // Create grandchild resource
        $grandchild = new NestedGrandchildResource($grandchildData);

        // Create child resource with grandchild nested
        $child = new NestedChildResource($childData, $grandchild);

        // Create parent resource with child nested
        $parent = new NestedParentResource($parentData, $child);

        $result = $parent->jsonSerialize();

        // Property: parent wraps in envelope
        $this->assertArrayHasKey('data', $result);

        // Property: child is serialized without envelope (raw array)
        $this->assertArrayHasKey('child', $result['data']);
        $childResult = $result['data']['child'];
        $this->assertArrayNotHasKey('data', $childResult);

        // Property: child data fields are present
        foreach ($childData as $key => $value) {
            $this->assertArrayHasKey($key, $childResult);
            $this->assertSame($value, $childResult[$key]);
        }

        // Property: grandchild is serialized without envelope (raw array)
        $this->assertArrayHasKey('grandchild', $childResult);
        $grandchildResult = $childResult['grandchild'];
        $this->assertArrayNotHasKey('data', $grandchildResult);

        // Property: grandchild data fields are present
        foreach ($grandchildData as $key => $value) {
            $this->assertArrayHasKey($key, $grandchildResult);
            $this->assertSame($value, $grandchildResult[$key]);
        }

        // Property: parent data fields are present
        foreach ($parentData as $key => $value) {
            $this->assertArrayHasKey($key, $result['data']);
            $this->assertSame($value, $result['data'][$key]);
        }
    }

    /**
     * Feature: api-resource, Property 6: Nested ResourceCollection without envelope
     *
     * For any ResourceCollection instance used as a field value, the parent
     * serializes it by calling toArray() and including the raw sequential array
     * without any data envelope wrapper.
     *
     * Validates: Requirements 5.2, 5.6
     */
    #[Test]
    #[DataProvider('nestedCollectionDataProvider')]
    public function nestedCollectionSerializationWithoutEnvelope(
        array $parentData,
        array $collectionItems,
    ): void
    {
        $collection = new ResourceCollection(
            $collectionItems,
            NestedSimpleResource::class
        );

        $parent = new NestedParentWithCollectionResource($parentData, $collection);

        $result = $parent->jsonSerialize();

        // Property: parent wraps in envelope
        $this->assertArrayHasKey('data', $result);

        // Property: collection is serialized as raw sequential array (no envelope)
        $this->assertArrayHasKey('items', $result['data']);
        $itemsResult = $result['data']['items'];
        $this->assertIsArray($itemsResult);

        // Property: no 'data' key wrapping the collection items
        $this->assertArrayNotHasKey('data', $result['data']['items']);

        // Property: each item is transformed correctly
        $this->assertCount(count($collectionItems), $itemsResult);
        foreach ($collectionItems as $index => $item) {
            foreach ($item as $key => $value) {
                $this->assertSame($value, $itemsResult[$index][$key]);
            }
        }
    }

    /**
     * Feature: api-resource, Property 8: Context propagation through nesting
     *
     * For any parent Resource with context data containing nested Resource or
     * ResourceCollection instances, the parent propagates its context to each
     * nested instance via shallow-merge (parent wins for duplicate keys),
     * recursively through all nesting levels.
     *
     * Validates: Requirements 8.3, 8.4, 8.5, 8.7, 8.10
     */
    #[Test]
    #[DataProvider('contextPropagationDataProvider')]
    public function contextPropagationThroughNesting(
        array $parentContext,
        array $childPreContext,
        array $grandchildPreContext,
    ): void
    {
        $grandchild = new ContextAwareGrandchildResource(
            ['id' => 1],
            $grandchildPreContext
        );

        $child = new ContextAwareChildResource(
            ['id' => 2],
            $grandchild,
            $childPreContext
        );

        $parent = new ContextAwareParentResource(['id' => 3], $child);
        $parent->withContext($parentContext);

        $result = $parent->jsonSerialize();

        // Property: parent context is available in parent
        $parentResult = $result['data'];
        foreach ($parentContext as $key => $value) {
            $this->assertArrayHasKey('ctx_' . $key, $parentResult);
            $this->assertSame($value, $parentResult['ctx_' . $key]);
        }

        // Property: child receives parent context (parent wins for duplicates)
        $childResult = $parentResult['child'];
        $expectedChildContext = array_merge($childPreContext, $parentContext);
        foreach ($expectedChildContext as $key => $value) {
            $this->assertArrayHasKey('ctx_' . $key, $childResult);
            $this->assertSame($value, $childResult['ctx_' . $key]);
        }

        // Property: grandchild receives propagated context recursively
        $grandchildResult = $childResult['grandchild'];
        $expectedGrandchildContext = array_merge($grandchildPreContext, $parentContext);
        foreach ($expectedGrandchildContext as $key => $value) {
            $this->assertArrayHasKey('ctx_' . $key, $grandchildResult);
            $this->assertSame($value, $grandchildResult['ctx_' . $key]);
        }
    }

    /**
     * Feature: api-resource, Property 8: Context propagation to ResourceCollection
     *
     * For any parent Resource with context data containing a nested ResourceCollection,
     * the parent propagates its context to the collection and each item resource.
     *
     * Validates: Requirements 8.5, 8.7
     */
    #[Test]
    #[DataProvider('contextPropagationCollectionDataProvider')]
    public function contextPropagationToNestedCollection(
        array $parentContext,
        array $collectionItems,
    ): void
    {
        $collection = new ResourceCollection(
            $collectionItems,
            ContextAwareSimpleResource::class
        );

        $parent = new ContextAwareParentWithCollectionResource(
            ['id' => 1],
            $collection
        );
        $parent->withContext($parentContext);

        $result = $parent->jsonSerialize();

        // Property: each item in the collection receives the parent context
        $itemsResult = $result['data']['items'];
        foreach ($itemsResult as $item) {
            foreach ($parentContext as $key => $value) {
                $this->assertArrayHasKey('ctx_' . $key, $item);
                $this->assertSame($value, $item['ctx_' . $key]);
            }
        }
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    public static function nestedResourceDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $parentData = self::randomAssocArray('p');
            $childData = self::randomAssocArray('c');
            $grandchildData = self::randomAssocArray('g');
            $cases[] = [$parentData, $childData, $grandchildData];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}>
     */
    public static function nestedCollectionDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $parentData = self::randomAssocArray('p');
            $numItems = random_int(1, 5);
            $items = [];
            for ($jj = 0; $jj < $numItems; $jj++) {
                $items[] = self::randomAssocArray('i');
            }
            $cases[] = [$parentData, $items];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>}>
     */
    public static function contextPropagationDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $parentContext = self::randomContext('parent');
            $childPreContext = self::randomContext('child');
            $grandchildPreContext = self::randomContext('grand');

            // Ensure some overlapping keys to test parent-wins semantics
            if ($ii % 3 === 0) {
                $sharedKey = 'shared_' . random_int(1, 5);
                $parentContext[$sharedKey] = 'parent_val_' . $ii;
                $childPreContext[$sharedKey] = 'child_val_' . $ii;
                $grandchildPreContext[$sharedKey] = 'grand_val_' . $ii;
            }

            $cases[] = [$parentContext, $childPreContext, $grandchildPreContext];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<int, array<string, mixed>>}>
     */
    public static function contextPropagationCollectionDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $parentContext = self::randomContext('ctx');
            $numItems = random_int(1, 4);
            $items = [];
            for ($jj = 0; $jj < $numItems; $jj++) {
                $items[] = self::randomAssocArray('i');
            }
            $cases[] = [$parentContext, $items];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomAssocArray(string $prefix): array
    {
        $numFields = random_int(1, 4);
        $result = [];

        for ($ii = 0; $ii < $numFields; $ii++) {
            $result[$prefix . '_field_' . $ii] = self::randomScalar();
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomContext(string $prefix): array
    {
        $numFields = random_int(1, 3);
        $result = [];

        for ($ii = 0; $ii < $numFields; $ii++) {
            $result[$prefix . '_' . $ii] = self::randomScalar();
        }

        return $result;
    }

    private static function randomScalar(): int|string|bool|float
    {
        $type = random_int(0, 3);

        return match ($type) {
            0 => random_int(1, 9999),
            1 => bin2hex(random_bytes(4)),
            2 => random_int(0, 1) === 1,
            default => random_int(1, 100) / 10.0,
        };
    }
}

/**
 * Simple resource that returns its data as-is.
 */
class NestedSimpleResource extends Resource
{
    public function toArray(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return (array)$this->resource;
    }
}

/**
 * Grandchild resource for 3-level nesting tests.
 */
class NestedGrandchildResource extends Resource
{
    public function toArray(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return (array)$this->resource;
    }
}

/**
 * Child resource that nests a grandchild.
 */
class NestedChildResource extends Resource
{
    private Resource $grandchild;

    public function __construct(object|array $resource, Resource $grandchild)
    {
        parent::__construct($resource);
        $this->grandchild = $grandchild;
    }

    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        $data['grandchild'] = $this->grandchild;

        return $data;
    }
}

/**
 * Parent resource that nests a child.
 */
class NestedParentResource extends Resource
{
    private Resource $child;

    public function __construct(object|array $resource, Resource $child)
    {
        parent::__construct($resource);
        $this->child = $child;
    }

    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        $data['child'] = $this->child;

        return $data;
    }
}

/**
 * Parent resource that nests a ResourceCollection.
 */
class NestedParentWithCollectionResource extends Resource
{
    private ResourceCollection $collection;

    public function __construct(object|array $resource, ResourceCollection $collection)
    {
        parent::__construct($resource);
        $this->collection = $collection;
    }

    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        $data['items'] = $this->collection;

        return $data;
    }
}

/**
 * Context-aware grandchild resource that exposes context in output.
 */
class ContextAwareGrandchildResource extends Resource
{
    public function __construct(object|array $resource, array $preContext = [])
    {
        parent::__construct($resource);
        if ($preContext !== []) {
            $this->withContext($preContext);
        }
    }

    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        foreach ($this->context as $key => $value) {
            $data['ctx_' . $key] = $value;
        }

        return $data;
    }
}

/**
 * Context-aware child resource that nests a grandchild and exposes context.
 */
class ContextAwareChildResource extends Resource
{
    private Resource $grandchild;

    public function __construct(object|array $resource, Resource $grandchild, array $preContext = [])
    {
        parent::__construct($resource);
        $this->grandchild = $grandchild;
        if ($preContext !== []) {
            $this->withContext($preContext);
        }
    }

    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        foreach ($this->context as $key => $value) {
            $data['ctx_' . $key] = $value;
        }
        $data['grandchild'] = $this->grandchild;

        return $data;
    }
}

/**
 * Context-aware parent resource that nests a child and exposes context.
 */
class ContextAwareParentResource extends Resource
{
    private Resource $child;

    public function __construct(object|array $resource, Resource $child)
    {
        parent::__construct($resource);
        $this->child = $child;
    }

    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        foreach ($this->context as $key => $value) {
            $data['ctx_' . $key] = $value;
        }
        $data['child'] = $this->child;

        return $data;
    }
}

/**
 * Simple context-aware resource for collection items.
 */
class ContextAwareSimpleResource extends Resource
{
    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        foreach ($this->context as $key => $value) {
            $data['ctx_' . $key] = $value;
        }

        return $data;
    }
}

/**
 * Context-aware parent with nested collection.
 */
class ContextAwareParentWithCollectionResource extends Resource
{
    private ResourceCollection $collection;

    public function __construct(object|array $resource, ResourceCollection $collection)
    {
        parent::__construct($resource);
        $this->collection = $collection;
    }

    public function toArray(): array
    {
        $data = is_array($this->resource) ? $this->resource : (array)$this->resource;
        $data['items'] = $this->collection;

        return $data;
    }
}
