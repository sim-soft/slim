<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\NullResource;

/**
 * Property-based tests for NullResource serialization.
 *
 * @SuppressWarnings(PHPMD)
 */
class NullResourcePropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 10: NullResource serialization
     *
     * For any NullResource with wrapping enabled, jsonSerialize() produces
     * an envelope with null as the data value. With metadata provided and
     * wrapping enabled, produces {"data": null, "meta": {...}}.
     * With links provided and wrapping enabled, produces {"data": null, "links": {...}}.
     *
     * Validates: Requirements 10.3, 10.5, 10.7
     */
    #[Test]
    #[DataProvider('wrappedNullResourceProvider')]
    public function nullResourceWithWrappingEnabledProducesEnvelope(
        array $meta,
        array $links,
        array $context
    ): void
    {
        $resource = new NullResource();

        if ($meta !== []) {
            $resource->withMeta($meta);
        }

        if ($links !== []) {
            $resource->withLinks($links);
        }

        if ($context !== []) {
            $resource->withContext($context);
        }

        $result = $resource->jsonSerialize();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('data', $result);
        $this->assertNull($result['data']);

        if ($meta !== []) {
            $this->assertArrayHasKey('meta', $result);
            $this->assertSame($meta, $result['meta']);
        } else {
            $this->assertArrayNotHasKey('meta', $result);
        }

        if ($links !== []) {
            $this->assertArrayHasKey('links', $result);
            $this->assertSame($links, $result['links']);
        } else {
            $this->assertArrayNotHasKey('links', $result);
        }
    }

    /**
     * Feature: api-resource, Property 10: NullResource serialization
     *
     * With wrapping disabled, NullResource produces null, discarding any
     * metadata, links, or context.
     *
     * Validates: Requirements 10.4, 10.6, 10.8
     */
    #[Test]
    #[DataProvider('unwrappedNullResourceProvider')]
    public function nullResourceWithWrappingDisabledProducesNull(
        array $meta,
        array $links,
        array $context
    ): void
    {
        $resource = new class extends NullResource {
            protected bool $wrap = false;
        };

        if ($meta !== []) {
            $resource->withMeta($meta);
        }

        if ($links !== []) {
            $resource->withLinks($links);
        }

        if ($context !== []) {
            $resource->withContext($context);
        }

        $result = $resource->jsonSerialize();

        $this->assertNull($result);
    }

    /**
     * Feature: api-resource, Property 10: NullResource serialization
     *
     * withContext stores context but has no effect on output regardless
     * of wrapping state.
     *
     * Validates: Requirements 10.3, 10.4
     */
    #[Test]
    #[DataProvider('contextHasNoEffectProvider')]
    public function withContextStoresButDoesNotAffectOutput(
        array $context,
        bool  $wrapEnabled
    ): void
    {
        if ($wrapEnabled) {
            $resource = new NullResource();
        } else {
            $resource = new class extends NullResource {
                protected bool $wrap = false;
            };
        }

        $resource->withContext($context);
        $result = $resource->jsonSerialize();

        if ($wrapEnabled) {
            $this->assertIsArray($result);
            $this->assertSame(['data' => null], $result);
        } else {
            $this->assertNull($result);
        }
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, mixed>}>
     */
    public static function wrappedNullResourceProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 120; $ii++) {
            $meta = self::randomMetaArray($ii);
            $links = self::randomLinksArray($ii);
            $context = self::randomContextArray($ii);
            $cases[] = [$meta, $links, $context];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: array<string, string>, 2: array<string, mixed>}>
     */
    public static function unwrappedNullResourceProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 120; $ii++) {
            $meta = self::randomMetaArray($ii);
            $links = self::randomLinksArray($ii);
            $context = self::randomContextArray($ii);
            $cases[] = [$meta, $links, $context];
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: bool}>
     */
    public static function contextHasNoEffectProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            $context = self::randomNonEmptyContextArray();
            $wrapEnabled = $ii % 2 === 0;
            $cases[] = [$context, $wrapEnabled];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomMetaArray(int $index): array
    {
        if ($index % 4 === 0) {
            return [];
        }

        $keys = ['timestamp', 'version', 'total', 'page', 'request_id', 'server', 'duration'];
        $numFields = random_int(1, 4);
        $selectedKeys = array_slice($keys, 0, $numFields);
        $result = [];

        foreach ($selectedKeys as $key) {
            $result[$key] = self::randomScalarValue();
        }

        return $result;
    }

    /**
     * @return array<string, string>
     */
    private static function randomLinksArray(int $index): array
    {
        if ($index % 3 === 0) {
            return [];
        }

        $linkKeys = ['self', 'next', 'prev', 'first', 'last', 'related', 'parent'];
        $numLinks = random_int(1, 4);
        $selectedKeys = array_slice($linkKeys, 0, $numLinks);
        $result = [];

        foreach ($selectedKeys as $key) {
            $result[$key] = '/api/' . bin2hex(random_bytes(3)) . '/' . random_int(1, 999);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomContextArray(int $index): array
    {
        if ($index % 5 === 0) {
            return [];
        }

        return self::randomNonEmptyContextArray();
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomNonEmptyContextArray(): array
    {
        $keys = ['user_id', 'role', 'locale', 'tenant', 'permissions', 'debug', 'request_id'];
        $numFields = random_int(1, 4);
        $selectedKeys = array_slice($keys, 0, $numFields);
        $result = [];

        foreach ($selectedKeys as $key) {
            $result[$key] = self::randomScalarValue();
        }

        return $result;
    }

    private static function randomScalarValue(): mixed
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
