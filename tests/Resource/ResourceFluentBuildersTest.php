<?php

declare(strict_types=1);

namespace Tests\Resource;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Exceptions\InvalidHeaderException;
use Simsoft\Resource\Resource;

/**
 * Concrete resource subclass for testing fluent builder methods.
 */
class TestableResource extends Resource
{
    public function toArray(): array
    {
        return ['id' => 1, 'name' => 'test'];
    }
}

/**
 * Tests for Resource fluent builder methods.
 */
class ResourceFluentBuildersTest extends TestCase
{
    #[Test]
    public function withContextMergesContextAndReturnsSelf(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $result = $resource->withContext(['key' => 'value']);

        $this->assertSame($resource, $result);
    }

    #[Test]
    public function withContextShallowMergesMultipleCalls(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $resource->withContext(['aa' => 1, 'bb' => 2]);
        $resource->withContext(['bb' => 3, 'cc' => 4]);

        // Access context via reflection to verify merge
        $reflection = new \ReflectionProperty(Resource::class, 'context');
        $context = $reflection->getValue($resource);

        $this->assertSame(['aa' => 1, 'bb' => 3, 'cc' => 4], $context);
    }

    #[Test]
    public function withMetaMergesAndReturnsSelf(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $result = $resource->withMeta(['total' => 10]);

        $this->assertSame($resource, $result);

        $resource->withMeta(['page' => 1, 'total' => 20]);

        $reflection = new \ReflectionProperty(Resource::class, 'meta');
        $meta = $reflection->getValue($resource);

        $this->assertSame(['total' => 20, 'page' => 1], $meta);
    }

    #[Test]
    public function withLinksMergesAndReturnsSelf(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $result = $resource->withLinks(['self' => '/users/1']);

        $this->assertSame($resource, $result);

        $resource->withLinks(['next' => '/users/2', 'self' => '/users/1b']);

        $reflection = new \ReflectionProperty(Resource::class, 'links');
        $links = $reflection->getValue($resource);

        $this->assertSame(['self' => '/users/1b', 'next' => '/users/2'], $links);
    }

    #[Test]
    public function withHeadersMergesWithCaseInsensitiveDedup(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $resource->withHeaders(['X-Rate-Limit' => '100']);
        $resource->withHeaders(['x-rate-limit' => '200', 'X-Custom' => 'abc']);

        $headers = $resource->getHeaders();

        // The later call's casing wins for the key name
        $this->assertSame(['x-rate-limit' => '200', 'X-Custom' => 'abc'], $headers);
        $this->assertCount(2, $headers);
    }

    #[Test]
    public function withHeadersThrowsOnEmptyName(): void
    {
        $resource = new TestableResource(['id' => 1]);

        $this->expectException(InvalidHeaderException::class);
        $this->expectExceptionMessage('Header name must not be empty');

        $resource->withHeaders(['' => 'value']);
    }

    #[Test]
    public function onlyNormalizesVariadicStrings(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $result = $resource->only('id', 'name');

        $this->assertSame($resource, $result);

        $reflection = new \ReflectionProperty(Resource::class, 'onlyFields');
        $fields = $reflection->getValue($resource);

        $this->assertSame(['id', 'name'], $fields);
    }

    #[Test]
    public function onlyNormalizesArrayArgument(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $resource->only(['id', 'name', 'email']);

        $reflection = new \ReflectionProperty(Resource::class, 'onlyFields');
        $fields = $reflection->getValue($resource);

        $this->assertSame(['id', 'name', 'email'], $fields);
    }

    #[Test]
    public function onlyNormalizesMixedVariadicAndArray(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $resource->only('id', ['name', 'email'], 'age');

        $reflection = new \ReflectionProperty(Resource::class, 'onlyFields');
        $fields = $reflection->getValue($resource);

        $this->assertSame(['id', 'name', 'email', 'age'], $fields);
    }

    #[Test]
    public function onlyClearsExceptFields(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $resource->except('password');
        $resource->only('id', 'name');

        $onlyRef = new \ReflectionProperty(Resource::class, 'onlyFields');
        $exceptRef = new \ReflectionProperty(Resource::class, 'exceptFields');

        $this->assertSame(['id', 'name'], $onlyRef->getValue($resource));
        $this->assertNull($exceptRef->getValue($resource));
    }

    #[Test]
    public function exceptNormalizesAndClearsOnlyFields(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $resource->only('id', 'name');
        $resource->except('password', ['secret']);

        $onlyRef = new \ReflectionProperty(Resource::class, 'onlyFields');
        $exceptRef = new \ReflectionProperty(Resource::class, 'exceptFields');

        $this->assertNull($onlyRef->getValue($resource));
        $this->assertSame(['password', 'secret'], $exceptRef->getValue($resource));
    }

    #[Test]
    public function getHeadersReturnsEmptyArrayByDefault(): void
    {
        $resource = new TestableResource(['id' => 1]);

        $this->assertSame([], $resource->getHeaders());
    }

    #[Test]
    public function fluentChainingWorksAcrossAllMethods(): void
    {
        $resource = new TestableResource(['id' => 1]);

        $result = $resource
            ->withContext(['role' => 'admin'])
            ->withMeta(['total' => 5])
            ->withLinks(['self' => '/test'])
            ->withHeaders(['X-Custom' => 'val'])
            ->only('id', 'name');

        $this->assertSame($resource, $result);
    }

    #[Test]
    public function withContextInvalidatesCache(): void
    {
        $resource = new TestableResource(['id' => 1]);

        // Set a cached result via reflection
        $cacheRef = new \ReflectionProperty(Resource::class, 'cachedResult');
        $cacheRef->setValue($resource, ['cached' => true]);

        $resource->withContext(['new' => 'data']);

        $this->assertNull($cacheRef->getValue($resource));
    }

    #[Test]
    public function withHeadersSupportsArrayValues(): void
    {
        $resource = new TestableResource(['id' => 1]);
        $resource->withHeaders(['X-Multi' => ['val1', 'val2']]);

        $headers = $resource->getHeaders();

        $this->assertSame(['X-Multi' => ['val1', 'val2']], $headers);
    }
}
