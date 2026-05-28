<?php

declare(strict_types=1);

namespace Tests\Resource;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Resource;

/**
 * Resource subclass using $map for declarative field mapping.
 */
class MappedResource extends Resource
{
    protected array $map = [
        'name' => 'full_name',
        'city' => 'address.city',
        'zip' => 'address.zip_code',
    ];
}

/**
 * Resource subclass with $map that also overrides toArray().
 * When toArray() is overridden, $map should be ignored.
 */
class OverriddenMappedResource extends Resource
{
    protected array $map = [
        'name' => 'full_name',
    ];

    public function toArray(): array
    {
        return ['custom' => 'value'];
    }
}

/**
 * Resource subclass with empty $map (default toArray returns empty array).
 */
class EmptyMapResource extends Resource
{
}

/**
 * Tests for $map-based auto-transform with dot-notation resolution.
 */
class MappingTest extends TestCase
{
    #[Test]
    public function resolveMapWithDirectPropertyAccess(): void
    {
        $data = ['full_name' => 'John Doe', 'address' => ['city' => 'NYC', 'zip_code' => '10001']];
        $resource = new MappedResource($data);

        $result = $resource->toArray();

        $this->assertSame('John Doe', $result['name']);
        $this->assertSame('NYC', $result['city']);
        $this->assertSame('10001', $result['zip']);
    }

    #[Test]
    public function resolveMapWithObjectData(): void
    {
        $data = (object)[
            'full_name' => 'Jane Smith',
            'address' => (object)['city' => 'LA', 'zip_code' => '90001'],
        ];
        $resource = new MappedResource($data);

        $result = $resource->toArray();

        $this->assertSame('Jane Smith', $result['name']);
        $this->assertSame('LA', $result['city']);
        $this->assertSame('90001', $result['zip']);
    }

    #[Test]
    public function resolveMapWithMixedObjectAndArrayTraversal(): void
    {
        $data = (object)[
            'full_name' => 'Bob',
            'address' => ['city' => 'Chicago', 'zip_code' => '60601'],
        ];
        $resource = new MappedResource($data);

        $result = $resource->toArray();

        $this->assertSame('Bob', $result['name']);
        $this->assertSame('Chicago', $result['city']);
        $this->assertSame('60601', $result['zip']);
    }

    #[Test]
    public function resolveMapReturnsNullForMissingPath(): void
    {
        $data = ['full_name' => 'Alice'];
        $resource = new MappedResource($data);

        $result = $resource->toArray();

        $this->assertSame('Alice', $result['name']);
        $this->assertNull($result['city']);
        $this->assertNull($result['zip']);
    }

    #[Test]
    public function resolveMapReturnsNullForNullIntermediate(): void
    {
        $data = ['full_name' => 'Eve', 'address' => null];
        $resource = new MappedResource($data);

        $result = $resource->toArray();

        $this->assertSame('Eve', $result['name']);
        $this->assertNull($result['city']);
        $this->assertNull($result['zip']);
    }

    #[Test]
    public function resolveMapReturnsNullForNonTraversableIntermediate(): void
    {
        $data = ['full_name' => 'Frank', 'address' => 'not-traversable'];
        $resource = new MappedResource($data);

        $result = $resource->toArray();

        $this->assertSame('Frank', $result['name']);
        $this->assertNull($result['city']);
        $this->assertNull($result['zip']);
    }

    #[Test]
    public function overriddenToArrayIgnoresMap(): void
    {
        $data = ['full_name' => 'Ignored'];
        $resource = new OverriddenMappedResource($data);

        $result = $resource->toArray();

        $this->assertSame(['custom' => 'value'], $result);
        $this->assertArrayNotHasKey('name', $result);
    }

    #[Test]
    public function emptyMapReturnsEmptyArray(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $resource = new EmptyMapResource($data);

        $result = $resource->toArray();

        $this->assertSame([], $result);
    }

    #[Test]
    public function resolveMapPreservesAllMapKeys(): void
    {
        $data = ['full_name' => 'Test', 'address' => ['city' => 'Boston', 'zip_code' => '02101']];
        $resource = new MappedResource($data);

        $result = $resource->toArray();

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('city', $result);
        $this->assertArrayHasKey('zip', $result);
    }

    #[Test]
    public function resolveMapHandlesDeeplyNestedPaths(): void
    {
        $deepResource = new class (['level1' => ['level2' => ['level3' => 'deep_value']]]) extends Resource {
            protected array $map = [
                'deep' => 'level1.level2.level3',
            ];
        };

        $result = $deepResource->toArray();

        $this->assertSame('deep_value', $result['deep']);
    }
}
