<?php

declare(strict_types=1);

namespace Tests\Resource;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\NullResource;
use Simsoft\Resource\Resource;

/**
 * Tests for NullResource behavior.
 */
class NullResourceTest extends TestCase
{
    #[Test]
    public function constructorCreatesInstanceWithoutArguments(): void
    {
        $resource = new NullResource();

        $this->assertInstanceOf(NullResource::class, $resource);
        $this->assertInstanceOf(Resource::class, $resource);
    }

    #[Test]
    public function toArrayReturnsEmptyArray(): void
    {
        $resource = new NullResource();

        $this->assertSame([], $resource->toArray());
    }

    #[Test]
    public function jsonSerializeWithWrappingEnabledReturnsDataNull(): void
    {
        $resource = new NullResource();
        $result = $resource->jsonSerialize();

        $this->assertSame(['data' => null], $result);
    }

    #[Test]
    public function jsonSerializeWithWrappingDisabledReturnsNull(): void
    {
        $resource = new class extends NullResource {
            protected bool $wrap = false;
        };

        $this->assertNull($resource->jsonSerialize());
    }

    #[Test]
    public function jsonSerializeWithWrappingEnabledIncludesMeta(): void
    {
        $resource = new NullResource();
        $resource->withMeta(['timestamp' => '2024-01-01']);

        $result = $resource->jsonSerialize();

        $this->assertSame([
            'data' => null,
            'meta' => ['timestamp' => '2024-01-01'],
        ], $result);
    }

    #[Test]
    public function jsonSerializeWithWrappingDisabledDiscardsMeta(): void
    {
        $resource = new class extends NullResource {
            protected bool $wrap = false;
        };
        $resource->withMeta(['timestamp' => '2024-01-01']);

        $this->assertNull($resource->jsonSerialize());
    }

    #[Test]
    public function jsonSerializeWithWrappingEnabledIncludesLinks(): void
    {
        $resource = new NullResource();
        $resource->withLinks(['self' => '/users/1']);

        $result = $resource->jsonSerialize();

        $this->assertSame([
            'data' => null,
            'links' => ['self' => '/users/1'],
        ], $result);
    }

    #[Test]
    public function jsonSerializeWithWrappingDisabledDiscardsLinks(): void
    {
        $resource = new class extends NullResource {
            protected bool $wrap = false;
        };
        $resource->withLinks(['self' => '/users/1']);

        $this->assertNull($resource->jsonSerialize());
    }

    #[Test]
    public function jsonSerializeWithWrappingEnabledIncludesMetaAndLinks(): void
    {
        $resource = new NullResource();
        $resource->withMeta(['total' => 0]);
        $resource->withLinks(['self' => '/users']);

        $result = $resource->jsonSerialize();

        $this->assertSame([
            'data' => null,
            'meta' => ['total' => 0],
            'links' => ['self' => '/users'],
        ], $result);
    }

    #[Test]
    public function withContextStoresContextButDoesNotAffectOutput(): void
    {
        $resource = new NullResource();
        $resource->withContext(['role' => 'admin', 'user_id' => 42]);

        $result = $resource->jsonSerialize();

        $this->assertSame(['data' => null], $result);
    }

    #[Test]
    public function withContextReturnsSelf(): void
    {
        $resource = new NullResource();
        $result = $resource->withContext(['key' => 'value']);

        $this->assertSame($resource, $result);
    }

    #[Test]
    public function jsonEncodeProducesCorrectJsonWithWrapping(): void
    {
        $resource = new NullResource();

        $this->assertSame('{"data":null}', json_encode($resource));
    }

    #[Test]
    public function jsonEncodeProducesNullWithoutWrapping(): void
    {
        $resource = new class extends NullResource {
            protected bool $wrap = false;
        };

        $this->assertSame('null', json_encode($resource));
    }

    #[Test]
    public function resourceMakeWithNullReturnsNullResource(): void
    {
        $resource = TestableResourceForNull::make(null);

        $this->assertInstanceOf(NullResource::class, $resource);
    }
}

/**
 * Concrete resource subclass for testing make() with null.
 */
class TestableResourceForNull extends Resource
{
    public function toArray(): array
    {
        return ['id' => 1];
    }
}
