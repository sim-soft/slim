<?php

declare(strict_types=1);

namespace Simsoft\Slim\Tests\Resource;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Simsoft\Resource\Resource;

/**
 * Property-based tests for Resource construction and envelope wrapping.
 *
 * @SuppressWarnings(PHPMD)
 */
class ResourceConstructionPropertyTest extends TestCase
{
    /**
     * Feature: api-resource, Property 1: Construction preserves data
     *
     * For any valid data item (object or array), constructing a Resource via make()
     * stores the exact same reference in the resource property.
     *
     * Validates: Requirements 1.1, 1.4
     */
    #[Test]
    #[DataProvider('constructionDataProvider')]
    public function constructionPreservesData(object|array $data): void
    {
        $resource = SimplePropertyResource::make($data);

        $this->assertSame($data, $resource->resource);
        $this->assertInstanceOf(SimplePropertyResource::class, $resource);
    }

    /**
     * Feature: api-resource, Property 2: Envelope wrapping round-trip
     *
     * For any associative array returned by toArray(), when wrapping is enabled
     * the jsonSerialize() output contains that array under the configured $wrapKey,
     * and when wrapping is disabled the output IS the toArray() result directly.
     *
     * Validates: Requirements 2.1, 2.2, 15.2, 15.3
     */
    #[Test]
    #[DataProvider('envelopeWrappingDataProvider')]
    public function envelopeWrappingRoundTrip(array $data, bool $wrapEnabled, string $wrapKey): void
    {
        if ($wrapEnabled) {
            $resource = new WrappedPropertyResource($data, $wrapKey);
        } else {
            $resource = new UnwrappedPropertyResource($data);
        }

        $result = $resource->jsonSerialize();

        if ($wrapEnabled) {
            $this->assertArrayHasKey($wrapKey, $result);
            $this->assertSame($data, $result[$wrapKey]);
        } else {
            $this->assertSame($data, $result);
        }
    }

    /**
     * @return array<int, array{0: object|array}>
     */
    public static function constructionDataProvider(): array
    {
        $cases = [];

        for ($ii = 0; $ii < 100; $ii++) {
            if ($ii % 2 === 0) {
                $cases[] = [self::randomAssocArray()];
            } else {
                $cases[] = [self::randomObject()];
            }
        }

        return $cases;
    }

    /**
     * @return array<int, array{0: array<string, mixed>, 1: bool, 2: string}>
     */
    public static function envelopeWrappingDataProvider(): array
    {
        $cases = [];
        $wrapKeys = ['data', 'user', 'item', 'result', 'payload', 'response', 'record'];

        for ($ii = 0; $ii < 100; $ii++) {
            $data = self::randomAssocArray();
            $wrapEnabled = $ii % 2 === 0;
            $wrapKey = $wrapKeys[array_rand($wrapKeys)];
            $cases[] = [$data, $wrapEnabled, $wrapKey];
        }

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    private static function randomAssocArray(): array
    {
        $keys = ['id', 'name', 'email', 'age', 'active', 'score', 'title', 'role', 'status', 'level'];
        $numFields = random_int(1, 6);
        $selectedKeys = array_slice($keys, 0, $numFields);
        $result = [];

        foreach ($selectedKeys as $key) {
            $result[$key] = self::randomValue();
        }

        return $result;
    }

    private static function randomObject(): object
    {
        $obj = new \stdClass();
        $keys = ['id', 'name', 'email', 'age', 'active', 'score'];
        $numFields = random_int(1, 4);

        for ($ii = 0; $ii < $numFields; $ii++) {
            $key = $keys[$ii];
            $obj->{$key} = self::randomValue();
        }

        return $obj;
    }

    private static function randomValue(): mixed
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
 * Simple concrete Resource for property testing.
 */
class SimplePropertyResource extends Resource
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
 * Wrapped Resource with configurable wrapKey for property testing.
 */
class WrappedPropertyResource extends Resource
{
    public function __construct(
        object|array $resource,
        string       $customWrapKey = 'data'
    )
    {
        parent::__construct($resource);
        $this->wrapKey = $customWrapKey;
    }

    public function toArray(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return (array)$this->resource;
    }
}

/**
 * Unwrapped Resource for property testing.
 */
class UnwrappedPropertyResource extends Resource
{
    protected bool $wrap = false;

    public function toArray(): array
    {
        if (is_array($this->resource)) {
            return $this->resource;
        }

        return (array)$this->resource;
    }
}
